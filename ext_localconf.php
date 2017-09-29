<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter(\Crossmedia\Fourallportal\TypeConverter\FileReferenceTypeConverter::class);
\Crossmedia\Fourallportal\Mapping\MappingRegister::registerMapping(\Crossmedia\Fourallportal\Mapping\FalMapping::class);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][$_EXTKEY] = \Crossmedia\Fourallportal\Command\FourallportalCommandController::class;
