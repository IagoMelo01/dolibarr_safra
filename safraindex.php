<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry  <jfefe@aternatik.fr>
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
 *      \file       safra/safraindex.php
 *      \ingroup    safra
 *      \brief      Home page of safra top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
        $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
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

// Load translation files required by the page
$langs->loadLangs(array('safra@safra'));

$action = GETPOST('action', 'aZ09');

$max = 5;
$now = dol_now();

// Security check - Protection if external user
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
        $action = '';
        $socid = $user->socid;
}

/*
 * View helpers
 */

/**
 * Simple HTTP client helper for dashboard integrations.
 *
 * @param string $url
 * @return string|null
 */
function safraDashboardCallApi($url)
{
        if (empty($url)) {
                return null;
        }

        if (function_exists('curl_init')) {
                $curl = curl_init($url);
                curl_setopt_array($curl, array(
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_USERAGENT => 'Dolibarr Safra Dashboard'
                ));

                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                        return $response;
                }
        } else {
                $context = stream_context_create(array(
                        'http' => array(
                                'method' => 'GET',
                                'timeout' => 10,
                                'header' => "User-Agent: Dolibarr Safra Dashboard\r\n"
                        )
                ));
                $response = @file_get_contents($url, false, $context);
                if ($response !== false) {
                        return $response;
                }
        }

        return null;
}

/**
 * Map Open-Meteo weather code to readable description and icon.
 *
 * @param int $code
 * @return array
 */
function safraDashboardWeatherVisual($code)
{
        $code = (int) $code;

        $map = array(
                0 => array('icon' => '☀️', 'label' => 'Céu limpo'),
                1 => array('icon' => '🌤️', 'label' => 'Predomínio de sol'),
                2 => array('icon' => '⛅', 'label' => 'Parcialmente nublado'),
                3 => array('icon' => '☁️', 'label' => 'Nublado'),
                45 => array('icon' => '🌫️', 'label' => 'Nevoeiro'),
                48 => array('icon' => '🌫️', 'label' => 'Nevoeiro gelado'),
                51 => array('icon' => '🌦️', 'label' => 'Garoa leve'),
                53 => array('icon' => '🌦️', 'label' => 'Garoa moderada'),
                55 => array('icon' => '🌧️', 'label' => 'Garoa intensa'),
                61 => array('icon' => '🌦️', 'label' => 'Chuva fraca'),
                63 => array('icon' => '🌧️', 'label' => 'Chuva moderada'),
                65 => array('icon' => '🌧️', 'label' => 'Chuva forte'),
                71 => array('icon' => '🌨️', 'label' => 'Neve fraca'),
                73 => array('icon' => '🌨️', 'label' => 'Neve moderada'),
                75 => array('icon' => '❄️', 'label' => 'Neve intensa'),
                80 => array('icon' => '🌦️', 'label' => 'Pancadas isoladas'),
                81 => array('icon' => '🌧️', 'label' => 'Pancadas fortes'),
                82 => array('icon' => '⛈️', 'label' => 'Temporal'),
                95 => array('icon' => '⛈️', 'label' => 'Trovoadas'),
                96 => array('icon' => '⛈️', 'label' => 'Trovoadas com granizo'),
                99 => array('icon' => '🌩️', 'label' => 'Temporal severo')
        );

        return isset($map[$code]) ? $map[$code] : array('icon' => 'ℹ️', 'label' => 'Condição desconhecida');
}

/**
 * Format numeric values for dashboard display.
 *
 * @param float $value
 * @param int   $decimals
 * @return string
 */
function safraFormatNumber($value, $decimals = 2)
{
        return number_format((float) $value, $decimals, ',', '.');
}

/**
 * Retrieve weather data for dashboard widgets.
 *
 * @param string $location
 * @param Translate $langs
 * @return array
 */
function safraDashboardFetchWeather($location, $langs)
{
        $country = '';
        $locationName = '';
        $latitude = null;
        $longitude = null;

        if (is_array($location)) {
            $location = implode(',', $location);
        }

        $location = trim((string) $location);
        if (!empty($location) && preg_match('/^-?[0-9]+(?:\.[0-9]+)?\s*,\s*-?[0-9]+(?:\.[0-9]+)?$/', $location)) {
                $parts = preg_split('/\s*,\s*/', $location);
                if (count($parts) === 2) {
                        $latitude = (float) $parts[0];
                        $longitude = (float) $parts[1];
                        $locationName = $langs->trans('SafraDashboardWeatherDefaultLocation');
                }
        }

        if ($latitude === null || $longitude === null) {
                if (empty($location)) {
                        return array(
                                'success' => false,
                                'message' => $langs->trans('SafraDashboardNoWeather')
                        );
                }

                $geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?name=' . rawurlencode($location) . '&count=1&language=pt&format=json';
                $geoResponse = safraDashboardCallApi($geoUrl);
                if (empty($geoResponse)) {
                        return array(
                                'success' => false,
                                'message' => $langs->trans('SafraDashboardNoWeather')
                        );
                }

                $geoData = json_decode($geoResponse, true);
                if (empty($geoData['results'][0])) {
                        return array(
                                'success' => false,
                                'message' => $langs->trans('SafraDashboardNoWeather')
                        );
                }

                $result = $geoData['results'][0];
                $latitude = isset($result['latitude']) ? (float) $result['latitude'] : null;
                $longitude = isset($result['longitude']) ? (float) $result['longitude'] : null;
                $locationName = $result['name'];
                $country = isset($result['country']) ? $result['country'] : '';
        }

        if ($latitude === null || $longitude === null) {
                return array(
                        'success' => false,
                        'message' => $langs->trans('SafraDashboardNoWeather')
                );
        }

        $forecastUrl = 'https://api.open-meteo.com/v1/forecast?latitude=' . $latitude . '&longitude=' . $longitude . '&current_weather=true&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max&timezone=auto&forecast_days=5';
        $forecastResponse = safraDashboardCallApi($forecastUrl);
        if (empty($forecastResponse)) {
                return array(
                        'success' => false,
                        'message' => $langs->trans('SafraDashboardNoWeather')
                );
        }

        $forecastData = json_decode($forecastResponse, true);
        if (empty($forecastData['current_weather'])) {
                return array(
                        'success' => false,
                        'message' => $langs->trans('SafraDashboardNoWeather')
                );
        }

        $visual = safraDashboardWeatherVisual($forecastData['current_weather']['weathercode']);

        $dailyEntries = array();
        if (!empty($forecastData['daily']['time'])) {
                foreach ($forecastData['daily']['time'] as $index => $dateValue) {
                        $timestamp = strtotime($dateValue . ' 00:00:00');
                        $label = $timestamp ? dol_print_date($timestamp, 'daytextshort') : $dateValue;

                        $dailyEntries[] = array(
                                'label' => $label,
                                'raw' => $dateValue,
                                'min' => isset($forecastData['daily']['temperature_2m_min'][$index]) ? (float) $forecastData['daily']['temperature_2m_min'][$index] : null,
                                'max' => isset($forecastData['daily']['temperature_2m_max'][$index]) ? (float) $forecastData['daily']['temperature_2m_max'][$index] : null,
                                'rain' => isset($forecastData['daily']['precipitation_probability_max'][$index]) ? (int) $forecastData['daily']['precipitation_probability_max'][$index] : null
                        );
                }
        }

        return array(
                'success' => true,
                'location' => $locationName,
                'country' => $country,
                'current' => array(
                        'temperature' => isset($forecastData['current_weather']['temperature']) ? (float) $forecastData['current_weather']['temperature'] : null,
                        'windspeed' => isset($forecastData['current_weather']['windspeed']) ? (float) $forecastData['current_weather']['windspeed'] : null,
                        'description' => $visual['label'],
                        'icon' => $visual['icon']
                ),
                'units' => array(
                        'temperature' => isset($forecastData['daily_units']['temperature_2m_max']) ? $forecastData['daily_units']['temperature_2m_max'] : '°C',
                        'rain' => isset($forecastData['daily_units']['precipitation_probability_max']) ? $forecastData['daily_units']['precipitation_probability_max'] : '%',
                        'wind' => isset($forecastData['current_weather_units']['windspeed']) ? $forecastData['current_weather_units']['windspeed'] : 'km/h'
                ),
                'forecast' => $dailyEntries
        );
}

$form = new Form($db);
$formfile = new FormFile($db);

$latitudeConf = getDolGlobalString('SAFRA_LATITUDE', '');
$longitudeConf = getDolGlobalString('SAFRA_LONGITUDE', '');
$weatherLookup = '';
if ($latitudeConf !== '' && $longitudeConf !== '') {
        $weatherLookup = $latitudeConf . ',' . $longitudeConf;
} else {
        $locationCandidates = array();
        if (!empty($conf->global->MAIN_INFO_SOCIETE_TOWN)) {
                $locationCandidates[] = $conf->global->MAIN_INFO_SOCIETE_TOWN;
        }
        if (!empty($conf->global->MAIN_INFO_SOCIETE_ZIP)) {
                $locationCandidates[] = $conf->global->MAIN_INFO_SOCIETE_ZIP;
        }
        if (!empty($conf->global->MAIN_INFO_SOCIETE_COUNTRY)) {
                $locationCandidates[] = $conf->global->MAIN_INFO_SOCIETE_COUNTRY;
        }
        $weatherLookup = trim(implode(', ', array_filter($locationCandidates)));
}

$weatherData = safraDashboardFetchWeather($weatherLookup, $langs);
if (empty($weatherData['units'])) {
        $weatherData['units'] = array(
                'temperature' => '°C',
                'rain' => '%',
                'wind' => 'km/h'
        );
}

$objTalhao = new Talhao($db);
$listTalhao = $objTalhao->fetchAll('', 't.label');
if (!is_array($listTalhao)) {
        $listTalhao = array();
}

$jsonData = array();
$areaArray = array();
$talhaoAreaList = array();
$talhaoCount = 0;
$totalArea = 0;
$largestTalhao = null;
$kanbanHtml = '';
$municipioTotals = array();
$municipioNames = array();
$municipioObj = new Municipio($db);

foreach ($listTalhao as $talhao) {
        $talhaoCount++;
        $area = isset($talhao->area) ? (float) $talhao->area : 0;
        $totalArea += $area;

        if (!empty($talhao->geo_json)) {
                $jsonData[] = $talhao->geo_json;
                $areaArray[] = $area;
        }

        $talhaoLabel = !empty($talhao->label) ? $talhao->label : $talhao->ref;
        $talhaoAreaList[] = array(
                'label' => $talhaoLabel,
                'area' => $area
        );

        if ($area > 0 && (empty($largestTalhao) || $area > $largestTalhao['area'])) {
                $largestTalhao = array(
                        'label' => $talhaoLabel,
                        'area' => $area
                );
        }

        $municipioLabel = '';
        if (!empty($talhao->municipio)) {
                $municipioId = (int) $talhao->municipio;
                if (!isset($municipioNames[$municipioId])) {
                        if ($municipioObj->fetch($municipioId) > 0) {
                                $municipioNames[$municipioId] = !empty($municipioObj->label) ? $municipioObj->label : $municipioObj->ref;
                        } else {
                                $municipioNames[$municipioId] = '';
                        }
                }
                $municipioLabel = $municipioNames[$municipioId];
        }
        if (empty($municipioLabel)) {
                $municipioLabel = $langs->trans('SafraDashboardNoMunicipio');
        }
        if (!isset($municipioTotals[$municipioLabel])) {
                $municipioTotals[$municipioLabel] = 0;
        }
        $municipioTotals[$municipioLabel] += $area;

        $kanbanHtml .= '<div class="talhao-kanban-card">' . $talhao->getKanbanView() . '</div>';
}

$averageArea = $talhaoCount > 0 ? $totalArea / $talhaoCount : 0;

$talhaoAreaTop = $talhaoAreaList;
usort($talhaoAreaTop, function ($a, $b) {
        return (float) $b['area'] <=> (float) $a['area'];
});
$talhaoAreaTop = array_slice($talhaoAreaTop, 0, 10);
$talhaoChartLabels = array();
$talhaoChartAreas = array();
foreach ($talhaoAreaTop as $item) {
        $talhaoChartLabels[] = $item['label'];
        $talhaoChartAreas[] = (float) $item['area'];
}

arsort($municipioTotals);
$municipioChartLabels = array_keys($municipioTotals);
$municipioChartAreas = array_map('floatval', array_values($municipioTotals));

$weatherChartData = null;
if (!empty($weatherData['success']) && !empty($weatherData['forecast'])) {
        $weatherChartData = array(
                'labels' => array(),
                'min' => array(),
                'max' => array(),
                'rain' => array()
        );
        foreach ($weatherData['forecast'] as $entry) {
                $weatherChartData['labels'][] = $entry['label'];
                $weatherChartData['min'][] = isset($entry['min']) ? (float) $entry['min'] : null;
                $weatherChartData['max'][] = isset($entry['max']) ? (float) $entry['max'] : null;
                $weatherChartData['rain'][] = isset($entry['rain']) ? (int) $entry['rain'] : null;
        }
}

$recentTalhoes = $listTalhao;
usort($recentTalhoes, function ($a, $b) {
        $aTime = !empty($a->tms) ? strtotime($a->tms) : 0;
        $bTime = !empty($b->tms) ? strtotime($b->tms) : 0;
        return $bTime <=> $aTime;
});
$recentTalhoes = array_slice($recentTalhoes, 0, 5);

$temperatureUnit = !empty($weatherData['units']['temperature']) ? $weatherData['units']['temperature'] : '°C';
$windUnit = !empty($weatherData['units']['wind']) ? $weatherData['units']['wind'] : 'km/h';
$rainUnit = !empty($weatherData['units']['rain']) ? $weatherData['units']['rain'] : '%';

$talhaoChartData = array('labels' => $talhaoChartLabels, 'areas' => $talhaoChartAreas);
$municipioChartData = array('labels' => $municipioChartLabels, 'areas' => $municipioChartAreas);

llxHeader('', $langs->trans('SafraArea'), '', '', 0, 0, '', '', '', 'mod-safra page-index');

print load_fiche_titre($langs->trans('SafraArea'), '', 'safra.png@safra');

$cssUrl = dol_buildpath('/safra/css/safra_dashboard.css', 1);
print '<link rel="stylesheet" type="text/css" href="' . $cssUrl . '">' . "\n";

print '<div class="fichecenter">';
print '<div class="safra-dashboard">';

print '<div class="safra-dashboard__grid safra-dashboard__grid--summary">';
print '  <div class="safra-card safra-card--stats">';
print '    <h3>' . dol_escape_htmltag($langs->trans('SafraDashboardOverview')) . '</h3>';
print '    <div class="safra-stats">';
print '      <div class="safra-stat"><span>' . dol_escape_htmltag($langs->trans('SafraDashboardTalhoes')) . '</span><strong>' . number_format($talhaoCount, 0, ',', '.') . '</strong></div>';
print '      <div class="safra-stat"><span>' . dol_escape_htmltag($langs->trans('SafraDashboardAreaTotal')) . '</span><strong>' . safraFormatNumber($totalArea) . ' ha</strong></div>';
print '      <div class="safra-stat"><span>' . dol_escape_htmltag($langs->trans('SafraDashboardAreaMedia')) . '</span><strong>' . safraFormatNumber($averageArea) . ' ha</strong></div>';
if (!empty($largestTalhao)) {
        print '      <div class="safra-stat safra-stat--highlight"><span>' . dol_escape_htmltag($langs->trans('SafraDashboardLargestTalhao')) . '</span><strong>' . dol_escape_htmltag($largestTalhao['label']) . '</strong><small>' . safraFormatNumber($largestTalhao['area']) . ' ha</small></div>';
} else {
        print '      <div class="safra-stat safra-stat--highlight"><span>' . dol_escape_htmltag($langs->trans('SafraDashboardLargestTalhao')) . '</span><strong>--</strong></div>';
}
print '    </div>';
print '  </div>';

print '  <div class="safra-card safra-card--weather">';
print '    <h3>' . dol_escape_htmltag($langs->trans('SafraDashboardWeather')) . '</h3>';
if (!empty($weatherData['success'])) {
        $locationLabel = trim($weatherData['location'] . (!empty($weatherData['country']) ? ', ' . $weatherData['country'] : ''));
        print '    <p class="weather-location">' . dol_escape_htmltag($langs->trans('SafraDashboardWeatherLocation', $locationLabel)) . '</p>';
        print '    <div class="weather-current">';
        print '      <div class="weather-current__icon">' . dol_escape_htmltag($weatherData['current']['icon']) . '</div>';
        print '      <div class="weather-current__details">';
        if (isset($weatherData['current']['temperature'])) {
                print '        <span class="weather-temp">' . safraFormatNumber($weatherData['current']['temperature'], 1) . ' ' . dol_escape_htmltag($temperatureUnit) . '</span>';
        }
        print '        <span class="weather-description">' . dol_escape_htmltag($weatherData['current']['description']) . '</span>';
        if (isset($weatherData['current']['windspeed'])) {
                $windText = $langs->trans('SafraDashboardWeatherWind', safraFormatNumber($weatherData['current']['windspeed'], 1) . ' ' . $windUnit);
                print '        <span class="weather-wind">' . dol_escape_htmltag($windText) . '</span>';
        }
        print '      </div>';
        print '    </div>';

        if (!empty($weatherData['forecast'])) {
                print '    <div class="weather-forecast">';
                foreach ($weatherData['forecast'] as $entry) {
                        print '      <div class="weather-forecast__day">';
                        print '        <span class="weather-forecast__label">' . dol_escape_htmltag($entry['label']) . '</span>';
                        $maxText = isset($entry['max']) ? safraFormatNumber($entry['max'], 1) . ' ' . $temperatureUnit : '--';
                        $minText = isset($entry['min']) ? safraFormatNumber($entry['min'], 1) . ' ' . $temperatureUnit : '--';
                        print '        <span class="weather-forecast__temps">' . dol_escape_htmltag($langs->trans('SafraDashboardWeatherMax')) . ': ' . dol_escape_htmltag($maxText) . ' · ' . dol_escape_htmltag($langs->trans('SafraDashboardWeatherMin')) . ': ' . dol_escape_htmltag($minText) . '</span>';
                        if (isset($entry['rain'])) {
                                $rainText = $langs->trans('SafraDashboardWeatherRainChance') . ': ' . (int) $entry['rain'] . ' ' . $rainUnit;
                                print '        <span class="weather-forecast__rain">' . dol_escape_htmltag($rainText) . '</span>';
                        }
                        print '      </div>';
                }
                print '    </div>';
        } else {
                print '    <p class="empty-state">' . dol_escape_htmltag($langs->trans('SafraDashboardWeatherNoForecast')) . '</p>';
        }
} else {
        print '    <p class="empty-state">' . dol_escape_htmltag($weatherData['message']) . '</p>';
}
print '  </div>';
print '</div>';

print '<div class="safra-dashboard__grid safra-dashboard__grid--map">';
print '  <div class="safra-card safra-card--map">';
print '    <div class="safra-card__header"><h3>' . dol_escape_htmltag($langs->trans('SafraDashboardMap')) . '</h3></div>';
print '    <div id="mapIndex" class="safra-map"><div id="boxLoading" class="display"></div></div>';
print '  </div>';
print '  <div class="safra-card safra-card--kanban">';
print '    <div class="safra-card__header"><h3>' . dol_escape_htmltag($langs->trans('SafraDashboardTalhoes')) . '</h3></div>';
if (!empty($kanbanHtml)) {
        print '    <div class="talhao-kanban">' . $kanbanHtml . '</div>';
} else {
        print '    <p class="empty-state">' . dol_escape_htmltag($langs->trans('SafraDashboardNoTalhao')) . '</p>';
}
print '  </div>';
print '  <div class="safra-card safra-card--recent">';
print '    <div class="safra-card__header"><h3>' . dol_escape_htmltag($langs->trans('SafraDashboardRecentUpdates')) . '</h3></div>';
if (!empty($recentTalhoes)) {
        print '    <ul class="safra-recent-list">';
        foreach ($recentTalhoes as $talhao) {
                $link = $talhao->getNomUrl(1);
                $dateLabel = !empty($talhao->tms) ? dol_print_date(strtotime($talhao->tms), 'dayhour') : '';
                print '      <li class="safra-recent-item">';
                print '        <div class="safra-recent-item__title">' . $link . '</div>';
                if (!empty($dateLabel)) {
                        print '        <div class="safra-recent-item__meta">' . dol_escape_htmltag($dateLabel) . '</div>';
                }
                print '      </li>';
        }
        print '    </ul>';
} else {
        print '    <p class="empty-state">' . dol_escape_htmltag($langs->trans('SafraDashboardNoRecent')) . '</p>';
}
print '  </div>';
print '</div>';

print '<div class="safra-dashboard__grid safra-dashboard__grid--charts">';
print '  <div class="safra-card safra-card--chart">';
print '    <div class="safra-card__header"><h3>' . dol_escape_htmltag($langs->trans('SafraDashboardTalhaoDistribution')) . '</h3></div>';
if (!empty($talhaoChartLabels)) {
        print '    <canvas id="talhaoAreaChart" class="chart-canvas"></canvas>';
} else {
        print '    <p class="empty-state">' . dol_escape_htmltag($langs->trans('SafraDashboardNoChartData')) . '</p>';
}
print '  </div>';

print '  <div class="safra-card safra-card--chart">';
print '    <div class="safra-card__header"><h3>' . dol_escape_htmltag($langs->trans('SafraDashboardMunicipioDistribution')) . '</h3></div>';
if (!empty($municipioChartLabels)) {
        print '    <canvas id="municipioAreaChart" class="chart-canvas"></canvas>';
} else {
        print '    <p class="empty-state">' . dol_escape_htmltag($langs->trans('SafraDashboardNoChartData')) . '</p>';
}
print '  </div>';

print '  <div class="safra-card safra-card--chart">';
print '    <div class="safra-card__header"><h3>' . dol_escape_htmltag($langs->trans('SafraDashboardWeatherTrend')) . '</h3></div>';
if (!empty($weatherChartData['labels'])) {
        print '    <canvas id="weatherForecastChart" class="chart-canvas"></canvas>';
} else {
        print '    <p class="empty-state">' . dol_escape_htmltag($langs->trans('SafraDashboardWeatherNoForecast')) . '</p>';
}
print '  </div>';
print '</div>';

print '</div>'; // safra-dashboard
print '</div>'; // fichecenter

$chartJsUrl = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
print '<script src="' . $chartJsUrl . '"></script>' . "\n";

$jsJsonData = json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$jsAreaArray = json_encode($areaArray, JSON_NUMERIC_CHECK);
$jsTalhaoChart = json_encode($talhaoChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
$jsMunicipioChart = json_encode($municipioChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
$jsWeatherChart = json_encode($weatherChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
$jsLabelsTalhao = json_encode($langs->trans('SafraDashboardTalhaoDistribution'));
$jsLabelsMunicipio = json_encode($langs->trans('SafraDashboardMunicipioDistribution'));
$jsLabelMax = json_encode($langs->trans('SafraDashboardChartMax'));
$jsLabelMin = json_encode($langs->trans('SafraDashboardChartMin'));
$jsLabelRain = json_encode($langs->trans('SafraDashboardChartRain'));
$jsTemperatureUnit = json_encode($temperatureUnit);
$jsRainUnit = json_encode($rainUnit);

print "<script>\n";
print 'const json = ' . $jsJsonData . ';' . "\n";
print 'const area_array = ' . $jsAreaArray . ';' . "\n";
print 'const talhaoChartData = ' . $jsTalhaoChart . ';' . "\n";
print 'const municipioChartData = ' . $jsMunicipioChart . ';' . "\n";
print 'const weatherChartData = ' . $jsWeatherChart . ';' . "\n";
print 'const chartLabels = {' . "\n";
print '  talhao: ' . $jsLabelsTalhao . ',' . "\n";
print '  municipio: ' . $jsLabelsMunicipio . ',' . "\n";
print '  max: ' . $jsLabelMax . ',' . "\n";
print '  min: ' . $jsLabelMin . ',' . "\n";
print '  rain: ' . $jsLabelRain . "\n";
print '};' . "\n";
print 'const weatherAxisUnits = {' . "\n";
print '  temperature: ' . $jsTemperatureUnit . ',' . "\n";
print '  rain: ' . $jsRainUnit . "\n";
print '};' . "\n";
print 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
print '  if (window.Chart && talhaoChartData && talhaoChartData.labels && talhaoChartData.labels.length) {' . "\n";
print '    const ctxTalhao = document.getElementById("talhaoAreaChart");' . "\n";
print '    if (ctxTalhao) {' . "\n";
print '      new Chart(ctxTalhao, {' . "\n";
print '        type: "bar",' . "\n";
print '        data: {' . "\n";
print '          labels: talhaoChartData.labels,' . "\n";
print '          datasets: [{' . "\n";
print '            label: chartLabels.talhao,' . "\n";
print '            data: talhaoChartData.areas,' . "\n";
print '            backgroundColor: "rgba(34, 197, 94, 0.4)",' . "\n";
print '            borderColor: "rgba(22, 163, 74, 0.8)",' . "\n";
print '            borderWidth: 1,' . "\n";
print '            borderRadius: 6' . "\n";
print '          }]' . "\n";
print '        },' . "\n";
print '        options: {' . "\n";
print '          responsive: true,' . "\n";
print '          maintainAspectRatio: false,' . "\n";
print '          scales: {' . "\n";
print '            y: {' . "\n";
print '              beginAtZero: true,' . "\n";
print '              ticks: {' . "\n";
print '                callback: function(value) {' . "\n";
print '                  return value + " ha";' . "\n";
print '                }' . "\n";
print '              }' . "\n";
print '            }' . "\n";
print '          }' . "\n";
print '        }' . "\n";
print '      });' . "\n";
print '    }' . "\n";
print '  }' . "\n";
print '  if (window.Chart && municipioChartData && municipioChartData.labels && municipioChartData.labels.length) {' . "\n";
print '    const ctxMunicipio = document.getElementById("municipioAreaChart");' . "\n";
print '    if (ctxMunicipio) {' . "\n";
print '      new Chart(ctxMunicipio, {' . "\n";
print '        type: "doughnut",' . "\n";
print '        data: {' . "\n";
print '          labels: municipioChartData.labels,' . "\n";
print '          datasets: [{' . "\n";
print '            data: municipioChartData.areas,' . "\n";
print '            backgroundColor: ["#0ea5e9","#38bdf8","#a5f3fc","#0284c7","#22d3ee","#06b6d4","#0f172a","#2dd4bf","#14b8a6","#0d9488"],' . "\n";
print '            borderWidth: 1' . "\n";
print '          }]' . "\n";
print '        },' . "\n";
print '        options: {' . "\n";
print '          responsive: true,' . "\n";
print '          maintainAspectRatio: false,' . "\n";
print '          plugins: {' . "\n";
print '            legend: {' . "\n";
print '              position: "bottom"' . "\n";
print '            }' . "\n";
print '          }' . "\n";
print '        }' . "\n";
print '      });' . "\n";
print '    }' . "\n";
print '  }' . "\n";
print '  if (window.Chart && weatherChartData && weatherChartData.labels && weatherChartData.labels.length) {' . "\n";
print '    const ctxWeather = document.getElementById("weatherForecastChart");' . "\n";
print '    if (ctxWeather) {' . "\n";
print '      new Chart(ctxWeather, {' . "\n";
print '        type: "bar",' . "\n";
print '        data: {' . "\n";
print '          labels: weatherChartData.labels,' . "\n";
print '          datasets: [' . "\n";
print '            {' . "\n";
print '              type: "line",' . "\n";
print '              label: chartLabels.max,' . "\n";
print '              data: weatherChartData.max,' . "\n";
print '              borderColor: "rgba(234, 88, 12, 1)",' . "\n";
print '              backgroundColor: "rgba(234, 88, 12, 0.25)",' . "\n";
print '              borderWidth: 2,' . "\n";
print '              tension: 0.3,' . "\n";
print '              yAxisID: "y"' . "\n";
print '            },' . "\n";
print '            {' . "\n";
print '              type: "line",' . "\n";
print '              label: chartLabels.min,' . "\n";
print '              data: weatherChartData.min,' . "\n";
print '              borderColor: "rgba(37, 99, 235, 1)",' . "\n";
print '              backgroundColor: "rgba(37, 99, 235, 0.25)",' . "\n";
print '              borderWidth: 2,' . "\n";
print '              tension: 0.3,' . "\n";
print '              yAxisID: "y"' . "\n";
print '            },' . "\n";
print '            {' . "\n";
print '              label: chartLabels.rain,' . "\n";
print '              data: weatherChartData.rain,' . "\n";
print '              backgroundColor: "rgba(34, 197, 94, 0.4)",' . "\n";
print '              borderColor: "rgba(21, 128, 61, 0.8)",' . "\n";
print '              borderWidth: 1,' . "\n";
print '              yAxisID: "y1"' . "\n";
print '            }' . "\n";
print '          ]' . "\n";
print '        },' . "\n";
print '        options: {' . "\n";
print '          responsive: true,' . "\n";
print '          maintainAspectRatio: false,' . "\n";
print '          scales: {' . "\n";
print '            y: {' . "\n";
print '              type: "linear",' . "\n";
print '              position: "left",' . "\n";
print '              title: {' . "\n";
print '                display: true,' . "\n";
print '                text: weatherAxisUnits.temperature' . "\n";
print '              }' . "\n";
print '            },' . "\n";
print '            y1: {' . "\n";
print '              type: "linear",' . "\n";
print '              position: "right",' . "\n";
print '              beginAtZero: true,' . "\n";
print '              grid: {' . "\n";
print '                drawOnChartArea: false' . "\n";
print '              },' . "\n";
print '              title: {' . "\n";
print '                display: true,' . "\n";
print '                text: weatherAxisUnits.rain' . "\n";
print '              }' . "\n";
print '            }' . "\n";
print '          },' . "\n";
print '          plugins: {' . "\n";
print '            legend: {' . "\n";
print '              position: "bottom"' . "\n";
print '            }' . "\n";
print '          }' . "\n";
print '        }' . "\n";
print '      });' . "\n";
print '    }' . "\n";
print '  }' . "\n";
print '});' . "\n";
print '</script>' . "\n";


include_once './js/talhao_index.js.php';

llxFooter();
$db->close();
