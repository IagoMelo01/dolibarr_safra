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
dol_include_once('/safra/class/FvActivity.class.php');
dol_include_once('/safra/class/ActivityPlanningService.class.php');
dol_include_once('/safra/class/safra_satellite_statistics.class.php');
dol_include_once('/safra/class/safra_satellite_health.class.php');

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
         * Count entries inside a safra table.
         *
         * @param DoliDB $db    Database handler
         * @param string $table Table name without prefix (ex: safra_cultura)
         * @return int
         */
        function safra_count_table(DoliDB $db, $table)
        {
                $table = preg_replace('/[^a-z0-9_]+/i', '', (string) $table);
                if (empty($table)) {
                        return 0;
                }

                $sql = 'SELECT COUNT(*) as cnt FROM '.MAIN_DB_PREFIX.$table;
                $resql = $db->query($sql);
                if (!$resql) {
                        dol_syslog(__FUNCTION__.': Error when counting table '.$table.' - '.$db->lasterror(), LOG_ERR);
                        return 0;
                }

                $obj = $db->fetch_object($resql);
                $db->free($resql);

                return $obj ? (int) $obj->cnt : 0;
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

llxHeader("", $langs->trans("SafraDashboard"), '', '', 0, 0, '', '', '', 'mod-safra page-index');

print '<link rel="stylesheet" href="./css/leaflet.css">';
print '<link rel="stylesheet" href="./css/leaflet.draw.css">';

print load_fiche_titre($langs->trans("SafraDashboard"), '', 'safra.png@safra');

// Prepare dashboard data
$talhaoObject = new Talhao($db);
$talhaoList = $talhaoObject->fetchAll('ASC', 't.ref');
if (!is_array($talhaoList)) {
        $talhaoList = array();
}

$talhaoData = array();
$talhaoCache = array();
$totalArea = 0;

foreach ($talhaoList as $talhao) {
        $talhaoId = !empty($talhao->id) ? $talhao->id : $talhao->rowid;
        $talhaoLabel = $talhao->label ? $talhao->label : $talhao->ref;

        $talhaoCache[$talhaoId] = array(
                'label' => $talhaoLabel,
                'ref' => $talhao->ref,
        );

        $area = (float) $talhao->area;
        $totalArea += $area;

        $talhaoData[] = array(
                'id' => $talhaoId,
                'ref' => $talhao->ref,
                'label' => $talhaoLabel,
                'area' => $area,
                'geo_json' => trim((string) $talhao->geo_json),
        );
}

$countTalhoes = count($talhaoData);
$countCulturas = safra_count_table($db, 'safra_cultura');

$canReadActivities = !empty($user->rights->safra->SafraActivity->read);
$canWriteActivities = !empty($user->rights->safra->SafraActivity->write);
$canReadSatellite = !empty($user->rights->safra->ndvi->read)
        || !empty($user->rights->safra->ndmi->read)
        || !empty($user->rights->safra->swir->read);

$activityCounts = array(
        FvActivity::STATUS_DRAFT => 0,
        FvActivity::STATUS_PLANNED => 0,
        FvActivity::STATUS_IN_PROGRESS => 0,
        FvActivity::STATUS_COMPLETED => 0,
        FvActivity::STATUS_CANCELED => 0,
);
$activityAreaPlanned = 0;
$activityAreaDone = 0;
$activityOverdue = 0;
$activeActivities = array();

if ($canReadActivities) {
        $sql = 'SELECT status, COUNT(*) as activity_count, SUM(area_planned) as area_planned, SUM(area_done) as area_done';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'safra_activity';
        $sql .= ' WHERE entity IN ('.getEntity('safra_activity').')';
        $sql .= ' GROUP BY status';
        $resql = $db->query($sql);
        if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                        $status = FvActivity::normalizeStatus($obj->status);
                        if (!isset($activityCounts[$status])) {
                                $activityCounts[$status] = 0;
                        }
                        $activityCounts[$status] += (int) $obj->activity_count;
                        if (in_array($status, array(FvActivity::STATUS_DRAFT, FvActivity::STATUS_PLANNED, FvActivity::STATUS_IN_PROGRESS), true)) {
                                $activityAreaPlanned += (float) $obj->area_planned;
                                $activityAreaDone += (float) $obj->area_done;
                        }
                }
                $db->free($resql);
        } else {
                dol_syslog(__FILE__.': Error when loading activity summary - '.$db->lasterror(), LOG_ERR);
        }

        $sql = 'SELECT a.rowid, a.ref, a.label, a.type, a.status, a.priority, a.progress, a.season, a.crop_name,';
        $sql .= ' a.area_planned, a.area_done, a.date_planned_start, a.date_planned_end,';
        $sql .= ' t.ref as talhao_ref, t.label as talhao_label';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'safra_activity as a';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'safra_talhao as t ON t.rowid = a.fk_fieldplot';
        $sql .= ' WHERE a.entity IN ('.getEntity('safra_activity').')';
        $sql .= ' AND a.status IN ('.FvActivity::STATUS_DRAFT.','.FvActivity::STATUS_PLANNED.','.FvActivity::STATUS_IN_PROGRESS.')';
        $sql .= ' ORDER BY COALESCE(a.date_planned_end, a.date_planned_start, a.date_creation) ASC, a.priority DESC, a.rowid ASC';
        $resql = $db->query($sql);
        if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                        $obj->is_overdue = ActivityPlanningService::isOverdue($obj->status, $obj->date_planned_start, $obj->date_planned_end);
                        $obj->deadline = !empty($obj->date_planned_end) ? $obj->date_planned_end : $obj->date_planned_start;
                        if ($obj->is_overdue) {
                                $activityOverdue++;
                        }
                        $activeActivities[] = $obj;
                }
                $db->free($resql);
        } else {
                dol_syslog(__FILE__.': Error when loading active activities - '.$db->lasterror(), LOG_ERR);
        }

        usort($activeActivities, static function ($left, $right) {
                if ((bool) $left->is_overdue !== (bool) $right->is_overdue) {
                        return $left->is_overdue ? -1 : 1;
                }

                $leftDeadline = !empty($left->deadline) ? strtotime($left->deadline) : PHP_INT_MAX;
                $rightDeadline = !empty($right->deadline) ? strtotime($right->deadline) : PHP_INT_MAX;

                return $leftDeadline <=> $rightDeadline;
        });
}

$countActivities = array_sum($activityCounts);
$countOpenActivities = $activityCounts[FvActivity::STATUS_DRAFT] + $activityCounts[FvActivity::STATUS_PLANNED] + $activityCounts[FvActivity::STATUS_IN_PROGRESS];

$operationalSummaryCards = array(
        array(
                'title' => $langs->trans('SafraDashboardOpenActivities'),
                'value' => safra_format_number($countOpenActivities),
                'description' => $langs->trans('SafraDashboardOpenActivitiesDesc'),
                'tone' => 'open',
        ),
        array(
                'title' => $langs->trans('SafraActivityStatusInProgress'),
                'value' => safra_format_number($activityCounts[FvActivity::STATUS_IN_PROGRESS]),
                'description' => $langs->trans('SafraDashboardInProgressDesc'),
                'tone' => 'running',
        ),
        array(
                'title' => $langs->trans('SafraDashboardOverdue'),
                'value' => safra_format_number($activityOverdue),
                'description' => $langs->trans('SafraDashboardOverdueDesc'),
                'tone' => 'overdue',
        ),
        array(
                'title' => $langs->trans('SafraActivityStatusCompleted'),
                'value' => safra_format_number($activityCounts[FvActivity::STATUS_COMPLETED]),
                'description' => $langs->trans('SafraDashboardCompletedDesc'),
                'tone' => 'done',
        ),
);

$contextSummaryCards = array(
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
                'title' => $langs->trans('SafraSummaryCulturas'),
                'value' => safra_format_number($countCulturas),
                'description' => $langs->trans('SafraSummaryCulturasDesc'),
        ),
);
if ($canReadActivities) {
        $contextSummaryCards[] = array(
                'title' => $langs->trans('SafraDashboardTotalActivities'),
                'value' => safra_format_number($countActivities),
                'description' => $langs->trans('SafraDashboardTotalActivitiesDesc'),
        );
}

$weatherLatitude = getDolGlobalString('SAFRA_LATITUDE');
$weatherLongitude = getDolGlobalString('SAFRA_LONGITUDE');
$weatherLocation = getDolGlobalString('SAFRA_FAZENDA');

$dashboardChartConfig = null;
$dashboardTalhaoId = 0;
$dashboardTalhaoLabel = '';
$dashboardWeeklyMessage = '';
$dashboardWeeklyChartTitle = $langs->trans('SafraDashboardSatelliteEvolution');
$hasDashboardSeries = false;
if ($canReadSatellite && !empty($talhaoCache)) {
        $requestedDashboardTalhaoId = GETPOSTINT('dashboard_talhao');
        $dashboardTalhaoIds = array_keys($talhaoCache);
        $dashboardTalhaoId = $requestedDashboardTalhaoId > 0 && isset($talhaoCache[$requestedDashboardTalhaoId])
                ? $requestedDashboardTalhaoId
                : (int) $dashboardTalhaoIds[0];
        $dashboardTalhaoLabel = isset($talhaoCache[$dashboardTalhaoId]['label']) ? $talhaoCache[$dashboardTalhaoId]['label'] : '';

        $dashboardSeriesDefinitions = array(
                'ndvi' => array('axis' => 'index', 'label' => 'SafraIndexNDVIShort', 'color' => '#16a34a', 'decimals' => 3),
                'ndmi' => array('axis' => 'index', 'label' => 'SafraIndexNDMIShort', 'color' => '#2563eb', 'decimals' => 3),
                'swir' => array('axis' => 'index', 'label' => 'SafraIndexSWIRShort', 'color' => '#f97316', 'decimals' => 3),
                'health' => array('axis' => 'health', 'label' => 'SafraIndexHealthShort', 'color' => '#7c3aed', 'decimals' => 2, 'valueUnit' => 'pts'),
        );
        $dashboardSeriesByIndex = array();
        foreach ($dashboardSeriesDefinitions as $seriesCode => $seriesDefinition) {
                $dashboardSeriesByIndex[$seriesCode] = $seriesCode === 'health'
                        ? SafraSatelliteHealth::getWeeklySeries($db, $dashboardTalhaoId, 12)
                        : SafraSatelliteStatistics::getWeeklySeries($db, $dashboardTalhaoId, $seriesCode, 12);
        }

        $hasDashboardSeries = false;
        $dashboardGeneratedAt = null;
        $dashboardValidUntil = null;
        foreach ($dashboardSeriesByIndex as $seriesPayload) {
                if (!empty($seriesPayload['generatedAt']) && ($dashboardGeneratedAt === null || strtotime($seriesPayload['generatedAt']) > strtotime($dashboardGeneratedAt))) {
                        $dashboardGeneratedAt = $seriesPayload['generatedAt'];
                }
                if (!empty($seriesPayload['validUntil']) && ($dashboardValidUntil === null || strtotime($seriesPayload['validUntil']) < strtotime($dashboardValidUntil))) {
                        $dashboardValidUntil = $seriesPayload['validUntil'];
                }
                foreach (isset($seriesPayload['points']) && is_array($seriesPayload['points']) ? $seriesPayload['points'] : array() as $seriesPoint) {
                        if (isset($seriesPoint['mean']) && is_numeric($seriesPoint['mean'])) {
                                $hasDashboardSeries = true;
                                break;
                        }
                }
        }

        if (!$hasDashboardSeries) {
                $messagePriority = array('invalid_credentials', 'missing_credentials', 'credential_connection_error', 'missing_geometry', 'talhao_not_found', 'no_data');
                $dashboardWeeklyMessageKey = 'no_data';
                foreach ($messagePriority as $messageCode) {
                        foreach ($dashboardSeriesByIndex as $seriesPayload) {
                                if (!empty($seriesPayload['message']) && $seriesPayload['message'] === $messageCode) {
                                        $dashboardWeeklyMessageKey = $messageCode;
                                        break 2;
                                }
                        }
                }

                switch ($dashboardWeeklyMessageKey) {
                        case 'invalid_credentials':
                                $dashboardWeeklyMessage = $langs->trans('SafraSatelliteWeeklyMessageInvalidCredentials');
                                break;
                        case 'credential_connection_error':
                                $dashboardWeeklyMessage = $langs->trans('SafraSatelliteWeeklyMessageCredentialConnectionError');
                                break;
                        case 'missing_credentials':
                                $dashboardWeeklyMessage = $langs->trans('SafraSatelliteWeeklyMessageMissingCredentials');
                                break;
                        case 'missing_geometry':
                                $dashboardWeeklyMessage = $langs->trans('SafraSatelliteWeeklyMessageMissingGeometry');
                                break;
                        default:
                                $dashboardWeeklyMessage = $langs->trans('SafraSatelliteWeeklyMessageNoData');
                                break;
                }
        }

        $dashboardChartSeries = array();
        foreach ($dashboardSeriesDefinitions as $seriesCode => $seriesDefinition) {
                $seriesPayload = isset($dashboardSeriesByIndex[$seriesCode]) && is_array($dashboardSeriesByIndex[$seriesCode])
                        ? $dashboardSeriesByIndex[$seriesCode]
                        : array();
                $dashboardChartSeries[] = array(
                        'code' => $seriesCode,
                        'axis' => $seriesDefinition['axis'],
                        'label' => $langs->trans($seriesDefinition['label']),
                        'color' => $seriesDefinition['color'],
                        'decimals' => $seriesDefinition['decimals'],
                        'valueUnit' => isset($seriesDefinition['valueUnit']) ? $seriesDefinition['valueUnit'] : '',
                        'points' => isset($seriesPayload['points']) && is_array($seriesPayload['points']) ? array_values($seriesPayload['points']) : array(),
                );
        }

        $dashboardCombinedLabel = $langs->trans('SafraSatelliteWeeklyCombinedLabel');
        $dashboardWeeklyChartTitle = sprintf($langs->trans('SafraSatelliteWeeklyTitle'), $dashboardCombinedLabel);
        $dashboardChartConfig = array(
                'canvasId' => 'dashboardSatelliteSeriesChart',
                'emptyId' => 'dashboardSatelliteChartEmpty',
                'metaId' => 'dashboardWeeklyMeta',
                'data' => array(
                        'series' => $dashboardChartSeries,
                        'generatedAt' => $dashboardGeneratedAt,
                        'validUntil' => $dashboardValidUntil,
                        'message' => $dashboardWeeklyMessage,
                ),
                'options' => array(
                        'label' => $dashboardCombinedLabel,
                        'emptyMessage' => $langs->trans('SafraSatelliteWeeklyEmpty'),
                        'tooltipLabel' => $langs->trans('SafraSatelliteWeeklyTooltip'),
                        'tooltipMeanLabel' => $langs->trans('SafraSatelliteWeeklyTooltip'),
                        'updatedLabel' => $langs->trans('SafraSatelliteWeeklyUpdated'),
                        'nextLabel' => $langs->trans('SafraSatelliteWeeklyNextUpdate'),
                        'validCoverageLabel' => $langs->trans('SafraSatelliteWeeklyValidCoverage'),
                        'lowQualityLabel' => $langs->trans('SafraSatelliteWeeklyLowQuality'),
                        'rejectedQualityLabel' => $langs->trans('SafraSatelliteWeeklyRejectedQuality'),
                        'cloudWarningSummary' => $langs->trans('SafraSatelliteWeeklyCloudWarningSummary'),
                        'qualityNoticeLabel' => $langs->trans('SafraSatelliteWeeklyQualityNotice'),
                        'qualityNoticeDetailLabel' => $langs->trans('SafraSatelliteWeeklyQualityNoticeDetail'),
                        'showLegend' => true,
                        'leftAxis' => array(
                                'min' => -0.5,
                                'max' => 1,
                                'decimals' => 3,
                                'title' => $langs->trans('SafraSatelliteWeeklyAxisIndices'),
                        ),
                        'rightAxis' => array(
                                'min' => 0,
                                'max' => 100,
                                'decimals' => 2,
                                'title' => $langs->trans('SafraSatelliteWeeklyAxisHealth'),
                        ),
                ),
        );
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

$quickActions = array();
if ($canWriteActivities) {
        $quickActions[] = array(
                'icon' => 'fa-plus',
                'title' => $langs->trans('SafraDashboardActionNewActivity'),
                'description' => $langs->trans('SafraDashboardActionNewActivityDesc'),
                'url' => dol_buildpath('/safra/activity/activity_card.php', 1).'?action=create',
        );
}
if ($canReadActivities) {
        $quickActions[] = array(
                'icon' => 'fa-columns',
                'title' => $langs->trans('SafraDashboardActionAgenda'),
                'description' => $langs->trans('SafraDashboardActionAgendaDesc'),
                'url' => dol_buildpath('/safra/activity/activity_kanban.php', 1),
        );
        $quickActions[] = array(
                'icon' => 'fa-list',
                'title' => $langs->trans('SafraDashboardActionActivityList'),
                'description' => $langs->trans('SafraDashboardActionActivityListDesc'),
                'url' => dol_buildpath('/safra/activity/activity_list.php', 1),
        );
        $quickActions[] = array(
                'icon' => 'fa-chart-bar',
                'title' => $langs->trans('SafraDashboardActionConsumption'),
                'description' => $langs->trans('SafraDashboardActionConsumptionDesc'),
                'url' => dol_buildpath('/safra/report/input_consumption.php', 1),
        );
        $quickActions[] = array(
                'icon' => 'fa-book-open',
                'title' => $langs->trans('SafraDashboardActionManual'),
                'description' => $langs->trans('SafraDashboardActionManualDesc'),
                'url' => dol_buildpath('/safra/manual/operator_manual.php', 1),
        );
}
if ($canReadSatellite) {
        $quickActions[] = array(
                'icon' => 'fa-satellite',
                'title' => $langs->trans('SafraDashboardActionSatellite'),
                'description' => $langs->trans('SafraDashboardActionSatelliteDesc'),
                'url' => dol_buildpath('/safra/satellite_view.php', 1),
        );
        $quickActions[] = array(
                'icon' => 'fa-exchange-alt',
                'title' => $langs->trans('SafraDashboardActionSatelliteCompare'),
                'description' => $langs->trans('SafraDashboardActionSatelliteCompareDesc'),
                'url' => dol_buildpath('/safra/satellite_compare.php', 1),
        );
}

print '<div class="safra-dashboard">';
print '<p class="safra-dashboard__intro">'.$langs->trans('SafraDashboardIntro').'</p>';
print '<div class="safra-dashboard__grid">';

print '<section class="safra-card safra-card--main safra-card--map">';
print '<div class="safra-card__header"><h2>'.$langs->trans('SafraMapTitle').'</h2></div>';
print '<div id="mapIndex" class="safra-map"><div id="boxLoading" class="display"></div></div>';
print '</section>';

print '<section class="safra-card safra-card--side safra-card--weather">';
print '<div class="safra-card__header"><h2>'.$langs->trans('SafraWeatherTitle').'</h2>';
if (!empty($weatherLocation)) {
        print '<span class="safra-chip">'.dol_escape_htmltag($weatherLocation).'</span>';
}
print '</div>';
print '<div id="weather-content" class="safra-weather">'.$langs->trans('SafraWeatherLoading').'</div>';
print '</section>';

if ($canReadSatellite) {
        print '<section class="safra-card safra-card--wide safra-card--chart">';
        print '<div class="safra-card__header safra-dashboard-chart-header"><div><h2>'.dol_escape_htmltag($dashboardWeeklyChartTitle).'</h2>';
        print '<p>'.$langs->trans('SafraSatelliteWeeklySubtitle').'</p></div>';
        if (!empty($talhaoCache)) {
                print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="safra-dashboard-field-switcher">';
                print '<label for="dashboard_talhao">'.$langs->trans('SafraDashboardSatelliteField').'</label>';
                print '<select name="dashboard_talhao" id="dashboard_talhao" class="flat">';
                foreach ($talhaoCache as $talhaoId => $talhaoInfo) {
                        print '<option value="'.((int) $talhaoId).'"'.($dashboardTalhaoId === (int) $talhaoId ? ' selected' : '').'>'.dol_escape_htmltag($talhaoInfo['label']).'</option>';
                }
                print '</select>';
                print '<button type="submit" class="button small">'.$langs->trans('SafraDashboardChangeField').'</button>';
                print '</form>';
        }
        print '</div>';
        if (!empty($talhaoCache)) {
                print '<div class="safra-dashboard-chart-field">'.$langs->trans('SafraTalhaoShort').': <strong>'.dol_escape_htmltag($dashboardTalhaoLabel).'</strong></div>';
                print '<div class="safra-chart-container safra-chart-container--large">';
                print '<canvas id="dashboardSatelliteSeriesChart" class="safra-chart"></canvas>';
                print '<p class="safra-empty" id="dashboardSatelliteChartEmpty">'.dol_escape_htmltag($dashboardWeeklyMessage ?: $langs->trans('SafraSatelliteWeeklyEmpty')).'</p>';
                print '</div>';
                print '<p class="safra-chart__meta" id="dashboardWeeklyMeta"></p>';
        } else {
                print '<p class="safra-empty">'.$langs->trans('SafraNoTalhaoData').'</p>';
        }
        print '</section>';
}

if ($canReadActivities) {
        print '<section class="safra-card safra-card--wide safra-card--operations">';
        print '<div class="safra-card__header"><h2>'.$langs->trans('SafraDashboardOperationsTitle').'</h2>';
        print '<a href="'.dol_buildpath('/safra/activity/activity_kanban.php', 1).'">'.$langs->trans('SafraDashboardActionAgenda').'</a></div>';
        print '<div class="safra-operation-grid">';
        foreach ($operationalSummaryCards as $card) {
                print '<div class="safra-operation-metric safra-operation-metric--'.dol_escape_htmltag($card['tone']).'">';
                print '<div class="safra-operation-metric__value">'.dol_escape_htmltag($card['value']).'</div>';
                print '<div class="safra-operation-metric__label">'.dol_escape_htmltag($card['title']).'</div>';
                print '<div class="safra-operation-metric__description">'.dol_escape_htmltag($card['description']).'</div>';
                print '</div>';
        }
        print '</div>';
        print '<div class="safra-operation-area">';
        print '<span><strong>'.dol_escape_htmltag(safra_format_number($activityAreaPlanned, 2).' '.$langs->trans('SafraUnitHectareShort')).'</strong>'.$langs->trans('SafraDashboardActiveAreaPlanned').'</span>';
        print '<span><strong>'.dol_escape_htmltag(safra_format_number($activityAreaDone, 2).' '.$langs->trans('SafraUnitHectareShort')).'</strong>'.$langs->trans('SafraDashboardActiveAreaDone').'</span>';
        print '</div>';
        print '</section>';

        print '<section class="safra-card safra-card--main">';
        print '<div class="safra-card__header"><h2>'.$langs->trans('SafraDashboardActiveAgendaTitle').'</h2>';
        print '<a href="'.dol_buildpath('/safra/activity/activity_list.php', 1).'">'.$langs->trans('List').'</a></div>';
        if (!empty($activeActivities)) {
                print '<ul class="safra-activity-list">';
                foreach (array_slice($activeActivities, 0, 6) as $row) {
                        $activity = new FvActivity($db);
                        $activity->id = (int) $row->rowid;
                        $activity->ref = $row->ref;
                        $activity->label = $row->label;
                        $statusTone = $row->is_overdue ? 'overdue' : 'status-'.((int) $row->status);
                        $statusLabel = $row->is_overdue ? $langs->trans('SafraDashboardOverdue') : FvActivity::getStatusLabel($row->status, $langs);
                        $talhaoLabel = trim((string) (($row->talhao_ref ? $row->talhao_ref.' - ' : '').$row->talhao_label));
                        print '<li class="safra-activity-list__item">';
                        print '<div class="safra-activity-list__top"><div><div class="safra-activity-list__ref">'.$activity->getNomUrl(1).'</div>';
                        print '<strong>'.dol_escape_htmltag($row->label).'</strong></div>';
                        print '<span class="safra-activity-status safra-activity-status--'.dol_escape_htmltag($statusTone).'">'.dol_escape_htmltag($statusLabel).'</span></div>';
                        print '<div class="safra-activity-list__meta">';
                        print '<span>'.dol_escape_htmltag(FvActivity::getTypeLabel($row->type, $langs)).'</span>';
                        if ($talhaoLabel !== '') {
                                print '<span>'.dol_escape_htmltag($talhaoLabel).'</span>';
                        }
                        if (!empty($row->deadline)) {
                                print '<span>'.$langs->trans('SafraDashboardDeadline').': '.dol_print_date($db->jdate($row->deadline), 'dayhour').'</span>';
                        }
                        if ((float) $row->area_planned > 0) {
                                print '<span>'.dol_escape_htmltag(safra_format_number($row->area_planned, 2).' '.$langs->trans('SafraUnitHectareShort')).'</span>';
                        }
                        print '</div></li>';
                }
                print '</ul>';
        } else {
                print '<p class="safra-empty">'.$langs->trans('SafraDashboardActiveAgendaEmpty').'</p>';
        }
        print '</section>';
}

if (!empty($quickActions)) {
        print '<section class="safra-card safra-card--side">';
        print '<div class="safra-card__header"><h2>'.$langs->trans('SafraDashboardQuickActions').'</h2></div>';
        print '<div class="safra-action-grid">';
        foreach ($quickActions as $actionCard) {
                print '<a class="safra-action-card" href="'.dol_escape_htmltag($actionCard['url']).'">';
                print '<span class="fas '.dol_escape_htmltag($actionCard['icon']).'"></span><span><strong>'.dol_escape_htmltag($actionCard['title']).'</strong>';
                print '<small>'.dol_escape_htmltag($actionCard['description']).'</small></span></a>';
        }
        print '</div></section>';
}

print '<section class="safra-card safra-card--wide safra-card--context">';
print '<div class="safra-card__header"><div><h2>'.$langs->trans('SafraDashboardOperationalBase').'</h2>';
print '<p>'.$langs->trans('SafraDashboardOperationalBaseDesc').'</p></div></div>';
print '<div class="safra-context-grid">';
foreach ($contextSummaryCards as $card) {
        print '<div class="safra-context-card">';
        print '<div class="safra-context-card__value">'.dol_escape_htmltag($card['value']).'</div>';
        print '<div class="safra-context-card__label">'.dol_escape_htmltag($card['title']).'</div>';
        if (!empty($card['description'])) {
                print '<div class="safra-context-card__description">'.dol_escape_htmltag($card['description']).'</div>';
        }
        print '</div>';
}
print '</div>';
print '</section>';

print '</div>';
print '</div>';

$jsOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

print '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
print '<script>';
print 'window.safraTalhoes = '.json_encode(array_values($talhaoData), $jsOptions).';';
print 'window.safraWeatherConfig = '.json_encode(array('latitude' => $weatherLatitude, 'longitude' => $weatherLongitude, 'location' => $weatherLocation), $jsOptions).';';
if ($dashboardChartConfig) {
        print 'window.satelliteChartInstances = window.satelliteChartInstances || [];';
        print 'window.satelliteChartInstances.push('.json_encode($dashboardChartConfig, $jsOptions).');';
}
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
include_once './js/satellite_chart.js.php';
include_once "./js/talhao_index.js.php";

// End of page
llxFooter();
$db->close();
