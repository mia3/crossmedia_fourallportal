<?php
namespace Crossmedia\Fourallportal\ViewHelpers;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\ValueReader\ResponseDataFieldValueReader;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;

class RelatedModuleViewHelper extends AbstractViewHelper
{
    use CompileWithContentArgumentAndRenderStatic;

    protected $escapeOutput = false;

    public function initializeArguments()
    {
        $this->registerArgument('content', 'string', 'Tag content - if not specified, taken from tag body');
        $this->registerArgument('module', Module::class, 'Module', true);
        $this->registerArgument('response', 'array', 'Response array', true);
        $this->registerArgument('field', 'string', 'Field name', true);
    }

    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        /** @var Module $module */
        $module = $arguments['module'];
        $response = $arguments['response'];
        $field = $arguments['field'];
        if (!array_key_exists($field, (array) ($response['result'][0]['properties'] ?? []))) {
            return '<span class="text-danger"><i class="icon fa fa-exclamation"></i> Not in response!</span>';
        }

        $fieldValue = (new ResponseDataFieldValueReader())->readResponseDataField($response['result'][0], $field);

        $relatedModuleName = static::detectRelatedModule(
            $field,
            $module->getConnectorConfiguration()['fieldsToLoad'][$field],
            $module->getModuleConfiguration()['relation_conf'],
            $module->getModuleName()
        );


        if (!$relatedModuleName) {
            return '<i class="icon fa fa-check"></i> Not a relation';
        }

        $relatedModule = static::getModuleByName($relatedModuleName);
        if (!$relatedModule) {
            return '<span class="text-warning"><i class="icon fa fa-exclamation"></i> Module "' . $relatedModuleName . '" is not supported</span>';
        }

        $relations = $module->getServer()->getClient()->getBeans($fieldValue, $relatedModule->getConnectorName());

        $variableProvider = $renderingContext->getVariableProvider();
        $variableProvider->add('relations', $relations['result']);
        $variableProvider->add('relatedModule', $relatedModule);
        $variableProvider->add('fieldValueDump', var_export($fieldValue, true));
        $content = $renderChildrenClosure();
        $variableProvider->remove('relations');
        $variableProvider->remove('relatedModule');
        $variableProvider->remove('fieldValueDump');

        return $content;
    }

    protected static function detectRelatedModule($field, array $fieldConfiguration, array $relationConfiguration, $currentModuleName)
    {
        if (!empty($fieldConfiguration['modules'])) {
            return reset($fieldConfiguration['modules']);
        } elseif (!empty($relationConfiguration[$field]['relatedModule'])) {
            return $relationConfiguration[$field]['relatedModule'];
        } elseif (!empty($relationConfiguration[$field]['parent']) && $relationConfiguration[$field]['parent'] !== $currentModuleName) {
            return $relationConfiguration[$field]['parent'];
        } elseif (!empty($relationConfiguration[$field]['child']) && $relationConfiguration[$field]['child'] !== $currentModuleName) {
            return $relationConfiguration[$field]['child'];
        }
    }

    protected static function getModuleByName($moduleName)
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get(ModuleRepository::class)->findOneByModuleName($moduleName);
    }
}
