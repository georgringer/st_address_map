<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

//include_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('st_address_map') . 'lib/user_st_addressmapOnCurrentPage.php'); // Conditions for JS including
//\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43($_EXTKEY, 'pi1/class.tx_staddressmap_pi1.php', '_pi1', 'list_type', 1);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
    '

plugin.tx_staddressmap_pi1 = USER_INT
plugin.tx_staddressmap_pi1 {
    userFunc = GeorgRinger\StAddressMap\Controller\MainController->main
    ajaxtypenum = 1991
}

tt_content.list.20.st_address_map_pi1 < plugin.tx_staddressmap_pi1

st_address_map_ajax = PAGE
st_address_map_ajax  {
	typeNum = 1991
	10 < plugin.tx_staddressmap_pi1

	config {
		disableAllHeaderCode = 1
		xhtml_cleaning = 0
		admPanel = 0
		debug = 0
		no_cache = 1
	}
}

    ');

