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

print load_fiche_titre($langs->trans("Índice de Vegetação com Diferença Normalizada (NDVI)"), '', 'safra.png@safra');

print '<div class="fichecenter">';

?>

<div class="container">
    <div id="mapIndex" class="item"></div>
</div>

<?php

print '<div class="fichethirdleft">';

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

<form action="" id="ndvi_form" method="post">
    <div id="seletores-ndvi">
        <strong>Talhão: </strong><select name="talhao_list" id="talhao_list"></select>
    </div>
    <label for="yearPicker">Escolha o ano:</label>
    <select id="yearPicker" onchange="updateWeekPicker()">
        <!-- JavaScript para gerar as opções de ano -->
    </select><br>

    <label for="weekPicker">Escolha uma semana:</label>
    <select id="weekPicker" onchange="getWeekDates(this.value)">
        <!-- JavaScript para gerar as opções de semana -->
    </select>
    <button type="button" id="btnConsulta">Consultar</button>
    <input name="dateRange" id="dateRange" type="hidden" disabled value="Selecione uma semana para ver as datas.">
    <!-- <br> -->
    <input type="hidden" name="arquivo" value="<?php echo $consulta; ?>" id="inputArquivo">
    <!-- <p>arquivo</p> -->
    <input type="hidden" name="consulta" id="inputConsulta">
    <!-- <p>consulta</p> -->
</form>

<?php


/* BEGIN MODULEBUILDER DRAFT MYOBJECT
// Draft MyObject
if (isModEnabled('safra') && $user->hasRight('safra', 'read')) {
	$langs->load("orders");

	$sql = "SELECT c.rowid, c.ref, c.ref_client, c.total_ht, c.tva as total_tva, c.total_ttc, s.rowid as socid, s.nom as name, s.client, s.canvas";
	$sql.= ", s.code_client";
	$sql.= " FROM ".MAIN_DB_PREFIX."commande as c";
	$sql.= ", ".MAIN_DB_PREFIX."societe as s";
	$sql.= " WHERE c.fk_soc = s.rowid";
	$sql.= " AND c.fk_statut = 0";
	$sql.= " AND c.entity IN (".getEntity('commande').")";
	if ($socid)	$sql.= " AND c.fk_soc = ".((int) $socid);

	$resql = $db->query($sql);
	if ($resql)
	{
		$total = 0;
		$num = $db->num_rows($resql);

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th colspan="3">'.$langs->trans("DraftMyObjects").($num?'<span class="badge marginleftonlyshort">'.$num.'</span>':'').'</th></tr>';

		$var = true;
		if ($num > 0)
		{
			$i = 0;
			while ($i < $num)
			{

				$obj = $db->fetch_object($resql);
				print '<tr class="oddeven"><td class="nowrap">';

				$myobjectstatic->id=$obj->rowid;
				$myobjectstatic->ref=$obj->ref;
				$myobjectstatic->ref_client=$obj->ref_client;
				$myobjectstatic->total_ht = $obj->total_ht;
				$myobjectstatic->total_tva = $obj->total_tva;
				$myobjectstatic->total_ttc = $obj->total_ttc;

				print $myobjectstatic->getNomUrl(1);
				print '</td>';
				print '<td class="nowrap">';
				print '</td>';
				print '<td class="right" class="nowrap">'.price($obj->total_ttc).'</td></tr>';
				$i++;
				$total += $obj->total_ttc;
			}
			if ($total>0)
			{

				print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td><td colspan="2" class="right">'.price($total)."</td></tr>";
			}
		}
		else
		{

			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("NoOrder").'</td></tr>';
		}
		print "</table><br>";

		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}
}
END MODULEBUILDER DRAFT MYOBJECT */


print '</div><div class="fichetwothirdright">';


$NBMAX = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT');
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT');

/* BEGIN MODULEBUILDER LASTMODIFIED MYOBJECT */
// Last modified myobject
/*
if (isModEnabled('safra') && $user->hasRight('safra', 'read')) {
    $sql = "SELECT s.rowid, s.ref, s.label, s.date_creation, s.tms";
    $sql .= " FROM " . MAIN_DB_PREFIX . "safra_myobject as s";
    $sql .= " WHERE s.entity IN (" . getEntity($myobjectstatic->element) . ")";
    //if ($socid)	$sql.= " AND s.rowid = $socid";
    $sql .= " ORDER BY s.tms DESC";
    $sql .= $db->plimit($max, 0);

    $resql = $db->query($sql);
    if ($resql) {
        $num = $db->num_rows($resql);
        $i = 0;

        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th colspan="2">';
        print $langs->trans("BoxTitleLatestModifiedMyObjects", $max);
        print '</th>';
        print '<th class="right">' . $langs->trans("DateModificationShort") . '</th>';
        print '</tr>';
        if ($num) {
            while ($i < $num) {
                $objp = $db->fetch_object($resql);

                $myobjectstatic->id = $objp->rowid;
                $myobjectstatic->ref = $objp->ref;
                $myobjectstatic->label = $objp->label;
                $myobjectstatic->status = $objp->status;

                print '<tr class="oddeven">';
                print '<td class="nowrap">' . $myobjectstatic->getNomUrl(1) . '</td>';
                print '<td class="right nowrap">';
                print "</td>";
                print '<td class="right nowrap">' . dol_print_date($db->jdate($objp->tms), 'day') . "</td>";
                print '</tr>';
                $i++;
            }

            $db->free($resql);
        } else {
            print '<tr class="oddeven"><td colspan="3" class="opacitymedium">' . $langs->trans("None") . '</td></tr>';
        }
        print "</table><br>";
    }
}
*/




?>
<!-- <p id="mensagem"></p> -->
<style>
    body {
        background-color: #f0f2f5;
        font-family: Arial, Helvetica, sans-serif;
    }

    #ndvi_form {
        background-color: #fff;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    #ndvi_form label {
        margin-right: 5px;
    }

    #ndvi_form select {
        padding: 5px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        background-color: #fff;
    }

    #btnConsulta {
        padding: 6px 15px;
        background-color: #0069d9;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    #btnConsulta:hover {
        background-color: #0053ba;
    }

    .container {
        flex-wrap: nowrap;
        justify-content: center;
    }

    .item {
        max-width: 100%;
        margin-bottom: 1rem;
    }


    #map {
        height: 600px;
        width: 100%;
    }

    #mapIndex {
        height: 500px;
        width: 100%;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .layer-details {
        border-bottom: 1px solid #ddd;
        width: 100%;
        display: flex;
        flex-direction: row;
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .layer-legend {
        padding: 5px 0;
    }

    .continuous {
        display: flex;
        padding: 0 15px 5px 3px;
    }

    .layer-item {
        clear: both;
    }

    .gradients {
        display: flex;
        flex-direction: column;
        height: 200px;
        width: 30px;
        border: 1px solid #999;
        border-radius: 5px;
        overflow: hidden;
        margin: 10px 0;
        position: relative;
        z-index: 1;
    }

    .gradient {
        width: 30px;
        position: absolute;
    }

    .ticks {
        position: relative;
        margin: 10px 0;
        z-index: 0;
        border-top: 1px solid transparent;
        border-bottom: 1px solid transparent;
    }

    .tick {
        position: absolute;
        display: block;
        font-size: 12px;
        line-height: 26px;
        width: 30px;
        margin-bottom: -12px;
        /* color: #fff; */
    }

    .layer-description {
        display: flex;
        flex-direction: column;
        flex: 1;
        padding-left: 40px;
        padding-right: 30px;
        min-width: 50%;
        color: #333;
    }
</style>
<div class="layer-details">
    <div class="layer-legend">
        <div class="legend-item continuous">
            <div class="gradients">
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
            <div class="ticks"><label class="tick" style="bottom: 0%;">- 1</label><label class="tick" style="bottom: 7.7%;">- 0.5</label><label class="tick" style="bottom: 15.4%;">- 0.2</label><label class="tick" style="bottom: 23.1%;">- 0.1</label><label class="tick" style="bottom: 30.8%;"> 0</label><label class="tick" style="bottom: 61.5%;"> 0.2</label><label class="tick" style="bottom: 92.3%;"> 0.6</label><label class="tick" style="bottom: 100%;"> 1</label>
                <!-- <div class="hidden-width-placeholders"><label class="tick">- 1</label><label class="tick">- 0.5</label><label class="tick">- 0.2</label><label class="tick">- 0.1</label><label class="tick"> 0</label><label class="tick"> 0.2</label><label class="tick"> 0.6</label><label class="tick"> 1</label></div> -->
            </div>
        </div>
    </div>
    <div class="layer-description">
        <h1>Índice de Vegetação com Diferença Normalizada (NDVI)</h1>
        <p>O Índice de vegetação com diferença normalizada é um índice simples mas eficiente para quantificar a vegetação verde. É uma medida do estado da saúde da vegetação baseado em como as plantas refletem a luz com determinados comprimentos de onda. O intervalo de valores do NDVI é entre -1 e 1. valores negativos de NDVI (valores próximos de -1) correspondem a água. Valores próximos de 0 (de -0,1 a 0,1) correspondem geralmente a zonas áridas de rocha, areia ou neve. Valores baixos e positivos representam arbustos e prados (aproximadamente 0,2 a 0,4), enquanto que valores elevados indicam florestas húmidas temperadas ou tropicais (valores próximos de 1).</p>
        <!-- <p>Mais informação <a href="https://custom-scripts.sentinel-hub.com/sentinel-2/ndvi/" target="_blank" rel="noopener noreferrer">aqui.</a> e <a href="https://eos.com/ndvi/" target="_blank" rel="noopener noreferrer">aqui.</a></p> -->
    </div>
</div>
<!-- <p>O Índice de vegetação com diferença normalizada é um índice simples mas eficiente para quantificar a vegetação verde. É uma medida do estado da saúde da vegetação baseado em como as plantas refletem a luz com determinados comprimentos de onda. O intervalo de valores do NDVI é entre -1 e 1. valores negativos de NDVI (valores próximos de -1) correspondem a água. Valores próximos de 0 (de -0,1 a 0,1) correspondem geralmente a zonas áridas de rocha, areia ou neve. Valores baixos e positivos representam arbustos e prados (aproximadamente 0,2 a 0,4), enquanto que valores elevados indicam florestas húmidas temperadas ou tropicais (valores próximos de 1).</p> -->
<script>
    let talhao_array = [<?php foreach ($name_array as $key) {
                            echo "'" . $key . "'" . ',';
                        }; ?>]
    let talhao_ids = [<?php foreach ($list_talhao as $key) {
                            echo $key->id . ',';
                        }; ?>]
    let talhao_selected = null;
    let json = <?php echo json_encode($json_data); ?>;
    let area_array = <?php echo json_encode($area_array); ?>;
    let arquivo_post = '<?php echo $consulta ? $consulta : ''; ?>';
</script>

<?php
// echo '<pre>';
// print_r($_POST);
// echo '</pre>';
// $ndvi = new NDVI($db);
// $ndvi->requestNDVIData();

print '</div></div>';

// include do script
include_once "./js/ndvi_view.js.php";

// End of page
llxFooter();
$db->close();
