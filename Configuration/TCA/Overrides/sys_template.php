<?php
defined('TYPO3_MODE') or die();

$boot = function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('st_address_map', 'Configuration/TypoScript/', 'st_address_map');

};

$boot();
unset($boot);
