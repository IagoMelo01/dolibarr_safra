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
include_once './class/ndvi.class.php';

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

llxHeader("", $langs->trans("Safra - NDVI"), '', '', 0, 0, '', '', '', 'mod-safra page-index');

print '<link rel="stylesheet" href="' . dol_buildpath('/safra/css/satellite-analysis.css', 1) . '?v=1">';

print load_fiche_titre($langs->trans("Índice de Vegetação com Diferença Normalizada (NDVI)"), '', 'safra.png@safra');

print '<div class="fichecenter satellite-analysis-wrapper">';

?>

<div class="satellite-analysis-page">
    <header class="satellite-header">
        <h2 class="satellite-header__title">Monitoramento de vigor vegetativo com NDVI</h2>
        <p class="satellite-header__subtitle">Selecione o talhão e o período desejado para acompanhar a saúde da cultura com dados de satélite sempre atualizados.</p>
    </header>
    <div class="satellite-grid">
        <section class="satellite-column satellite-column--map">
            <div class="satellite-card satellite-card--map">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow">Mapa interativo</span>
                    <h3 class="satellite-card__title">Visualização espacial do NDVI</h3>
                    <p class="satellite-card__subtitle">Acompanhe a distribuição do índice ao longo da área selecionada e identifique rapidamente zonas de atenção.</p>
                </div>
                <div id="mapIndex" class="satellite-map"></div>
                <div class="satellite-map__meta">
                    <span>Atualize os filtros para carregar uma nova cena</span>
                </div>
            </div>
        </section>
        <aside class="satellite-column satellite-column--sidebar">
            <div class="satellite-card satellite-card--controls">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow">Configurações</span>
                    <h3 class="satellite-card__title">Monte sua consulta</h3>
                    <p class="satellite-card__subtitle">Escolha o talhão, defina o intervalo de análise e gere o mapa mais recente em poucos cliques.</p>
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
foreach ($list_talhao as $key => $talhao) {
    if ($talhao->label) {
        $name_array[] = $talhao->label;
    } else {
        $name_array[] = $talhao->ref;
    }
    $json_data[] = $talhao->geo_json;
    $area_array[] = $talhao->area;
    // print $talhao->getKanbanView();
}

if ($consulta != '') {
    $ndvi_obj = new NDVI($db);
    $filename = './json/ndvi/' . $consulta . '.json';
    if (file_exists($filename)) {
        // echo filesize($filename);
        if (filesize($filename) < 1000) {
            $dados = explode("_", $consulta);
            $t = $dados[0] . '/' . $dados[1];
            $ndvi_obj->requestNDVIData(null, $t, null);
        }
    } else {
        $dados = explode("_", $consulta);
        $t = $dados[0] . '/' . $dados[1];
        $ndvi_obj->requestNDVIData(null, $t, null);
    }
    // echo $filename;
}

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
                        <li>Use diferentes semanas para comparar a evolução do vigor vegetativo.</li>
                        <li>Combine o mapa com inspeções em campo para validar áreas com baixo índice.</li>
                        <li>Exportações recentes ficam disponíveis após alguns minutos do processamento.</li>
                    </ul>
                </div>
            </div>
            <div class="satellite-card satellite-card--legend">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow">Interpretação</span>
                    <h3 class="satellite-card__title">Como ler o NDVI</h3>
                    <p class="satellite-card__subtitle">A escala ao lado apresenta o comportamento típico do índice, ajudando a identificar rapidamente áreas críticas.</p>
                </div>
                <div class="satellite-legend">
                    <div class="satellite-legend__scale">
                        <div class="satellite-legend__gradients">
                            <div class="gradient" style="bottom: 0%; height: 7.69231%; background: linear-gradient(to top, rgb(13, 13, 13), rgb(13, 13, 13));"></div>
                            <div class="gradient" style="bottom: 7.69231%; height: 0%; background: linear-gradient(to top, rgb(13, 13, 13), rgb(191, 191, 191));"></div>
                            <div class="gradient" style="bottom: 7.69231%; height: 7.69231%; background: linear-gradient(to top, rgb(191, 191, 191), rgb(191, 191, 191));"></div>
                            <div class="gradient" style="bottom: 15.3846%; height: 0%; background: linear-gradient(to top, rgb(191, 191, 191), rgb(219, 219, 219));"></div>
                            <div class="gradient" style="bottom: 15.3846%; height: 7.69231%; background: linear-gradient(to top, rgb(219, 219, 219), rgb(219, 219, 219));"></div>
                            <div class="gradient" style="bottom: 23.0769%; height: 0%; background: linear-gradient(to top, rgb(219, 219, 219), rgb(235, 235, 235));"></div>
                            <div class="gradient" style="bottom: 23.0769%; height: 7.69231%; background: linear-gradient(to top, rgb(235, 235, 235), rgb(235, 235, 235));"></div>
                            <div class="gradient" style="bottom: 30.7692%; height: 0%; background: linear-gradient(to top, rgb(235, 235, 235), rgb(255, 250, 204));"></div>
                            <div class="gradient" style="bottom: 30.7692%; height: 3.84615%; background: linear-gradient(to top, rgb(255, 250, 204), rgb(255, 250, 204));"></div>
                            <div class="gradient" style="bottom: 34.6154%; height: 0%; background: linear-gradient(to top, rgb(255, 250, 204), rgb(237, 232, 181));"></div>
                            <div class="gradient" style="bottom: 34.6154%; height: 3.84615%; background: linear-gradient(to top, rgb(237, 232, 181), rgb(237, 232, 181));"></div>
                            <div class="gradient" style="bottom: 38.4615%; height: 0%; background: linear-gradient(to top, rgb(237, 232, 181), rgb(222, 217, 156));"></div>
                            <div class="gradient" style="bottom: 38.4615%; height: 3.84615%; background: linear-gradient(to top, rgb(222, 217, 156), rgb(222, 217, 156));"></div>
                            <div class="gradient" style="bottom: 42.3077%; height: 0%; background: linear-gradient(to top, rgb(222, 217, 156), rgb(204, 199, 130));"></div>
                            <div class="gradient" style="bottom: 42.3077%; height: 3.84615%; background: linear-gradient(to top, rgb(204, 199, 130), rgb(204, 199, 130));"></div>
                            <div class="gradient" style="bottom: 46.1538%; height: 0%; background: linear-gradient(to top, rgb(204, 199, 130), rgb(189, 184, 107));"></div>
                            <div class="gradient" style="bottom: 46.1538%; height: 3.84615%; background: linear-gradient(to top, rgb(189, 184, 107), rgb(189, 184, 107));"></div>
                            <div class="gradient" style="bottom: 50%; height: 0%; background: linear-gradient(to top, rgb(189, 184, 107), rgb(176, 194, 97));"></div>
                            <div class="gradient" style="bottom: 50%; height: 3.84615%; background: linear-gradient(to top, rgb(176, 194, 97), rgb(176, 194, 97));"></div>
                            <div class="gradient" style="bottom: 53.8462%; height: 0%; background: linear-gradient(to top, rgb(176, 194, 97), rgb(163, 204, 89));"></div>
                            <div class="gradient" style="bottom: 53.8462%; height: 3.84615%; background: linear-gradient(to top, rgb(163, 204, 89), rgb(163, 204, 89));"></div>
                            <div class="gradient" style="bottom: 57.6923%; height: 0%; background: linear-gradient(to top, rgb(163, 204, 89), rgb(145, 191, 82));"></div>
                            <div class="gradient" style="bottom: 57.6923%; height: 3.84615%; background: linear-gradient(to top, rgb(145, 191, 82), rgb(145, 191, 82));"></div>
                            <div class="gradient" style="bottom: 61.5385%; height: 0%; background: linear-gradient(to top, rgb(145, 191, 82), rgb(128, 179, 71));"></div>
                            <div class="gradient" style="bottom: 61.5385%; height: 3.84615%; background: linear-gradient(to top, rgb(128, 179, 71), rgb(128, 179, 71));"></div>
                            <div class="gradient" style="bottom: 65.3846%; height: 0%; background: linear-gradient(to top, rgb(128, 179, 71), rgb(112, 163, 64));"></div>
                            <div class="gradient" style="bottom: 65.3846%; height: 3.84615%; background: linear-gradient(to top, rgb(112, 163, 64), rgb(112, 163, 64));"></div>
                            <div class="gradient" style="bottom: 69.2308%; height: 0%; background: linear-gradient(to top, rgb(112, 163, 64), rgb(97, 150, 54));"></div>
                            <div class="gradient" style="bottom: 69.2308%; height: 3.84615%; background: linear-gradient(to top, rgb(97, 150, 54), rgb(97, 150, 54));"></div>
                            <div class="gradient" style="bottom: 73.0769%; height: 0%; background: linear-gradient(to top, rgb(97, 150, 54), rgb(79, 138, 46));"></div>
                            <div class="gradient" style="bottom: 73.0769%; height: 3.84615%; background: linear-gradient(to top, rgb(79, 138, 46), rgb(79, 138, 46));"></div>
                            <div class="gradient" style="bottom: 76.9231%; height: 0%; background: linear-gradient(to top, rgb(79, 138, 46), rgb(64, 125, 36));"></div>
                            <div class="gradient" style="bottom: 76.9231%; height: 3.84615%; background: linear-gradient(to top, rgb(64, 125, 36), rgb(64, 125, 36));"></div>
                            <div class="gradient" style="bottom: 80.7692%; height: 0%; background: linear-gradient(to top, rgb(64, 125, 36), rgb(48, 110, 28));"></div>
                            <div class="gradient" style="bottom: 80.7692%; height: 3.84615%; background: linear-gradient(to top, rgb(48, 110, 28), rgb(48, 110, 28));"></div>
                            <div class="gradient" style="bottom: 84.6154%; height: 0%; background: linear-gradient(to top, rgb(48, 110, 28), rgb(33, 97, 18));"></div>
                            <div class="gradient" style="bottom: 84.6154%; height: 3.84615%; background: linear-gradient(to top, rgb(33, 97, 18), rgb(33, 97, 18));"></div>
                            <div class="gradient" style="bottom: 88.4615%; height: 0%; background: linear-gradient(to top, rgb(33, 97, 18), rgb(15, 84, 10));"></div>
                            <div class="gradient" style="bottom: 88.4615%; height: 3.84615%; background: linear-gradient(to top, rgb(15, 84, 10), rgb(15, 84, 10));"></div>
                            <div class="gradient" style="bottom: 92.3077%; height: 0%; background: linear-gradient(to top, rgb(15, 84, 10), rgb(0, 69, 0));"></div>
                            <div class="gradient" style="bottom: 92.3077%; height: 7.69231%; background: linear-gradient(to top, rgb(0, 69, 0), rgb(0, 69, 0));"></div>
                        </div>
                        <div class="satellite-legend__ticks">
                            <span class="tick" style="bottom: 0%;">-1</span>
                            <span class="tick" style="bottom: 7.7%;">-0.5</span>
                            <span class="tick" style="bottom: 15.4%;">-0.2</span>
                            <span class="tick" style="bottom: 23.1%;">-0.1</span>
                            <span class="tick" style="bottom: 30.8%;">0</span>
                            <span class="tick" style="bottom: 61.5%;">0.2</span>
                            <span class="tick" style="bottom: 92.3%;">0.6</span>
                            <span class="tick" style="bottom: 100%;">1</span>
                        </div>
                    </div>
                    <p class="satellite-legend__description">O NDVI varia de -1 a 1. Valores próximos de zero indicam superfícies expostas ou solo nu, enquanto valores elevados representam vegetação vigorosa e bem hidratada.</p>
                    <ul class="satellite-legend__highlights">
                        <li>Menor que 0 &rarr; corpos d'água ou áreas sem vegetação.</li>
                        <li>Entre 0,2 e 0,4 &rarr; pastagens, vegetação rala ou em estresse.</li>
                        <li>Acima de 0,6 &rarr; vegetação densa e saudável.</li>
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
// $ndvi->requestNDVIData();

print '</div>';

// include do script
include_once "./js/ndvi_view.js.php";

// End of page
llxFooter();
$db->close();
