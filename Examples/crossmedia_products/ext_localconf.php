<?php

declare(strict_types=1);

$conf = $_EXTCONF ?? null;

defined('TYPO3') or die('Access denied.');

use Crossmedia\Fourallportal\Mapping\MappingRegister;
use Crossmedia\Products\Mapping\EasyMapping;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

//DynamicModelRegister::registerModelForAutomaticHandling(AbstractEasy::class);

MappingRegister::registerMapping(
  EasyMapping::class,
  [
    'description' => 'description',
    'availability' => false,
    'launched' => false,
    'manufacturer' => false,
    'manufacturer_part_code' => false,
    'ex_vat_price' => false,
    'inc_vat_price' => false,
    'parent_category' => false,
    'child_category' => false,
    'stock_code' => false,
    'easy_dimension' => false,
    'image' => false,
    'alternative_images' => false,
  ]
);

ExtensionUtility::configurePlugin('Crossmedia.Products', 'easy_module', []);
