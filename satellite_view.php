<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-Francois Ferry  <jfefe@aternatik.fr>
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
 * Unified satellite monitoring view
 */

// Load Dolibarr environment
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

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
include_once './class/talhao.class.php';
dol_include_once('/safra/class/safra_satellite_statistics.class.php');
dol_include_once('/safra/class/safra_satellite_health.class.php');

$langs->loadLangs(array('safra@safra'));

$action = GETPOST('action', 'aZ09');
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
    $action = '';
    $socid = $user->socid;
}

$indexDefinitions = array(
    'ndvi' => array(
        'folder' => 'ndvi',
        'labelKey' => 'SafraIndexNDVIShort',
        'headerTitle' => 'Monitoramento de vigor vegetativo',
        'headerSubtitle' => 'Use NDVI para acompanhar a resposta da cultura e localizar rapidamente areas em estresse.',
        'mapTitle' => 'Mapa de NDVI',
        'mapSubtitle' => 'Valores mais altos indicam vegetacao mais vigorosa.',
        'tips' => array(
            'Compare semanas consecutivas para validar tendencia de crescimento.',
            'Cruze o mapa com inspeções em campo nas areas de baixo indice.',
            'Use junto com NDMI e SWIR para uma leitura mais completa.',
        ),
        'legendTitle' => 'Leitura NDVI',
        'legendSubtitle' => 'Escala normalizada de vigor vegetativo.',
        'legendGradient' => 'linear-gradient(to top, #0d0d0d 0%, #fef9c3 40%, #166534 100%)',
        'legendTicks' => array(
            array('bottom' => '0%', 'label' => '-1'),
            array('bottom' => '40%', 'label' => '0'),
            array('bottom' => '70%', 'label' => '0.4'),
            array('bottom' => '100%', 'label' => '1'),
        ),
        'legendDescription' => 'Valores baixos representam solo exposto, agua ou vegetacao em estresse. Valores altos indicam vegetacao densa e ativa.',
        'legendHighlights' => array(
            'Abaixo de 0: agua e areas sem cobertura.',
            'Entre 0.2 e 0.4: cobertura vegetal moderada.',
            'Acima de 0.6: vigor vegetativo alto.',
        ),
        'chart' => array(
            'color' => '#2563eb',
            'gradient' => array('rgba(37, 99, 235, 0.32)', 'rgba(37, 99, 235, 0.06)'),
            'range' => array('min' => -0.2, 'max' => 1),
            'decimals' => 3,
            'rangeFillColor' => 'rgba(37, 99, 235, 0.16)',
            'rangeLineColor' => 'rgba(37, 99, 235, 0.32)',
        ),
    ),
    'ndmi' => array(
        'folder' => 'ndmi',
        'labelKey' => 'SafraIndexNDMIShort',
        'headerTitle' => 'Monitoramento de umidade foliar',
        'headerSubtitle' => 'Use NDMI para detectar antecipadamente sinais de estresse hidrico na plantacao.',
        'mapTitle' => 'Mapa de NDMI',
        'mapSubtitle' => 'O indice aponta a disponibilidade de agua no dossel.',
        'tips' => array(
            'Observe quedas consecutivas para priorizar manejo hidrico.',
            'Confronte o mapa com historico de chuva e irrigacao.',
            'Use o indicador de saude geral para priorizar talhoes.',
        ),
        'legendTitle' => 'Leitura NDMI',
        'legendSubtitle' => 'Escala de umidade na vegetacao.',
        'legendGradient' => 'linear-gradient(to top, #7f1d1d 0%, #facc15 45%, #0ea5e9 100%)',
        'legendTicks' => array(
            array('bottom' => '0%', 'label' => '-1'),
            array('bottom' => '35%', 'label' => '-0.2'),
            array('bottom' => '65%', 'label' => '0.2'),
            array('bottom' => '100%', 'label' => '1'),
        ),
        'legendDescription' => 'Valores negativos tendem a indicar menor umidade. Valores positivos indicam maior disponibilidade de agua na planta.',
        'legendHighlights' => array(
            'Abaixo de -0.2: alerta de estresse hidrico.',
            'Entre -0.2 e 0.4: condicao intermediaria.',
            'Acima de 0.4: boa disponibilidade de agua.',
        ),
        'chart' => array(
            'color' => '#16a34a',
            'gradient' => array('rgba(22, 163, 74, 0.32)', 'rgba(22, 163, 74, 0.05)'),
            'range' => array('min' => -0.2, 'max' => 1),
            'decimals' => 3,
            'rangeFillColor' => 'rgba(22, 163, 74, 0.14)',
            'rangeLineColor' => 'rgba(22, 163, 74, 0.32)',
        ),
    ),
    'swir' => array(
        'folder' => 'swir',
        'labelKey' => 'SafraIndexSWIRShort',
        'headerTitle' => 'Monitoramento com composicao SWIR',
        'headerSubtitle' => 'SWIR ajuda a separar vegetacao, solo exposto e padroes de umidade no talhao.',
        'mapTitle' => 'Mapa SWIR',
        'mapSubtitle' => 'Componente espectral de infravermelho de onda curta.',
        'tips' => array(
            'Use para reforcar interpretacao de solo e umidade no mesmo periodo.',
            'Compare cenas apos chuva para identificar contrastes de drenagem.',
            'Valide area de baixa resposta com vistorias em campo.',
        ),
        'legendTitle' => 'Leitura SWIR',
        'legendSubtitle' => 'Escala ajustada para leitura da lavoura.',
        'legendGradient' => 'linear-gradient(to top, #0a1f3d 0%, #855e29 40%, #a3a33d 70%, #1f7836 100%)',
        'legendTicks' => array(
            array('bottom' => '0%', 'label' => '-0.5'),
            array('bottom' => '36%', 'label' => '-0.1'),
            array('bottom' => '63%', 'label' => '0.2'),
            array('bottom' => '100%', 'label' => '0.6'),
        ),
        'legendDescription' => 'Valores muito baixos tendem a marcar agua, cinza ou solo muito exposto. Valores altos tendem a acompanhar vegetacao ativa.',
        'legendHighlights' => array(
            'Abaixo de -0.1: area com baixa resposta espectral.',
            'Entre -0.1 e 0.2: transicao entre solo exposto e cobertura intermediaria.',
            'Acima de 0.2: maior atividade espectral da vegetacao.',
        ),
        'chart' => array(
            'color' => '#f97316',
            'gradient' => array('rgba(249, 115, 22, 0.28)', 'rgba(249, 115, 22, 0.05)'),
            'range' => array('min' => -0.5, 'max' => 0.6),
            'decimals' => 3,
            'rangeFillColor' => 'rgba(249, 115, 22, 0.16)',
            'rangeLineColor' => 'rgba(249, 115, 22, 0.35)',
        ),
    ),
    'health' => array(
        'folder' => 'saude_geral',
        'labelKey' => 'SafraIndexHealthShort',
        'headerTitle' => 'Indicador de saude geral da plantacao',
        'headerSubtitle' => 'Indicador combinado de NDVI + NDMI + SWIR para priorizacao rapida de manejo.',
        'mapTitle' => 'Mapa de saude geral',
        'mapSubtitle' => 'Score combinado (0 a 100) para visao consolidada do talhao.',
        'tips' => array(
            'O indicador pondera NDVI (50%), NDMI (30%) e SWIR (20%).',
            'Quando uma banda cai muito, o score recebe penalidade de estresse.',
            'Pontuacoes baixas indicam prioridade de avaliacao em campo.',
            'Use a tendencia semanal para definir a ordem de intervencao.',
        ),
        'legendTitle' => 'Leitura da saude geral',
        'legendSubtitle' => 'Classificacao consolidada da lavoura.',
        'legendGradient' => 'linear-gradient(to top, #dc2626 0%, #f59e0b 38%, #84cc16 58%, #15803d 78%, #15803d 100%)',
        'legendTicks' => array(
            array('bottom' => '0%', 'label' => '0'),
            array('bottom' => '38%', 'label' => '38'),
            array('bottom' => '58%', 'label' => '58'),
            array('bottom' => '78%', 'label' => '78'),
            array('bottom' => '100%', 'label' => '100'),
        ),
        'legendDescription' => 'Score de 0 a 100. Quanto maior, melhor a condicao espectral combinada da cultura no periodo.',
        'legendHighlights' => array(
            '0-37: critica.',
            '38-57: atencao.',
            '58-77: boa.',
            '78-100: excelente.',
        ),
        'chart' => array(
            'color' => '#15803d',
            'gradient' => array('rgba(21, 128, 61, 0.30)', 'rgba(21, 128, 61, 0.06)'),
            'range' => array('min' => 0, 'max' => 100),
            'decimals' => 2,
            'rangeFillColor' => 'rgba(21, 128, 61, 0.14)',
            'rangeLineColor' => 'rgba(21, 128, 61, 0.30)',
        ),
    ),
);

$allowedIndexes = array_keys($indexDefinitions);
$selectedIndex = strtolower(GETPOST('sat_index', 'aZ09'));
if (empty($selectedIndex)) {
    $selectedIndex = strtolower(GETPOST('band', 'aZ09'));
}

$consulta = GETPOST('consulta', 'alphanohtml');
$selectedTalhaoId = (int) GETPOST('talhao_list', 'int');
$fileKey = GETPOST('arquivo', 'alphanohtml');
$selectedDateRange = GETPOST('dateRange', 'alphanohtml');

if (!empty($consulta)) {
    $consultaParts = explode('_', $consulta);
    if (count($consultaParts) >= 4) {
        $fileKey = $consultaParts[0] . '_' . $consultaParts[1] . '_' . $consultaParts[2];
        $selectedDateRange = $consultaParts[0] . '/' . $consultaParts[1];
        if (!$selectedTalhaoId) {
            $selectedTalhaoId = (int) $consultaParts[2];
        }
        if (empty($selectedIndex)) {
            $selectedIndex = strtolower($consultaParts[3]);
        }
    }
}

if (!in_array($selectedIndex, $allowedIndexes, true)) {
    $selectedIndex = 'ndvi';
}

$selectedMeta = $indexDefinitions[$selectedIndex];

$objTalhao = new Talhao($db);
$listTalhao = $objTalhao->fetchAll();
$talhaoGeoById = array();
$talhaoAreaById = array();
$talhaoLabelById = array();

foreach ((array) $listTalhao as $talhao) {
    $id = (int) $talhao->id;
    $label = $talhao->label ? $talhao->label : $talhao->ref;
    $talhaoGeoById[$id] = $talhao->geo_json;
    $talhaoAreaById[$id] = $talhao->area;
    $talhaoLabelById[$id] = $label;
}

$selectedTalhaoLabel = '';
if ($selectedTalhaoId > 0 && isset($talhaoLabelById[$selectedTalhaoId])) {
    $selectedTalhaoLabel = $talhaoLabelById[$selectedTalhaoId];
}

$mapStatusMessage = '';
if (!empty($fileKey) && $selectedTalhaoId > 0) {
    $mapAbsoluteFile = DOL_DOCUMENT_ROOT . '/custom/safra/json/' . $selectedMeta['folder'] . '/' . $fileKey . '.json';
    $validSelectedRange = !empty($selectedDateRange) && preg_match('/^\d{4}-\d{2}-\d{2}\/\d{4}-\d{2}-\d{2}$/', $selectedDateRange);
    $selectedTalhao = new Talhao($db);
    if ($validSelectedRange && $selectedTalhao->fetch($selectedTalhaoId) > 0) {
        if ($selectedIndex === 'health') {
            $sourceBase = str_replace('/', '_', $selectedDateRange) . '_' . $selectedTalhaoId;
            $ndviPath = DOL_DOCUMENT_ROOT . '/custom/safra/json/ndvi/' . $sourceBase . '.json';
            $ndmiPath = DOL_DOCUMENT_ROOT . '/custom/safra/json/ndmi/' . $sourceBase . '.json';
            $swirPath = DOL_DOCUMENT_ROOT . '/custom/safra/json/swir/' . $sourceBase . '.json';

            dol_include_once('/safra/class/ndvi.class.php');
            dol_include_once('/safra/class/ndmi.class.php');
            dol_include_once('/safra/class/swir.class.php');

            if (!is_file($ndviPath)) {
                $ndvi = new NDVI($db);
                $ndvi->requestNDVIData(null, $selectedDateRange, $selectedTalhao);
            }
            if (!is_file($ndmiPath)) {
                $ndmi = new NDMI($db);
                $ndmi->requestNDMIData(null, $selectedDateRange, $selectedTalhao);
            }
            if (!is_file($swirPath)) {
                $swir = new SWIR($db);
                $swir->requestSWIRData(null, $selectedDateRange, $selectedTalhao);
            }

            // Always regenerate health to apply the latest scoring model.
            SafraSatelliteHealth::generateForRange($db, $selectedDateRange, $selectedTalhaoId);
        } elseif (!is_file($mapAbsoluteFile)) {
            if ($selectedIndex === 'ndvi') {
                dol_include_once('/safra/class/ndvi.class.php');
                $obj = new NDVI($db);
                $obj->requestNDVIData(null, $selectedDateRange, $selectedTalhao);
            } elseif ($selectedIndex === 'ndmi') {
                dol_include_once('/safra/class/ndmi.class.php');
                $obj = new NDMI($db);
                $obj->requestNDMIData(null, $selectedDateRange, $selectedTalhao);
            } elseif ($selectedIndex === 'swir') {
                dol_include_once('/safra/class/swir.class.php');
                $obj = new SWIR($db);
                $obj->requestSWIRData(null, $selectedDateRange, $selectedTalhao);
            }
        }
    }

    if (!is_file($mapAbsoluteFile)) {
        $mapStatusMessage = $langs->trans('SafraSatelliteMapMissingFile');
    }
}

$chartSeriesDefinitions = array(
    'ndvi' => array('axis' => 'index'),
    'ndmi' => array('axis' => 'index'),
    'swir' => array('axis' => 'index'),
    'health' => array('axis' => 'health'),
);

$weeklySeriesByIndex = array();
$weeklyWindowWeeks = 12;
if ($selectedTalhaoId > 0) {
    foreach ($chartSeriesDefinitions as $seriesCode => $seriesDefinition) {
        if ($seriesCode === 'health') {
            $weeklySeriesByIndex[$seriesCode] = SafraSatelliteHealth::getWeeklySeries($db, $selectedTalhaoId, $weeklyWindowWeeks);
        } else {
            $weeklySeriesByIndex[$seriesCode] = SafraSatelliteStatistics::getWeeklySeries($db, $selectedTalhaoId, $seriesCode, $weeklyWindowWeeks);
        }
    }
}

$pickLatestIsoDate = function (array $values) {
    $selectedDate = null;
    $selectedTimestamp = null;

    foreach ($values as $value) {
        if (empty($value)) {
            continue;
        }
        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            continue;
        }
        if ($selectedTimestamp === null || $timestamp > $selectedTimestamp) {
            $selectedTimestamp = $timestamp;
            $selectedDate = (string) $value;
        }
    }

    return $selectedDate;
};

$pickEarliestIsoDate = function (array $values) {
    $selectedDate = null;
    $selectedTimestamp = null;

    foreach ($values as $value) {
        if (empty($value)) {
            continue;
        }
        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            continue;
        }
        if ($selectedTimestamp === null || $timestamp < $selectedTimestamp) {
            $selectedTimestamp = $timestamp;
            $selectedDate = (string) $value;
        }
    }

    return $selectedDate;
};

$generatedCandidates = array();
$validCandidates = array();
$hasChartNumericData = false;
foreach ($weeklySeriesByIndex as $seriesPayload) {
    if (!empty($seriesPayload['generatedAt'])) {
        $generatedCandidates[] = $seriesPayload['generatedAt'];
    }
    if (!empty($seriesPayload['validUntil'])) {
        $validCandidates[] = $seriesPayload['validUntil'];
    }
    if (empty($seriesPayload['points']) || !is_array($seriesPayload['points'])) {
        continue;
    }
    foreach ($seriesPayload['points'] as $seriesPoint) {
        if (isset($seriesPoint['mean']) && is_numeric($seriesPoint['mean'])) {
            $hasChartNumericData = true;
            break 2;
        }
    }
}

$weeklyMessageKey = '';
if ($selectedTalhaoId > 0 && !$hasChartNumericData) {
    $messagePriority = array('missing_credentials', 'missing_geometry', 'talhao_not_found', 'no_data');
    foreach ($messagePriority as $messageCode) {
        foreach ($weeklySeriesByIndex as $seriesPayload) {
            if (!empty($seriesPayload['message']) && $seriesPayload['message'] === $messageCode) {
                $weeklyMessageKey = $messageCode;
                break 2;
            }
        }
    }
    if (empty($weeklyMessageKey)) {
        $weeklyMessageKey = 'no_data';
    }
}

$weeklyMessageText = '';
switch ($weeklyMessageKey) {
    case 'missing_credentials':
        $weeklyMessageText = $langs->trans('SafraSatelliteWeeklyMessageMissingCredentials');
        break;
    case 'missing_geometry':
        $weeklyMessageText = $langs->trans('SafraSatelliteWeeklyMessageMissingGeometry');
        break;
    case 'no_data':
    case 'talhao_not_found':
        $weeklyMessageText = $langs->trans('SafraSatelliteWeeklyMessageNoData');
        break;
}

$weeklySeries = isset($weeklySeriesByIndex[$selectedIndex]) && is_array($weeklySeriesByIndex[$selectedIndex])
    ? $weeklySeriesByIndex[$selectedIndex]
    : array('points' => array());
$weeklySeries['message'] = $weeklyMessageText;

$weeklyIndexLabel = $langs->trans($selectedMeta['labelKey']);
$weeklyCombinedLabel = $langs->trans('SafraSatelliteWeeklyCombinedLabel');
if (empty($weeklyCombinedLabel) || $weeklyCombinedLabel === 'SafraSatelliteWeeklyCombinedLabel') {
    $weeklyCombinedLabel = 'NDVI + NDMI + SWIR + ' . $langs->trans('SafraIndexHealthShort');
}
$weeklyChartTitle = sprintf($langs->trans('SafraSatelliteWeeklyTitle'), $weeklyCombinedLabel);
$weeklyChartSubtitle = $langs->trans('SafraSatelliteWeeklySubtitle');
$chartGeneratedAt = $pickLatestIsoDate($generatedCandidates);
$chartValidUntil = $pickEarliestIsoDate($validCandidates);

$indexClientConfig = array();
foreach ($indexDefinitions as $indexCode => $definition) {
    $indexClientConfig[$indexCode] = array(
        'folder' => $definition['folder'],
        'label' => $langs->trans($definition['labelKey']),
    );
}

$chartSeriesPayload = array();
foreach ($chartSeriesDefinitions as $seriesCode => $seriesDefinition) {
    $seriesPayload = isset($weeklySeriesByIndex[$seriesCode]) && is_array($weeklySeriesByIndex[$seriesCode])
        ? $weeklySeriesByIndex[$seriesCode]
        : array();
    $seriesMeta = $indexDefinitions[$seriesCode];
    $seriesChartMeta = $seriesMeta['chart'];

    $chartSeriesPayload[] = array(
        'code' => $seriesCode,
        'axis' => $seriesDefinition['axis'],
        'label' => $langs->trans($seriesMeta['labelKey']),
        'color' => $seriesChartMeta['color'],
        'gradient' => $seriesChartMeta['gradient'],
        'range' => isset($seriesChartMeta['range']) && is_array($seriesChartMeta['range']) ? $seriesChartMeta['range'] : array(),
        'decimals' => isset($seriesChartMeta['decimals']) ? (int) $seriesChartMeta['decimals'] : 2,
        'rangeFillColor' => isset($seriesChartMeta['rangeFillColor']) ? $seriesChartMeta['rangeFillColor'] : '',
        'rangeLineColor' => isset($seriesChartMeta['rangeLineColor']) ? $seriesChartMeta['rangeLineColor'] : '',
        'valueUnit' => $seriesCode === 'health' ? 'pts' : '',
        'points' => isset($seriesPayload['points']) && is_array($seriesPayload['points']) ? array_values($seriesPayload['points']) : array(),
    );
}

$chartConfig = array(
    'canvasId' => 'satelliteSeriesChart',
    'emptyId' => 'satelliteChartEmpty',
    'metaId' => 'satelliteChartMeta',
    'data' => array(
        'points' => isset($weeklySeries['points']) && is_array($weeklySeries['points']) ? array_values($weeklySeries['points']) : array(),
        'series' => $chartSeriesPayload,
        'generatedAt' => $chartGeneratedAt,
        'validUntil' => $chartValidUntil,
        'message' => isset($weeklySeries['message']) ? $weeklySeries['message'] : '',
    ),
    'options' => array(
        'label' => $weeklyCombinedLabel,
        'color' => $selectedMeta['chart']['color'],
        'gradient' => $selectedMeta['chart']['gradient'],
        'decimals' => $selectedMeta['chart']['decimals'],
        'emptyMessage' => $langs->trans('SafraSatelliteWeeklyEmpty'),
        'tooltipLabel' => $langs->trans('SafraSatelliteWeeklyTooltip'),
        'tooltipMeanLabel' => $langs->trans('SafraSatelliteWeeklyTooltip'),
        'tooltipMinLabel' => $langs->trans('SafraSatelliteWeeklyMin'),
        'tooltipMaxLabel' => $langs->trans('SafraSatelliteWeeklyMax'),
        'minLabel' => $langs->trans('SafraSatelliteWeeklyMin'),
        'maxLabel' => $langs->trans('SafraSatelliteWeeklyMax'),
        'rangeFillColor' => $selectedMeta['chart']['rangeFillColor'],
        'rangeLineColor' => $selectedMeta['chart']['rangeLineColor'],
        'updatedLabel' => $langs->trans('SafraSatelliteWeeklyUpdated'),
        'nextLabel' => $langs->trans('SafraSatelliteWeeklyNextUpdate'),
        'valueUnit' => $selectedIndex === 'health' ? 'pts' : '',
        'range' => $selectedMeta['chart']['range'],
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
$jsOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader('', $langs->trans('SafraMenuSatelliteUnified'), '', '', 0, 0, '', '', '', 'mod-safra page-index');

print '<link rel="stylesheet" href="' . dol_buildpath('/safra/css/satellite-analysis.css', 1) . '?v=2">';
print load_fiche_titre($langs->trans('SafraMenuSatelliteUnified'), '', 'safra.png@safra');
print '<div class="fichecenter satellite-analysis-wrapper">';
?>

<div class="satellite-analysis-page">
    <header class="satellite-header">
        <h2 class="satellite-header__title"><?php echo dol_escape_htmltag($selectedMeta['headerTitle']); ?></h2>
        <p class="satellite-header__subtitle"><?php echo dol_escape_htmltag($selectedMeta['headerSubtitle']); ?></p>
    </header>

    <div class="satellite-grid">
        <section class="satellite-column satellite-column--map">
            <div class="satellite-card satellite-card--map">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow">Mapa interativo</span>
                    <h3 class="satellite-card__title"><?php echo dol_escape_htmltag($selectedMeta['mapTitle']); ?></h3>
                    <p class="satellite-card__subtitle"><?php echo dol_escape_htmltag($selectedMeta['mapSubtitle']); ?></p>
                </div>
                <div id="mapIndex" class="satellite-map"></div>
                <div class="satellite-map__meta">
                    <span id="mapStatus"><?php echo dol_escape_htmltag($mapStatusMessage ? $mapStatusMessage : $langs->trans('SafraSatelliteMapChooseFilters')); ?></span>
                </div>
            </div>

            <div class="satellite-card satellite-card--chart">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteWeeklyEyebrow')); ?></span>
                    <h3 class="satellite-card__title"><?php echo dol_escape_htmltag($weeklyChartTitle); ?></h3>
                    <p class="satellite-card__subtitle"><?php echo dol_escape_htmltag($weeklyChartSubtitle); ?></p>
                </div>
                <div class="satellite-chart">
                    <canvas id="satelliteSeriesChart" class="satellite-chart__canvas"></canvas>
                    <p class="satellite-chart__empty" id="satelliteChartEmpty"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteWeeklyEmpty')); ?></p>
                </div>
                <p class="satellite-chart__meta" id="satelliteChartMeta"></p>
            </div>
        </section>

        <aside class="satellite-column satellite-column--sidebar">
            <div class="satellite-card satellite-card--controls">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow">Configuracoes</span>
                    <h3 class="satellite-card__title">Consulta unificada</h3>
                    <p class="satellite-card__subtitle">Selecione banda, talhao e semana para visualizar o geojson processado.</p>
                </div>

                <form action="" id="satellite_form" method="post" class="analysis-form">
                    <div class="analysis-form__row">
                        <label class="analysis-form__label" for="sat_index"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteBandLabel')); ?></label>
                        <select name="sat_index" id="sat_index" class="analysis-form__control">
<?php foreach ($indexDefinitions as $code => $definition) { ?>
                            <option value="<?php echo dol_escape_htmltag($code); ?>"<?php echo $selectedIndex === $code ? ' selected' : ''; ?>>
                                <?php echo dol_escape_htmltag($langs->trans($definition['labelKey'])); ?>
                            </option>
<?php } ?>
                        </select>
                    </div>

                    <div class="analysis-form__row">
                        <label class="analysis-form__label" for="talhao_list">Talhao</label>
                        <select name="talhao_list" id="talhao_list" class="analysis-form__control">
                            <option value=""><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteChooseTalhao')); ?></option>
<?php foreach ((array) $listTalhao as $talhao) {
    $talhaoId = (int) $talhao->id;
    $talhaoLabel = $talhao->label ? $talhao->label : $talhao->ref;
    $talhaoArea = isset($talhao->area) ? $talhao->area : '';
    ?>
                            <option value="<?php echo $talhaoId; ?>" data-area="<?php echo dol_escape_htmltag($talhaoArea); ?>"<?php echo $selectedTalhaoId === $talhaoId ? ' selected' : ''; ?>>
                                <?php echo dol_escape_htmltag($talhaoLabel); ?>
                            </option>
<?php } ?>
                        </select>
                    </div>

                    <div class="analysis-form__row">
                        <label class="analysis-form__label" for="yearPicker">Ano</label>
                        <select id="yearPicker" class="analysis-form__control"></select>
                    </div>

                    <div class="analysis-form__row">
                        <label class="analysis-form__label" for="weekPicker">Semana</label>
                        <select id="weekPicker" class="analysis-form__control"></select>
                    </div>

                    <button type="button" id="btnConsulta" class="analysis-form__button"><?php echo dol_escape_htmltag($langs->trans('Search')); ?></button>
                    <p class="analysis-form__hint" id="dateRangeDisplay"><?php echo dol_escape_htmltag($langs->trans('SafraSatelliteMapChooseWeek')); ?></p>

                    <input name="dateRange" id="dateRange" type="hidden" value="<?php echo dol_escape_htmltag($selectedDateRange); ?>">
                    <input type="hidden" name="arquivo" id="inputArquivo" value="<?php echo dol_escape_htmltag($fileKey); ?>">
                    <input type="hidden" name="consulta" id="inputConsulta" value="<?php echo dol_escape_htmltag($consulta); ?>">
                </form>

                <div class="analysis-summary">
                    <div class="analysis-summary__item">
                        <span class="analysis-summary__label">Indice selecionado</span>
                        <span class="analysis-summary__value" id="selectedBandLabel"><?php echo dol_escape_htmltag($weeklyIndexLabel); ?></span>
                    </div>
                    <div class="analysis-summary__item">
                        <span class="analysis-summary__label">Talhao selecionado</span>
                        <span class="analysis-summary__value" id="selectedFieldName"><?php echo dol_escape_htmltag($selectedTalhaoLabel ? $selectedTalhaoLabel : $langs->trans('SafraSatelliteChooseTalhao')); ?></span>
                    </div>
                    <div class="analysis-summary__item">
                        <span class="analysis-summary__label">Area estimada</span>
                        <span class="analysis-summary__value" id="selectedFieldArea">--</span>
                    </div>
                    <div class="analysis-summary__item">
                        <span class="analysis-summary__label">Periodo analisado</span>
                        <span class="analysis-summary__value analysis-summary__value--accent" id="selectedPeriod">--</span>
                    </div>
                </div>

                <div class="satellite-tips">
                    <p class="satellite-tips__title">Dicas rapidas</p>
                    <ul class="satellite-tips__list">
<?php foreach ($selectedMeta['tips'] as $tip) { ?>
                        <li><?php echo dol_escape_htmltag($tip); ?></li>
<?php } ?>
                    </ul>
                </div>
            </div>

            <div class="satellite-card satellite-card--legend">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow">Interpretacao</span>
                    <h3 class="satellite-card__title"><?php echo dol_escape_htmltag($selectedMeta['legendTitle']); ?></h3>
                    <p class="satellite-card__subtitle"><?php echo dol_escape_htmltag($selectedMeta['legendSubtitle']); ?></p>
                </div>
                <div class="satellite-legend">
                    <div class="satellite-legend__scale">
                        <div class="satellite-legend__gradients">
                            <div class="gradient" style="top: 0; bottom: 0; background: <?php echo dol_escape_htmltag($selectedMeta['legendGradient']); ?>;"></div>
                        </div>
                        <div class="satellite-legend__ticks">
<?php foreach ($selectedMeta['legendTicks'] as $tick) { ?>
                            <span class="tick" style="bottom: <?php echo dol_escape_htmltag($tick['bottom']); ?>;"><?php echo dol_escape_htmltag($tick['label']); ?></span>
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
            </div>
        </aside>
    </div>
</div>

<?php
print '</div>';
?>
<script>
    const talhao_geo_map = <?php echo json_encode($talhaoGeoById, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const talhao_area_map = <?php echo json_encode($talhaoAreaById, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const talhao_selected = <?php echo $selectedTalhaoId > 0 ? $selectedTalhaoId : 'null'; ?>;
    const arquivo_post = <?php echo json_encode($fileKey ? $fileKey : ''); ?>;
    const satellite_index_selected = <?php echo json_encode($selectedIndex); ?>;
    const satellite_index_options = <?php echo json_encode($indexClientConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const map_choose_filters_message = <?php echo json_encode($langs->trans('SafraSatelliteMapChooseFilters')); ?>;
    const map_missing_file_message = <?php echo json_encode($langs->trans('SafraSatelliteMapMissingFile')); ?>;
    window.satelliteChartInstances = window.satelliteChartInstances || [];
    window.satelliteChartInstances.push(<?php echo json_encode($chartConfig, $jsOptions); ?>);
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
include_once './js/satellite_chart.js.php';
include_once './js/satellite_view.js.php';

llxFooter();
$db->close();
