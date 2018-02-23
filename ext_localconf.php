<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'GeorgRinger.StAddressMap',
    'Pi1',
    [
        'Map' => 'index,ajax'
    ],
    [
        'Map' => 'index,ajax',
    ]
);


