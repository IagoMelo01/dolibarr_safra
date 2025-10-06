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
include_once './class/ndwi.class.php';

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

llxHeader("", $langs->trans("Safra - NDWI"), '', '', 0, 0, '', '', '', 'mod-safra page-index');

print load_fiche_titre($langs->trans("Índice de Água com Diferença Normalizada(NDWI)"), '', 'safra.png@safra');

print '<div class="fichecenter">';

$consulta = '';
if (isset($_POST['consulta'])) {
    $consulta = $_POST['consulta'];
}

$obj_talhao = new Talhao($db);
$list_talhao = $obj_talhao->fetchAll();
$talhao_geojson = array();
$talhao_areas = array();
$talhao_names = array();
$talhao_ids = array();

foreach ($list_talhao as $talhao) {
    $talhao_ids[] = (int) $talhao->id;
    $talhao_names[] = $talhao->label ? $talhao->label : $talhao->ref;
    $talhao_geojson[] = $talhao->geo_json;
    $talhao_areas[] = (float) $talhao->area;
}

if ($consulta != '') {
    $ndwi_obj = new NDWI($db);
    $filename = './json/ndwi/' . $consulta . '.json';
    if (file_exists($filename)) {
        if (filesize($filename) < 1000) {
            $dados = explode('_', $consulta);
            $t = $dados[0] . '/' . $dados[1];
            $ndwi_obj->requestNDWIData(null, $t, null);
        }
    } else {
        $dados = explode('_', $consulta);
        $t = $dados[0] . '/' . $dados[1];
        $ndwi_obj->requestNDWIData(null, $t, null);
    }
}

$mapTips = array(
    'Acompanhe áreas azuladas para identificar encharcamento ou reservatórios.',
    'Cruze o NDWI com o NDMI para confirmar excesso de umidade em talhões produtivos.',
    'Baixe o GeoJSON para delimitar represas, canais ou faixas de proteção permanente.'
);

$interpretationPoints = array(
    '<strong>Valores abaixo de 0</strong> correspondem a vegetação ou solo exposto.',
    '<strong>Entre 0 e 0,3</strong> indica umidade moderada.',
    '<strong>Acima de 0,5</strong> destaca corpos d\'água, encharcamento ou solos saturados.',
    '<strong>Variações abruptas</strong> ajudam a monitorar a expansão de lâminas d\'água.'
);

?>
<div class="satellite-page">
    <div class="satellite-toolbar">
        <form action="" id="satelliteForm" class="satellite-form" method="post">
            <div class="satellite-form__field">
                <label for="talhao_list">Talhão monitorado</label>
                <select name="talhao_list" id="talhao_list" class="satellite-select"></select>
            </div>
            <div class="satellite-form__field">
                <label for="yearPicker">Ano</label>
                <select id="yearPicker" class="satellite-select"></select>
            </div>
            <div class="satellite-form__field">
                <label for="weekPicker">Semana</label>
                <select id="weekPicker" class="satellite-select"></select>
            </div>
            <div class="satellite-form__actions">
                <button type="button" id="btnConsulta" class="satellite-button">Consultar</button>
            </div>
            <input type="hidden" name="dateRange" id="dateRange" value="">
            <input type="hidden" name="arquivo" value="<?php echo dol_escape_htmltag($consulta); ?>" id="inputArquivo">
            <input type="hidden" name="consulta" id="inputConsulta">
        </form>
        <a id="downloadLayer" class="satellite-button satellite-button--ghost is-disabled" aria-disabled="true" role="button">Baixar GeoJSON</a>
    </div>

    <div class="satellite-metrics">
        <div class="satellite-metric">
            <span class="satellite-metric__label">Talhão selecionado</span>
            <span class="satellite-metric__value" id="metricTalhao">Selecione um talhão</span>
        </div>
        <div class="satellite-metric">
            <span class="satellite-metric__label">Área aproximada</span>
            <span class="satellite-metric__value" id="metricArea">--</span>
        </div>
        <div class="satellite-metric">
            <span class="satellite-metric__label">Período analisado</span>
            <span class="satellite-metric__value" id="metricDate">Selecione uma semana</span>
        </div>
        <div class="satellite-metric">
            <span class="satellite-metric__label">Status do mapa</span>
            <span class="satellite-metric__value" id="metricStatus" aria-live="polite">Selecione um talhão e uma semana para gerar o mapa.</span>
        </div>
    </div>

    <div class="satellite-layout">
        <div class="satellite-layout__main">
            <article class="satellite-card satellite-card--map">
                <div class="satellite-card__header">
                    <h2 class="satellite-card__title">Mapa NDWI</h2>
                    <p class="satellite-card__subtitle" id="selectionTitle">Escolha um talhão e um período para analisar o índice.</p>
                </div>
                <div class="satellite-card__body">
                    <div id="mapIndex" class="satellite-map">
                        <div id="mapLoadingState" class="satellite-map__status" role="status" aria-live="polite" hidden>
                            <span class="satellite-map__spinner" aria-hidden="true"></span>
                            <span id="mapLoadingMessage">Carregando camada...</span>
                        </div>
                    </div>
                </div>
                <div class="satellite-card__footer">
                    <h3 class="satellite-card__subtitle satellite-card__subtitle--accent">Boas práticas de navegação</h3>
                    <ul id="layerInsightList" class="satellite-insight-list"></ul>
                </div>
            </article>
        </div>
        <aside class="satellite-layout__aside">
            <article class="satellite-card satellite-card--legend">
                <h3 class="satellite-card__title">Legenda visual</h3>
                <div class="satellite-legend">
                    <div class="satellite-legend__scale">
                        <div class="satellite-legend__gradient satellite-legend__gradient--ndwi" aria-hidden="true"></div>
                        <ul class="satellite-legend__ticks">
                            <li>-1.0</li>
                            <li>-0.3</li>
                            <li>0.0</li>
                            <li>0.3</li>
                            <li>0.6</li>
                            <li>1.0</li>
                        </ul>
                    </div>
                    <p class="satellite-legend__caption">Verdes e azuis intensos revelam maior presença de água; tons escuros mostram vegetação ou solo exposto.</p>
                </div>
            </article>
            <article class="satellite-card">
                <h3 class="satellite-card__title">Como interpretar</h3>
                <p>O Índice de Água com Diferença Normalizada (NDWI) destaca lâminas d\'água e zonas encharcadas, apoiando o manejo de drenagem e a proteção de recursos hídricos.</p>
                <ul class="satellite-tips">
                    <?php foreach ($interpretationPoints as $point) {
                        echo '<li>' . $point . '</li>';
                    } ?>
                </ul>
                <a class="satellite-link" href="https://eos.com/ndwi/" target="_blank" rel="noopener">Ver guia completo sobre NDWI</a>
            </article>
        </aside>
    </div>
</div>
<?php
$config = array(
    'folder' => 'ndwi',
    'name' => 'NDWI',
    'autoLoadFirst' => true,
    'autoSubmitOnChange' => true,
    'tips' => $mapTips,
);

$dataset = array(
    'ids' => $talhao_ids,
    'names' => $talhao_names,
    'areas' => $talhao_areas,
    'boundaries' => $talhao_geojson,
    'arquivo' => $consulta ? $consulta : '',
);

?>
<script>
    window.satelliteViewConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.satelliteDataset = <?php echo json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php

include_once "./js/satellite_view.js.php";

print '</div>';

// End of page
llxFooter();
$db->close();
