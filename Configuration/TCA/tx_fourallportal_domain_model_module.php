<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module',
        'label' => 'connector_name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'connector_name,mapping_class,config_hash,last_event_id,shell_path,storage_pid,server',
        'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_module.gif'
    ],
    'interface' => [
        'showRecordFieldList' => 'connector_name, mapping_class, config_hash, last_event_id, shell_path, storage_pid, server',
    ],
    'types' => [
        '1' => ['showitem' => 'connector_name, mapping_class, config_hash, last_event_id, shell_path, storage_pid, server'],
    ],
    'columns' => [
        'connector_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.connector_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'mapping_class' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.mapping_class',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'multiple' => '0',
                'itemsProcFunc' => \Crossmedia\Fourallportal\Mapping\MappingRegister::class . '->getTcaSelectItems',
                'items' => array(['', '']),
                'size' => '1'
            ],
        ],
        'config_hash' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.config_hash',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'last_event_id' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.last_event_id',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'shell_path' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.shell_path',
            'displayCond' => 'FIELD:mapping_class:=:' . \Crossmedia\Fourallportal\Mapping\FalMapping::class,
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'storage_pid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.storage_pid',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'pages',
                'size' => 1,
                'hideSuggest' => 1
            ]
        ],
        'server' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.server',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_fourallportal_domain_model_server',
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],

        'server' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
