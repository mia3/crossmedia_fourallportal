<?php

declare(strict_types=1);

defined('TYPO3') or die('Access denied.');

use Crossmedia\Fourallportal\Controller\EventController;
use Crossmedia\Fourallportal\Controller\ServerController;

/**
 * Register Controller-Actions as Module
 */
return [
  'admin_crossmedia_fourallportal' => [
    'parent' => 'tools',
    'position' => 'bottom',
    'access' => 'user,group',
    'workspaces' => 'live',
    'path' => '/module/tools/fourallportal',
    'icon' => 'EXT:fourallportal/Resources/Public/Icons/Extension.svg',
    'labels' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_fourallportal.xlf',
    'extensionName' => 'CrossmediaFourallportal',
    'controllerActions' => [
      EventController::class => [
        'index', 'check', 'reset', 'execute', 'sync'
      ],
      ServerController::class => [
        'index', 'check', 'disable', 'enable', 'delete', 'restartSynchronisation', 'module',
      ],
    ],
  ],
];