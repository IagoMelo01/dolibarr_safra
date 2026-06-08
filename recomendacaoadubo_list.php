<?php
/* Copyright (C) 2024 SuperAdmin */

/**
 * \file       recomendacaoadubo_list.php
 * \ingroup    safra
 * \brief      List page for fertilizer and liming recommendations.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/class/recomendacaoadubo.class.php';

$langs->loadLangs(array("safra@safra", "other"));

$object = new RecomendacaoAdubo($db);
$form = new Form($db);

$enablepermissioncheck = 0;
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('safra', 'recomendacaoadubo', 'read');
	$permissiontoadd = $user->hasRight('safra', 'recomendacaoadubo', 'write');
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1;
}

if ($user->socid > 0) {
	accessforbidden();
}
if (!isModEnabled("safra")) {
	accessforbidden('Module safra not enabled');
}
if (!$permissiontoread) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'list';
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;

$allowedSortFields = array(
	'r.ref' => 1,
	'r.label' => 1,
	'r.cultura' => 1,
	'r.status' => 1,
	'r.ai_generated_at' => 1,
	'r.tms' => 1,
);
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = strtoupper(GETPOST('sortorder', 'aZ09comma'));
if (empty($allowedSortFields[$sortfield])) {
	$sortfield = 'r.tms';
}
if ($sortorder !== 'ASC' && $sortorder !== 'DESC') {
	$sortorder = 'DESC';
}

$search_ref = trim(GETPOST('search_ref', 'alphanohtml'));
$search_label = trim(GETPOST('search_label', 'alphanohtml'));
$search_cultura = trim(GETPOST('search_cultura', 'alphanohtml'));
$search_analise = trim(GETPOST('search_analise', 'alphanohtml'));
$search_talhao = trim(GETPOST('search_talhao', 'alphanohtml'));
$search_status = GETPOSTISSET('search_status') ? GETPOST('search_status', 'int') : -1;

if (GETPOST('button_removefilter', 'alpha')) {
	$search_ref = '';
	$search_label = '';
	$search_cultura = '';
	$search_analise = '';
	$search_talhao = '';
	$search_status = -1;
}

$param = '';
foreach (array(
	'search_ref' => $search_ref,
	'search_label' => $search_label,
	'search_cultura' => $search_cultura,
	'search_analise' => $search_analise,
	'search_talhao' => $search_talhao,
) as $key => $value) {
	if ($value !== '') {
		$param .= '&'.$key.'='.urlencode($value);
	}
}
if ($search_status !== '' && $search_status >= 0) {
	$param .= '&search_status='.((int) $search_status);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}

$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'safra_recomendacaoadubo as r';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'safra_analisesolo as a ON a.rowid = r.analise_solo';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'safra_talhao as th ON th.rowid = a.fk_talhao';
$sqlWhere = ' WHERE 1 = 1';
if ($search_ref !== '') {
	$sqlWhere .= natural_search('r.ref', $search_ref);
}
if ($search_label !== '') {
	$sqlWhere .= natural_search('r.label', $search_label);
}
if ($search_cultura !== '') {
	$sqlWhere .= natural_search('r.cultura', $search_cultura);
}
if ($search_analise !== '') {
	$sqlWhere .= natural_search(array('a.ref', 'a.label'), $search_analise);
}
if ($search_talhao !== '') {
	$sqlWhere .= natural_search(array('th.ref', 'th.label'), $search_talhao);
}
if ($search_status !== '' && $search_status >= 0) {
	$sqlWhere .= ' AND r.status = '.((int) $search_status);
}

$sqlCount = 'SELECT COUNT(*) as nb'.$sqlFrom.$sqlWhere;
$nbtotalofrecords = 0;
$resql = $db->query($sqlCount);
if ($resql) {
	$objCount = $db->fetch_object($resql);
	$nbtotalofrecords = $objCount ? (int) $objCount->nb : 0;
	$db->free($resql);
} else {
	dol_print_error($db);
}

$sql = 'SELECT r.rowid, r.ref, r.label, r.status, r.cultura, r.produtividade_alvo, r.area_ha, r.recomendacao, r.ai_model, r.ai_generated_at, r.tms,';
$sql .= ' a.rowid as analysis_id, a.ref as analysis_ref, a.label as analysis_label, a.latitude, a.longitude,';
$sql .= ' th.rowid as talhao_id, th.ref as talhao_ref, th.label as talhao_label';
$sql .= $sqlFrom.$sqlWhere;
$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

/**
 * @param string $raw
 * @return string
 */
function safraRecommendationListPreview($raw)
{
	$text = preg_replace('/<\s*li[^>]*>/i', ' - ', (string) $raw);
	$text = preg_replace('/<\s*br\s*\/?>/i', ' ', $text);
	$text = strip_tags($text);
	$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	$text = str_replace('**', '', $text);
	$text = preg_replace('/\s+/', ' ', $text);
	$text = trim($text);
	if ($text === '') {
		return '';
	}
	return dol_trunc($text, 260);
}

/**
 * @param mixed $value
 * @param string $fallback
 * @return string
 */
function safraRecommendationListValue($value, $fallback = '-')
{
	return ($value !== null && $value !== '') ? dol_escape_htmltag((string) $value) : dol_escape_htmltag($fallback);
}

$title = $langs->trans("RecomendacaoAdubos");
$help_url = '';
$morejs = array();
$morecss = array();

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss, '', 'mod-safra page-list bodyforlist');

print '<style>
.safra-rec-filter{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:8px;align-items:end;margin:12px 0 16px;padding:12px;border:1px solid #d8dee4;background:#fff;border-radius:6px;}
.safra-rec-filter label{display:block;font-size:12px;color:#667085;margin-bottom:4px;}
.safra-rec-filter input,.safra-rec-filter select{width:100%;}
.safra-rec-list{display:grid;gap:10px;margin-top:8px;}
.safra-rec-row{display:grid;grid-template-columns:minmax(220px,1.2fr) minmax(260px,1.8fr) minmax(180px,.9fr) auto;gap:14px;align-items:center;border:1px solid #d8dee4;background:#fff;border-radius:6px;padding:14px 16px;}
.safra-rec-main a{font-weight:700;font-size:15px;}
.safra-rec-sub{color:#667085;margin-top:4px;}
.safra-rec-preview{color:#1f2933;line-height:1.45;}
.safra-rec-meta{display:grid;gap:5px;color:#475467;}
.safra-rec-meta strong{color:#17202a;}
.safra-rec-actions{text-align:right;white-space:nowrap;}
.safra-rec-empty{border:1px dashed #b8c2cc;background:#f8fafb;border-radius:6px;padding:18px;color:#586069;}
@media (max-width: 1050px){.safra-rec-filter{grid-template-columns:repeat(3,minmax(120px,1fr));}.safra-rec-row{grid-template-columns:1fr;}.safra-rec-actions{text-align:left;}}
@media (max-width: 620px){.safra-rec-filter{grid-template-columns:1fr;}}
</style>';

$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/safra/recomendacaoadubo_card.php', 1).'?action=create&backtopage='.urlencode($_SERVER['PHP_SELF']), '', $permissiontoadd);
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'object_'.$object->picto, 0, $newcardbutton, '', $limit, 0, 0, 1);

print '<form method="GET" id="searchFormList" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<div class="safra-rec-filter">';
print '<div><label>'.$langs->trans('Ref').'</label><input class="flat" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></div>';
print '<div><label>'.$langs->trans('Label').'</label><input class="flat" type="text" name="search_label" value="'.dol_escape_htmltag($search_label).'"></div>';
print '<div><label>'.$langs->trans('SafraRecommendationCrop').'</label><input class="flat" type="text" name="search_cultura" value="'.dol_escape_htmltag($search_cultura).'"></div>';
print '<div><label>'.$langs->trans('SafraRecommendationSoilAnalysis').'</label><input class="flat" type="text" name="search_analise" value="'.dol_escape_htmltag($search_analise).'"></div>';
print '<div><label>'.$langs->trans('SafraAnaliseSoloTalhao').'</label><input class="flat" type="text" name="search_talhao" value="'.dol_escape_htmltag($search_talhao).'"></div>';
print '<div><label>'.$langs->trans('Status').'</label>'.$form->selectarray('search_status', $object->fields['status']['arrayofkeyval'], ($search_status === '' ? -1 : $search_status), 1, 0, 0, '', 0, 0, 0, '', 'flat').'</div>';
print '<div>';
print '<button class="button" type="submit" name="button_search" value="1">'.$langs->trans('Search').'</button> ';
print '<button class="button button-cancel" type="submit" name="button_removefilter" value="1">'.$langs->trans('RemoveFilter').'</button>';
print '</div>';
print '</div>';
print '</form>';

print '<div class="safra-rec-list">';
$i = 0;
$imaxinloop = ($limit ? min($num, $limit) : $num);
while ($i < $imaxinloop) {
	$row = $db->fetch_object($resql);
	if (empty($row)) {
		break;
	}

	$cardObject = new RecomendacaoAdubo($db);
	$cardObject->id = (int) $row->rowid;
	$cardObject->ref = $row->ref;
	$cardObject->status = (int) $row->status;

	$url = dol_buildpath('/safra/recomendacaoadubo_card.php', 1).'?id='.(int) $row->rowid;
	$analysisLabel = trim((string) $row->analysis_ref . (!empty($row->analysis_label) ? ' - '.$row->analysis_label : ''));
	$talhaoLabel = trim((string) $row->talhao_ref . (!empty($row->talhao_label) ? ' - '.$row->talhao_label : ''));
	$preview = safraRecommendationListPreview($row->recomendacao);

	print '<div class="safra-rec-row">';
	print '<div class="safra-rec-main">';
	print '<a href="'.$url.'">'.dol_escape_htmltag($row->ref).'</a> '.$cardObject->getLibStatut(5);
	if (!empty($row->label)) {
		print '<div class="safra-rec-sub">'.dol_escape_htmltag($row->label).'</div>';
	}
	print '</div>';
	print '<div class="safra-rec-preview">'.($preview !== '' ? dol_escape_htmltag($preview) : '<span class="opacitymedium">'.$langs->trans('SafraRecommendationEmptyAi').'</span>').'</div>';
	print '<div class="safra-rec-meta">';
	print '<div><strong>'.$langs->trans('SafraRecommendationCrop').':</strong> '.safraRecommendationListValue($row->cultura).'</div>';
	print '<div><strong>'.$langs->trans('SafraRecommendationSoilAnalysis').':</strong> '.safraRecommendationListValue($analysisLabel).'</div>';
	print '<div><strong>'.$langs->trans('SafraAnaliseSoloTalhao').':</strong> '.safraRecommendationListValue($talhaoLabel).'</div>';
	print '<div><strong>'.$langs->trans('SafraRecommendationAiGeneratedAt').':</strong> '.safraRecommendationListValue($row->ai_generated_at).'</div>';
	print '</div>';
	print '<div class="safra-rec-actions">';
	print '<a class="button small" href="'.$url.'">'.$langs->trans('Open').'</a>';
	print '</div>';
	print '</div>';

	$i++;
}

if ($i === 0) {
	print '<div class="safra-rec-empty">'.$langs->trans('NoRecordFound').'</div>';
}
print '</div>';

$db->free($resql);

llxFooter();
$db->close();
