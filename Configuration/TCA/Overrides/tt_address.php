<?php
defined('TYPO3_MODE') or die();

$boot = function () {
    $newColoumns = [
        'tx_staddressmap_lat' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:st_address_map/Resources/Private/Language/locallang_db.xml:tt_address.tx_staddressmap_lat',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'default' => ''
            ]
        ],
        'tx_staddressmap_lng' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:st_address_map/Resources/Private/Language/locallang_db.xml:tt_address.tx_staddressmap_lng',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'default' => ''
            ]
        ],
    ];

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_address', $newColoumns);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('tt_address', 'tx_staddressmap_lat,tx_staddressmap_lng');
};

$boot();
unset($boot);
