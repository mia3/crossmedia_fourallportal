<?php
defined('TYPO3_MODE') or die('Access denied');

$GLOBALS['TCA']['sys_file']['columns']['remote_id'] = [
    'config' => [
        'type' => 'passthrough'
    ]
];