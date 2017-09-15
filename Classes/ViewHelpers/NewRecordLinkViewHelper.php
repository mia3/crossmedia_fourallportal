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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;


/**
 * Create internal link within backend app
 * @internal
 */
class NewRecordLinkViewHelper extends AbstractTagBasedViewHelper
{

    /**
     * @var string
     */
    protected $tagName = 'a';

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        parent::registerUniversalTagAttributes();
        $this->registerArgument('table', 'string', '', true);
    }

    /**
     * Render method
     * @return NULL|string
     */
    public function render()
    {
        $uri = BackendUtility::getModuleUrl('record_edit', [
            'edit' => [
                $this->arguments['table'] => [
                    0 => 'new'
                ]
            ],
            'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
        ]);
        $this->tag->addAttribute('href', $uri);
        $this->tag->setContent($this->renderChildren());
        return $this->tag->render();
    }
}
