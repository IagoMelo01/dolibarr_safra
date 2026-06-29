<?php
/*
 * Temporal comparison of satellite analyses for one field plot.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php')) {
    $res = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php')) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

include_once './class/talhao.class.php';
dol_include_once('/safra/class/safra_satellite_statistics.class.php');
dol_include_once('/safra/class/safra_satellite_health.class.php');
dol_include_once('/safra/lib/safra_satellite_compare.lib.php');

$langs->loadLangs(array('safra@safra'));

$definitions = safra_satellite_comparison_definitions();
$selectedIndex = strtolower(GETPOST('sat_index', 'aZ09'));
if (!isset($definitions[$selectedIndex])) {
    $selectedIndex = 'ndvi';
}
$selectedMeta = $definitions[$selectedIndex];
$selectedTalhaoId = (int) GETPOST('talhao_id', 'int');
$shortcut = strtolower(GETPOST('period', 'aZ09'));
$referenceDate = GETPOST('reference_date', 'alphanohtml');
$comparisonReferenceDate = GETPOST('comparison_reference_date', 'alphanohtml');
$action = GETPOST('action', 'aZ09');
$periods = safra_satellite_comparison_periods($referenceDate, $shortcut, $comparisonReferenceDate);
$shortcut = $periods['shortcut'];

$hasAnySatelliteRight = !empty($user->admin)
    || $user->hasRight('safra', 'ndvi', 'read')
    || $user->hasRight('safra', 'ndmi', 'read')
    || $user->hasRight('safra', 'swir', 'read');
if (!$hasAnySatelliteRight) {
    accessforbidden();
}
if ($selectedIndex !== 'health' && empty($user->admin) && !$user->hasRight('safra', $selectedIndex, 'read')) {
    accessforbidden();
}
if ($action === 'compare') {
    if (empty($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
        accessforbidden('POST required');
    }
    if (function_exists('checkToken') && !checkToken(GETPOST('token', 'alphanohtml'))) {
        accessforbidden('Invalid token');
    }
}

$talhaoObject = new Talhao($db);
$listTalhao = $talhaoObject->fetchAll();
$selectedTalhao = null;
$selectedTalhaoLabel = '';
$selectedTalhaoGeoJson = '';
if ($selectedTalhaoId > 0) {
    $candidate = new Talhao($db);
    if ($candidate->fetch($selectedTalhaoId) > 0) {
        $selectedTalhao = $candidate;
        $selectedTalhaoLabel = $candidate->label ? $candidate->label : $candidate->ref;
        $selectedTalhaoGeoJson = $candidate->geo_json;
    }
}

$mapResults = array(
    'current' => array('available' => false, 'generated' => false, 'cacheHit' => false),
    'comparison' => array('available' => false, 'generated' => false, 'cacheHit' => false),
);
if ($action === 'compare' && $selectedTalhao !== null) {
    $mapResults['current'] = safra_satellite_ensure_comparison_file($db, $selectedIndex, $periods['current']['range'], $selectedTalhao);
    $mapResults['comparison'] = safra_satellite_ensure_comparison_file($db, $selectedIndex, $periods['comparison']['range'], $selectedTalhao);
}

$mapStatus = function (array $result) use ($langs, $selectedTalhao) {
    if ($selectedTalhao === null) {
        return $langs->trans('SafraSatelliteCompareChooseTalhao');
    }
    if (!empty($result['cacheHit'])) {
        return $langs->trans('SafraSatelliteCompareCacheHit');
    }
    if (!empty($result['generated'])) {
        return $langs->trans('SafraSatelliteCompareGenerated');
    }
    return $langs->trans('SafraSatelliteMapMissingFile');
};

$buildMapConfig = function ($side) use ($periods, $mapResults, $selectedTalhaoId, $selectedTalhaoLabel, $selectedTalhaoGeoJson, $selectedIndex, $selectedMeta, $mapStatus, $langs) {
    $fileBase = str_replace('/', '_', $periods[$side]['range']) . '_' . (int) $selectedTalhaoId;
    return array(
        'mapId' => $side === 'current' ? 'satelliteCompareCurrentMap' : 'satelliteComparePreviousMap',
        'statusId' => $side === 'current' ? 'satelliteCompareCurrentStatus' : 'satelliteComparePreviousStatus',
        'url' => !empty($mapResults[$side]['available']) ? safra_satellite_json_url($selectedIndex, $fileBase) : '',
        'range' => $periods[$side]['range'],
        'status' => $mapStatus($mapResults[$side]),
        'missingMessage' => $mapStatus(array()),
        'talhaoGeoJson' => $selectedTalhaoGeoJson,
        'talhaoLabel' => $selectedTalhaoLabel,
        'index' => $selectedIndex,
        'indexLabel' => $langs->trans($selectedMeta['labelKey']),
    );
};

$weeklyPayloads = array();
$hasWeeklyData = false;
$generatedDates = array();
$validUntilDates = array();
if ($selectedTalhao !== null) {
    foreach ($definitions as $code => $definition) {
        $payload = $code === 'health'
            ? SafraSatelliteHealth::getWeeklySeries($db, $selectedTalhaoId, 12)
            : SafraSatelliteStatistics::getWeeklySeries($db, $selectedTalhaoId, $code, 12);
        $weeklyPayloads[$code] = $payload;
        if (!empty($payload['generatedAt'])) {
            $generatedDates[] = $payload['generatedAt'];
        }
        if (!empty($payload['validUntil'])) {
            $validUntilDates[] = $payload['validUntil'];
        }
        foreach (!empty($payload['points']) && is_array($payload['points']) ? $payload['points'] : array() as $point) {
            if (isset($point['mean']) && is_numeric($point['mean'])) {
                $hasWeeklyData = true;
                break;
            }
        }
    }
}

$weeklyMessage = '';
if ($selectedTalhao === null) {
    $weeklyMessage = $langs->trans('SafraSatelliteWeeklyEmpty');
} elseif (!$hasWeeklyData) {
    $messageCode = 'no_data';
    foreach (array('invalid_credentials', 'missing_credentials', 'credential_connection_error', 'missing_geometry', 'talhao_not_found', 'no_data') as $candidateCode) {
        foreach ($weeklyPayloads as $payload) {
            if (!empty($payload['message']) && $payload['message'] === $candidateCode) {
                $messageCode = $candidateCode;
                break 2;
            }
        }
    }
    $messageKeys = array(
        'invalid_credentials' => 'SafraSatelliteWeeklyMessageInvalidCredentials',
        'missing_credentials' => 'SafraSatelliteWeeklyMessageMissingCredentials',
        'credential_connection_error' => 'SafraSatelliteWeeklyMessageCredentialConnectionError',
        'missing_geometry' => 'SafraSatelliteWeeklyMessageMissingGeometry',
        'talhao_not_found' => 'SafraSatelliteWeeklyMessageNoData',
        'no_data' => 'SafraSatelliteWeeklyMessageNoData',
    );
    $weeklyMessage = $langs->trans($messageKeys[$messageCode]);
}

$chartSeries = array();
foreach ($definitions as $code => $definition) {
    $payload = isset($weeklyPayloads[$code]) ? $weeklyPayloads[$code] : array();
    $chartSeries[] = array(
        'code' => $code,
        'axis' => $definition['chart']['axis'],
        'label' => $langs->trans($definition['labelKey']),
        'color' => $definition['chart']['color'],
        'decimals' => $definition['chart']['decimals'],
        'valueUnit' => $code === 'health' ? 'pts' : '',
        'points' => isset($payload['points']) && is_array($payload['points']) ? array_values($payload['points']) : array(),
    );
}
sort($generatedDates);
sort($validUntilDates);
$combinedLabel = $langs->trans('SafraSatelliteWeeklyCombinedLabel');
$chartConfig = array(
    'canvasId' => 'satelliteCompareSeriesChart',
    'emptyId' => 'satelliteCompareChartEmpty',
    'metaId' => 'satelliteCompareChartMeta',
    'data' => array(
        'series' => $chartSeries,
        'generatedAt' => !empty($generatedDates) ? end($generatedDates) : '',
        'validUntil' => !empty($validUntilDates) ? reset($validUntilDates) : '',
        'message' => $weeklyMessage,
    ),
    'options' => array(
        'label' => $combinedLabel,
        'emptyMessage' => $langs->trans('SafraSatelliteWeeklyEmpty'),
        'tooltipLabel' => $langs->trans('SafraSatelliteWeeklyTooltip'),
        'updatedLabel' => $langs->trans('SafraSatelliteWeeklyUpdated'),
        'nextLabel' => $langs->trans('SafraSatelliteWeeklyNextUpdate'),
        'validCoverageLabel' => $langs->trans('SafraSatelliteWeeklyValidCoverage'),
        'lowQualityLabel' => $langs->trans('SafraSatelliteWeeklyLowQuality'),
        'rejectedQualityLabel' => $langs->trans('SafraSatelliteWeeklyRejectedQuality'),
        'cloudWarningSummary' => $langs->trans('SafraSatelliteWeeklyCloudWarningSummary'),
        'qualityNoticeLabel' => $langs->trans('SafraSatelliteWeeklyQualityNotice'),
        'qualityNoticeDetailLabel' => $langs->trans('SafraSatelliteWeeklyQualityNoticeDetail'),
        'showLegend' => true,
        'leftAxis' => array('min' => -0.5, 'max' => 1, 'decimals' => 3, 'title' => $langs->trans('SafraSatelliteWeeklyAxisIndices')),
        'rightAxis' => array('min' => 0, 'max' => 100, 'decimals' => 2, 'title' => $langs->trans('SafraSatelliteWeeklyAxisHealth')),
    ),
);

$formatPeriod = function ($period) {
    return dol_print_date(strtotime($period['from']), 'day') . ' - ' . dol_print_date(strtotime($period['to']), 'day');
};
$farmevoLogoUrl = DOL_URL_ROOT . '/core/modules/farmevo/farmevo_logo_full.png';
$reportGeneratedAt = dol_print_date(dol_now(), 'dayhour');

llxHeader('', $langs->trans('SafraMenuSatelliteCompare'), '', '', 0, 0, '', '', '', 'mod-safra page-index');
print '<link rel="stylesheet" href="' . dol_buildpath('/safra/css/satellite-analysis.css', 1) . '?v=3">';
print load_fiche_titre($langs->trans('SafraMenuSatelliteCompare'), '', 'safra.png@safra');
print '<div class="fichecenter satellite-analysis-wrapper">';
?>
<div class="satellite-analysis-page satellite-compare-page">
    <div class="satellite-compare-print-header">
        <img class="satellite-compare-print-header__logo" src="<?php echo dol_escape_htmltag($farmevoLogoUrl); ?>" alt="Farmevo">
        <div class="satellite-compare-print-header__content">
            <h1><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareReportTitle')); ?></h1>
            <p><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareReportSubtitle')); ?></p>
            <div class="satellite-compare-print-header__meta">
                <span><strong><?php echo dol_escape_htmltag($langs->trans('SafraTalhaoShort')); ?>:</strong> <?php echo dol_escape_htmltag($selectedTalhaoLabel ?: $langs->trans('SafraSatelliteChooseTalhao')); ?></span>
                <span><strong><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteBandLabel')); ?>:</strong> <?php echo dol_escape_htmltag($langs->trans($selectedMeta['labelKey'])); ?></span>
                <span><strong><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareReportGeneratedAt')); ?>:</strong> <?php echo dol_escape_htmltag($reportGeneratedAt); ?></span>
            </div>
        </div>
    </div>

    <header class="satellite-header satellite-compare-header">
        <div>
            <h2 class="satellite-header__title"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareTitle')); ?></h2>
            <p class="satellite-header__subtitle"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareSubtitle')); ?></p>
        </div>
        <div class="satellite-compare-header__actions">
            <button type="button" class="analysis-form__button satellite-compare-export-button" id="satelliteCompareExportPdf">
                <?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareExportPdf')); ?>
            </button>
            <a class="analysis-form__button satellite-compare-header__link" href="<?php echo dol_buildpath('/safra/satellite_view.php', 1); ?>"><?php echo dol_escape_htmltag($langs->trans('SafraMenuSatelliteUnified')); ?></a>
        </div>
    </header>

    <section class="satellite-card satellite-card--controls">
        <form action="" method="post" class="satellite-compare-form" id="satelliteCompareForm">
            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
            <input type="hidden" name="action" value="compare">
            <input type="hidden" name="period" id="satelliteComparePeriod" value="<?php echo dol_escape_htmltag($shortcut); ?>">
            <div class="analysis-form__row satellite-compare-form__band">
                <label class="analysis-form__label" for="sat_index"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteBandLabel')); ?></label>
                <select class="analysis-form__control" name="sat_index" id="sat_index">
<?php foreach ($definitions as $code => $definition) { ?>
                    <option value="<?php echo dol_escape_htmltag($code); ?>"<?php echo $selectedIndex === $code ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($langs->trans($definition['labelKey'])); ?></option>
<?php } ?>
                </select>
            </div>
            <div class="analysis-form__row satellite-compare-form__field">
                <label class="analysis-form__label" for="talhao_id"><?php echo dol_escape_htmltag($langs->trans('SafraTalhaoShort')); ?></label>
                <select class="analysis-form__control" name="talhao_id" id="talhao_id" required>
                    <option value=""><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteChooseTalhao')); ?></option>
<?php foreach ((array) $listTalhao as $talhao) {
    $talhaoId = (int) $talhao->id;
    $talhaoLabel = $talhao->label ? $talhao->label : $talhao->ref;
    ?>
                    <option value="<?php echo $talhaoId; ?>"<?php echo $selectedTalhaoId === $talhaoId ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($talhaoLabel); ?></option>
<?php } ?>
                </select>
            </div>
            <div class="analysis-form__row satellite-compare-form__current-date">
                <label class="analysis-form__label" for="reference_date"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareReferenceDate')); ?></label>
                <input class="analysis-form__control" type="date" name="reference_date" id="reference_date" value="<?php echo dol_escape_htmltag($periods['referenceDate']); ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="analysis-form__row satellite-compare-form__shortcuts">
                <span class="analysis-form__label"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareShortcut')); ?></span>
                <div class="satellite-compare-shortcuts">
<?php
$shortcutLabels = array(
    'year' => 'SafraSatelliteCompareYear',
    'quarter' => 'SafraSatelliteCompareQuarter',
    'month' => 'SafraSatelliteCompareMonth',
    'week' => 'SafraSatelliteCompareWeek',
);
foreach ($shortcutLabels as $code => $labelKey) {
    ?>
                    <button class="satellite-compare-shortcut<?php echo $periods['comparisonMode'] === 'shortcut' && $shortcut === $code ? ' is-active' : ''; ?>" type="submit" data-period="<?php echo dol_escape_htmltag($code); ?>"><?php echo dol_escape_htmltag($langs->trans($labelKey)); ?></button>
<?php } ?>
                    <button class="satellite-compare-shortcut<?php echo $periods['comparisonMode'] === 'custom' ? ' is-active' : ''; ?>" type="button" id="satelliteCompareCustomPeriod"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareCustom')); ?></button>
                </div>
            </div>
            <div class="satellite-compare-form__submit">
                <button class="analysis-form__button satellite-compare-search-button" type="submit"><?php echo dol_escape_htmltag($langs->trans('Search')); ?></button>
            </div>
            <div class="analysis-form__row satellite-compare-form__previous-date<?php echo $periods['comparisonMode'] === 'custom' ? ' is-visible' : ''; ?>" id="satelliteComparePreviousDateRow">
                <label class="analysis-form__label" for="comparison_reference_date"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteComparePreviousReferenceDate')); ?></label>
                <input class="analysis-form__control" type="date" name="comparison_reference_date" id="comparison_reference_date" value="<?php echo dol_escape_htmltag($periods['comparisonReferenceDate']); ?>" max="<?php echo dol_escape_htmltag($periods['referenceDate']); ?>">
                <span class="analysis-form__hint"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteComparePreviousReferenceDateHint')); ?></span>
            </div>
        </form>
    </section>

    <section class="satellite-compare-maps">
        <article class="satellite-card satellite-card--map">
            <div class="satellite-card__header">
                <span class="satellite-card__eyebrow"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareCurrent')); ?></span>
                <h3 class="satellite-card__title"><?php echo dol_escape_htmltag($formatPeriod($periods['current'])); ?></h3>
                <p class="satellite-card__subtitle"><?php echo dol_escape_htmltag($selectedTalhaoLabel ?: $langs->trans('SafraSatelliteCompareChooseTalhao')); ?></p>
            </div>
            <div id="satelliteCompareCurrentMap" class="satellite-map satellite-compare-map"></div>
            <div class="satellite-map__meta"><span id="satelliteCompareCurrentStatus"><?php echo dol_escape_htmltag($mapStatus($mapResults['current'])); ?></span></div>
        </article>

        <article class="satellite-card satellite-card--map">
            <div class="satellite-card__header">
                <span class="satellite-card__eyebrow"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteComparePrevious')); ?></span>
                <h3 class="satellite-card__title"><?php echo dol_escape_htmltag($formatPeriod($periods['comparison'])); ?></h3>
                <p class="satellite-card__subtitle"><?php echo dol_escape_htmltag($periods['comparisonMode'] === 'custom' ? $langs->trans('SafraSatelliteComparePreviousCustomHint') : sprintf($langs->trans('SafraSatelliteComparePreviousHint'), $langs->trans($shortcutLabels[$shortcut]))); ?></p>
            </div>
            <div id="satelliteComparePreviousMap" class="satellite-map satellite-compare-map"></div>
            <div class="satellite-map__meta"><span id="satelliteComparePreviousStatus"><?php echo dol_escape_htmltag($mapStatus($mapResults['comparison'])); ?></span></div>
        </article>
    </section>

    <section class="satellite-compare-insights">
        <div class="satellite-card satellite-card--chart">
            <div class="satellite-card__header">
                <span class="satellite-card__eyebrow"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteWeeklyEyebrow')); ?></span>
                <h3 class="satellite-card__title"><?php echo dol_escape_htmltag(sprintf($langs->trans('SafraSatelliteWeeklyTitle'), $combinedLabel)); ?></h3>
                <p class="satellite-card__subtitle"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteWeeklySubtitle')); ?></p>
            </div>
            <div class="satellite-chart">
                <canvas id="satelliteCompareSeriesChart" class="satellite-chart__canvas"></canvas>
                <p class="satellite-chart__empty" id="satelliteCompareChartEmpty"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteWeeklyEmpty')); ?></p>
            </div>
            <p class="satellite-chart__meta" id="satelliteCompareChartMeta"></p>
        </div>

        <aside class="satellite-card satellite-card--legend">
            <div class="satellite-card__header">
                <span class="satellite-card__eyebrow"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteCompareLegend')); ?></span>
                <h3 class="satellite-card__title"><?php echo dol_escape_htmltag($selectedMeta['legendTitle']); ?></h3>
                <p class="satellite-card__subtitle"><?php echo dol_escape_htmltag($selectedMeta['legendSubtitle']); ?></p>
            </div>
            <div class="satellite-legend">
                <div class="satellite-legend__scale">
                    <div class="satellite-legend__gradients"><div class="gradient" style="top:0;bottom:0;background:<?php echo dol_escape_htmltag($selectedMeta['legendGradient']); ?>;"></div></div>
                    <div class="satellite-legend__ticks">
<?php foreach ($selectedMeta['legendTicks'] as $tick) { ?>
                        <span class="tick" style="bottom:<?php echo dol_escape_htmltag($tick['bottom']); ?>;"><?php echo dol_escape_htmltag($tick['label']); ?></span>
<?php } ?>
                    </div>
                </div>
                <p class="satellite-legend__description"><?php echo dol_escape_htmltag($selectedMeta['legendDescription']); ?></p>
                <ul class="satellite-legend__highlights">
<?php foreach ($selectedMeta['legendHighlights'] as $highlight) { ?>
                    <li><?php echo dol_escape_htmltag($highlight); ?></li>
<?php } ?>
                </ul>
            </div>
        </aside>
    </section>
</div>
<?php
print '</div>';
$jsOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
?>
<script>
window.safraSatelliteCompare = {
    maps: <?php echo json_encode(array($buildMapConfig('current'), $buildMapConfig('comparison')), $jsOptions); ?>,
    loadedMessage: <?php echo json_encode($langs->trans('SafraSatelliteMapLoaded')); ?>,
    missingMessage: <?php echo json_encode($langs->trans('SafraSatelliteMapMissingFile')); ?>,
    labels: {
        value: <?php echo json_encode($langs->trans('SafraSatelliteCompareValue')); ?>,
        field: <?php echo json_encode($langs->trans('SafraTalhaoShort')); ?>
    },
    exportButtonId: 'satelliteCompareExportPdf',
    exportLabel: <?php echo json_encode($langs->trans('SafraSatelliteCompareExportPdf')); ?>,
    preparingLabel: <?php echo json_encode($langs->trans('SafraSatelliteComparePreparingPdf')); ?>
};
window.satelliteChartInstances = window.satelliteChartInstances || [];
window.satelliteChartInstances.push(<?php echo json_encode($chartConfig, $jsOptions); ?>);
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
include_once './js/satellite_chart.js.php';
include_once './js/satellite_compare.js.php';

llxFooter();
$db->close();
