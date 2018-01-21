<?php
defined('TYPO3_MODE') or die();

$boot = function () {
    $key = 'st_address_map';

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$key . '_pi1'] = 'layout,select_key,pages';

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin([
        'LLL:EXT:st_address_map/Resources/Private/Language/locallang_db.xml:tt_content.list_type_pi1',
        'st_address_map_pi1',
        'EXT:st_address_map/ext_icon.gif'
    ],'list_type', 'st_address_map');

    $key2 = 'staddress_map';
    $key2 = $key;
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$key2 . '_pi1'] = 'pi_flexform';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$key2 . '_pi1'] = 'recursive,select_key,pages';
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($key2 . '_pi1', 'FILE:EXT:st_address_map/Configuration/Flexforms/flexform_staddress.xml');
};

$boot();
unset($boot);
