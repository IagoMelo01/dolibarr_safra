<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
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
 *  \file       zoneamento_windows.php
 *  \ingroup    safra
 *  \brief      Visual timeline for Embrapa Zarc planting windows.
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/safra/class/embrapaapi.class.php');

dol_syslog(__FILE__, LOG_DEBUG);

if (!isModEnabled('safra')) {
    accessforbidden();
}

if (!$user->rights['safra']['zoneamento']['read']) {
    accessforbidden();
}

if (!function_exists('safra_zoneamento_expand_months')) {
    /**
     * Expand start/end months into list of numeric months.
     *
     * @param int $start
     * @param int $end
     *
     * @return array
     */
    function safra_zoneamento_expand_months($start, $end)
    {
        $start = (int) $start;
        $end = (int) $end;
        if ($start < 1 || $start > 12 || $end < 1 || $end > 12) {
            return array();
        }

        $months = array($start);
        $current = $start;
        $guard = 0;
        while ($current != $end && $guard < 12) {
            $current++;
            if ($current > 12) {
                $current = 1;
            }
            $months[] = $current;
            $guard++;
        }

        return $months;
    }
}

if (!function_exists('safra_zoneamento_build_matrix')) {
    /**
     * Build month coverage matrix for timeline.
     *
     * @param array  $rows        API response rows
     * @param string $riscoFilter Selected risk filter (20|30|40|todos)
     *
     * @return array
     */
    function safra_zoneamento_build_matrix(array $rows, $riscoFilter)
    {
        $coverage = array();
        $details = array();
        foreach (range(1, 12) as $month) {
            $coverage[$month] = null;
            $details[$month] = array();
        }

        foreach ($rows as $row) {
            if (!isset($row['mesIni'], $row['mesFim'], $row['diaIni'], $row['diaFim'], $row['risco'])) {
                continue;
            }

            $risk = (int) $row['risco'];
            if (empty($risk)) {
                continue;
            }

            if ($riscoFilter !== 'todos' && (string) $risk !== (string) $riscoFilter) {
                continue;
            }

            $months = safra_zoneamento_expand_months((int) $row['mesIni'], (int) $row['mesFim']);
            if (empty($months)) {
                continue;
            }

            $startLabel = sprintf('%02d/%02d', (int) $row['diaIni'], (int) $row['mesIni']);
            $endLabel = sprintf('%02d/%02d', (int) $row['diaFim'], (int) $row['mesFim']);
            $soil = !empty($row['solo']) ? (string) $row['solo'] : '';
            $ciclo = !empty($row['ciclo']) ? (string) $row['ciclo'] : '';

            foreach ($months as $month) {
                if ($coverage[$month] === null || $risk < $coverage[$month]) {
                    $coverage[$month] = $risk;
                }

                $details[$month][] = array(
                    'risk' => $risk,
                    'start' => $startLabel,
                    'end' => $endLabel,
                    'soil' => $soil,
                    'ciclo' => $ciclo,
                );
            }
        }

        return array($coverage, $details);
    }
}

$maxCultures = 12;
$riscoOptions = array('20', '30', '40', 'todos');

$codigoIBGE = trim(GETPOST('codigoIBGE', 'alpha'));
$risco = GETPOST('risco', 'alpha');
$culturasSelection = GETPOST('culturas', 'array');
$submit = GETPOST('submitfilter', 'alpha');

if (empty($risco) || !in_array($risco, $riscoOptions, true)) {
    $risco = '20';
}

$errors = array();
$messages = array();

$form = new Form($db);
$availableCulturas = array();
$culturasMap = array();

$sql = "SELECT rowid, label, embrapa_id FROM ".MAIN_DB_PREFIX."safra_cultura WHERE status = 1 AND embrapa_id IS NOT NULL ORDER BY label ASC";
$resql = $db->query($sql);
if (!$resql) {
    $errors[] = $db->lasterror();
} else {
    while ($obj = $db->fetch_object($resql)) {
        $label = $obj->label ?: $obj->ref;
        $embrapaId = (int) $obj->embrapa_id;
        if ($embrapaId <= 0) {
            continue;
        }
        $availableCulturas[] = array(
            'label' => $label,
            'embrapa_id' => $embrapaId,
        );
        $culturasMap[$embrapaId] = $label;
    }
    $db->free($resql);
}

$defaultSelection = array();
foreach ($availableCulturas as $entry) {
    if (count($defaultSelection) >= $maxCultures) {
        break;
    }
    $defaultSelection[] = $entry['embrapa_id'];
}

if (empty($culturasSelection) || !is_array($culturasSelection)) {
    $culturasSelection = $defaultSelection;
} else {
    $filteredSelection = array();
    foreach ($culturasSelection as $value) {
        $value = (int) $value;
        if ($value > 0 && isset($culturasMap[$value])) {
            $filteredSelection[] = $value;
        }
    }
    $culturasSelection = array_values(array_unique($filteredSelection));
    if (empty($culturasSelection)) {
        $culturasSelection = $defaultSelection;
    }
}

if (count($culturasSelection) > $maxCultures) {
    $culturasSelection = array_slice($culturasSelection, 0, $maxCultures);
    $messages[] = $langs->trans('ZoneamentoCulturesTrimmed', $maxCultures);
}

$results = array();

if (!empty($codigoIBGE) && !empty($culturasSelection)) {
    $api = new EmbrapaApi($db);
    foreach ($culturasSelection as $embrapaId) {
        $label = isset($culturasMap[$embrapaId]) ? $culturasMap[$embrapaId] : $langs->trans('Unknown');
        $apiError = '';
        $response = $api->fetchZoneamento(array(
            'idCultura' => $embrapaId,
            'codigoIBGE' => $codigoIBGE,
            'risco' => $risco,
        ), $apiError);

        if ($response === null) {
            $errors[] = $langs->trans('ZoneamentoApiErrorForCulture', $label, $apiError);
            continue;
        }

        $rows = array();
        if (isset($response['data']) && is_array($response['data'])) {
            $rows = $response['data'];
        }

        if (empty($rows)) {
            $messages[] = $langs->trans('ZoneamentoNoDataForCulture', $label);
        }

        list($coverage, $details) = safra_zoneamento_build_matrix($rows, $risco);
        $results[] = array(
            'label' => $label,
            'coverage' => $coverage,
            'details' => $details,
            'rows' => $rows,
        );
    }
} elseif (!empty($submit)) {
    $messages[] = $langs->trans('ZoneamentoMissingFilters');
}

$pagetitle = $langs->trans('ZoneamentoPlantingWindowsTitle');
llxHeader('', $pagetitle, '', '', 0, 0, '', '', '', 'mod-safra page-zoneamento-windows');

print load_fiche_titre($pagetitle, '', 'safra@safra');

if (!empty($errors)) {
    dol_htmloutput_errors('', $errors);
}
if (!empty($messages)) {
    dol_htmloutput_mesg($messages);
}

print '<style>
.zoneamento-grid {border-collapse: collapse; width: 100%; table-layout: fixed;}
.zoneamento-grid th, .zoneamento-grid td {border: 1px solid #d8d8d8; padding: 6px; font-size: 12px; text-align: center;}
.zoneamento-grid th {background: #f5f7fb; font-weight: 600;}
.zoneamento-grid td {min-width: 48px; height: 36px;}
.zoneamento-grid .culture-cell {text-align: left; font-weight: 600; white-space: nowrap;}
.zoneamento-grid .no-data {color: #888; font-style: italic;}
.zoneamento-legend {display: flex; gap: 18px; flex-wrap: wrap; margin: 16px 0;}
.zoneamento-legend span {display: flex; align-items: center; gap: 6px; font-size: 12px;}
.zoneamento-legend i {display: inline-block; width: 14px; height: 14px; border-radius: 3px;}
.zoneamento-disclaimer {margin-top: 20px; font-size: 12px; color: #555;}
.zoneamento-form select[multiple] {min-width: 320px; height: 220px;}
</style>';

print '<form class="zoneamento-form" method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<div class="fichecenter">';
print '<table class="border centpercent">';
print '<tr>';
print '<td class="titlefield">'.$langs->trans('ZoneamentoIbgeCode').'</td>';
print '<td><input type="text" name="codigoIBGE" value="'.dol_escape_htmltag($codigoIBGE).'" class="maxwidth100" placeholder="3502804"></td>';
print '</tr>';
print '<tr>';
print '<td>'.$langs->trans('ZoneamentoRiskLevel').'</td>';
print '<td>';
print '<select name="risco" class="flat">';
foreach ($riscoOptions as $option) {
    $label = $langs->trans('ZoneamentoRiskOption'.$option);
    $selected = $risco === $option ? ' selected' : '';
    print '<option value="'.$option.'"'.$selected.'>'.$label.'</option>';
}
print '</select>';
print '</td>';
print '</tr>';
print '<tr>';
print '<td>'.$langs->trans('ZoneamentoCulturesSelection').'</td>';
print '<td>';
if (!empty($availableCulturas)) {
    print '<select name="culturas[]" multiple> ';
    foreach ($availableCulturas as $entry) {
        $selected = in_array($entry['embrapa_id'], $culturasSelection, true) ? ' selected' : '';
        print '<option value="'.$entry['embrapa_id'].'"'.$selected.'>'.dol_escape_htmltag($entry['label']).'</option>';
    }
    print '</select>';
    print '<div class="small opacitymedium">'.$langs->trans('ZoneamentoCulturesHelp', $maxCultures).'</div>';
} else {
    print '<span class="opacitymedium">'.$langs->trans('ZoneamentoNoCulturesAvailable').'</span>';
}
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '<div class="center" style="margin-top: 12px;">';
print '<input type="submit" class="button" name="submitfilter" value="'.$langs->trans('Refresh').'">';
print '</div>';
print '</form>';

if (!empty($codigoIBGE) && !empty($culturasSelection)) {
    $months = array();
    for ($month = 1; $month <= 12; $month++) {
        $months[$month] = dol_print_date(dol_mktime(12, 0, 0, $month, 1, 2024), '%b');
    }

    $riskColors = array(
        20 => '#2e7d32',
        30 => '#ff8f00',
        40 => '#c62828',
    );

    print '<div class="zoneamento-legend">';
    print '<span><i style="background: '.$riskColors[20].';"></i>'.$langs->trans('ZoneamentoLegend20').'</span>';
    print '<span><i style="background: '.$riskColors[30].';"></i>'.$langs->trans('ZoneamentoLegend30').'</span>';
    print '<span><i style="background: '.$riskColors[40].';"></i>'.$langs->trans('ZoneamentoLegend40').'</span>';
    print '</div>';

    if (!empty($results)) {
        print '<div class="fichecenter">';
        print '<div class="table-container">';
        print '<table class="zoneamento-grid">';
        print '<tr>';
        print '<th class="culture-cell">'.$langs->trans('ZoneamentoCultureHeader').'</th>';
        foreach ($months as $monthLabel) {
            print '<th>'.$monthLabel.'</th>';
        }
        print '</tr>';

        foreach ($results as $item) {
            print '<tr>';
            print '<td class="culture-cell">'.dol_escape_htmltag($item['label']).'</td>';
            foreach ($months as $index => $monthLabel) {
                $risk = isset($item['coverage'][$index]) ? $item['coverage'][$index] : null;
                $cellStyle = '';
                $cellClass = '';
                $tooltip = array();
                if (!empty($item['details'][$index])) {
                    foreach ($item['details'][$index] as $detail) {
                        $parts = array();
                        $parts[] = $langs->trans('ZoneamentoTooltipRisk', (int) $detail['risk']);
                        $parts[] = $langs->trans('ZoneamentoTooltipWindow', $detail['start'], $detail['end']);
                        if (!empty($detail['soil'])) {
                            $parts[] = $langs->trans('ZoneamentoTooltipSoil', $detail['soil']);
                        }
                        if (!empty($detail['ciclo'])) {
                            $parts[] = $langs->trans('ZoneamentoTooltipCycle', $detail['ciclo']);
                        }
                        $tooltip[] = implode(' - ', $parts);
                    }
                }

                $title = !empty($tooltip) ? dol_escape_htmltag(implode("\n", $tooltip)) : '';

                if ($risk !== null && isset($riskColors[$risk])) {
                    $cellStyle = ' style="background: '.$riskColors[$risk].'; color: #fff;"';
                } elseif ($risk !== null) {
                    $cellStyle = ' style="background: #607d8b; color: #fff;"';
                } else {
                    $cellClass = ' class="no-data"';
                }

                print '<td'.$cellClass.$cellStyle.(!empty($title) ? ' title="'.$title.'"' : '').'>'; 
                if ($risk !== null) {
                    print $risk.'%';
                } else {
                    print '&nbsp;';
                }
                print '</td>';
            }
            print '</tr>';
        }

        print '</table>';
        print '</div>';
        print '</div>';
    }

    print '<div class="zoneamento-disclaimer">';
    print '<strong>'.$langs->trans('ZoneamentoDataSourceNotice').'</strong><br>';
    print $langs->trans('ZoneamentoRiskDisclaimer').'<br>';
    print $langs->trans('ZoneamentoResponsibilityDisclaimer');
    print '</div>';
}

llxFooter();
$db->close();
