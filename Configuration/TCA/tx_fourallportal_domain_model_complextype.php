<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype',
        'label' => 'field_name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'rootLevel' => 1,
        'delete' => 'deleted',
        'searchFields' => 'event_id,event_type,status,skip_until,object_id,module',
        'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_complextype.gif'
    ],
    'interface' => [
        'showRecordFieldList' => 'name, type, label, field_name, actual_value, normalized_value, cast_type',
    ],
    'types' => [
        '1' => ['showitem' => 'name, type, label, field_name, actual_value, normalized_value, cast_type'],
    ],
    'columns' => [

        'crdate' => [
            'exclude' => true,
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'tstamp' => [
            'exclude' => true,
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.name',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.type',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'label' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.label',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'field_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.field_name',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'actual_value' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.actual_value',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'normalized_value' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.normalized_value',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'cast_type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.cast_type',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'parent_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.parent_uid',
            'config' => [
                'type' => 'passthrough',
            ]
        ],


    ],
];
