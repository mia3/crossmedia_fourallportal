<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype',
        'label' => 'field_name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'rootLevel' => 1,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'searchFields' => 'event_id,event_type,status,skip_until,object_id,module',
        'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_complextype.gif',
        'security' => [
          'ignorePageTypeRestriction' => true,
        ],
    ],
    'interface' => [
        'showRecordFieldList' => 'name, type, label, field_name, actual_value, normalized_value, cast_type',
    ],
    'types' => [
        '1' => ['showitem' => 'name, type, label, field_name, actual_value, normalized_value, cast_type'],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => [
                    ['LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages', -1],
                    ['LLL:EXT:lang/locallang_general.xlf:LGL.default_value', 0],
                ],
            ],
        ],
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
        'label_max' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.label_max',
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
        'actual_value_max' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.actual_value_max',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'normalized_value_max' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.normalized_value_max',
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
