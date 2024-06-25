<?php
defined('TYPO3') or die('Access denied');

$GLOBALS['TCA']['sys_file']['columns']['remote_id'] = [
    'config' => [
        'type' => 'passthrough'
    ]
];