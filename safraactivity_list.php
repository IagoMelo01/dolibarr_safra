<?php
/* Copyright (C) 2024 Farmevo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
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

require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/safra/class/talhao.class.php';
require_once __DIR__ . '/class/FvActivity.class.php';

global $db, $user, $langs, $hookmanager, $conf;

$langs->loadLangs(array('safra@safra', 'projects'));

$action = GETPOST('action', 'aZ09') ?: 'view';
$sortfield = GETPOST('sortfield', 'alpha') ?: 't.date_creation';
$sortorder = GETPOST('sortorder', 'alpha') ?: 'DESC';
$page = max(0, GETPOSTINT('page'));
$limit = GETPOSTINT('limit') ?: $conf->liste_limit;
$offset = $limit * $page;

$search_ref = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_project = GETPOST('search_project', 'alpha');
$search_fieldplot = GETPOST('search_fieldplot', 'alpha');
$search_type = GETPOST('search_type', 'alpha');
$search_status = GETPOSTINT('search_status');

$object = new FvActivity($db);
$form = new Form($db);
$formother = new FormOther($db);

$permissiontoread = $user->rights->safra->SafraActivity->read ?? 0;
if (!$permissiontoread) {
    accessforbidden();
}

$hookmanager->initHooks(array('safraactivitylist'));

$param = '';
foreach (array('search_ref', 'search_label', 'search_project', 'search_fieldplot', 'search_type', 'search_status', 'limit') as $key) {
    if (GETPOST($key, 'alpha') !== '') {
        $param .= '&' . $key . '=' . urlencode(GETPOST($key, 'alpha'));
    }
}

$sql = 'SELECT t.rowid, t.ref, t.label, t.fk_project, t.fk_fieldplot, t.type, t.status, t.area_total, t.date_creation, t.tms';
$sql .= ', p.ref as project_ref, p.title as project_title, tp.label as fieldplot_label';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'safra_activity as t';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet as p ON t.fk_project = p.rowid';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'safra_talhao as tp ON t.fk_fieldplot = tp.rowid';
$sql .= ' WHERE t.entity IN (' . getEntity('safra_activity') . ')';
if ($search_ref !== '') {
    $sql .= natural_search('t.ref', $search_ref);
}
if ($search_label !== '') {
    $sql .= natural_search('t.label', $search_label);
}
if ($search_project !== '') {
    $sql .= natural_search('p.ref', $search_project);
}
if ($search_fieldplot !== '') {
    $sql .= natural_search('tp.label', $search_fieldplot);
}
if ($search_type !== '') {
    $sql .= natural_search('t.type', $search_type);
}
if ($search_status !== '') {
    $sql .= ' AND t.status = ' . ((int) $search_status);
}

$sql .= $db->order($sortfield, $sortorder);
$nbtotalofrecords = 0;
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
    $resql = $db->query($sql);
    if ($resql) {
        $nbtotalofrecords = $db->num_rows($resql);
    }
}
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}

$num = $db->num_rows($resql);
$moreforfilter = '';

llxHeader('', $langs->trans('SafraActivityListTitle'), '');

print load_fiche_titre($langs->trans('SafraActivityListTitle'), '', 'safra@safra');

$massactionbutton = '';
if ($user->rights->safra->SafraActivity->write ?? 0) {
    $massactionbutton = $form->selectMassAction('', array());
}

$varpage = $_SERVER['PHP_SELF'];
print '<form method="GET" action="' . $varpage . '" name="formfilter">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('Ref'), $varpage, 't.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Label'), $varpage, 't.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Project'), $varpage, 'p.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('FieldPlot'), $varpage, 'tp.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('SafraActivityType'), $varpage, 't.type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Status'), $varpage, 't.status', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('DateCreation'), $varpage, 't.date_creation', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('', $varpage, '', '', $param, '', $sortfield, $sortorder, 'right ');
print '</tr>';

print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input class="flat" type="text" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '" size="8"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_label" value="' . dol_escape_htmltag($search_label) . '" size="12"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_project" value="' . dol_escape_htmltag($search_project) . '" size="10"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_fieldplot" value="' . dol_escape_htmltag($search_fieldplot) . '" size="10"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_type" value="' . dol_escape_htmltag($search_type) . '" size="8"></td>';
print '<td class="liste_titre">';
print $form->selectarray('search_status', array(
    '' => '',
    FvActivity::STATUS_DRAFT => $langs->trans('Draft'),
    FvActivity::STATUS_COMPLETED => $langs->trans('SafraActivityStatusCompleted'),
    FvActivity::STATUS_CANCELED => $langs->trans('SafraActivityStatusCanceled'),
), $search_status, 0, 0, 0, '', 0, 0, 0, '', '', 1);
print '</td>';
print '<td class="liste_titre center">';
print '</td>';
print '<td class="liste_titre right">';
print $form->showFilterButtons();
print '</td>';
print '</tr>';

$activities = array();
while ($obj = $db->fetch_object($resql)) {
    $activities[] = $obj;
}

foreach ($activities as $i => $obj) {
    $var = ($i % 2) ? 'class="oddeven"' : 'class="oddeven"';
    print '<tr ' . $var . '>';

    $activityLink = '<a href="' . dol_buildpath('/safra/safraactivity_card.php', 1) . '?id=' . ((int) $obj->rowid) . '">' . dol_escape_htmltag($obj->ref ?: $obj->rowid) . '</a>';
    print '<td>' . $activityLink . '</td>';

    print '<td>' . dol_escape_htmltag($obj->label) . '</td>';

    $projectLink = '';
    if (!empty($obj->fk_project)) {
        $projectLink = '<a href="' . dol_buildpath('/projet/card.php', 1) . '?id=' . ((int) $obj->fk_project) . '">' . dol_escape_htmltag($obj->project_ref ?: $obj->project_title) . '</a>';
    }
    print '<td>' . $projectLink . '</td>';

    print '<td>' . dol_escape_htmltag($obj->fieldplot_label) . '</td>';

    print '<td>' . dol_escape_htmltag($obj->type) . '</td>';

    $statusLabel = $langs->trans('Draft');
    if ((int) $obj->status === FvActivity::STATUS_COMPLETED) {
        $statusLabel = $langs->trans('SafraActivityStatusCompleted');
    } elseif ((int) $obj->status === FvActivity::STATUS_CANCELED) {
        $statusLabel = $langs->trans('SafraActivityStatusCanceled');
    }
    print '<td>' . $statusLabel . '</td>';

    print '<td class="center">' . dol_print_date($db->jdate($obj->date_creation), 'dayhour') . '</td>';

    print '<td class="right">';
    print '<a class="btn btn-sm btn-secondary" href="' . dol_buildpath('/safra/safraactivity_card.php', 1) . '?id=' . ((int) $obj->rowid) . '">' . $langs->trans('Card') . '</a>';
    print '</td>';

    print '</tr>';
}

print '</table>';
print '</div>';
print '</form>';

print '<div class="pagination">';
print_pagination($page, $varpage, $param, $num, $nbtotalofrecords, $limit);
print '</div>';

dol_fiche_end();
llxFooter();
$db->close();
