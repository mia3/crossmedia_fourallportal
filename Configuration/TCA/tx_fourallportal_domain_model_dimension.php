<?php
return [
  'ctrl' => [
    'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimension',
    'label' => 'name',
    'tstamp' => 'tstamp',
    'crdate' => 'crdate',
    'cruser_id' => 'cruser_id',
    //'delete' => 'deleted',
    'rootLevel' => 1,
    'enablecolumns' => [
      //'disabled' => 'hidden',
      //'starttime' => 'starttime',
      //'endtime' => 'endtime',
    ],
    'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_dimension.gif',
    'security' => [
      'ignorePageTypeRestriction' => true,
    ],
  ],
  'interface' => [
    'showRecordFieldList' => '--palette--;;1, dimension',
  ],
  'types' => [
    '1' => ['showitem' => '--palette--;;1, dimension'],
  ],
  'palettes' => [
    '1' => [
      'showitem' => 'name, value',
    ],
  ],
  'columns' => [
    'name' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimension.name',
      'config' => [
        'type' => 'input',
        'size' => 300,
        'eval' => 'trim'
      ],
    ],
    'value' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimension.value',
      'config' => [
        'type' => 'input',
        'size' => 300,
        'eval' => 'trim'
      ],
    ],
    'dimension_mapping' => [
      'config' => [
        'type' => 'passthrough',
      ],
    ],
  ],
];
