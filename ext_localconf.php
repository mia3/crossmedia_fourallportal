<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter(\Crossmedia\Fourallportal\TypeConverter\FileReferenceTypeConverter::class);
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter(\Crossmedia\Fourallportal\TypeConverter\ComplexTypeConverter::class);

//$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] = \Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator::class . '->regenerateModelsAfterCacheFlush';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][$_EXTKEY] = \Crossmedia\Fourallportal\Command\FourallportalCommandController::class;

\Crossmedia\Fourallportal\Mapping\MappingRegister::registerMapping(
    \Crossmedia\Fourallportal\Mapping\FalMapping::class,
    [
        'xmp.cm_image.orientation' => false, // ignored for now
        'xmp.cm_image.width_mm' => false, // ignored for now
        'xmp.cm_image.height_mm' => false, // ignored for now
        'xmp.cm_image.has_clippingpath' => false, // ignored for now
        'xmp.cm_image.colorprofile' => false, // ignored for now
        'xmp.cm_image.colordepth' => false, // ignored for now
        'xmp.cm_image.colorspace' => false, // ignored for now
        'xmp.cm_image.resolution_dpi' => false, // ignored for now
        'xmp.cm_image.has_alpha' => false, // ignored for now,
        'standardtest_image_ceexternalid' => false, // Ignored; no connector exists for "standardtest" which is required by this relation
        'idnt_mkp_txt_file_id' => false, // Ignored; no connector exists for "idnt_mkp_txt" which is required by this relation
        'succ_stories_main_image' => false, // Ignored; no connector exists for "succ_stories" which is required by this relation
        'kits_option_option_kit_downloads' => false, // Ignored; no connector exists for "kits_option" which is required by this relation
        'data_owner_user' => false, // Ignored; no connector exists for "contact" which is required by this relation
        'usa_his_ass_data_id' => false, // Ignored; no connector exists for "contact" which is required by this relation
        'product_tech_general_image' => false, // Ignored; no connector exists for "product_tech" which is required by this relation
        'kits_option_main_image' => false, // Ignored; no connector exists for "kits_option" which is required by this relation
        'data_created_by' => false, // Ignored; no connector exists for "contact" which is required by this relation
        'product_tech_main_image' => false, // Ignored; no connector exists for "product_tech" which is required by this relation
        'product_tech_learn_more_image' => false, // Ignored; no connector exists for "product_tech" which is required by this relation
        'standardtest_text_ceexternalid' => false, // Ignored; no connector exists for "standardtest" which is required by this relation
        'hedgehog_main_image' => false, // Ignored; no connector exists for "hedgehog" which is required by this relation
        'data_mod_by' => false, // Ignored; no connector exists for "contact" which is required by this relation
        'kits_option_option_kit_main_videos' => false, // Ignored; no connector exists for "kits_option" which is required by this relation
        'data_owner_role' => false, // Ignored; no connector exists for "ce_role" which is required by this relation
        'standardtest_reference_ceexternalid' => false, // Ignored; no connector exists for "standardtest" which is required by this relation
        'product_tech_assistance_image' => false, // Ignored; no connector exists for "product_tech" which is required by this relation
        'product_tech_availability_image' => false, // Ignored; no connector exists for "product_tech" which is required by this relation
        'product_main_image' => false, // Ignored; requires custom property (CEExternalId w/o relatedModule)
        'product_background_image' => false, // Ignored; requires custom property (CEExternalId w/o relatedModule)
        'product_cat_product_cat_media' => false, // Ignored; requires custom property (CEExternalId w/o relatedModule)
        'product_cat_main_image' => false, // Ignored; requires custom property (CEExternalId w/o relatedModule)
        'product_main_video' => false, // Ignored; requires custom property (CEExternalId w/o relatedModule)
    ]
);

if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['fourallportal_classes'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['fourallportal_classes'] = array(
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend::class,
        'backend' => \TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend::class,
        'groups' => array(), // This cache is not a member of any group and will not be flushed on cache flush commands.
        'options' => [
            'defaultLifetime' => 9999999999, // Entries in this cache have extremely long life and won't auto-expire
        ],
    );
}

// Closure to prevent leaking variables
(function() {

    $signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
    $signalSlotDispatcher->connect(
        \TYPO3\CMS\Install\Service\SqlExpectedSchemaService::class,
        'tablesDefinitionIsBeingBuilt',
        \Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator::class,
        'addSchemasForAllModules'
    );

})();
