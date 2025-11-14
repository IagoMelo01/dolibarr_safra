<?php
/*
 * Copyright (C) 2024 SuperAdmin
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
 *      \file       safraactivity_list.php
 *      \ingroup    safra
 *      \brief      List page for Safra activities
 */

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
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/price.lib.php';

require_once __DIR__ . '/class/safraactivity.class.php';
require_once __DIR__ . '/class/talhao.class.php';

/**
 * Ensure the activity extrafield infrastructure exists so progress can be stored.
 */
function safraEnsureActivityProgressInfrastructure($db, SafraActivity $activity, ExtraFields $extrafields)
{
        static $ensured = false;
        if ($ensured) {
                return;
        }
        $ensured = true;

        global $conf;

        $extrafieldTable = $db->prefix() . $activity->table_element . '_extrafields';
        $resql = $db->query('SHOW TABLES LIKE "' . $db->escape($extrafieldTable) . '"');
        $tableExists = $resql && $db->num_rows($resql) > 0;
        if (!$tableExists) {
                $sql = "CREATE TABLE IF NOT EXISTS `" . $extrafieldTable . "` (
                        rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
                        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        fk_object INTEGER NOT NULL,
                        import_key VARCHAR(14)
                ) ENGINE=innodb";
                if ($db->query($sql)) {
                        $db->query("ALTER TABLE `" . $extrafieldTable . "` ADD INDEX idx_safra_activity_extrafields_fk_object(fk_object)");
                } else {
                        dol_syslog('safraEnsureActivityProgressInfrastructure failed to create ' . $extrafieldTable, LOG_ERR);
                }
        }

        $sqlExtra = "SELECT rowid FROM " . $db->prefix() . "extrafields WHERE elementtype = '" . $db->escape($activity->table_element) . "' AND name = 'options_progress'";
        if (!empty($conf->entity)) {
                $sqlExtra .= " AND entity IN (0, " . ((int) $conf->entity) . ")";
        }
        $resExtra = $db->query($sqlExtra);
        if ($resExtra && $db->num_rows($resExtra) === 0) {
                $extrafields->addExtraField(
                        'options_progress',
                        'SafraActivityProgress',
                        'double',
                        110,
                        '24,8',
                        $activity->table_element,
                        0,
                        0,
                        '0',
                        '',
                        1,
                        'isModEnabled("safra")',
                        1,
                        '',
                        '',
                        '',
                        'safra@safra',
                        'isModEnabled("safra")',
                        0,
                        0,
                        array()
                );
        }
}

$langs->loadLangs(array('safra@safra', 'projects'));

$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$show_files = GETPOST('show_files', 'int');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'safraactivitylist';
$backtopage = GETPOST('backtopage', 'alpha');
$optioncss = GETPOST('optioncss', 'aZ');
$mode = GETPOST('mode', 'aZ');

$search_ref = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_fk_project = GETPOST('search_fk_project', 'int');
$search_fk_talhao = GETPOST('search_fk_talhao', 'int');
$search_activity_type = GETPOST('search_activity_type', 'alpha');
$search_status = GETPOST('search_status', 'int');
$search_overdue = GETPOST('search_overdue', 'alpha');

if (GETPOST('button_removefilter', 'alpha')) {
        $search_ref = '';
        $search_label = '';
        $search_fk_project = '';
        $search_fk_talhao = '';
        $search_activity_type = '';
        $search_status = '';
        $search_overdue = '';
}

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0) {
        $page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (!$sortfield) {
        $sortfield = 't.date_planned_start';
}
if (!$sortorder) {
        $sortorder = 'ASC';
}

$object = new SafraActivity($db);
$form = new Form($db);
$formother = new FormOther($db);
$extrafields = new ExtraFields($db);
safraEnsureActivityProgressInfrastructure($db, $object, $extrafields);
$hookmanager->initHooks(array('safraactivitylist', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

if (!isModEnabled('safra')) {
        accessforbidden();
}

$permissiontoread = 1;
$permissiontoadd = 1;
$permissiontodelete = 1;

$now = dol_now();

$arrayfields = array(
        't.ref' => array('label' => 'Ref', 'checked' => 1, 'position' => 1),
        't.label' => array('label' => 'Label', 'checked' => 1, 'position' => 2),
        'project' => array('label' => 'Project', 'checked' => 1, 'position' => 3),
        'talhao' => array('label' => 'SafraTalhao', 'checked' => 1, 'position' => 4),
        't.activity_type' => array('label' => 'SafraActivityType', 'checked' => 1, 'position' => 5),
        't.date_planned_start' => array('label' => 'SafraActivityDatePlannedStart', 'checked' => 1, 'position' => 6),
        't.date_planned_end' => array('label' => 'SafraActivityDatePlannedEnd', 'checked' => 1, 'position' => 7),
        'progress' => array('label' => 'Progress', 'checked' => 1, 'position' => 8),
        'status' => array('label' => 'Status', 'checked' => 1, 'position' => 9),
        'overdue' => array('label' => 'SafraActivityFilterOverdue', 'checked' => 1, 'position' => 10),
        't.planned_cost' => array('label' => 'SafraActivityPlannedCost', 'checked' => 0, 'position' => 11),
        't.actual_cost' => array('label' => 'SafraActivityActualCost', 'checked' => 0, 'position' => 12),
);

$parameters = array('arrayfields' => $arrayfields);
$hookmanager->executeHooks('doActions', $parameters, $object, $action);

$types = array('' => '');
$sqlTypes = "SELECT DISTINCT activity_type FROM " . MAIN_DB_PREFIX . "safra_activity WHERE activity_type IS NOT NULL AND activity_type <> '' ORDER BY activity_type";
$resqlTypes = $db->query($sqlTypes);
if ($resqlTypes) {
        while ($objType = $db->fetch_object($resqlTypes)) {
                $label = trim($objType->activity_type);
                if ($label === '') {
                        continue;
                }
                $types[$label] = $label;
        }
        $db->free($resqlTypes);
}

$projectOptions = array('' => '');
$sqlProjects = "SELECT rowid, ref, title FROM " . MAIN_DB_PREFIX . "projet WHERE entity IN (" . getEntity('project') . ") ORDER BY ref";
$resqlProjects = $db->query($sqlProjects);
if ($resqlProjects) {
        while ($objProj = $db->fetch_object($resqlProjects)) {
                $text = dol_trunc($objProj->ref . ' - ' . $objProj->title, 60);
                $projectOptions[$objProj->rowid] = $text;
        }
        $db->free($resqlProjects);
}

$talhaoOptions = array('' => '');
$sqlTalhao = "SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "safra_talhao WHERE entity IN (" . getEntity('safra_talhao') . ") ORDER BY ref";
$resqlTalhao = $db->query($sqlTalhao);
if ($resqlTalhao) {
        while ($objTalhao = $db->fetch_object($resqlTalhao)) {
                $text = dol_trunc($objTalhao->ref . ' - ' . $objTalhao->label, 60);
                $talhaoOptions[$objTalhao->rowid] = $text;
        }
        $db->free($resqlTalhao);
}

$statusOptions = array('' => '');
if (!empty($object->fields['status']['arrayofkeyval'])) {
        foreach ($object->fields['status']['arrayofkeyval'] as $key => $label) {
                $statusOptions[$key] = $label;
        }
}

$overdueOptions = array(
        '' => '',
        'late' => $langs->trans('SafraActivityFilterOverdueLate'),
        'ontime' => $langs->trans('SafraActivityFilterOverdueOnTime'),
);

$sql = "SELECT t.rowid, t.ref, t.label, t.fk_project, t.fk_talhao, t.activity_type, t.date_planned_start, t.date_planned_end, t.date_real_start, t.date_real_end, t.status, ex.options_progress as progress, t.planned_cost, t.actual_cost, ";
$sql .= " p.ref as project_ref, p.title as project_title, ta.ref as talhao_ref, ta.label as talhao_label";
$sql .= " FROM " . MAIN_DB_PREFIX . "safra_activity as t";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "projet as p ON t.fk_project = p.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "safra_talhao as ta ON t.fk_talhao = ta.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "safra_activity_extrafields as ex ON ex.fk_object = t.rowid";
$sql .= " WHERE t.entity IN (" . getEntity('safra_activity') . ")";

if (!empty($search_ref)) {
        $sql .= natural_search("t.ref", $search_ref);
}
if (!empty($search_label)) {
        $sql .= natural_search("t.label", $search_label);
}
if (!empty($search_fk_project)) {
        $sql .= ' AND t.fk_project = ' . ((int) $search_fk_project);
}
if (!empty($search_fk_talhao)) {
        $sql .= ' AND t.fk_talhao = ' . ((int) $search_fk_talhao);
}
if (!empty($search_activity_type)) {
        $sql .= natural_search('t.activity_type', $search_activity_type);
}
if ($search_status !== '' && $search_status !== null) {
        $sql .= ' AND t.status = ' . ((int) $search_status);
}
if (!empty($search_overdue)) {
        if ($search_overdue === 'late') {
                $sql .= ' AND t.date_planned_end IS NOT NULL AND t.date_planned_end < ' . $db->idate($now);
                $sql .= ' AND t.status NOT IN (' . SafraActivity::STATUS_COMPLETED . ', ' . SafraActivity::STATUS_CANCELED . ')';
        } elseif ($search_overdue === 'ontime') {
                $sql .= ' AND (t.date_planned_end IS NULL OR t.date_planned_end >= ' . $db->idate($now) . ')';
        }
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
        dol_print_error($db);
        exit;
}

$num = $db->num_rows($resql);
if (($page * $limit) > $num && $page > 0) {
        $page = 0;
        $offset = 0;
}

$title = $langs->trans('SafraActivityListTitle');
llxHeader('', $title);

$param = '';
if (!empty($search_ref)) {
        $param .= '&search_ref=' . urlencode($search_ref);
}
if (!empty($search_label)) {
        $param .= '&search_label=' . urlencode($search_label);
}
if (!empty($search_fk_project)) {
        $param .= '&search_fk_project=' . ((int) $search_fk_project);
}
if (!empty($search_fk_talhao)) {
        $param .= '&search_fk_talhao=' . ((int) $search_fk_talhao);
}
if (!empty($search_activity_type)) {
        $param .= '&search_activity_type=' . urlencode($search_activity_type);
}
if ($search_status !== '' && $search_status !== null) {
        $param .= '&search_status=' . ((int) $search_status);
}
if (!empty($search_overdue)) {
        $param .= '&search_overdue=' . urlencode($search_overdue);
}

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="formfilteraction" value="list">';
print '<input type="hidden" name="sortfield" value="' . dol_escape_htmltag($sortfield) . '">';
print '<input type="hidden" name="sortorder" value="' . dol_escape_htmltag($sortorder) . '">';
print '<input type="hidden" name="page" value="' . ((int) $page) . '">';

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, '', 'title_generic');

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
foreach ($arrayfields as $key => $val) {
        if (empty($val['checked'])) {
                continue;
        }
        print '<th class="liste_titre">' . $langs->trans($val['label']) . '</th>';
}
print '</tr>';

print '<tr class="liste_titre">';
if (!empty($arrayfields['t.ref']['checked'])) {
        print '<td class="liste_titre"><input type="text" class="flat" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '"></td>';
}
if (!empty($arrayfields['t.label']['checked'])) {
        print '<td class="liste_titre"><input type="text" class="flat" name="search_label" value="' . dol_escape_htmltag($search_label) . '"></td>';
}
if (!empty($arrayfields['project']['checked'])) {
        print '<td class="liste_titre">' . $form->selectarray('search_fk_project', $projectOptions, $search_fk_project, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth300') . '</td>';
}
if (!empty($arrayfields['talhao']['checked'])) {
        print '<td class="liste_titre">' . $form->selectarray('search_fk_talhao', $talhaoOptions, $search_fk_talhao, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth300') . '</td>';
}
if (!empty($arrayfields['t.activity_type']['checked'])) {
        print '<td class="liste_titre">' . $form->selectarray('search_activity_type', $types, $search_activity_type, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth200') . '</td>';
}
if (!empty($arrayfields['t.date_planned_start']['checked'])) {
        print '<td class="liste_titre"></td>';
}
if (!empty($arrayfields['t.date_planned_end']['checked'])) {
        print '<td class="liste_titre"></td>';
}
if (!empty($arrayfields['progress']['checked'])) {
        print '<td class="liste_titre"></td>';
}
if (!empty($arrayfields['status']['checked'])) {
        print '<td class="liste_titre">' . $form->selectarray('search_status', $statusOptions, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150') . '</td>';
}
if (!empty($arrayfields['overdue']['checked'])) {
        print '<td class="liste_titre">' . $form->selectarray('search_overdue', $overdueOptions, $search_overdue, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150') . '</td>';
}
if (!empty($arrayfields['t.planned_cost']['checked'])) {
        print '<td class="liste_titre"></td>';
}
if (!empty($arrayfields['t.actual_cost']['checked'])) {
        print '<td class="liste_titre"></td>';
}
print '</tr>';

$shown = 0;
$objectstatic = new SafraActivity($db);

for ($i = 0; $i < min($num, $limit); $i++) {
        $obj = $db->fetch_object($resql);
        if (!$obj) {
                break;
        }

        $isOverdue = 0;
        if (!empty($obj->date_planned_end)) {
                $plannedEnd = $db->jdate($obj->date_planned_end);
                if ($plannedEnd && $plannedEnd < $now && !in_array((int) $obj->status, array(SafraActivity::STATUS_COMPLETED, SafraActivity::STATUS_CANCELED), true)) {
                        $isOverdue = 1;
                }
        }

        print '<tr class="oddeven">';

        if (!empty($arrayfields['t.ref']['checked'])) {
                $objectstatic->id = $obj->rowid;
                $objectstatic->ref = $obj->ref;
                $objectstatic->status = (int) $obj->status;
                print '<td>' . $objectstatic->getNomUrl(1) . '</td>';
        }
        if (!empty($arrayfields['t.label']['checked'])) {
                print '<td>' . dol_escape_htmltag($obj->label) . '</td>';
        }
        if (!empty($arrayfields['project']['checked'])) {
                if (!empty($obj->fk_project)) {
                        $project = new Project($db);
                        $project->id = $obj->fk_project;
                        $project->ref = $obj->project_ref;
                        $project->title = $obj->project_title;
                        print '<td>' . $project->getNomUrl(1) . '</td>';
                } else {
                        print '<td class="opacitymedium">&mdash;</td>';
                }
        }
        if (!empty($arrayfields['talhao']['checked'])) {
                if (!empty($obj->fk_talhao)) {
                        $talhao = new Talhao($db);
                        $talhao->id = $obj->fk_talhao;
                        $talhao->ref = $obj->talhao_ref;
                        $talhao->label = $obj->talhao_label;
                        print '<td>' . $talhao->getNomUrl(1) . '</td>';
                } else {
                        print '<td class="opacitymedium">&mdash;</td>';
                }
        }
        if (!empty($arrayfields['t.activity_type']['checked'])) {
                print '<td>' . dol_escape_htmltag($obj->activity_type) . '</td>';
        }
        if (!empty($arrayfields['t.date_planned_start']['checked'])) {
                print '<td>' . (!empty($obj->date_planned_start) ? dol_print_date($db->jdate($obj->date_planned_start), 'dayhour') : '&mdash;') . '</td>';
        }
        if (!empty($arrayfields['t.date_planned_end']['checked'])) {
                print '<td>' . (!empty($obj->date_planned_end) ? dol_print_date($db->jdate($obj->date_planned_end), 'dayhour') : '&mdash;') . '</td>';
        }
        if (!empty($arrayfields['progress']['checked'])) {
                $progress = isset($obj->progress) ? (float) $obj->progress : 0.0;
                print '<td>' . round($progress, 1) . '%</td>';
        }
        if (!empty($arrayfields['status']['checked'])) {
                $objectstatic->status = (int) $obj->status;
                print '<td>' . $objectstatic->getLibStatut(5) . '</td>';
        }
        if (!empty($arrayfields['overdue']['checked'])) {
                if ($isOverdue) {
                        print '<td class="error">' . img_warning('', 'class="pictofixedwidth"') . $langs->trans('SafraActivityFilterOverdueLate') . '</td>';
                } else {
                        print '<td class="ok">' . $langs->trans('SafraActivityFilterOverdueOnTime') . '</td>';
                }
        }
        if (!empty($arrayfields['t.planned_cost']['checked'])) {
                print '<td class="right">' . price($obj->planned_cost) . '</td>';
        }
        if (!empty($arrayfields['t.actual_cost']['checked'])) {
                print '<td class="right">' . price($obj->actual_cost) . '</td>';
        }

        print '</tr>';
        $shown++;
}

if ($shown === 0) {
        print '<tr class="oddeven"><td colspan="' . count(array_filter(array_column($arrayfields, 'checked'))) . '" class="opacitymedium center">' . $langs->trans('NoRecordFound') . '</td></tr>';
}

print '<tr class="liste_titre">';
print '<td colspan="' . count(array_filter(array_column($arrayfields, 'checked'))) . '">';
print '<div class="center">';
print '<input type="submit" class="button" name="button_search" value="' . dol_escape_htmltag($langs->trans('Search')) . '">';
print '&nbsp;';
print '<input type="submit" class="button" name="button_removefilter" value="' . dol_escape_htmltag($langs->trans('RemoveFilter')) . '">';
print '</div>';
print '</td>';
print '</tr>';

print '</table>';
print '</div>';
print '</form>';

$db->free($resql);

llxFooter();
$db->close();

