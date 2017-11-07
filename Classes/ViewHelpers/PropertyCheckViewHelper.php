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
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

class PropertyCheckViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    protected $escapeOutput = false;

    public function initializeArguments()
    {
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
        if (!array_key_exists($field, $response['result'][0]['properties'])) {
            return '<span class="text-danger"><i class="icon fa fa-exclamation"></i> Not in response!</span>';
        }

        return '<span class="text-success"><i class="icon fa fa-check"></i> Appears OK</span>';
    }

}
