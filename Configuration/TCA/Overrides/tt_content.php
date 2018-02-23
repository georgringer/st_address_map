<?php
defined('TYPO3_MODE') or die();

$boot = function () {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        'GeorgRinger.StAddressMap',
        'Pi1',
        'LLL:EXT:st_address_map/Resources/Private/Language/locallang_db.xml:tt_content.list_type_pi1'
    );

    $key = 'staddressmap_pi1';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$key] = 'pi_flexform';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$key] = 'recursive,select_key,pages';
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($key, 'FILE:EXT:st_address_map/Configuration/Flexforms/flexform_staddress.xml');
};

$boot();
unset($boot);
