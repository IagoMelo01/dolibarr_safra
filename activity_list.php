<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       activity_list.php
 *  \ingroup    safra
 *  \brief      Activity list with enhanced filters, status colors and bulk transitions.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

if (!isModEnabled('safra')) {
    accessforbidden();
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
dol_include_once('/safra/class/SfActivity.class.php');
dol_include_once('/safra/lib/safra_rights.lib.php');

global $langs, $db, $conf, $user;

$langs->loadLangs(array('safra@safra', 'companies', 'projects', 'stocks'));

$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$confirmmassaction = GETPOST('confirmmassaction', 'alpha');
$toselect = GETPOST('toselect', 'array');
$show_files = GETPOST('show_files', 'int');
$optioncss = GETPOST('optioncss', 'aZ');

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (empty($sortfield)) {
    $sortfield = 't.date_activity';
}
if (empty($sortorder)) {
    $sortorder = 'DESC';
}
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page === null || $page < 0) {
    $page = 0;
}
$offset = $limit * $page;

$search_ref = trim(GETPOST('search_ref', 'alphanohtml'));
$search_label = trim(GETPOST('search_label', 'alphanohtml'));
$search_type = GETPOST('search_activity_type', 'alpha');
$search_status = GETPOST('search_status', 'int');
$search_project = GETPOST('search_fk_project', 'int');
$search_soc = GETPOST('search_fk_soc', 'int');
$search_date_start = trim(GETPOST('search_date_start', 'alphanohtml'));
$search_date_end = trim(GETPOST('search_date_end', 'alphanohtml'));

if (GETPOST('button_removefilter', 'alpha')) {
    $search_ref = '';
    $search_label = '';
    $search_type = '';
    $search_status = '';
    $search_project = '';
    $search_soc = '';
    $search_date_start = '';
    $search_date_end = '';
}

$object = new SfActivity($db);
$form = new Form($db);
$formcompany = new FormCompany($db);
$formproject = new FormProjets($db);

$permissiontoread = getSafraRightValue($user, 'read');
$permissiontoadd = getSafraRightValue($user, 'write');
$permissiontodelete = getSafraRightValue($user, 'delete');
if (!$permissiontoread) {
    accessforbidden();
}

$massTransitions = array(
    'mass_validate' => array(
        'label' => $langs->trans('SafraActivityMassActionValidate'),
        'callback' => 'validate',
        'icon' => 'fa fa-check',
    ),
    'mass_start' => array(
        'label' => $langs->trans('SafraActivityMassActionStart'),
        'callback' => 'markAsInProgress',
        'icon' => 'fa fa-play',
    ),
    'mass_complete' => array(
        'label' => $langs->trans('SafraActivityMassActionComplete'),
        'callback' => 'markAsCompleted',
        'icon' => 'fa fa-flag-checkered',
    ),
    'mass_cancel' => array(
        'label' => $langs->trans('SafraActivityMassActionCancel'),
        'callback' => 'cancel',
        'icon' => 'fa fa-ban',
    ),
    'mass_reopen' => array(
        'label' => $langs->trans('SafraActivityMassActionReopen'),
        'callback' => 'reopen',
        'icon' => 'fa fa-undo',
    ),
);

if ($massaction && isset($massTransitions[$massaction]) && $confirmmassaction === 'yes' && !empty($toselect)) {
    $langs->load('errors');
    $updated = 0;
    $errors = array();
    if (empty($permissiontoadd)) {
        setEventMessages($langs->trans('ErrorSafraActivityNoRights'), null, 'errors');
    } else {
        foreach ($toselect as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $activity = new SfActivity($db);
            if ($activity->fetch($id) <= 0) {
                $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
                continue;
            }
            $callback = $massTransitions[$massaction]['callback'];
            $result = 0;
            if ($callback === 'markAsInProgress' || $callback === 'markAsCompleted') {
                $result = $activity->$callback($user);
            } else {
                $result = $activity->$callback($user);
            }
            if ($result > 0) {
                $updated++;
            } else {
                $errorMsg = $activity->error ?: (is_array($activity->errors) ? implode(', ', $activity->errors) : '');
                $errors[] = $errorMsg ?: $langs->trans('Error');
            }
        }
        if ($updated > 0) {
            setEventMessages($langs->trans('SafraActivityMassActionResult', $updated, count($toselect)), null, 'mesgs');
        }
        if (!empty($errors)) {
            setEventMessages('', $errors, 'errors');
        }
    }
    $massaction = '';
}

$title = $langs->trans('SafraActivityListTitle');
$help_url = '';
$morejs = array();
$morecss = array();

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss, '', 'mod-safra activity-list');

print '<style>
.activity-status-pill {display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:12px;font-size:0.85em;font-weight:600;color:#fff;}
.activity-status-pill.status-'.SfActivity::STATUS_DRAFT.'{background:#6c757d;}
.activity-status-pill.status-'.SfActivity::STATUS_VALIDATED.'{background:#0d6efd;}
.activity-status-pill.status-'.SfActivity::STATUS_IN_PROGRESS.'{background:#198754;}
.activity-status-pill.status-'.SfActivity::STATUS_COMPLETED.'{background:#6f42c1;}
.activity-status-pill.status-'.SfActivity::STATUS_CANCELED.'{background:#dc3545;}
tr.activity-status-row-'.SfActivity::STATUS_IN_PROGRESS.'{border-left:4px solid #198754;}
tr.activity-status-row-'.SfActivity::STATUS_COMPLETED.'{border-left:4px solid #6f42c1;}
tr.activity-status-row-'.SfActivity::STATUS_CANCELED.'{border-left:4px solid #dc3545;opacity:0.7;}
.activity-list-kanban {margin-left:8px;}
</style>';

$param = '';
if ($search_ref !== '') {
    $param .= '&search_ref='.urlencode($search_ref);
}
if ($search_label !== '') {
    $param .= '&search_label='.urlencode($search_label);
}
if ($search_type !== '') {
    $param .= '&search_activity_type='.urlencode($search_type);
}
if ($search_status !== '' && $search_status !== null) {
    $param .= '&search_status='.(int) $search_status;
}
if ($search_project) {
    $param .= '&search_fk_project='.(int) $search_project;
}
if ($search_soc) {
    $param .= '&search_fk_soc='.(int) $search_soc;
}
if ($search_date_start !== '') {
    $param .= '&search_date_start='.urlencode($search_date_start);
}
if ($search_date_end !== '') {
    $param .= '&search_date_end='.urlencode($search_date_end);
}
if ($limit) {
    $param .= '&limit='.(int) $limit;
}

$newcardbutton = '';
$newcardbutton .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/safra/activity_card.php', 1).'?action=create&backtopage='.urlencode($_SERVER['PHP_SELF']), '', $permissiontoadd);
$newcardbutton .= dolGetButtonTitle($langs->trans('ViewKanban'), '', 'fa fa-th-large activity-list-kanban', dol_buildpath('/safra/activity_kanban.php', 1));

$arrayofmassactions = array();
foreach ($massTransitions as $key => $def) {
    $icon = empty($def['icon']) ? '' : '<span class="'.$def['icon'].' pictofixedwidth"></span>';
    $arrayofmassactions[$key] = $icon.$def['label'];
}
if (!empty($permissiontodelete)) {
    $arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans('Delete');
}

if (GETPOST('nomassaction', 'int')) {
    $arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="searchFormList">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" value="list">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="contextpage" value="activitylist">';
print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, $massactionbutton, 0, 0, 'fa-seedling', 0, $newcardbutton, '', $limit, 0, 0, 1);

$objecttmp = new SfActivity($db);
$topicmail = 'SendActivityRef';
$modelmail = 'activity';
$trackid = 'activity'.$objecttmp->id;
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

print '<tr class="liste_titre">';
if (!empty($arrayofmassactions)) {
    print '<th class="center nowraponall"><input type="checkbox" class="checkall" data-target="checkforselect"></th>';
}
print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 't.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Label'), $_SERVER['PHP_SELF'], 't.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Project'), $_SERVER['PHP_SELF'], 'project_label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('ThirdParty'), $_SERVER['PHP_SELF'], 'thirdparty_name', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('SafraActivityType'), $_SERVER['PHP_SELF'], 't.activity_type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Date'), $_SERVER['PHP_SELF'], 't.date_activity', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('SafraActivityLinesCount'), $_SERVER['PHP_SELF'], 'line_count', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('SafraActivityTotalQuantity'), $_SERVER['PHP_SELF'], 'total_qty', '', $param, '', $sortfield, $sortorder, 'right');
print_liste_field_titre($langs->trans('Status'), $_SERVER['PHP_SELF'], 't.status', '', $param, '', $sortfield, $sortorder, 'center');
print '</tr>';

print '<tr class="liste_titre">';
if (!empty($arrayofmassactions)) {
    print '<td></td>';
}
print '<td class="liste_titre"><input class="flat" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" size="8"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_label" value="'.dol_escape_htmltag($search_label).'" size="10"></td>';
print '<td class="liste_titre">'.$formproject->select_projects_list(-1, $search_project, 'search_fk_project', 0, 0, 1, 0, 0, 0, 0, '', 1, 0, 'search_fk_project', 'minwidth200', 1).'</td>';
print '<td class="liste_titre">'.$formcompany->select_company($search_soc, 'search_fk_soc', '', 1, 0, 0, array(), 0, 'minwidth200', '', 1, 1, array(), '', 0, '', '', 1).'</td>';
$typeList = SfActivity::getActivityTypeList($langs);
print '<td class="liste_titre">'.$form->selectarray('search_activity_type', array('' => '') + $typeList, $search_type, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150').'</td>';
print '<td class="liste_titre">';
print '<input class="flat" type="date" name="search_date_start" value="'.dol_escape_htmltag($search_date_start).'">';
print ' &raquo; ';
print '<input class="flat" type="date" name="search_date_end" value="'.dol_escape_htmltag($search_date_end).'">';
print '</td>';
print '<td class="liste_titre center"></td>';
$statusOptions = array('' => $langs->trans('All'));
$statusOptions[SfActivity::STATUS_DRAFT] = $langs->trans('Draft');
$statusOptions[SfActivity::STATUS_VALIDATED] = $langs->trans('SafraActivityStatusValidated');
$statusOptions[SfActivity::STATUS_IN_PROGRESS] = $langs->trans('SafraActivityStatusInProgress');
$statusOptions[SfActivity::STATUS_COMPLETED] = $langs->trans('SafraActivityStatusCompleted');
$statusOptions[SfActivity::STATUS_CANCELED] = $langs->trans('SafraActivityStatusCanceled');
print '<td class="liste_titre right"></td>';
print '<td class="liste_titre center">'.$form->selectarray('search_status', $statusOptions, ($search_status === '' ? '' : (int) $search_status), 0, 0, 0, '', 0, 0, 0, '', 'minwidth125').'</td>';
print '</tr>';

print '<tr class="liste_titre">';
if (!empty($arrayofmassactions)) {
    print '<td></td>';
}
print '<td colspan="9" class="right">';
print '<button type="submit" class="button" name="button_search" value="search">'.$langs->trans('Search').'</button>'; // @phpstan-ignore-line
print '&nbsp;';
print '<button type="submit" class="button button-cancel" name="button_removefilter" value="reset">'.$langs->trans('Reset').'</button>';
print '</td>';
print '</tr>';

$sql = 'SELECT t.rowid, t.ref, t.label, t.fk_project, t.fk_soc, t.activity_type, t.date_activity, t.qty, t.status, t.amount,'
    .' (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'safra_activity_line AS l WHERE l.fk_activity = t.rowid) AS line_count,'
    .' (SELECT SUM(l2.total_qty) FROM '.MAIN_DB_PREFIX.'safra_activity_line AS l2 WHERE l2.fk_activity = t.rowid) AS total_qty,'
    .' s.nom AS thirdparty_name, s.rowid AS thirdparty_id,'
    .' p.ref AS project_ref, p.title AS project_title'
    .' FROM '.MAIN_DB_PREFIX.'safra_activity AS t'
    .' LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = t.fk_soc'
    .' LEFT JOIN '.MAIN_DB_PREFIX.'projet AS p ON p.rowid = t.fk_project';
$sql .= ' WHERE t.entity IN ('.getEntity('safra_activity').')';
if ($search_ref !== '') {
    $sql .= natural_search('t.ref', $search_ref);
}
if ($search_label !== '') {
    $sql .= natural_search('t.label', $search_label);
}
if ($search_type !== '') {
    $sql .= natural_search('t.activity_type', $search_type, 2);
}
if ($search_project) {
    $sql .= ' AND t.fk_project = '.((int) $search_project);
}
if ($search_soc) {
    $sql .= ' AND t.fk_soc = '.((int) $search_soc);
}
if ($search_status !== '' && $search_status !== null) {
    $sql .= ' AND t.status = '.((int) $search_status);
}
if ($search_date_start !== '') {
    $ts = dol_stringtotime($search_date_start.' 00:00:00');
    if ($ts) {
        $sql .= " AND t.date_activity >= '".$db->idate($ts)."'";
    }
}
if ($search_date_end !== '') {
    $te = dol_stringtotime($search_date_end.' 23:59:59');
    if ($te) {
        $sql .= " AND t.date_activity <= '".$db->idate($te)."'";
    }
}

$countsql = preg_replace('/SELECT .* FROM/', 'SELECT COUNT(*) as nb FROM', $sql, 1);
$resqlcount = $db->query($countsql);
$nbtotalofrecords = 0;
if ($resqlcount) {
    $objcount = $db->fetch_object($resqlcount);
    if ($objcount) {
        $nbtotalofrecords = (int) $objcount->nb;
    }
    $db->free($resqlcount);
}

$sql .= $db->order($sortfield, $sortorder);
if ($limit > 0) {
    $sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
}

$num = $resql ? $db->num_rows($resql) : 0;
$activityStatic = new SfActivity($db);
$thirdpartyStatic = new Societe($db);
$projectStatic = new Project($db);

$i = 0;
while ($resql && $i < min($num, $limit)) {
    $obj = $db->fetch_object($resql);
    if (!$obj) {
        break;
    }
    $dateActivity = empty($obj->date_activity) ? '' : $db->jdate($obj->date_activity);
    $totalQty = isset($obj->total_qty) ? (float) $obj->total_qty : 0.0;
    $lineCount = (int) $obj->line_count;
    $activityStatic->id = $obj->rowid;
    $activityStatic->ref = $obj->ref;
    $activityStatic->status = (int) $obj->status;
    $activityStatic->activity_type = $obj->activity_type;
    $statusHtml = '<span class="activity-status-pill status-'.$activityStatic->status.'">'.img_picto('', 'status'.$activityStatic->status).$activityStatic->LibStatut($activityStatic->status, 1).'</span>';

    $rowClass = 'oddeven activity-status-row-'.$activityStatic->status;
    print '<tr class="'.$rowClass.'">';
    if (!empty($arrayofmassactions)) {
        print '<td class="center"><input class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$activityStatic->id.'"></td>';
    }
    $url = dol_buildpath('/safra/activity_card.php', 1).'?id='.$activityStatic->id;
    print '<td class="nowraponall"><a class="classfortooltip" href="'.$url.'">'.dol_escape_htmltag($activityStatic->ref).'</a></td>';
    print '<td>'.dol_escape_htmltag($obj->label).'</td>';

    print '<td>';
    if (!empty($obj->fk_project)) {
        $projectStatic->id = (int) $obj->fk_project;
        $projectStatic->ref = $obj->project_ref;
        $projectStatic->title = $obj->project_title;
        print $projectStatic->getNomUrl(1);
    }
    print '</td>';

    print '<td>';
    if (!empty($obj->thirdparty_id)) {
        $thirdpartyStatic->id = (int) $obj->thirdparty_id;
        $thirdpartyStatic->name = $obj->thirdparty_name;
        print $thirdpartyStatic->getNomUrl(1);
    }
    print '</td>';

    $typeLabel = SfActivity::getActivityTypeLabel($obj->activity_type, $langs);
    print '<td>'.dol_escape_htmltag($typeLabel).'</td>';
    print '<td class="nowraponall">'.($dateActivity ? dol_print_date($dateActivity, 'day') : '').'</td>';
    print '<td class="center">'.$lineCount.'</td>';
    print '<td class="right">'.dol_print_decimal($totalQty, 2).'</td>';
    print '<td class="center">'.$statusHtml.'</td>';
    print '</tr>';
    $i++;
}

if ($num == 0) {
    $colspan = 9 + (!empty($arrayofmassactions) ? 1 : 0);
    print '<tr><td colspan="'.$colspan.'" class="center">'.$langs->trans('None').'</td></tr>';
}

print '</table>';
print '</div>';

print '</form>';

if ($resql) {
    $db->free($resql);
}

llxFooter();
$db->close();
