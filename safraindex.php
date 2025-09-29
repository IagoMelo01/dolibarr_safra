<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       safra/safraindex.php
 *	\ingroup    safra
 *	\brief      Home page of safra top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
include_once './class/talhao.class.php';
include_once './class/ndvi.class.php';
include_once './class/municipio.class.php';

if (!function_exists('safra_format_number')) {
        /**
         * Format numbers using current locale separators.
         *
         * @param float $value
         * @param int   $decimals
         * @return string
         */
        function safra_format_number($value, $decimals = 0)
        {
                $locale = localeconv();
                $decimal = (!empty($locale['decimal_point']) ? $locale['decimal_point'] : '.');
                $thousand = (!empty($locale['thousands_sep']) ? $locale['thousands_sep'] : ',');

                return number_format((float) $value, $decimals, $decimal, $thousand);
        }
}

if (!function_exists('safra_count_table')) {
        /**
         * Count rows from a given Safra table.
         *
         * @param DoliDB $db    Database handler
         * @param string $table Table name without llx_ prefix
         * @return int
         */
        function safra_count_table($db, $table)
        {
                if (empty($table)) {
                        return 0;
                }

                // Avoid double prefix if already provided.
                $tableName = (strpos($table, MAIN_DB_PREFIX) === 0 ? $table : MAIN_DB_PREFIX.$table);

                $sql = 'SELECT COUNT(*) as cnt FROM '.$tableName;
                $resql = $db->query($sql);
                if (!$resql) {
                        dol_syslog(__METHOD__.': SQL error: '.$db->lasterror(), LOG_ERR);
                        return 0;
                }

                $obj = $db->fetch_object($resql);
                $db->free($resql);

                return (int) (!empty($obj->cnt) ? $obj->cnt : 0);
        }
}

// Load translation files required by the page
$langs->loadLangs(array("safra@safra"));

$action = GETPOST('action', 'aZ09');

$max = 5;
$now = dol_now();

// Security check - Protection if external user
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//if (!isModEnabled('safra')) {
//	accessforbidden('Module not enabled');
//}
//if (! $user->hasRight('safra', 'myobject', 'read')) {
//	accessforbidden();
//}
//restrictedArea($user, 'safra', 0, 'safra_myobject', 'myobject', '', 'rowid');
//if (empty($user->admin)) {
//	accessforbidden('Must be admin');
//}


/*
 * Actions
 */

// None


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("SafraArea"), '', '', 0, 0, '', '', '', 'mod-safra page-index');

print load_fiche_titre($langs->trans("SafraArea"), '', 'safra.png@safra');

// Prepare dashboard data
$talhaoObject = new Talhao($db);
$talhaoList = $talhaoObject->fetchAll('ASC', 't.ref');
if (!is_array($talhaoList)) {
        $talhaoList = array();
}

$municipioObject = new Municipio($db);
$municipioRecords = $municipioObject->fetchAll('ASC', 't.label');
$municipioCache = array();
if (!is_array($municipioRecords)) {
        $municipioRecords = array();
}

$talhaoData = array();
$talhaoCache = array();
$areaByMunicipio = array();
$totalArea = 0;
$largestTalhao = null;

foreach ($municipioRecords as $municipio) {
        $municipioCache[$municipio->id] = $municipio->label ?: $municipio->ref;
}

foreach ($talhaoList as $talhao) {
        $talhaoId = !empty($talhao->id) ? $talhao->id : $talhao->rowid;
        $talhaoLabel = $talhao->label ? $talhao->label : $talhao->ref;
        $municipioId = !empty($talhao->municipio) ? (int) $talhao->municipio : 0;
        $municipioLabel = $municipioId && isset($municipioCache[$municipioId]) ? $municipioCache[$municipioId] : '';

        $talhaoCache[$talhaoId] = array(
                'label' => $talhaoLabel,
                'ref' => $talhao->ref,
        );

        $area = (float) $talhao->area;
        $totalArea += $area;

        $municipioKey = $municipioLabel ?: $langs->trans('SafraUnknownMunicipio');
        if (!isset($areaByMunicipio[$municipioKey])) {
                $areaByMunicipio[$municipioKey] = 0;
        }
        $areaByMunicipio[$municipioKey] += $area;

        if ($largestTalhao === null || $area > $largestTalhao['area']) {
                $largestTalhao = array('label' => $talhaoLabel, 'area' => $area);
        }

        $talhaoData[] = array(
                'id' => $talhaoId,
                'ref' => $talhao->ref,
                'label' => $talhaoLabel,
                'area' => $area,
                'municipio' => $municipioLabel,
                'geo_json' => $talhao->geo_json,
        );
}

$countTalhoes = count($talhaoData);
$averageArea = $countTalhoes > 0 ? ($totalArea / $countTalhoes) : 0;
$countCulturas = safra_count_table($db, 'safra_cultura');
$countAplicacoes = safra_count_table($db, 'safra_aplicacao');
$countEventos = safra_count_table($db, 'safra_evento');
$countColheitas = safra_count_table($db, 'safra_colheita');
$countMunicipios = count($areaByMunicipio);

$summaryCards = array(
        array(
                'title' => $langs->trans('SafraSummaryTalhoes'),
                'value' => safra_format_number($countTalhoes),
                'description' => $langs->trans('SafraSummaryTalhoesDesc'),
        ),
        array(
                'title' => $langs->trans('SafraSummaryArea'),
                'value' => ($totalArea > 0 ? safra_format_number($totalArea, 2).' '.$langs->trans('SafraUnitHectareShort') : '0 '.$langs->trans('SafraUnitHectareShort')),
                'description' => $langs->trans('SafraSummaryAreaDesc'),
        ),
        array(
                'title' => $langs->trans('SafraSummaryAverage'),
                'value' => ($countTalhoes > 0 ? safra_format_number($averageArea, 2).' '.$langs->trans('SafraUnitHectareShort') : $langs->trans('SafraSummaryAverageEmpty')),
                'description' => $langs->trans('SafraSummaryAverageDesc'),
        ),
        array(
                'title' => $langs->trans('SafraSummaryLargest'),
                'value' => ($largestTalhao ? safra_format_number($largestTalhao['area'], 2).' '.$langs->trans('SafraUnitHectareShort') : $langs->trans('SafraSummaryLargestEmpty')),
                'description' => ($largestTalhao ? $largestTalhao['label'] : $langs->trans('SafraSummaryLargestDesc')),
        ),
        array(
                'title' => $langs->trans('SafraSummaryCulturas'),
                'value' => safra_format_number($countCulturas),
                'description' => $langs->trans('SafraSummaryCulturasDesc'),
        ),
        array(
                'title' => $langs->trans('SafraSummaryAplicacoes'),
                'value' => safra_format_number($countAplicacoes),
                'description' => $langs->trans('SafraSummaryAplicacoesDesc'),
        ),
);

$insights = array(
        array(
                'label' => $langs->trans('SafraSummaryMunicipios'),
                'value' => safra_format_number($countMunicipios),
                'description' => $langs->trans('SafraSummaryMunicipiosDesc'),
        ),
        array(
                'label' => $langs->trans('SafraSummaryEventos'),
                'value' => safra_format_number($countEventos),
                'description' => $langs->trans('SafraSummaryEventosDesc'),
        ),
        array(
                'label' => $langs->trans('SafraSummaryColheitas'),
                'value' => safra_format_number($countColheitas),
                'description' => $langs->trans('SafraSummaryColheitasDesc'),
        ),
);

$weatherLatitude = getDolGlobalString('SAFRA_LATITUDE');
$weatherLongitude = getDolGlobalString('SAFRA_LONGITUDE');
$weatherLocation = getDolGlobalString('SAFRA_FAZENDA');

$ndviStatic = new NDVI($db);
$ndviEntries = $ndviStatic->fetchAll('DESC', 't.data', 5);
if (!is_array($ndviEntries)) {
        $ndviEntries = array();
}

$areaByMunicipioData = array();
foreach ($areaByMunicipio as $label => $area) {
        $areaByMunicipioData[] = array('label' => $label, 'area' => $area);
}

$weatherDescriptions = array(
        '0' => $langs->transnoentities('SafraWeatherDesc0'),
        '1' => $langs->transnoentities('SafraWeatherDesc1'),
        '2' => $langs->transnoentities('SafraWeatherDesc2'),
        '3' => $langs->transnoentities('SafraWeatherDesc3'),
        '45' => $langs->transnoentities('SafraWeatherDesc45'),
        '48' => $langs->transnoentities('SafraWeatherDesc48'),
        '51' => $langs->transnoentities('SafraWeatherDesc51'),
        '53' => $langs->transnoentities('SafraWeatherDesc53'),
        '55' => $langs->transnoentities('SafraWeatherDesc55'),
        '56' => $langs->transnoentities('SafraWeatherDesc56'),
        '57' => $langs->transnoentities('SafraWeatherDesc57'),
        '61' => $langs->transnoentities('SafraWeatherDesc61'),
        '63' => $langs->transnoentities('SafraWeatherDesc63'),
        '65' => $langs->transnoentities('SafraWeatherDesc65'),
        '66' => $langs->transnoentities('SafraWeatherDesc66'),
        '67' => $langs->transnoentities('SafraWeatherDesc67'),
        '71' => $langs->transnoentities('SafraWeatherDesc71'),
        '73' => $langs->transnoentities('SafraWeatherDesc73'),
        '75' => $langs->transnoentities('SafraWeatherDesc75'),
        '77' => $langs->transnoentities('SafraWeatherDesc77'),
        '80' => $langs->transnoentities('SafraWeatherDesc80'),
        '81' => $langs->transnoentities('SafraWeatherDesc81'),
        '82' => $langs->transnoentities('SafraWeatherDesc82'),
        '85' => $langs->transnoentities('SafraWeatherDesc85'),
        '86' => $langs->transnoentities('SafraWeatherDesc86'),
        '95' => $langs->transnoentities('SafraWeatherDesc95'),
        '96' => $langs->transnoentities('SafraWeatherDesc96'),
        '99' => $langs->transnoentities('SafraWeatherDesc99'),
);

print '<div class="safra-dashboard">';
print '<div class="safra-dashboard__grid">';

print '<section class="safra-card safra-card--summary">';
print '<div class="safra-card__header"><h2>'.$langs->trans('SafraSummaryTitle').'</h2></div>';
print '<div class="safra-summary-grid">';
foreach ($summaryCards as $card) {
        print '<div class="safra-summary-card">';
        print '<div class="safra-summary-card__value">'.dol_escape_htmltag($card['value']).'</div>';
        print '<div class="safra-summary-card__label">'.dol_escape_htmltag($card['title']).'</div>';
        if (!empty($card['description'])) {
                print '<div class="safra-summary-card__description">'.dol_escape_htmltag($card['description']).'</div>';
        }
        print '</div>';
}
print '</div>';
print '</section>';

print '<section class="safra-card safra-card--weather">';
print '<div class="safra-card__header"><h2>'.$langs->trans('SafraWeatherTitle').'</h2>';
if (!empty($weatherLocation)) {
        print '<span class="safra-chip">'.dol_escape_htmltag($weatherLocation).'</span>';
}
print '</div>';
print '<div id="weather-content" class="safra-weather">'.$langs->trans('SafraWeatherLoading').'</div>';
print '</section>';

print '<section class="safra-card safra-card--map">';
print '<div class="safra-card__header"><h2>'.$langs->trans('SafraMapTitle').'</h2></div>';
print '<div id="mapIndex" class="safra-map"><div id="boxLoading" class="display"></div></div>';
print '</section>';

print '<section class="safra-card safra-card--chart">';
print '<div class="safra-card__header"><h2>'.$langs->trans('SafraAreaByTalhao').'</h2></div>';
if ($countTalhoes > 0) {
        print '<canvas id="talhaoAreaChart" class="safra-chart"></canvas>';
} else {
        print '<p class="safra-empty">'.$langs->trans('SafraNoTalhaoData').'</p>';
}
print '</section>';

print '<section class="safra-card safra-card--chart">';
print '<div class="safra-card__header"><h2>'.$langs->trans('SafraAreaByMunicipio').'</h2></div>';
if (!empty($areaByMunicipioData)) {
        print '<canvas id="municipioAreaChart" class="safra-chart"></canvas>';
} else {
        print '<p class="safra-empty">'.$langs->trans('SafraNoMunicipioData').'</p>';
}
print '</section>';

print '<section class="safra-card safra-card--insights">';
print '<div class="safra-card__header"><h2>'.$langs->trans('SafraHighlights').'</h2></div>';
print '<ul class="safra-insights">';
foreach ($insights as $insight) {
        print '<li class="safra-insights__item">';
        print '<div class="safra-insights__value">'.dol_escape_htmltag($insight['value']).'</div>';
        print '<div class="safra-insights__label">'.dol_escape_htmltag($insight['label']).'</div>';
        if (!empty($insight['description'])) {
                print '<div class="safra-insights__description">'.dol_escape_htmltag($insight['description']).'</div>';
        }
        print '</li>';
}
print '</ul>';
print '</section>';

print '<section class="safra-card safra-card--list">';
print '<div class="safra-card__header"><h2>'.$langs->trans('SafraLatestNdvi').'</h2></div>';
if (!empty($ndviEntries)) {
        print '<ul class="safra-list">';
        foreach ($ndviEntries as $entry) {
                $talhaoLabel = '';
                if (!empty($entry->talhao)) {
                        $entryTalhaoId = (int) $entry->talhao;
                        if (isset($talhaoCache[$entryTalhaoId]['label'])) {
                                $talhaoLabel = $talhaoCache[$entryTalhaoId]['label'];
                        }
                }
                $dateLabel = !empty($entry->data) ? dol_print_date($db->jdate($entry->data), 'day') : '';
                print '<li class="safra-list__item">';
                print '<div class="safra-list__primary">'.$entry->getNomUrl(1).'</div>';
                print '<div class="safra-list__meta">';
                if ($talhaoLabel) {
                        print '<span>'.dol_escape_htmltag($langs->trans('SafraTalhaoShort').': '.$talhaoLabel).'</span>';
                }
                if ($dateLabel) {
                        print '<span>'.dol_escape_htmltag($langs->trans('Date').': '.$dateLabel).'</span>';
                }
                print '</div>';
                print '</li>';
        }
        print '</ul>';
} else {
        print '<p class="safra-empty">'.$langs->trans('SafraNoNdvi').'</p>';
}
print '</section>';

print '</div>';
print '</div>';

$jsOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

print '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
print '<script>';
print 'window.safraTalhoes = '.json_encode(array_values($talhaoData), $jsOptions).';';
print 'window.safraAreaByMunicipio = '.json_encode($areaByMunicipioData, $jsOptions).';';
print 'window.safraWeatherConfig = '.json_encode(array('latitude' => $weatherLatitude, 'longitude' => $weatherLongitude, 'location' => $weatherLocation), $jsOptions).';';
print 'window.safraLabels = '.json_encode(array(
        'areaUnit' => $langs->transnoentities('SafraUnitHectareShort'),
        'noTalhaoData' => $langs->transnoentities('SafraNoTalhaoData'),
        'weatherLoading' => $langs->transnoentities('SafraWeatherLoading'),
        'weatherConfigure' => $langs->transnoentities('SafraWeatherConfigure'),
        'weatherError' => $langs->transnoentities('SafraWeatherError'),
        'weatherToday' => $langs->transnoentities('SafraWeatherToday'),
        'weatherForecast' => $langs->transnoentities('SafraWeatherForecast'),
        'weatherTemperature' => $langs->transnoentities('SafraWeatherTemperature'),
        'weatherHumidity' => $langs->transnoentities('SafraWeatherHumidity'),
        'weatherWind' => $langs->transnoentities('SafraWeatherWind'),
        'weatherPrecipitation' => $langs->transnoentities('SafraWeatherPrecipitation'),
        'weatherFeelsLike' => $langs->transnoentities('SafraWeatherFeelsLike'),
        'weatherUnknown' => $langs->transnoentities('SafraWeatherUnknown'),
        'chartEmpty' => $langs->transnoentities('SafraChartEmpty'),
        'weatherDescriptions' => $weatherDescriptions,
), $jsOptions).';';
print '</script>';
// include do script
include_once "./js/talhao_index.js.php";

// End of page
llxFooter();
$db->close();
