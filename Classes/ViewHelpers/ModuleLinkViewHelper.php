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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;


/**
 * Create internal link within backend app
 * @internal
 */
class ModuleLinkViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('arguments', 'array', '', false, []);
        $this->registerArgument('module', 'array', '', false, 'system_Fourallportal4allportal');
    }

    /**
     * Render module link with command and arguments
     *
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
//        $url = BackendUtility::getModuleUrl('record_edit', [
//            'edit' => [
//                $table => [
//                    $row['record_uid'] => 'edit'
//                ]
//            ],
//            'returnUrl' => $requestUri
//        ]);
//
//        $parameters = ['id' => $this->id, 'pagesOnly' => 1, 'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')];
//        $href = BackendUtility::getModuleUrl('db_new', $parameters);


        return BackendUtility::getModuleUrl($arguments['module'], $arguments['arguments']);
    }
}
