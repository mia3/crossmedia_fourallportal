<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server',
        'label' => 'domain',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'rootLevel' => 1,
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'domain,customer_name,username,password,active,modules',
        'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_server.gif'
    ],
    'interface' => [
        'showRecordFieldList' => 'domain, customer_name, username, password, active, modules',
    ],
    'types' => [
        '1' => ['showitem' => 'active, domain, customer_name, username, password, modules'],
    ],
    'columns' => [
        'domain' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.domain',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'customer_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.customer_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'username' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.username',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'password' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.password',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'active' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.active',
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
        'modules' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.modules',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_fourallportal_domain_model_module',
                'foreign_field' => 'server',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 0,
                    'levelLinksPosition' => 'top',
                    'showSynchronizationLink' => 1,
                    'showPossibleLocalizationRecords' => 1,
                    'showAllLocalizationLink' => 1
                ],
            ],

        ],

    ],
];
