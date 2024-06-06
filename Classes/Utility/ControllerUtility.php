<?php

namespace Crossmedia\Fourallportal\Utility;

use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ControllerUtility
{
  public static function addMainMenu(RequestInterface $request, UriBuilder $uriBuilder, ModuleTemplate $view, string $controllerName): void
  {
    $uriBuilder->setRequest($request);
    $menu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
    $menu->setIdentifier('FourallportalBackendMenu');
    $menu->addMenuItem(
      $menu->makeMenuItem()
        ->setTitle(LocalizationUtility::translate(
          'LLL:EXT:fourallportal/Resources/Private/Language/locallang.xlf:tx_fourallportal_domain_model_event',
          'Crossmedia.Fourallportal'))
        ->setHref($uriBuilder->uriFor('index', [], 'Event'))
        ->setActive($controllerName === 'Event')
    );
    $menu->addMenuItem(
      $menu->makeMenuItem()
        ->setTitle(LocalizationUtility::translate(
          'LLL:EXT:fourallportal/Resources/Private/Language/locallang.xlf:tx_fourallportal_domain_model_module.server',
          'Crossmedia.Fourallportal'))
        ->setHref($uriBuilder->uriFor('index', [], 'Server'))
        ->setActive($controllerName === 'Server')
    );
    $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
  }
}