<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping',
        'label' => 'language',
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
        'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_dimensionmapping.gif'
    ],
    'interface' => [
        'showRecordFieldList' => 'active, language, metric_or_imperial, dimensions, server',
    ],
    'types' => [
        '1' => ['showitem' => 'active, language, metric_or_imperial, dimensions, server'],
    ],
    'columns' => [
        'language' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'default' => 0,
            ]
        ],
        'metric_or_imperial' => [
            'label' => 'Imperial/Metric unit',
            'config' => [
                'type' => 'select',
                'items' => [
                    [
                        'Metric',
                        'Metric'
                    ],
                    [
                        'Imperial',
                        'Imperial',
                    ],
                ],
            ],
        ],
        'server' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'active' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping.active',
            'config' => [
                'type' => 'check',
                'items' => [
                    '1' => [
                        '0' => 'LLL:EXT:lang/locallang_core.xlf:labels.enabled'
                    ]
                ],
                'default' => 0,
            ]
        ],
        'dimensions' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping.dimensions',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_fourallportal_domain_model_dimension',
                'foreign_field' => 'dimension_mapping',
                'minitems' => 1,
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 1,
                    'levelLinksPosition' => 'top'
                ],
            ],

        ],
    ],
];
