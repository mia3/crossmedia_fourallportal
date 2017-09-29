<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter(\Crossmedia\Fourallportal\TypeConverter\FileReferenceTypeConverter::class);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Crossmedia\Fourallportal\Task\EventHandler::class] = array(
    'extension' => $_EXTKEY,
    'title' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_scheduler.xlf:eventHandler.name',
    'description' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_scheduler.xlf:eventHandler.description',
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Crossmedia\Fourallportal\Task\EventQueueHandler::class] = array(
    'extension' => $_EXTKEY,
    'title' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_scheduler.xlf:eventQueueHandler.name',
    'description' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_scheduler.xlf:eventQueueHandler.description',
    'additionalFields' => \Crossmedia\Fourallportal\Task\EventQueueHandlerFieldProvider::class,
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][$_EXTKEY] = \Crossmedia\Fourallportal\Command\FourallportalCommandController::class;


\Crossmedia\Fourallportal\Mapping\MappingRegister::registerMapping(\Crossmedia\Fourallportal\Mapping\FalMapping::class);
