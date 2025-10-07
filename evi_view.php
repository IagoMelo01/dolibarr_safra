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
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
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
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
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

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
include_once './class/talhao.class.php';
include_once './class/evi.class.php';
dol_include_once('/safra/class/safra_satellite_statistics.class.php');

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

llxHeader("", $langs->trans("Safra - EVI"), '', '', 0, 0, '', '', '', 'mod-safra page-index');

print '<link rel="stylesheet" href="' . dol_buildpath('/safra/css/satellite-analysis.css', 1) . '?v=1">';

print load_fiche_titre($langs->trans("Índice De Vegetação Melhorado (EVI)"), '', 'safra.png@safra');

print '<div class="fichecenter satellite-analysis-wrapper">';

?>

<div class="satellite-analysis-page">
    <header class="satellite-header">
        <h2 class="satellite-header__title">Análise de vigor com o Índice EVI</h2>
        <p class="satellite-header__subtitle">O EVI reduz ruídos atmosféricos e de solo, oferecendo leituras confiáveis para áreas com cobertura vegetal densa.</p>
    </header>
    <div class="satellite-grid">
        <section class="satellite-column satellite-column--map">
            <div class="satellite-card satellite-card--map">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow">Mapa interativo</span>
                    <h3 class="satellite-card__title">Visualização espacial do EVI</h3>
                    <p class="satellite-card__subtitle">Realce áreas com vegetação vigorosa e identifique sinais sutis de estresse em dosséis fechados.</p>
                </div>
                <div id="mapIndex" class="satellite-map"></div>
                <div class="satellite-map__meta">
                    <span>Atualize os filtros para carregar uma nova cena</span>
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
                    <span class="satellite-card__eyebrow">Configurações</span>
                    <h3 class="satellite-card__title">Monte sua consulta</h3>
                    <p class="satellite-card__subtitle">Escolha o talhão, defina o período de observação e gere o mapa processado automaticamente.</p>
                </div>
<?php

$consulta = '';
if (isset($_POST['consulta'])) {
    $consulta = $_POST['consulta'];
}

$obj_talhao = new Talhao($db);
$list_talhao = $obj_talhao->fetchAll();
$json_data = [];
$area_array = [];
$name_array = [];
$id_array = [];
$talhaoMap = [];
foreach ($list_talhao as $key => $talhao) {
    $label = $talhao->label ? $talhao->label : $talhao->ref;
    $name_array[] = $label;
    $json_data[] = $talhao->geo_json;
    $area_array[] = $talhao->area;
    $id = (int) $talhao->id;
    $id_array[] = $id;
    $talhaoMap[$id] = array(
        'label' => $label,
        'area' => $talhao->area,
    );
    // print $talhao->getKanbanView();
}

if ($consulta != '') {
    $evi_obj = new EVI($db);
    $filename = './json/evi/' . $consulta . '.json';
    if (file_exists($filename)) {
        // echo filesize($filename);
        if (filesize($filename) < 1000) {
            $dados = explode("_", $consulta);
            $t = $dados[0] . '/' . $dados[1];
            $evi_obj->requestEVIData(null, $t, null);
        }
    } else {
        $dados = explode("_", $consulta);
        $t = $dados[0] . '/' . $dados[1];
        $evi_obj->requestEVIData(null, $t, null);
    }
    // echo $filename;
}

$selectedTalhaoId = (int) GETPOST('talhao_list', 'int');
if (!$selectedTalhaoId && !empty($consulta)) {
    $parts = explode('_', $consulta);
    if (isset($parts[2])) {
        $selectedTalhaoId = (int) $parts[2];
    }
}

$selectedTalhaoLabel = '';
if ($selectedTalhaoId > 0 && isset($talhaoMap[$selectedTalhaoId]['label'])) {
    $selectedTalhaoLabel = $talhaoMap[$selectedTalhaoId]['label'];
}

$weeklySeries = array('points' => array());
$weeklyMessageKey = '';
if ($selectedTalhaoId > 0) {
    $weeklySeries = SafraSatelliteStatistics::getWeeklySeries($db, $selectedTalhaoId, 'evi', 8);
    if (!empty($weeklySeries['message'])) {
        $weeklyMessageKey = $weeklySeries['message'];
    }
} else {
    $weeklySeries = array('points' => array());
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

$weeklySeries['message'] = $weeklyMessageText;
$weeklyIndexLabel = $langs->trans('SafraIndexEVIShort');
$weeklyChartTitle = sprintf($langs->trans('SafraSatelliteWeeklyTitle'), $weeklyIndexLabel);
$weeklyChartSubtitle = $langs->trans('SafraSatelliteWeeklySubtitle');

?>

<form action="" id="ndvi_form" method="post" class="analysis-form">
    <div class="analysis-form__row">
        <label class="analysis-form__label" for="talhao_list">Talhão</label>
        <select name="talhao_list" id="talhao_list" class="analysis-form__control"></select>
    </div>
    <div class="analysis-form__row">
        <label class="analysis-form__label" for="yearPicker">Escolha o ano</label>
        <select id="yearPicker" class="analysis-form__control" onchange="updateWeekPicker()"></select>
    </div>
    <div class="analysis-form__row">
        <label class="analysis-form__label" for="weekPicker">Escolha uma semana</label>
        <select id="weekPicker" class="analysis-form__control" onchange="getWeekDates(this.value)"></select>
    </div>
    <button type="button" id="btnConsulta" class="analysis-form__button">Consultar</button>
    <p class="analysis-form__hint" id="dateRangeDisplay">Selecione uma semana para ver as datas.</p>
    <input name="dateRange" id="dateRange" type="hidden" value="Selecione uma semana para ver as datas.">
    <input type="hidden" name="arquivo" value="<?php echo $consulta; ?>" id="inputArquivo">
    <input type="hidden" name="consulta" id="inputConsulta">
</form>

                <div class="analysis-summary">
                    <div class="analysis-summary__item">
                        <span class="analysis-summary__label">Talhão selecionado</span>
                        <span class="analysis-summary__value" id="selectedFieldName">Selecione um talhão</span>
                    </div>
                    <div class="analysis-summary__item">
                        <span class="analysis-summary__label">Área estimada</span>
                        <span class="analysis-summary__value" id="selectedFieldArea">--</span>
                    </div>
                    <div class="analysis-summary__item">
                        <span class="analysis-summary__label">Período analisado</span>
                        <span class="analysis-summary__value analysis-summary__value--accent" id="selectedPeriod">--</span>
                    </div>
                </div>

                <div class="satellite-tips">
                    <p class="satellite-tips__title">Dicas rápidas</p>
                    <ul class="satellite-tips__list">
                        <li>Utilize o EVI para áreas com alta biomassa onde o NDVI satura facilmente.</li>
                        <li>Compare diferentes semanas para detectar mudanças sutis no dossel.</li>
                        <li>Combine o índice com observações climáticas para interpretar variações repentinas.</li>
                    </ul>
                </div>
            </div>
            <div class="satellite-card satellite-card--legend">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow">Interpretação</span>
                    <h3 class="satellite-card__title">Como ler o EVI</h3>
                    <p class="satellite-card__subtitle">A escala ajuda a distinguir níveis de vigor mesmo em cenários de vegetação muito densa.</p>
                </div>
                <div class="satellite-legend">
                    <div class="satellite-legend__scale">
                        <div class="satellite-legend__gradients">
                            <div class="gradient" style="top: 0; bottom: 0; background: linear-gradient(to top, #1f2937 0%, #2563eb 45%, #22c55e 100%);"></div>
                        </div>
                        <div class="satellite-legend__ticks">
                            <span class="tick" style="bottom: 0%;">-1</span>
                            <span class="tick" style="bottom: 20%;">-0.2</span>
                            <span class="tick" style="bottom: 40%;">0</span>
                            <span class="tick" style="bottom: 65%;">0.3</span>
                            <span class="tick" style="bottom: 85%;">0.6</span>
                            <span class="tick" style="bottom: 100%;">1</span>
                        </div>
                    </div>
                    <p class="satellite-legend__description">O EVI varia de -1 a 1 e oferece maior sensibilidade em vegetação com alta densidade de clorofila quando comparado ao NDVI tradicional.</p>
                    <ul class="satellite-legend__highlights">
                        <li>Menor que 0 &rarr; solos expostos, água ou áreas com pouca vegetação.</li>
                        <li>Entre 0,2 e 0,5 &rarr; vegetação moderadamente vigorosa com alguma proteção do solo.</li>
                        <li>Acima de 0,6 &rarr; dossel fechado, vegetação saudável e alta biomassa.</li>
                        <li>Coeficientes padrão: C1=6, C2=7,5 e L=1.</li>
                    </ul>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php
// echo '<pre>';
// print_r($_POST);
// echo '</pre>';
// $ndvi = new NDVI($db);
// $ndvi->requestEVIData();

print '</div>';

$chartConfig = array(
    'canvasId' => 'satelliteSeriesChart',
    'emptyId' => 'satelliteChartEmpty',
    'metaId' => 'satelliteChartMeta',
    'data' => array(
        'points' => isset($weeklySeries['points']) && is_array($weeklySeries['points']) ? array_values($weeklySeries['points']) : array(),
        'generatedAt' => isset($weeklySeries['generatedAt']) ? $weeklySeries['generatedAt'] : null,
        'validUntil' => isset($weeklySeries['validUntil']) ? $weeklySeries['validUntil'] : null,
        'message' => isset($weeklySeries['message']) ? $weeklySeries['message'] : '',
    ),
    'options' => array(
        'label' => $weeklyIndexLabel,
        'color' => '#9333ea',
        'gradient' => array('rgba(147, 51, 234, 0.28)', 'rgba(147, 51, 234, 0.05)'),
        'decimals' => 3,
        'emptyMessage' => $langs->trans('SafraSatelliteWeeklyEmpty'),
        'tooltipLabel' => $langs->trans('SafraSatelliteWeeklyTooltip'),
        'tooltipMeanLabel' => $langs->trans('SafraSatelliteWeeklyTooltip'),
        'tooltipMinLabel' => $langs->trans('SafraSatelliteWeeklyMin'),
        'tooltipMaxLabel' => $langs->trans('SafraSatelliteWeeklyMax'),
        'minLabel' => $langs->trans('SafraSatelliteWeeklyMin'),
        'maxLabel' => $langs->trans('SafraSatelliteWeeklyMax'),
        'rangeFillColor' => 'rgba(147, 51, 234, 0.16)',
        'rangeLineColor' => 'rgba(147, 51, 234, 0.32)',
        'updatedLabel' => $langs->trans('SafraSatelliteWeeklyUpdated'),
        'nextLabel' => $langs->trans('SafraSatelliteWeeklyNextUpdate'),
        'valueUnit' => '',
        'range' => array('min' => -0.1, 'max' => 1),
    ),
);
$jsOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

?>
<script>
    const talhao_array = <?php echo json_encode($name_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const talhao_ids = <?php echo json_encode($id_array); ?>;
    const talhao_selected = <?php echo $selectedTalhaoId > 0 ? $selectedTalhaoId : 'null'; ?>;
    const json = <?php echo json_encode($json_data); ?>;
    const area_array = <?php echo json_encode($area_array); ?>;
    const arquivo_post = <?php echo json_encode($consulta ? $consulta : ''); ?>;
    window.satelliteChartInstances = window.satelliteChartInstances || [];
    window.satelliteChartInstances.push(<?php echo json_encode($chartConfig, $jsOptions); ?>);
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
include_once './js/satellite_chart.js.php';
// include do script
include_once './js/evi_view.js.php';

// End of page
llxFooter();
$db->close();
