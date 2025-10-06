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

print '<link rel="stylesheet" href="' . dol_buildpath('/safra/css/satellite-analysis.css', 1) . '?v=1">';

print load_fiche_titre($langs->trans("Índice de Água com Diferença Normalizada(NDWI)"), '', 'safra.png@safra');

print '<div class="fichecenter satellite-analysis-wrapper">';

?>

<div class="satellite-analysis-page">
    <header class="satellite-header">
        <h2 class="satellite-header__title">Monitoramento de umidade com NDWI</h2>
        <p class="satellite-header__subtitle">O NDWI destaca corpos d'água, áreas encharcadas e variações de umidade superficial nos talhões.</p>
    </header>
    <div class="satellite-grid">
        <section class="satellite-column satellite-column--map">
            <div class="satellite-card satellite-card--map">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow">Mapa interativo</span>
                    <h3 class="satellite-card__title">Visualização espacial do NDWI</h3>
                    <p class="satellite-card__subtitle">Identifique lâminas d'água, áreas irrigadas e zonas com déficit hídrico em segundos.</p>
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
                    <p class="satellite-card__subtitle">Selecione o talhão, defina o período desejado e acompanhe a disponibilidade de água no campo.</p>
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
foreach ($list_talhao as $key => $talhao) {
    if ($talhao->label) {
        $name_array[] = $talhao->label;
    } else {
        $name_array[] = $talhao->ref;
    }
    $json_data[] = $talhao->geo_json;
    $area_array[] = $talhao->area;
    $id_array[] = (int) $talhao->id;
    // print $talhao->getKanbanView();
}

if ($consulta != '') {
    $ndwi_obj = new NDWI($db);
    $filename = './json/ndwi/' . $consulta . '.json';
    if (file_exists($filename)) {
        // echo filesize($filename);
        if (filesize($filename) < 1000) {
            $dados = explode("_", $consulta);
            $t = $dados[0] . '/' . $dados[1];
            $ndwi_obj->requestNDWIData(null, $t, null);
        }
    } else {
        $dados = explode("_", $consulta);
        $t = $dados[0] . '/' . $dados[1];
        $ndwi_obj->requestNDWIData(null, $t, null);
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
                        <li>Valores acima de 0,5 normalmente indicam presença de água ou solo saturado.</li>
                        <li>Combine o NDWI com dados de chuva e irrigação para planejar manejos.</li>
                        <li>Use valores negativos para encontrar áreas com risco de déficit hídrico.</li>
                    </ul>
                </div>
            </div>
            <div class="satellite-card satellite-card--legend">
                <div class="satellite-card__header">
                    <span class="satellite-card__eyebrow">Interpretação</span>
                    <h3 class="satellite-card__title">Como ler o NDWI</h3>
                    <p class="satellite-card__subtitle">A escala destaca rapidamente a distribuição de umidade no terreno.</p>
                </div>
                <div class="satellite-legend">
                    <div class="satellite-legend__scale">
                        <div class="satellite-legend__gradients">
                            <div class="gradient" style="background: linear-gradient(to top, rgb(0, 102, 0), rgb(255, 255, 255)); height: 50%; bottom: 0%;"></div>
                            <div class="gradient" style="background: linear-gradient(to top, rgb(255, 255, 255), rgb(0, 0, 255)); height: 50%; bottom: 50%;"></div>
                        </div>
                        <div class="satellite-legend__ticks">
                            <span class="tick" style="bottom: 0%;">-1</span>
                            <span class="tick" style="bottom: 50%;">0</span>
                            <span class="tick" style="bottom: 100%;">1</span>
                        </div>
                    </div>
                    <p class="satellite-legend__description">Valores positivos indicam maior presença de água, enquanto valores negativos representam áreas secas ou com pouca umidade.</p>
                    <ul class="satellite-legend__highlights">
                        <li>Menor que 0 &rarr; solos secos, vegetação estressada ou áreas construídas.</li>
                        <li>Entre 0 e 0,5 &rarr; vegetação com umidade moderada.</li>
                        <li>Acima de 0,5 &rarr; corpos d'água superficiais ou solos saturados.</li>
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
// $ndvi->requestNDWIData();

print '</div>';

?>
<script>
    const talhao_array = <?php echo json_encode($name_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const talhao_ids = <?php echo json_encode($id_array); ?>;
    const talhao_selected = <?php echo isset($_POST['talhao_list']) ? (int) $_POST['talhao_list'] : 'null'; ?>;
    const json = <?php echo json_encode($json_data); ?>;
    const area_array = <?php echo json_encode($area_array); ?>;
    const arquivo_post = <?php echo json_encode($consulta ? $consulta : ''); ?>;
</script>
<?php
// include do script
include_once "./js/ndwi_view.js.php";

// End of page
llxFooter();
$db->close();
