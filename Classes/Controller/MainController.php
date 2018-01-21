<?php

namespace GeorgRinger\StAddressMap\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;


class MainController extends AbstractPlugin
{
    var $prefixId = 'tx_staddressmap_pi1';
    var $scriptRelPath = 'pi1/class.tx_staddressmap_pi1.php';
    var $extKey = 'st_address_map';
    var $pi_checkCHash = TRUE;
    protected $ttAddressFieldArray = [];
    protected $templateHtml = '';


    /**
     * @param string $field
     * @return bool
     */
    protected function isValidDatabaseColumn($field)
    {
        if (empty($this->ttAddressFieldArray)) {
            $this->ttAddressFieldArray = array_keys($GLOBALS['TYPO3_DB']->admin_get_fields('tt_address'));
        }

        return in_array($field, $this->ttAddressFieldArray, true);
    }

    protected function getTemplate(): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName('EXT:st_address_map/Resources/Private/Templates/FluidTemplate.html'));
        $view->getRequest()->setControllerExtensionName('StAddressMap');
        return $view;
    }

    /**
     * The main method of the PlugIn
     *
     * @param    string $content : The PlugIn content
     * @param    array $conf : The PlugIn configuration
     * @return   string  The content that is displayed on the website
     */
    public function main($content, $conf)
    {
        $this->conf = $conf;
        $this->pi_setPiVarDefaults();
        $this->pi_loadLL();
        $this->pi_initPIflexForm();
        $errormessage = '';

        $this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_staddressmap_pi1.'];

        $view = $this->getTemplate();

        // errorhandling
        $mapsettings = $this->cObj->data['pi_flexform']['data']['sDEF']['lDEF'];
        if (is_array($mapsettings)) {
            foreach ($mapsettings as $key => $value) {
                $$key = reset($value);
                if (reset($value) == '') {
                    $errormessage .= $this->checkEmptyFields($key);
                    if ($errormessage != '') $errormessage .= '<br />';
                }
            }
        }
        if ($errormessage != '') return '<div class="error">' . $errormessage . '</div>';

        // set addresslist
        $addresslist = GeneralUtility::intExplode(',', $addresslist, TRUE);
        $addresslist = implode(' or pid = ', $addresslist);

        $content_id = $this->cObj->data['uid'];
        $tablefields = ($this->conf['tablefields'] == '') ? '' : $this->conf['tablefields'] . ',';

        /* ----- Ajax ----- */
        if (GeneralUtility::_GET('type') === $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_staddressmap_pi1.']['ajaxtypenum']) {
            $cid = GeneralUtility::_GET('cid');
            $hmac = GeneralUtility::_GET('hmac');

            if ($hmac !== GeneralUtility::hmac($cid, 'st_address_map')) {
                return $this->pi_getLL('nodata');
            }
            return $this->gimmeData(GeneralUtility::_GET('v'), $cid, GeneralUtility::_GET('t'), $tablefields);
        }

        /* ----- selectfields ----- */
        $dropdownList = GeneralUtility::trimExplode(',', $this->conf['dropdownfields'], true);
        $dropdownResults = [];
        foreach ($dropdownList as $value) {

            $option = '';

            if ($this->isValidDatabaseColumn($value)) {
                $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                    $value,
                    'tt_address',
                    'pid = ' . $addresslist . ' AND hidden=0 AND deleted=0',
                    $value,
                    $value
                );

                if ($rows) {
                    if ($value === 'country') {

                        $items = array();
                        foreach ($rows as $row) {
                            $fields = 'cn_short_en'; // @todo make configurable
                            if ($row[$value] == '') {
                                continue;
                            }
                            $table = 'static_countries';
                            $where = 'uid=' . $row[$value];
                            $cn_short = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
                                $fields,
                                $table,
                                $where
                            );
                            $items[$row[$value]] = $cn_short[$fields];
                        }

                        // Array sort with Umlauts
                        // based on http://www.marcokrings.de/arrays-sortieren-mit-umlauten/

                        $aOriginal = $items;
                        if (count($aOriginal) == 0) {

                            return $aOriginal;
                        }
                        $aModified = array();
                        $aReturn = array();
                        $aSearch = array("Ä", "ä", "Ö", "ö", "Ü", "ü", "ß", "-");
                        $aReplace = array("Ae", "ae", "Oe", "oe", "Ue", "ue", "ss", " ");
                        foreach ($aOriginal as $key => $val) {
                            $aModified[$key] = str_replace($aSearch, $aReplace, $val);
                        }
                        natcasesort($aModified);
                        foreach ($aModified as $key => $val) {
                            $aReturn[$key] = $aOriginal[$key];
                        }
                        $items = $aReturn;

                    } else {
                        $items = $rows;
                    }
                    $dropdownResults[$value] = $items;
                }
            }
            $view->assign('dropdowns', $dropdownResults);
        }

        /* ----- inputfields ----- */
        $inputfieldList = GeneralUtility::trimExplode(',', $this->conf['inputfields'], true);
        $inputfieldListResults = [];
        foreach ($inputfieldList as $value) {
            if ($this->isValidDatabaseColumn($value)) {
                $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                    $value,
                    'tt_address',
                    'hidden=0 AND deleted=0',
                    $value,
                    $value);
                if ($rows && $GLOBALS['TYPO3_DB']->sql_affected_rows($rows) != 0) {
                    $inputfieldListResults[] = $value;
                }
            }
        }
        $view->assign('inputs', $inputfieldListResults);

        $bubblemarker = ($this->conf['bubblemarker']) ? 'var icon = "' . $this->conf['bubblemarker'] . '";' : 'var icon = "";';
        $GLOBALS['TSFE']->additionalFooterData[$this->extKey . '_665_' . $content_id] = '
			<script type="text/javascript">
			var map;
			var circle = null;
			var circledata = null;
			var marker = new Array();
			var centerpoints = new Array();
			var detailzoom = new Array();
			var city_marker = new Array();
			var city_centerpoints = new Array();
			var city_detailzoom = new Array();
			var region_marker = new Array();
			var region_centerpoints = new Array();
			var region_detailzoom = new Array();
			' . $bubblemarker . '

			function initialize(){
				var latlng = new google.maps.LatLng(' . $center_coordinates . ');
				var myMap_' . $content_id . ' = {
					zoom: ' . $start_zoom . ',
					center: latlng,
					mapTypeId: google.maps.MapTypeId.ROADMAP
				};
				map = new google.maps.Map(document.getElementById("tx_staddressmap_gmap_' . $content_id . '"), myMap_' . $content_id . ');
			}
			</script>';

        $view->assignMultiple([
            'contentId' => $this->cObj->data['uid'],
            'cidHmac' => GeneralUtility::hmac($this->cObj->data['uid'], 'st_address_map'),
            'settings' => $this->conf,
            'currentPageId' => $GLOBALS['TSFE']->id,
            'ajaxTypeNum' => $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_staddressmap_pi1.']['ajaxtypenum']
        ]);
        return $view->render();
    }

    private function checkEmptyFields($key)
    {
        return $this->pi_getLL('error_empty_' . $key);
    }


    /**
     * Get geo coordinates from address
     *
     * @param string $data
     * @return array lat, lng
     */

    protected function getMapsCoordinates($data): array
    {
        $json = GeneralUtility::getUrl('https://maps.googleapis.com/maps/api/geocode/json?sensor=false&region=de&address=' . urlencode($data));
        $jsonDecoded = json_decode($json, TRUE);
        if (!empty($jsonDecoded['results'])) {
            $lat = $jsonDecoded['results']['0']['geometry']['location']['lat'];
            $lng = $jsonDecoded['results']['0']['geometry']['location']['lng'];
        } else {
            $lat = 0;
            $lng = 0;
        }
        return [$lat, $lng];
    }

    private function gimmeData($var, $cid, $what, $tablefields)
    {
        $view = $this->getTemplate();
        $items = [];
        $validDatabaseFields = array();
        foreach (GeneralUtility::trimExplode(',', $tablefields, TRUE) as $field) {
            if ($this->isValidDatabaseColumn($field)) {
                $validDatabaseFields[] = $field;
            }
        }

        $this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_staddressmap_pi1.'];

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pi_flexform', 'tt_content', '(hidden=0 and deleted=0) and uid=' . (int)$cid);
        if ($res && $GLOBALS['TYPO3_DB']->sql_affected_rows($res) != 0) {
            while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
                $flexform = GeneralUtility::xml2array($row['pi_flexform']);
            }
        } else {
            return $this->pi_getLL('nodata');
        }

        foreach ($flexform['data']['sDEF']['lDEF'] as $key => $value) {
            $$key = reset($value);
        }
        $rad = ($this->conf['searchradius'] or $this->conf['searchradius'] != 0) ? $this->conf['searchradius'] : '20000';
        // ----- set addresslist ------
        $addresslist = GeneralUtility::intExplode(',', $addresslist, TRUE);
        $addresslist = implode(' or pid = ', $addresslist);

        //  ----- radius -----
        $js_circle = 'circledata = null;';
        if (in_array($what, preg_split('/\s?,\s?/', $this->conf['radiusfields']))) {
            // radius
            $rc = ($this->conf['radiuscountry']) ? ',' . $this->conf['radiuscountry'] : '';
            $koord = $this->getMapsCoordinates(GeneralUtility::_GET('v') . $rc);

            $radiusSearch =
                '6378.388 * acos(' .
                'sin(RADIANS(tx_staddressmap_lng)) * ' .
                'sin(RADIANS(' . (float)$koord[1] . ')) + ' .
                'cos(RADIANS(tx_staddressmap_lng)) * ' .
                'cos(RADIANS(' . (float)$koord[1] . ')) * ' .
                'cos(RADIANS(' . (float)$koord[0] . ') - RADIANS(tx_staddressmap_lat))' .
                ')';

            $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                'uid, ' . (!empty($validDatabaseFields) ? implode(', ', $validDatabaseFields) . ', ' : '') . 'tx_staddressmap_lat, tx_staddressmap_lng, ' . $radiusSearch . ' AS EAdvanced', 'tt_address',
                '(hidden=0 AND deleted=0) AND (pid = ' . $addresslist . ') AND ' . $radiusSearch . ' <= ' . (float)$rad,
                '',
                'EAdvanced'
            );

            // see radius
            if ($this->conf['circle'] == 1) {
                $js_circle .= '
					circledata = {
						strokeColor: "' . $this->conf['circleStrokeColor'] . '",
						strokeOpacity: ' . $this->conf['circleStrokeOpacity'] . ',
						strokeWeight: ' . $this->conf['circleStrokeWeight'] . ',
						fillColor: "' . $this->conf['circlefillColor'] . '",
						fillOpacity: ' . $this->conf['circlefillOpacity'] . ',
						map: map,
						center: new google.maps.LatLng(' . $koord['1'] . ', ' . $koord['0'] . '),
						radius: ' . ($rad * 1000) . '
					};
				';
            }
        } else {
            if ($this->isValidDatabaseColumn($what)) {
                $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                    'uid, ' . (!empty($validDatabaseFields) ? implode(', ', $validDatabaseFields) . ', ' : '') . 'tx_staddressmap_lat, tx_staddressmap_lng',
                    'tt_address',
                    '(hidden=0 and deleted=0) and (pid = ' . $addresslist . ') and ' . $what . ' like "' . $GLOBALS['TYPO3_DB']->escapeStrForLike($var, 'tt_address') . '"'
                );
            }
        }

        // see all
        if (GeneralUtility::_GET('all') == 1) {
            $orderBy = ($this->isValidDatabaseColumn($this->conf['orderall'])) ? $this->conf['orderall'] : 'city';
            $rad = ($this->conf['searchradius'] or $this->conf['searchradius'] != 0) ? $this->conf['searchradius'] : '20000';
            $res = $GLOBALS['TYPO3_DB']->exec_selectgetRows(
                'uid, ' . (!empty($validDatabaseFields) ? implode(', ', $validDatabaseFields) . ', ' : '') . 'tx_staddressmap_lat, tx_staddressmap_lng',
                'tt_address',
                '(hidden=0 and deleted=0) and (pid = ' . $addresslist . ')',
                '',
                $orderBy
            );
            $js_circle = '';
        }

        if ($res && $GLOBALS['TYPO3_DB']->sql_affected_rows($res) != 0) {
            $ji = 0;
            $common_lat = array();
            $common_lng = array();
            $js_output = '<script type="text/javascript">' . "\n";
            $js_output .= 'var a = new Array();' . LF;

            foreach ($res as $row) {
                if ($row['tx_staddressmap_lat'] == 0 || $row['tx_staddressmap_lng'] == 0) {
                    $newkoord = $this->getMapsCoordinates($row['zip'] . ' ' . $row['city'] . ',' . $row['address'] . ',' . $row['country']);
                    $koor_update = array();
                    $koor_update['tx_staddressmap_lat'] = $newkoord[0];
                    $koor_update['tx_staddressmap_lng'] = $newkoord[1];
                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                        'tt_address',
                        'uid = ' . $row['uid'],
                        $koor_update
                    );
                }

                // begin js
                $js_output .= 'a[' . $ji . '] = new Object();' . "\n";
                // begin bubbletext
                $bubbletext = '';
                foreach (preg_split('/\s?,\s?/', $this->conf['bubblefields']) as $tvalue) {
                    if ($row[$tvalue]) {
                        $bubblewrap = $this->conf['bubblelayout.'][$tvalue] ? $this->conf['bubblelayout.'][$tvalue] : '|';
                        if ($tvalue === 'email') {
                            //$bubbletext .= \TYPO3\CMS\Core\TypoScript\TemplateService::wrap(str_replace(array('<a', "'", '"'), array("tx_addressmap_replace", "|-|", "-|-"), $this->cObj->mailto_makelinks('mailto:' . $row[$tvalue], NULL )), $bubblewrap);
                            $bubbletext .= $bubblewrap;
                            //$bubbletext .= \TYPO3\CMS\Core\TypoScript\TemplateService::wrap(str_replace("\r\n", '<br />', htmlentities($row[$tvalue], ENT_COMPAT, 'UTF-8', 0)), $bubblewrap);
                        } else {

                            // \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump( $bubbletext );
                            //$bubbletext .= \TYPO3\CMS\Core\TypoScript\TemplateService::wrap( str_replace("\r\n", '<br />', htmlentities($row[$tvalue], ENT_COMPAT, 'UTF-8', 0)), $bubblewrap );
                            $bubbletext .= $bubblewrap;
                        }
                    }
                }

                // list
                foreach (preg_split('/\s?,\s?/', $tablefields) as $tvalue) {
                    if ($row[$tvalue]) {
                        $listwrap = $this->conf['listlayout.'][$tvalue] ? $this->conf['listlayout.'][$tvalue] : '|';
                        $markerArray['###' . strtoupper($tvalue) . '###'] = $row[$tvalue];
                    } else {
                        $markerArray['###' . strtoupper($tvalue) . '###'] = '';
                    }
                }

                $js_output .= 'a[' . $ji . '].name = \'' . $bubbletext . '\'' . "\n";
                $js_output .= 'a[' . $ji . '].lat = ' . $row['tx_staddressmap_lat'] . ';' . "\n";
                $js_output .= 'a[' . $ji . '].lng = ' . $row['tx_staddressmap_lng'] . ';' . "\n";

                // ----- Calculate average coordinates
                $common_lat[] = $row['tx_staddressmap_lat'];
                $common_lng[] = $row['tx_staddressmap_lng'];
                $ji++;

                $row['_distance'] = ($this->conf['radiusfields'] != '' && round($row['EAdvanced'], 1) > 0) ? $row['EAdvanced'] : '';
                $items[] = $row;
            }
            $js_output .= 'marker[0] = a;' . "\n";
            $js_output .= 'centerpoints[0] = new Object();' . "\n";

            if (in_array($what, preg_split('/\s?,\s?/', $this->conf['radiusfields']))) {
                $js_output .= 'centerpoints[0].lat = ' . $koord['1'] . ';' . "\n";
                $js_output .= 'centerpoints[0].lng = ' . $koord['0'] . ';' . "\n";
            } else {
                $js_output .= 'centerpoints[0].lat = ' . ((max($common_lat) + min($common_lat)) / 2) . ';' . "\n";
                $js_output .= 'centerpoints[0].lng = ' . ((max($common_lng) + min($common_lng)) / 2) . ';' . "\n";
            }

            $js_output .= 'detailzoom[0] = new Object();' . "\n";
            $js_output .= 'detailzoom[0] = ' . $detail_zoom . ';' . "\n";
            $js_output .= $js_circle;
            $js_output .= '</script>';

        } else {
            return $this->pi_getLL('nodata') . '<script type="text/javascript">marker = new Array();</script>';
        }

        $view->assignMultiple([
            'ajaxlist' => 1,
            'items' => $items,
            'js' => $js_output
        ]);
        return $view->render();
    }
}
