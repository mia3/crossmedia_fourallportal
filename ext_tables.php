<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function()
    {

        if (TYPO3_MODE === 'BE') {

            \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
                'Crossmedia.Fourallportal',
                'tools', // Make module a submodule of 'tools'
                'fourallportal', // Submodule key
                '', // Position
                [
                    'Event' => 'index, check, reset, execute, sync',
                    'Server' => 'index, check, disable, enable, delete, restartSynchronisation, module',

                ],
                [
                    'access' => 'user,group',
                    'icon'   => 'EXT:fourallportal/Resources/Public/Icons/Extension.svg',
                    'labels' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_fourallportal.xlf',
                ]
            );

        }

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('fourallportal', 'Configuration/TypoScript', '4AllPortal Connector');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_fourallportal_domain_model_server', 'EXT:fourallportal/Resources/Private/Language/locallang_csh_tx_fourallportal_domain_model_server.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_fourallportal_domain_model_server');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_fourallportal_domain_model_complextype', 'EXT:fourallportal/Resources/Private/Language/locallang_csh_tx_fourallportal_domain_model_complextype.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_fourallportal_domain_model_complextype');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_fourallportal_domain_model_module', 'EXT:fourallportal/Resources/Private/Language/locallang_csh_tx_fourallportal_domain_model_module.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_fourallportal_domain_model_module');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_fourallportal_domain_model_event', 'EXT:fourallportal/Resources/Private/Language/locallang_csh_tx_fourallportal_domain_model_event.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_fourallportal_domain_model_event');

    }
);
