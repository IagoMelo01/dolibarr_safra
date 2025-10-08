<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2024 SuperAdmin
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
 *      \file       safra/zoneamento_view.php
 *      \ingroup    safra
 *      \brief      Planting window dashboard using Embrapa Zarc API.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] === $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
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
    die('Include of main fails');
}

dol_include_once('/safra/class/embrapaapi.class.php');
dol_include_once('/safra/class/cultura.class.php');

require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

// Load translation files required by the page
$langs->loadLangs(array('safra@safra', 'other'));

if (!$user->hasRight('safra', 'zoneamento', 'read')) {
    accessforbidden();
}

$form = new Form($db);

$action = GETPOST('action', 'aZ09');
$highlightRisk = GETPOST('risco', 'alpha');
if (!in_array($highlightRisk, array('20', '30', '40'), true)) {
    $highlightRisk = '20';
}

$codigoIBGEInput = trim(GETPOST('codigoIBGE', 'alphanohtml'));
$culturesInput = GETPOST('culturas', 'array');
$maxCultures = 12;

$cultureOptions = safra_zoneamento_fetch_culture_options($db, 180);
$tooManySelected = false;
$selectedCultures = safra_zoneamento_normalize_selection($culturesInput, $cultureOptions, $maxCultures, $tooManySelected);

if ($tooManySelected) {
    setEventMessages($langs->trans('ZoneamentoTooManyCultures', $maxCultures), null, 'warnings');
}

$errors = array();
$results = array();
$municipioResult = '';
$ufResult = '';
$hasSuccess = false;

if ($action === 'consult') {
    if (empty($selectedCultures)) {
        $errors[] = $langs->trans('ZoneamentoNoSelection');
    }
    if ($codigoIBGEInput === '' || !preg_match('/^\d{7}$/', $codigoIBGEInput)) {
        $errors[] = $langs->trans('ZoneamentoInvalidCodigoIBGE');
    }

    if (!empty($errors)) {
        setEventMessages(null, $errors, 'errors');
    } else {
        $codigoIBGE = (int) $codigoIBGEInput;
        $api = new EmbrapaApi($db);

        foreach ($selectedCultures as $cultureId => $cultureLabel) {
            $parameters = array(
                'idCultura' => (int) $cultureId,
                'codigoIBGE' => $codigoIBGE,
                'risco' => 'todos',
            );

            $apiError = '';
            $response = $api->fetchZoneamento($parameters, $apiError);

            if (!is_array($response) || empty($response['data'])) {
                $results[] = array(
                    'status' => empty($apiError) ? 'empty' : 'error',
                    'message' => $apiError,
                    'id' => $cultureId,
                    'label' => safra_zoneamento_clean_label($cultureLabel),
                    'rawLabel' => $cultureLabel,
                );
                continue;
            }

            $analysis = safra_zoneamento_analyze_records($response['data']);
            $analysis['label'] = safra_zoneamento_clean_label($cultureLabel);
            $analysis['rawLabel'] = $cultureLabel;
            $analysis['id'] = $cultureId;

            if (empty($municipioResult) && !empty($analysis['municipio'])) {
                $municipioResult = $analysis['municipio'];
            }
            if (empty($ufResult) && !empty($analysis['uf'])) {
                $ufResult = $analysis['uf'];
            }

            if (!empty($analysis['timeline'])) {
                $hasSuccess = true;
            }

            $results[] = $analysis;
        }

        if ($hasSuccess) {
            setEventMessages($langs->trans('ZoneamentoSuccess'), null, 'mesgs');
        }
    }
}

llxHeader('', $langs->trans('ZoneamentoViewTitle'), '', '', 0, 0, '', '', '', 'mod-safra zoneamento-page');

print '<link rel="stylesheet" href="' . dol_buildpath('/safra/css/zoneamento.css', 1) . '?v=1">';

$monthLabels = safra_zoneamento_get_month_labels($langs);

print '<div class="zoneamento-hero">';
print '<div class="zoneamento-hero__content">';
print '<h2 class="zoneamento-hero__title">' . dol_escape_htmltag($langs->trans('ZoneamentoViewTitle')) . '</h2>';
print '<p class="zoneamento-hero__subtitle">' . dol_escape_htmltag($langs->trans('ZoneamentoViewSubtitle')) . '</p>';
print '</div>';
print '<div class="zoneamento-hero__legend">';
print '<div class="zoneamento-legend__intro">' . dol_escape_htmltag($langs->trans('ZoneamentoFiltersHelp')) . '</div>';
$legendItems = array(
    array('risk' => '20', 'label' => $langs->trans('ZoneamentoLegend20')),
    array('risk' => '30', 'label' => $langs->trans('ZoneamentoLegend30')),
    array('risk' => '40', 'label' => $langs->trans('ZoneamentoLegend40')),
);
foreach ($legendItems as $legendItem) {
    print '<div class="zoneamento-legend__item zoneamento-legend__item--risk' . $legendItem['risk'] . '">';
    print '<span class="zoneamento-legend__dot zoneamento-legend__dot--risk' . $legendItem['risk'] . '"></span>';
    print '<span class="zoneamento-legend__text">' . dol_escape_htmltag($legendItem['label']) . '</span>';
    print '</div>';
}
print '</div>';
print '</div>';

print '<form method="post" class="zoneamento-form">';
print '<input type="hidden" name="action" value="consult">';
print '<div class="zoneamento-form__field">';
print '<label for="codigoIBGE" class="zoneamento-form__label">' . dol_escape_htmltag($langs->trans('ZoneamentoFilterMunicipio')) . '</label>';
print '<input type="text" id="codigoIBGE" name="codigoIBGE" value="' . dol_escape_htmltag($codigoIBGEInput) . '" placeholder="' . dol_escape_htmltag($langs->trans('ZoneamentoPlaceholderCodigoIBGE')) . '" class="zoneamento-form__input">';
print '</div>';

print '<div class="zoneamento-form__field">';
print '<label for="risco" class="zoneamento-form__label">' . dol_escape_htmltag($langs->trans('ZoneamentoFilterRisk')) . '</label>';
print '<select name="risco" id="risco" class="zoneamento-form__select">';
foreach (array('20', '30', '40') as $riskValue) {
    $selected = $riskValue === $highlightRisk ? ' selected' : '';
    print '<option value="' . $riskValue . '"' . $selected . '>' . dol_escape_htmltag($langs->trans('ZoneamentoRiskLabel', $riskValue)) . '</option>';
}
print '</select>';
print '</div>';

print '<div class="zoneamento-form__field zoneamento-form__field--multiselect">';
print '<label for="culturas" class="zoneamento-form__label">' . dol_escape_htmltag($langs->trans('ZoneamentoFilterCultures')) . '</label>';
print '<select name="culturas[]" id="culturas" multiple size="12" class="zoneamento-form__multiselect">';
foreach ($cultureOptions as $embrapaId => $label) {
    $isSelected = isset($selectedCultures[$embrapaId]) ? ' selected' : '';
    print '<option value="' . (int) $embrapaId . '"' . $isSelected . '>' . dol_escape_htmltag(safra_zoneamento_clean_label($label)) . '</option>';
}
print '</select>';
print '<div class="zoneamento-form__help">' . dol_escape_htmltag($langs->trans('ZoneamentoSelectedCount', count($selectedCultures))) . '</div>';
print '</div>';

print '<div class="zoneamento-form__actions">';
print '<button type="submit" class="zoneamento-form__submit butAction">' . dol_escape_htmltag($langs->trans('ZoneamentoFilterSubmit')) . '</button>';
print '</div>';
print '</form>';

if (!empty($results)) {
    $dashboardAttributes = ' class="zoneamento-dashboard" data-highlight="' . dol_escape_htmltag($highlightRisk) . '"';
    print '<section' . $dashboardAttributes . '>';

    print '<header class="zoneamento-dashboard__header">';
    if ($municipioResult && $ufResult) {
        print '<h3 class="zoneamento-dashboard__title">' . dol_escape_htmltag(sprintf($langs->trans('ZoneamentoResultsFor'), safra_zoneamento_format_location($municipioResult), $ufResult)) . '</h3>';
    } else {
        print '<h3 class="zoneamento-dashboard__title">' . dol_escape_htmltag($langs->trans('ZoneamentoLegendTitle')) . '</h3>';
    }

    if ($municipioResult && $ufResult) {
        print '<div class="zoneamento-dashboard__meta">' . dol_escape_htmltag(sprintf($langs->trans('ZoneamentoMunicipioSummary'), safra_zoneamento_format_location($municipioResult), $ufResult)) . '</div>';
    }
    print '</header>';

    foreach ($results as $item) {
        if (isset($item['status']) && $item['status'] !== 'ok' && empty($item['timeline'])) {
            $cssClass = $item['status'] === 'empty' ? 'zoneamento-row zoneamento-row--empty' : 'zoneamento-row zoneamento-row--error';
            print '<div class="' . $cssClass . '">';
            print '<div class="zoneamento-row__label">';
            print '<h4>' . dol_escape_htmltag($item['label']) . '</h4>';
            if ($item['status'] === 'empty') {
                print '<p>' . dol_escape_htmltag($langs->trans('ZoneamentoRiskNoData')) . '</p>';
            } else {
                $message = !empty($item['message']) ? $item['message'] : $langs->trans('ZoneamentoErrorFetching', $item['label']);
                print '<p>' . dol_escape_htmltag($message) . '</p>';
            }
            print '</div>';
            print '</div>';
            continue;
        }

        $timeline = isset($item['timeline']) ? $item['timeline'] : array();
        print '<div class="zoneamento-row">';
        print '<div class="zoneamento-row__label">';
        print '<h4>' . dol_escape_htmltag($item['label']) . '</h4>';

        if (!empty($item['riskDetails'])) {
            print '<div class="zoneamento-row__ranges">';
            foreach (array('20', '30', '40') as $riskKey) {
                $detail = isset($item['riskDetails'][$riskKey]) ? $item['riskDetails'][$riskKey] : null;
                $rangeText = $detail && !empty($detail['ranges']) ? safra_zoneamento_format_ranges($detail['ranges']) : $langs->trans('ZoneamentoRiskNoData');
                $extraParts = array();
                if ($detail && !empty($detail['soils'])) {
                    $extraParts[] = $langs->trans('ZoneamentoSoilsLabel') . ': ' . implode(', ', array_map('dol_escape_htmltag', $detail['soils']));
                }
                if ($detail && !empty($detail['cycles'])) {
                    $extraParts[] = $langs->trans('ZoneamentoCycleLabel') . ': ' . implode(', ', array_map('dol_escape_htmltag', $detail['cycles']));
                }
                if (!empty($extraParts)) {
                    $rangeText .= ' • ' . implode(' • ', $extraParts);
                }
                print '<div class="zoneamento-range zoneamento-range--risk' . $riskKey . '">';
                print '<span class="zoneamento-range__badge zoneamento-range__badge--risk' . $riskKey . '"></span>';
                print '<span class="zoneamento-range__label">' . dol_escape_htmltag($langs->trans('ZoneamentoRiskLabel', $riskKey)) . '</span>';
                print '<span class="zoneamento-range__text">' . dol_escape_htmltag($rangeText) . '</span>';
                print '</div>';
            }
            print '</div>';
        }

        $metaPieces = array();
        if (!empty($item['cultivo'])) {
            $metaPieces[] = '<span class="zoneamento-detail__label">' . dol_escape_htmltag($langs->trans('ZoneamentoCultivoLabel')) . '</span>' . dol_escape_htmltag(implode(', ', $item['cultivo']));
        }
        if (!empty($item['portarias'])) {
            $metaPieces[] = '<span class="zoneamento-detail__label">' . dol_escape_htmltag($langs->trans('ZoneamentoPortariaLabel')) . '</span>' . dol_escape_htmltag(implode(', ', $item['portarias']));
        }
        if (!empty($metaPieces)) {
            print '<div class="zoneamento-row__details">' . implode('<span class="zoneamento-detail__separator">•</span>', $metaPieces) . '</div>';
        }

        print '</div>';

        print '<div class="zoneamento-row__chart">';
        foreach ($monthLabels as $monthNumber => $monthName) {
            print '<div class="zoneamento-month">';
            print '<div class="zoneamento-month__label">' . dol_escape_htmltag($monthName) . '</div>';
            print '<div class="zoneamento-month__body">';
            foreach (array('20', '30', '40') as $riskLevel) {
                $intervals = isset($timeline[$riskLevel][$monthNumber]) ? $timeline[$riskLevel][$monthNumber] : array();
                $daysInMonth = safra_zoneamento_days_in_month($monthNumber);
                if (empty($intervals)) {
                    continue;
                }
                foreach ($intervals as $interval) {
                    $startDay = max(1, (int) $interval['start']);
                    $endDay = max($startDay, (int) $interval['end']);
                    $length = $endDay - $startDay + 1;
                    $startPct = (($startDay - 1) / $daysInMonth) * 100;
                    $widthPct = ($length / $daysInMonth) * 100;
                    $style = '--start:' . round($startPct, 2) . ';--width:' . round($widthPct, 2) . ';';
                    print '<span class="zoneamento-bar zoneamento-bar--risk' . $riskLevel . '" style="' . $style . '"></span>';
                }
            }
            print '</div>';
            print '</div>';
        }
        print '</div>';
        print '</div>';
    }

    print '<div class="zoneamento-disclaimer">';
    print '<p><strong>' . dol_escape_htmltag($langs->trans('ZoneamentoDataSource')) . '</strong></p>';
    print '<p>' . dol_escape_htmltag($langs->trans('ZoneamentoRiskDisclaimer')) . '</p>';
    print '<p>' . dol_escape_htmltag($langs->trans('ZoneamentoLiabilityDisclaimer')) . '</p>';
    print '</div>';

    print '</section>';
} elseif ($action === 'consult' && empty($results)) {
    print '<div class="zoneamento-empty">' . dol_escape_htmltag($langs->trans('ZoneamentoRiskNoData')) . '</div>';
}

llxFooter();

/**
 * Fetch distinct cultures with Embrapa IDs for selection.
 *
 * @param DoliDB $db    Database handler
 * @param int    $limit Limit of records to fetch
 *
 * @return array<int, string>
 */
function safra_zoneamento_fetch_culture_options(DoliDB $db, $limit = 180)
{
    $options = array();

    $sql = 'SELECT c.embrapa_id, c.label FROM ' . MAIN_DB_PREFIX . 'safra_cultura AS c';
    $sql .= ' INNER JOIN (';
    $sql .= ' SELECT embrapa_id, MIN(rowid) AS minrowid';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'safra_cultura';
    $sql .= ' WHERE embrapa_id IS NOT NULL AND embrapa_id <> 0';
    $sql .= ' GROUP BY embrapa_id';
    $sql .= ' ) t ON c.rowid = t.minrowid';
    $sql .= ' ORDER BY c.label ASC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . ((int) $limit);
    }

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__METHOD__ . ' SQL error: ' . $db->lasterror(), LOG_ERR);
        return $options;
    }

    while ($obj = $db->fetch_object($resql)) {
        $embrapaId = (int) $obj->embrapa_id;
        if ($embrapaId <= 0) {
            continue;
        }
        $options[$embrapaId] = $obj->label;
    }
    $db->free($resql);

    return $options;
}

/**
 * Normalize selected cultures ensuring valid Embrapa IDs and max length.
 *
 * @param array|null $selection  Input selection
 * @param array      $options    Available options (embrapaId => label)
 * @param int        $max        Maximum allowed entries
 * @param bool       $tooManyRef Reference flag set when input exceeds the limit
 *
 * @return array<int, string>
 */
function safra_zoneamento_normalize_selection($selection, array $options, $max, &$tooManyRef)
{
    $tooManyRef = false;
    $normalized = array();

    if (is_array($selection) && !empty($selection)) {
        $sanitized = array();
        foreach ($selection as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $sanitized[] = $id;
            }
        }
        $sanitized = array_values(array_unique($sanitized));
        if (count($sanitized) > $max) {
            $tooManyRef = true;
            $sanitized = array_slice($sanitized, 0, $max);
        }
        foreach ($sanitized as $id) {
            if (isset($options[$id])) {
                $normalized[$id] = $options[$id];
            }
        }
    }

    if (empty($normalized)) {
        foreach ($options as $id => $label) {
            $normalized[$id] = $label;
            if (count($normalized) >= $max) {
                break;
            }
        }
    }

    return $normalized;
}

/**
 * Clean culture label removing UF/state suffixes.
 *
 * @param string $label Original label
 *
 * @return string
 */
function safra_zoneamento_clean_label($label)
{
    $label = preg_replace('/\s*-\s*[A-Z]{2}\s*$/u', '', (string) $label);
    $label = preg_replace('/\s*\/\s*[A-Z]{2}$/u', '', $label);
    $label = preg_replace('/\s*-\s*\d{2,}$/u', '', $label);
    return trim($label);
}

/**
 * Analyze API records into coverage and metadata.
 *
 * @param array $records API response data
 *
 * @return array
 */
function safra_zoneamento_analyze_records(array $records)
{
    $risks = array('20', '30', '40');
    $daysInMonths = array();
    for ($month = 1; $month <= 12; $month++) {
        $daysInMonths[$month] = safra_zoneamento_days_in_month($month);
    }

    $coverage = array();
    $rangeSegments = array();
    $riskDetails = array();
    foreach ($risks as $risk) {
        $coverage[$risk] = array();
        foreach ($daysInMonths as $month => $days) {
            $coverage[$risk][$month] = array_fill(1, $days, false);
        }
        $rangeSegments[$risk] = array();
        $riskDetails[$risk] = array(
            'ranges' => array(),
            'soils' => array(),
            'cycles' => array(),
        );
    }

    $municipio = '';
    $uf = '';
    $portarias = array();
    $cultivos = array();

    foreach ($records as $record) {
        $risk = isset($record['risco']) ? (string) $record['risco'] : '';
        if (!isset($coverage[$risk])) {
            continue;
        }

        $segments = safra_zoneamento_entry_segments((int) $record['mesIni'], (int) $record['diaIni'], (int) $record['mesFim'], (int) $record['diaFim']);
        foreach ($segments as $segment) {
            $rangeSegments[$risk][] = array(
                'start' => $segment['start_index'],
                'end' => $segment['end_index'],
            );

            for ($month = $segment['start_month']; $month <= $segment['end_month']; $month++) {
                $daysInMonth = $daysInMonths[$month];
                $startDay = ($month === $segment['start_month']) ? $segment['start_day'] : 1;
                $endDay = ($month === $segment['end_month']) ? $segment['end_day'] : $daysInMonth;
                $startDay = max(1, min($startDay, $daysInMonth));
                $endDay = max($startDay, min($endDay, $daysInMonth));
                for ($day = $startDay; $day <= $endDay; $day++) {
                    $coverage[$risk][$month][$day] = true;
                }
            }
        }

        if (!empty($record['solo'])) {
            $riskDetails[$risk]['soils'][$record['solo']] = true;
        }
        if (!empty($record['ciclo'])) {
            $riskDetails[$risk]['cycles'][$record['ciclo']] = true;
        }

        if ($municipio === '' && !empty($record['municipio'])) {
            $municipio = (string) $record['municipio'];
        }
        if ($uf === '' && !empty($record['uf'])) {
            $uf = (string) $record['uf'];
        }
        if (!empty($record['portaria'])) {
            $portarias[$record['portaria']] = true;
        }
        if (!empty($record['culturaCultivo'])) {
            $cultivos[$record['culturaCultivo']] = true;
        }
    }

    $timeline = array();
    foreach ($risks as $risk) {
        $timeline[$risk] = array();
        for ($month = 1; $month <= 12; $month++) {
            $days = $coverage[$risk][$month];
            $intervals = array();
            $currentStart = null;
            $daysInMonth = $daysInMonths[$month];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                if (!empty($days[$day])) {
                    if ($currentStart === null) {
                        $currentStart = $day;
                    }
                } else {
                    if ($currentStart !== null) {
                        $intervals[] = array('start' => $currentStart, 'end' => $day - 1);
                        $currentStart = null;
                    }
                }
            }
            if ($currentStart !== null) {
                $intervals[] = array('start' => $currentStart, 'end' => $daysInMonth);
            }
            $timeline[$risk][$month] = $intervals;
        }

        $merged = safra_zoneamento_merge_index_segments($rangeSegments[$risk]);
        $ranges = array();
        foreach ($merged as $segment) {
            $ranges[] = safra_zoneamento_index_segment_to_range($segment['start'], $segment['end']);
        }
        $riskDetails[$risk]['ranges'] = $ranges;
        $riskDetails[$risk]['soils'] = array_values(array_unique(array_keys($riskDetails[$risk]['soils'])));
        sort($riskDetails[$risk]['soils'], SORT_NATURAL | SORT_FLAG_CASE);
        $riskDetails[$risk]['cycles'] = array_values(array_unique(array_keys($riskDetails[$risk]['cycles'])));
        sort($riskDetails[$risk]['cycles'], SORT_NATURAL | SORT_FLAG_CASE);
    }

    $portariasList = array_keys($portarias);
    sort($portariasList, SORT_NATURAL | SORT_FLAG_CASE);
    $cultivoList = array_keys($cultivos);
    sort($cultivoList, SORT_NATURAL | SORT_FLAG_CASE);

    return array(
        'status' => 'ok',
        'timeline' => $timeline,
        'riskDetails' => $riskDetails,
        'municipio' => $municipio,
        'uf' => $uf,
        'portarias' => $portariasList,
        'cultivo' => $cultivoList,
    );
}

/**
 * Convert entry into non-wrapping segments.
 *
 * @param int $startMonth
 * @param int $startDay
 * @param int $endMonth
 * @param int $endDay
 *
 * @return array<int, array>
 */
function safra_zoneamento_entry_segments($startMonth, $startDay, $endMonth, $endDay)
{
    $segments = array();

    $startMonth = max(1, min(12, (int) $startMonth));
    $endMonth = max(1, min(12, (int) $endMonth));
    $startDay = max(1, min(31, (int) $startDay));
    $endDay = max(1, min(31, (int) $endDay));

    $base = new DateTimeImmutable('2024-01-01');
    $startDate = @DateTimeImmutable::createFromFormat('Y-n-j', '2024-' . $startMonth . '-' . $startDay);
    $endDate = @DateTimeImmutable::createFromFormat('Y-n-j', '2024-' . $endMonth . '-' . $endDay);
    if (!$startDate || !$endDate) {
        return $segments;
    }

    $startIndex = (int) $base->diff($startDate)->days;
    $endIndex = (int) $base->diff($endDate)->days;

    if ($endDate < $startDate) {
        $endOfYear = new DateTimeImmutable('2024-12-31');
        $segments[] = array(
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_month' => 12,
            'end_day' => 31,
            'start_index' => $startIndex,
            'end_index' => (int) $base->diff($endOfYear)->days,
        );
        $nextStart = new DateTimeImmutable('2025-01-01');
        $nextEnd = @DateTimeImmutable::createFromFormat('Y-n-j', '2025-' . $endMonth . '-' . $endDay);
        if ($nextEnd) {
            $segments[] = array(
                'start_month' => 1,
                'start_day' => 1,
                'end_month' => $endMonth,
                'end_day' => $endDay,
                'start_index' => (int) $base->diff($nextStart)->days,
                'end_index' => (int) $base->diff($nextEnd)->days,
            );
        }
    } else {
        $segments[] = array(
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_month' => $endMonth,
            'end_day' => $endDay,
            'start_index' => $startIndex,
            'end_index' => $endIndex,
        );
    }

    return $segments;
}

/**
 * Merge overlapping segments represented by day indexes.
 *
 * @param array $segments
 *
 * @return array
 */
function safra_zoneamento_merge_index_segments(array $segments)
{
    if (empty($segments)) {
        return array();
    }

    usort($segments, function ($a, $b) {
        if ($a['start'] === $b['start']) {
            return $a['end'] <=> $b['end'];
        }
        return $a['start'] <=> $b['start'];
    });

    $merged = array();
    foreach ($segments as $segment) {
        $start = (int) $segment['start'];
        $end = (int) $segment['end'];
        if (empty($merged)) {
            $merged[] = array('start' => $start, 'end' => $end);
            continue;
        }
        $lastIndex = count($merged) - 1;
        if ($start <= $merged[$lastIndex]['end'] + 1) {
            if ($end > $merged[$lastIndex]['end']) {
                $merged[$lastIndex]['end'] = $end;
            }
        } else {
            $merged[] = array('start' => $start, 'end' => $end);
        }
    }

    return $merged;
}

/**
 * Convert index-based segment into calendar range.
 *
 * @param int $startIndex
 * @param int $endIndex
 *
 * @return array
 */
function safra_zoneamento_index_segment_to_range($startIndex, $endIndex)
{
    $base = new DateTimeImmutable('2024-01-01');
    $startDate = $base->modify('+' . (int) $startIndex . ' days');
    $endDate = $base->modify('+' . (int) $endIndex . ' days');

    return array(
        'start_month' => (int) $startDate->format('n'),
        'start_day' => (int) $startDate->format('j'),
        'end_month' => (int) $endDate->format('n'),
        'end_day' => (int) $endDate->format('j'),
    );
}

/**
 * Format a list of ranges into human-readable string.
 *
 * @param array $ranges
 *
 * @return string
 */
function safra_zoneamento_format_ranges(array $ranges)
{
    $parts = array();
    foreach ($ranges as $range) {
        $parts[] = sprintf('%02d/%02d – %02d/%02d', $range['start_day'], $range['start_month'], $range['end_day'], $range['end_month']);
    }
    return implode('; ', $parts);
}

/**
 * Return month labels according to locale.
 *
 * @param Translate $langs
 *
 * @return array<int, string>
 */
function safra_zoneamento_get_month_labels(Translate $langs)
{
    $months = array();
    for ($month = 1; $month <= 12; $month++) {
        $timestamp = dol_mktime(12, 0, 0, $month, 1, 2024);
        $months[$month] = dol_print_date($timestamp, '%b');
    }
    return $months;
}

/**
 * Days in month for planting calendar.
 *
 * @param int $month
 *
 * @return int
 */
function safra_zoneamento_days_in_month($month)
{
    static $map = array(
        1 => 31,
        2 => 29,
        3 => 31,
        4 => 30,
        5 => 31,
        6 => 30,
        7 => 31,
        8 => 31,
        9 => 30,
        10 => 31,
        11 => 30,
        12 => 31,
    );

    $month = (int) $month;
    return isset($map[$month]) ? $map[$month] : 30;
}

/**
 * Format municipality/UF into a friendly label.
 *
 * @param string $municipio
 *
 * @return string
 */
function safra_zoneamento_format_location($municipio)
{
    $municipio = trim((string) $municipio);
    if ($municipio === '') {
        return '';
    }

    $lower = mb_strtolower($municipio, 'UTF-8');
    return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
}
