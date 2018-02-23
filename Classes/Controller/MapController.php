<?php

namespace GeorgRinger\StAddressMap\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\FlexFormService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;


class MapController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    protected $ttAddressFieldArray = [];

    /** @var array  */
    protected $currentContentElement = [];

    protected function initializeAction()
    {
        $this->currentContentElement = $this->configurationManager->getContentObject()->data;
    }


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


    public function indexAction()
    {

        $wtfMappingFields = ['mapwidth', 'mapheight', 'addresslist', 'start_zoom', 'detail_zoom', 'center_coordinates'];
        foreach ($wtfMappingFields as $field) {
            $$field = $this->settings[$field];
        }
        // set addresslist
        $addresslist = GeneralUtility::intExplode(',', $addresslist, TRUE);
        $addresslist = implode(' or pid = ', $addresslist);

        $content_id = $this->currentContentElement['uid'];
        $tablefields = ($this->settings['tablefields'] == '') ? '' : $this->settings['tablefields'] . ',';
        /* ----- Ajax ----- */
        $ajaxType = (int)GeneralUtility::_GET('type');
        if ($ajaxType !== 0  && $ajaxType === (int)$this->settings['ajaxtypenum']) {
            $cid = GeneralUtility::_GET('cid');
            $hmac = GeneralUtility::_GET('hmac');

            if ($hmac !== GeneralUtility::hmac($cid, 'st_address_map')) {
                return $this->translate('nodata');
            }
            return $this->gimmeData(GeneralUtility::_GET('v'), $cid, GeneralUtility::_GET('t'), $tablefields);
        }

        /* ----- selectfields ----- */
        $dropdownList = GeneralUtility::trimExplode(',', $this->settings['dropdownfields'], true);
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
            $this->view->assign('dropdowns', $dropdownResults);
        }

        /* ----- inputfields ----- */
        $inputfieldList = GeneralUtility::trimExplode(',', $this->settings['inputfields'], true);
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
        $this->view->assign('inputs', $inputfieldListResults);

        $bubblemarker = ($this->settings['bubblemarker']) ? 'var icon = "' . $this->settings['bubblemarker'] . '";' : 'var icon = "";';
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

        $this->view->assignMultiple([
            'contentId' => $this->currentContentElement['uid'],
            'cidHmac' => GeneralUtility::hmac($this->currentContentElement['uid'], 'st_address_map'),
            'settings' => $this->settings,
            'currentPageId' => $GLOBALS['TSFE']->id,
            'ajaxTypeNum' => $this->settings['ajaxtypenum']
        ]);
    }

    private function translate($label)
    {
        return LocalizationUtility::translate($label, 'st_address_map');
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
        $items = [];
        $validDatabaseFields = array();
        foreach (GeneralUtility::trimExplode(',', $tablefields, TRUE) as $field) {
            if ($this->isValidDatabaseColumn($field)) {
                $validDatabaseFields[] = $field;
            }
        }

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pi_flexform', 'tt_content', '(hidden=0 and deleted=0) and uid=' . (int)$cid);
        $flex = '';
        if ($res && $GLOBALS['TYPO3_DB']->sql_affected_rows($res) != 0) {
            while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
                $flexform = GeneralUtility::xml2array($row['pi_flexform']);
                $flex = $row['pi_flexform'];
            }
        } else {
            return $this->translate('nodata');
        }

        $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
        $settings = $flexFormService->convertFlexFormContentToArray($flex);
        foreach ($settings['settings'] as $key => $value) {
            $$key = ($value);
        }

        $rad = ($this->settings['searchradius'] or $this->settings['searchradius'] != 0) ? $this->settings['searchradius'] : '20000';
        // ----- set addresslist ------
        $addresslist = GeneralUtility::intExplode(',', $addresslist, TRUE);
        $addresslist = implode(' or pid = ', $addresslist);

        //  ----- radius -----
        $js_circle = 'circledata = null;';
        if (in_array($what, preg_split('/\s?,\s?/', $this->settings['radiusfields']))) {
            // radius
            $rc = ($this->settings['radiuscountry']) ? ',' . $this->settings['radiuscountry'] : '';
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
            if ($this->settings['circle'] == 1) {
                $js_circle .= '
					circledata = {
						strokeColor: "' . $this->settings['circleStrokeColor'] . '",
						strokeOpacity: ' . $this->settings['circleStrokeOpacity'] . ',
						strokeWeight: ' . $this->settings['circleStrokeWeight'] . ',
						fillColor: "' . $this->settings['circlefillColor'] . '",
						fillOpacity: ' . $this->settings['circlefillOpacity'] . ',
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
            $orderBy = ($this->isValidDatabaseColumn($this->settings['orderall'])) ? $this->settings['orderall'] : 'city';
            $rad = ($this->settings['searchradius'] or $this->settings['searchradius'] != 0) ? $this->settings['searchradius'] : '20000';
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
                foreach (preg_split('/\s?,\s?/', $this->settings['bubblefields']) as $tvalue) {
                    if ($row[$tvalue]) {
                        $bubblewrap = $this->settings['bubblelayout.'][$tvalue] ? $this->settings['bubblelayout.'][$tvalue] : '|';
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
                        $listwrap = $this->settings['listlayout.'][$tvalue] ? $this->settings['listlayout.'][$tvalue] : '|';
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

                $row['_distance'] = ($this->settings['radiusfields'] != '' && round($row['EAdvanced'], 1) > 0) ? $row['EAdvanced'] : '';
                $items[] = $row;
            }
            $js_output .= 'marker[0] = a;' . "\n";
            $js_output .= 'centerpoints[0] = new Object();' . "\n";

            if (in_array($what, preg_split('/\s?,\s?/', $this->settings['radiusfields']))) {
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
            return $this->translate('nodata') . '<script type="text/javascript">marker = new Array();</script>';
        }

        $this->view->assignMultiple([
            'ajaxlist' => 1,
            'items' => $items,
            'js' => $js_output
        ]);
        return $this->view->render();
    }
}
