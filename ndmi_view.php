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
include_once './class/ndmi.class.php';

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

llxHeader("", $langs->trans("Safra - NDMI"), '', '', 0, 0, '', '', '', 'mod-safra page-index');

print load_fiche_titre($langs->trans("Índice de Humidade de Diferença Normalizada (NDMI)"), '', 'safra.png@safra');

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
    $ndmi_obj = new NDMI($db);
    $filename = './json/ndmi/' . $consulta . '.json';
    if (file_exists($filename)) {
        // echo filesize($filename);
        if (filesize($filename) < 1000) {
            $dados = explode("_", $consulta);
            $t = $dados[0] . '/' . $dados[1];
            $ndmi_obj->requestNDMIData(null, $t, null);
        }
    } else {
        $dados = explode("_", $consulta);
        $t = $dados[0] . '/' . $dados[1];
        $ndmi_obj->requestNDMIData(null, $t, null);
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
        background-color: #555;
    }

    .container{
        flex-wrap: nowrap;
    }

    .item{
        max-width: 100%;
    }

    #map {
        height: 600px;
        width: 100% !important;
    }

    .layer-details {
        border-bottom: 1px solid #555;
        width: 100%;
        display: flex;
        flex-direction: row;
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
        /* color: #d0d5dd; */
        min-width: 50%;
    }
</style>
<div class="layer-details">
    <div class="layer-legend">
        <div class="legend-item continuous">
            <div class="gradients">
                <div class="gradient" style="background: linear-gradient(to top, rgb(128, 0, 0), rgb(255, 0, 0)); height: 20%; bottom: 0%;"></div> <!-- Dark Red to Red -->
                <div class="gradient" style="background: linear-gradient(to top, rgb(255, 0, 0), rgb(255, 255, 0)); height: 20%; bottom: 20%;"></div> <!-- Red to Red-Orange -->
                <div class="gradient" style="background: linear-gradient(to top, rgb(255, 255, 0), rgb(0, 255, 255)); height: 20%; bottom: 40%;"></div> <!-- Yellow to Cyan -->
                <div class="gradient" style="background: linear-gradient(to top, rgb(0, 255, 255), rgb(0, 0, 255)); height: 20%; bottom: 60%;"></div> <!-- Cyan to Blue -->
                <div class="gradient" style="background: linear-gradient(to top, rgb(0, 0, 255), rgb(0, 0, 128)); height: 20%; bottom: 80%;"></div> <!-- Blue to Dark Blue -->
            </div>
            <div class="ticks">
                <label class="tick" style="bottom: 0%;">-1.0</label>
                <label class="tick" style="bottom: 20%;">-0.6</label>
                <label class="tick" style="bottom: 40%;">-0.2</label>
                <label class="tick" style="bottom: 60%;">0.2</label>
                <label class="tick" style="bottom: 80%;">0.6</label>
                <label class="tick" style="bottom: 100%;">1.0</label>
            </div>
        </div>
    </div>
    <div class="layer-description">
        <h1>Índice de Humidade de Diferença Normalizada (NDMI)</h1>
        <p>O Índice de humidade de diferença normalizada (NDMI) é usado para determinar o conteúdo em água da vegetação e para monitorizar secas. O intervalo de valores do NDMI é de -1 a 1. Valores negativos do NDMI (valores próximos de -1 [vermelho - laranja]) correspondem a solos áridos. Valores próximos de 0 (-0,2 a 0,4 [amarelo - ciano]) correspondem geralmente o stress hídrico. Valores altos e positivos correspondem a copa alta sem stress hídrico (aproximadamente de 0,4 a 1 [azul claro - azul escuro]).</p>
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
// $ndvi->requestNDMIData();

print '</div></div>';

// include do script
include_once "./js/ndmi_view.js.php";

// End of page
llxFooter();
$db->close();
