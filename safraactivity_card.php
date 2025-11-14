<?php
/*
 * Copyright (C) 2024 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
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
 *    \file       safraactivity_card.php
 *    \ingroup    safra
 *    \brief      Page to create/edit/view Safra activities
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

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/price.lib.php';

require_once __DIR__ . '/class/safraactivity.class.php';
require_once __DIR__ . '/class/talhao.class.php';
dol_include_once('/safra/lib/safra_activity.lib.php');

dol_include_once('/project/class/project.class.php');

dol_include_once('/core/class/extraFields.class.php');

$langs->loadLangs(array('safra@safra', 'projects', 'companies'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$token = GETPOST('token', 'alpha');

$object = new SafraActivity($db);
$extrafields = new ExtraFields($db);
$form = new Form($db);
$formother = new FormOther($db);
$formproject = new FormProjets($db);
$formfile = new FormFile($db);
$formproduct = new FormProduct($db);
$hookmanager->initHooks(array('safraactivitycard', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

if (!isModEnabled('safra')) {
        accessforbidden();
}

$permissiontoread = 1;
$permissiontoadd = 1;
$permissiontodelete = 1;
$permissionnote = 1;

if (empty($action) && empty($id) && empty($ref)) {
        $action = 'view';
}

if (($id > 0 || !empty($ref)) && $action !== 'create' && $action !== 'add') {
        $result = $object->fetch($id, $ref);
        if ($result <= 0) {
                setEventMessages($langs->trans('ErrorSafraActivityNotFound', $ref ? $ref : $id), $object->errors, 'errors');
                $action = 'create';
                $id = 0;
        }
}

if ($object->id > 0) {
        $id = $object->id;
}

$now = dol_now();

$hookmanager->executeHooks('doActions', array(), $object, $action);

if ($action == 'add' && $permissiontoadd) {
        if (!checkToken()) {
                accessforbidden('Invalid token');
        }

        $error = 0;
        $object->ref = trim(GETPOST('ref', 'alphanohtml'));
        $object->label = trim(GETPOST('label', 'alphanohtml'));
        $object->fk_project = GETPOST('fk_project', 'int');
        $object->fk_talhao = GETPOST('fk_talhao', 'int');
        $object->activity_type = trim(GETPOST('activity_type', 'alpha'));
        $object->date_planned_start = dol_mktime(GETPOST('date_planned_starthour', 'int'), GETPOST('date_planned_startmin', 'int'), 0, GETPOST('date_planned_startmonth', 'int'), GETPOST('date_planned_startday', 'int'), GETPOST('date_planned_startyear', 'int'));
        $object->date_planned_end = dol_mktime(GETPOST('date_planned_endhour', 'int'), GETPOST('date_planned_endmin', 'int'), 0, GETPOST('date_planned_endmonth', 'int'), GETPOST('date_planned_endday', 'int'), GETPOST('date_planned_endyear', 'int'));
        $object->note_public = GETPOST('note_public', 'restricthtml');
        $object->note_private = GETPOST('note_private', 'restricthtml');
        $object->status = SafraActivity::STATUS_DRAFT;

        $extrafields->setOptionalsFromPost($object);

        if (empty($object->ref)) {
                $error++;
                setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Ref')), null, 'errors');
        }

        if (!$error) {
                $result = $object->create($user);
                if ($result > 0) {
                        $id = $object->id;
                        setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
                        exit;
                } else {
                        setEventMessages($object->error, $object->errors, 'errors');
                        $action = 'create';
                }
        } else {
                $action = 'create';
        }
}

if ($action == 'update' && $permissiontoadd && $object->id > 0) {
        if (!checkToken()) {
                accessforbidden('Invalid token');
        }

        $object->ref = trim(GETPOST('ref', 'alphanohtml'));
        $object->label = trim(GETPOST('label', 'alphanohtml'));
        $object->fk_project = GETPOST('fk_project', 'int');
        $object->fk_talhao = GETPOST('fk_talhao', 'int');
        $object->activity_type = trim(GETPOST('activity_type', 'alpha'));
        $object->date_planned_start = dol_mktime(GETPOST('date_planned_starthour', 'int'), GETPOST('date_planned_startmin', 'int'), 0, GETPOST('date_planned_startmonth', 'int'), GETPOST('date_planned_startday', 'int'), GETPOST('date_planned_startyear', 'int'));
        $object->date_planned_end = dol_mktime(GETPOST('date_planned_endhour', 'int'), GETPOST('date_planned_endmin', 'int'), 0, GETPOST('date_planned_endmonth', 'int'), GETPOST('date_planned_endday', 'int'), GETPOST('date_planned_endyear', 'int'));
        $object->date_real_start = dol_mktime(GETPOST('date_real_starthour', 'int'), GETPOST('date_real_startmin', 'int'), 0, GETPOST('date_real_startmonth', 'int'), GETPOST('date_real_startday', 'int'), GETPOST('date_real_startyear', 'int'));
        $object->date_real_end = dol_mktime(GETPOST('date_real_endhour', 'int'), GETPOST('date_real_endmin', 'int'), 0, GETPOST('date_real_endmonth', 'int'), GETPOST('date_real_endday', 'int'), GETPOST('date_real_endyear', 'int'));
        $object->note_public = GETPOST('note_public', 'restricthtml');
        $object->note_private = GETPOST('note_private', 'restricthtml');

        $extrafields->setOptionalsFromPost($object);

        $result = $object->update($object->id, $user);
        if ($result > 0) {
                setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
                exit;
        } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = 'edit';
        }
}

if ($action == 'save_inputs' && $permissiontoadd && $object->id > 0) {
        if (!checkToken()) {
                accessforbidden('Invalid token');
        }

        $entries = isset($_POST['inputs']) && is_array($_POST['inputs']) ? $_POST['inputs'] : array();
        $lines = array();
        foreach ($entries as $entry) {
                if (!is_array($entry)) {
                        continue;
                }
                $fkProduct = isset($entry['fk_product']) ? (int) $entry['fk_product'] : 0;
                $label = isset($entry['label']) ? trim((string) $entry['label']) : '';
                $qty = isset($entry['qty']) && $entry['qty'] !== '' ? price2num($entry['qty'], 'MS') : null;
                if ($fkProduct <= 0 && $label === '') {
                        continue;
                }
                if ($qty === null) {
                        continue;
                }
                $line = array(
                        'fk_product' => $fkProduct > 0 ? $fkProduct : null,
                        'label' => $label,
                        'qty' => $qty,
                        'unit' => isset($entry['unit']) ? trim((string) $entry['unit']) : '',
                        'fk_unit' => isset($entry['fk_unit']) ? (int) $entry['fk_unit'] : null,
                        'fk_warehouse' => isset($entry['fk_warehouse']) ? (int) $entry['fk_warehouse'] : null,
                        'movement_type' => isset($entry['movement_type']) ? trim((string) $entry['movement_type']) : SafraActivity::MOVEMENT_CONSUME,
                        'note' => isset($entry['note']) ? trim((string) $entry['note']) : '',
                );
                $lines[] = $line;
        }
        $result = $object->addInputLines($user, $lines, true);
        if ($result >= 0) {
                        setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
                        exit;
        } else {
                setEventMessages($object->error, $object->errors, 'errors');
        }
}

if ($action == 'save_fleet' && $permissiontoadd && $object->id > 0) {
        if (!checkToken()) {
                accessforbidden('Invalid token');
        }
        $entries = isset($_POST['fleet']) && is_array($_POST['fleet']) ? $_POST['fleet'] : array();
        $resources = array();
        foreach ($entries as $entry) {
                if (!is_array($entry)) {
                        continue;
                }
                $equipment = isset($entry['fk_fleet_equipment']) ? (int) $entry['fk_fleet_equipment'] : 0;
                if ($equipment <= 0) {
                        continue;
                }
                $resources[] = array(
                        'fk_fleet_equipment' => $equipment,
                        'resource_type' => isset($entry['resource_type']) ? trim((string) $entry['resource_type']) : 'vehicle',
                        'planned_hours' => isset($entry['planned_hours']) && $entry['planned_hours'] !== '' ? price2num($entry['planned_hours'], 'MS') : null,
                        'fk_user_responsible' => isset($entry['fk_user_responsible']) ? (int) $entry['fk_user_responsible'] : null,
                        'note' => isset($entry['note']) ? trim((string) $entry['note']) : '',
                );
        }
        $result = $object->replaceFleetResources($user, $resources);
        if ($result >= 0) {
                setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
                exit;
        } else {
                setEventMessages($object->error, $object->errors, 'errors');
        }
}

if ($action == 'save_team' && $permissiontoadd && $object->id > 0) {
        if (!checkToken()) {
                accessforbidden('Invalid token');
        }
        $entries = isset($_POST['team']) && is_array($_POST['team']) ? $_POST['team'] : array();
        $members = array();
        foreach ($entries as $entry) {
                if (!is_array($entry)) {
                        continue;
                }
                $userId = isset($entry['fk_user']) ? (int) $entry['fk_user'] : 0;
                if ($userId <= 0) {
                        continue;
                }
                $members[] = array(
                        'fk_user' => $userId,
                        'planned_hours' => isset($entry['planned_hours']) && $entry['planned_hours'] !== '' ? price2num($entry['planned_hours'], 'MS') : null,
                        'is_responsible' => !empty($entry['is_responsible']) ? 1 : 0,
                        'note' => isset($entry['note']) ? trim((string) $entry['note']) : '',
                );
        }
        $result = $object->replaceTeamMembers($user, $members);
        if ($result >= 0) {
                setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
                exit;
        } else {
                setEventMessages($object->error, $object->errors, 'errors');
        }
}

if (in_array($action, array('validate', 'start', 'complete', 'cancel', 'reopen'), true) && $object->id > 0) {
        if (!checkToken()) {
                accessforbidden('Invalid token');
        }
        $result = 0;
        switch ($action) {
                case 'validate':
                        $result = $object->validate($user);
                        break;
                case 'start':
                        $result = $object->start($user);
                        break;
                case 'complete':
                        $result = $object->complete($user);
                        break;
                case 'cancel':
                        $result = $object->cancel($user);
                        break;
                case 'reopen':
                        $result = $object->reopen($user);
                        break;
        }
        if ($result >= 0) {
                setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
                exit;
        } else {
                setEventMessages($object->error, $object->errors, 'errors');
        }
}

$title = $langs->trans('SafraActivityListTitle');
if ($object->id > 0) {
        $title = $langs->trans('SafraActivityCardTitle', $object->ref);
}

llxHeader('', $title);

if ($action == 'create') {
        print load_fiche_titre($langs->trans('SafraActivityListTitle'));
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="add">';

        $projectOptions = array('0' => '');
        $resProject = $db->query('SELECT rowid, ref, title FROM ' . MAIN_DB_PREFIX . 'projet WHERE entity IN (' . getEntity('project') . ') ORDER BY ref');
        if ($resProject) {
                while ($objProject = $db->fetch_object($resProject)) {
                        $label = $objProject->ref;
                        if (!empty($objProject->title)) {
                                $label .= ' - ' . $objProject->title;
                        }
                        $projectOptions[$objProject->rowid] = dol_trunc($label, 60);
                }
                $db->free($resProject);
        }

        print '<table class="border centpercent tableforfield">';
        print '<tr><td class="titlefieldcreate">' . $langs->trans('Ref') . '</td><td><input type="text" class="minwidth200" name="ref" value="' . dol_escape_htmltag(GETPOST('ref', 'alpha')) . '"></td></tr>';
        print '<tr><td>' . $langs->trans('Label') . '</td><td><input type="text" class="minwidth300" name="label" value="' . dol_escape_htmltag(GETPOST('label', 'alphanohtml')) . '"></td></tr>';
        print '<tr><td>' . $langs->trans('Project') . '</td><td>' . $form->selectarray('fk_project', $projectOptions, GETPOST('fk_project', 'int'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth300') . '</td></tr>';
        $talhaoOptions = array('0' => '');
        $resTalhao = $db->query('SELECT rowid, ref, label FROM ' . MAIN_DB_PREFIX . 'safra_talhao WHERE entity IN (' . getEntity('safra_talhao') . ') ORDER BY ref');
        if ($resTalhao) {
                while ($objTalhao = $db->fetch_object($resTalhao)) {
                        $talhaoOptions[$objTalhao->rowid] = dol_trunc($objTalhao->ref . ' - ' . $objTalhao->label, 60);
                }
                $db->free($resTalhao);
        }
        print '<tr><td>' . $langs->trans('SafraTalhao') . '</td><td>' . $form->selectarray('fk_talhao', $talhaoOptions, GETPOST('fk_talhao', 'int'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth300') . '</td></tr>';
        print '<tr><td>' . $langs->trans('SafraActivityType') . '</td><td><input type="text" class="minwidth200" name="activity_type" value="' . dol_escape_htmltag(GETPOST('activity_type', 'alpha')) . '"></td></tr>';
        print '<tr><td>' . $langs->trans('SafraActivityDatePlannedStart') . '</td><td>' . $form->selectDate(dol_now(), 'date_planned_start', 1, 1, 1, '', 1, 0) . '</td></tr>';
        print '<tr><td>' . $langs->trans('SafraActivityDatePlannedEnd') . '</td><td>' . $form->selectDate(dol_now(), 'date_planned_end', 1, 1, 1, '', 1, 0) . '</td></tr>';
        print '<tr><td>' . $langs->trans('NotePublic') . '</td><td>' . dol_htmltextarea('note_public', '', '', 5, 50) . '</td></tr>';
        print '<tr><td>' . $langs->trans('NotePrivate') . '</td><td>' . dol_htmltextarea('note_private', '', '', 5, 50) . '</td></tr>';
        print '</table>';
        print '<div class="center">';
        print '<input type="submit" class="button" value="' . dol_escape_htmltag($langs->trans('Create')) . '">';
        print '&nbsp;';
        print '<a class="button" href="' . dol_buildpath('/safra/safraactivity_list.php', 1) . '">' . $langs->trans('Cancel') . '</a>';
        print '</div>';
        print '</form>';
        llxFooter();
        $db->close();
        exit;
}

if ($object->id > 0) {
        $head = safraactivityPrepareHead($object);
        print dol_get_fiche_head($head, 'card', $langs->trans('SafraActivityCardTitle', $object->ref), -1, $object->picto);

        $linkback = '<a href="' . dol_buildpath('/safra/safraactivity_list.php', 1) . '?restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>';
        dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '', '', 0, '', 1);

        print '<div class="fichecenter">';
        print '<div class="fichehalfleft">';
        print '<div class="underbanner clearboth"></div>';
        $projectHtml = '<span class="opacitymedium">&mdash;</span>';
        if ($object->fk_project) {
                $project = new Project($db);
                if ($project->fetch($object->fk_project) > 0) {
                        $projectHtml = $project->getNomUrl(1);
                        if (!empty($project->title)) {
                                $projectHtml .= '<span class="opacitymedium"> - ' . dol_escape_htmltag($project->title) . '</span>';
                        }
                }
        }
        $talhaoHtml = '<span class="opacitymedium">&mdash;</span>';
        if ($object->fk_talhao) {
                $talhao = new Talhao($db);
                if ($talhao->fetch($object->fk_talhao) > 0) {
                        $talhaoHtml = $talhao->getNomUrl(1);
                }
        }

        print '<table class="border centpercent tableforfield">';
        print '<tr><td class="titlefield">' . $langs->trans('Label') . '</td><td>' . dol_escape_htmltag($object->label) . '</td></tr>';
        print '<tr><td>' . $langs->trans('Project') . '</td><td>' . $projectHtml . '</td></tr>';
        print '<tr><td>' . $langs->trans('SafraTalhao') . '</td><td>' . $talhaoHtml . '</td></tr>';
        print '<tr><td>' . $langs->trans('SafraActivityType') . '</td><td>' . dol_escape_htmltag($object->activity_type) . '</td></tr>';
        print '<tr><td>' . $langs->trans('SafraActivityDatePlannedStart') . '</td><td>' . ($object->date_planned_start ? dol_print_date($object->date_planned_start, 'dayhour') : '&mdash;') . '</td></tr>';
        print '<tr><td>' . $langs->trans('SafraActivityDatePlannedEnd') . '</td><td>' . ($object->date_planned_end ? dol_print_date($object->date_planned_end, 'dayhour') : '&mdash;') . '</td></tr>';
        print '<tr><td>' . $langs->trans('DateStart') . '</td><td>' . ($object->date_real_start ? dol_print_date($object->date_real_start, 'dayhour') : '&mdash;') . '</td></tr>';
        print '<tr><td>' . $langs->trans('DateEnd') . '</td><td>' . ($object->date_real_end ? dol_print_date($object->date_real_end, 'dayhour') : '&mdash;') . '</td></tr>';
        print '<tr><td>' . $langs->trans('Status') . '</td><td>' . $object->getLibStatut(5) . '</td></tr>';
        print '<tr><td>' . $langs->trans('SafraActivityPlannedCost') . '</td><td>' . price($object->planned_cost) . '</td></tr>';
        print '<tr><td>' . $langs->trans('SafraActivityActualCost') . '</td><td>' . price($object->actual_cost) . '</td></tr>';
        print '</table>';
        print '</div>';

        print '<div class="fichehalfright">';
        print '<div class="box fichecenter">';
        print '<div class="titre">' . $langs->trans('Workflow') . '</div>';
        print '<div class="center">';
        $buttons = array();
        $baseUrl = $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&token=' . newToken();
        if ($object->status == SafraActivity::STATUS_DRAFT) {
                $buttons[] = '<a class="butAction" href="' . $baseUrl . '&action=edit">' . $langs->trans('Modify') . '</a>';
                $buttons[] = '<a class="butAction" href="' . $baseUrl . '&action=validate">' . $langs->trans('Validate') . '</a>';
        }
        if ($object->status == SafraActivity::STATUS_VALIDATED) {
                $buttons[] = '<a class="butAction" href="' . $baseUrl . '&action=start">' . $langs->trans('SafraActivityStart') . '</a>';
        }
        if ($object->status == SafraActivity::STATUS_IN_PROGRESS) {
                $buttons[] = '<a class="butAction" href="' . $baseUrl . '&action=complete">' . $langs->trans('SafraActivityComplete') . '</a>';
        }
        if (!in_array($object->status, array(SafraActivity::STATUS_CANCELED, SafraActivity::STATUS_COMPLETED), true)) {
                $buttons[] = '<a class="butActionDelete" href="' . $baseUrl . '&action=cancel">' . $langs->trans('Cancel') . '</a>';
        }
        if (in_array($object->status, array(SafraActivity::STATUS_CANCELED, SafraActivity::STATUS_COMPLETED), true)) {
                $buttons[] = '<a class="butAction" href="' . $baseUrl . '&action=reopen">' . $langs->trans('ReOpen') . '</a>';
        }
        print implode('&nbsp;', $buttons);
        print '</div>';
        print '</div>';
        print '</div>';

        print '</div>';
        print '<div class="clearboth"></div>';

        if ($action == 'edit') {
                print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
                print '<input type="hidden" name="token" value="' . newToken() . '">';
                print '<input type="hidden" name="action" value="update">';

                $projectOptions = array('0' => '');
                $resProject = $db->query('SELECT rowid, ref, title FROM ' . MAIN_DB_PREFIX . 'projet WHERE entity IN (' . getEntity('project') . ') ORDER BY ref');
                if ($resProject) {
                        while ($objProject = $db->fetch_object($resProject)) {
                                $label = $objProject->ref;
                                if (!empty($objProject->title)) {
                                        $label .= ' - ' . $objProject->title;
                                }
                                $projectOptions[$objProject->rowid] = dol_trunc($label, 60);
                        }
                        $db->free($resProject);
                }

                print '<table class="border centpercent tableforfield">';
                print '<tr><td class="titlefield">' . $langs->trans('Ref') . '</td><td><input type="text" name="ref" class="minwidth200" value="' . dol_escape_htmltag($object->ref) . '"></td></tr>';
                print '<tr><td>' . $langs->trans('Label') . '</td><td><input type="text" name="label" class="minwidth300" value="' . dol_escape_htmltag($object->label) . '"></td></tr>';
                print '<tr><td>' . $langs->trans('Project') . '</td><td>' . $form->selectarray('fk_project', $projectOptions, $object->fk_project, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300') . '</td></tr>';
                $resTalhao = $db->query('SELECT rowid, ref, label FROM ' . MAIN_DB_PREFIX . 'safra_talhao WHERE entity IN (' . getEntity('safra_talhao') . ') ORDER BY ref');
                $talhaoOptions = array('0' => '');
                if ($resTalhao) {
                        while ($objTalhao = $db->fetch_object($resTalhao)) {
                                $talhaoOptions[$objTalhao->rowid] = dol_trunc($objTalhao->ref . ' - ' . $objTalhao->label, 60);
                        }
                        $db->free($resTalhao);
                }
                print '<tr><td>' . $langs->trans('SafraTalhao') . '</td><td>' . $form->selectarray('fk_talhao', $talhaoOptions, $object->fk_talhao, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300') . '</td></tr>';
                print '<tr><td>' . $langs->trans('SafraActivityType') . '</td><td><input type="text" name="activity_type" class="minwidth200" value="' . dol_escape_htmltag($object->activity_type) . '"></td></tr>';
                print '<tr><td>' . $langs->trans('SafraActivityDatePlannedStart') . '</td><td>' . $form->selectDate($object->date_planned_start, 'date_planned_start', 1, 1, 1, '', 1, 0) . '</td></tr>';
                print '<tr><td>' . $langs->trans('SafraActivityDatePlannedEnd') . '</td><td>' . $form->selectDate($object->date_planned_end, 'date_planned_end', 1, 1, 1, '', 1, 0) . '</td></tr>';
                print '<tr><td>' . $langs->trans('DateStart') . '</td><td>' . $form->selectDate($object->date_real_start, 'date_real_start', 1, 1, 1, '', 1, 0) . '</td></tr>';
                print '<tr><td>' . $langs->trans('DateEnd') . '</td><td>' . $form->selectDate($object->date_real_end, 'date_real_end', 1, 1, 1, '', 1, 0) . '</td></tr>';
                print '<tr><td>' . $langs->trans('NotePublic') . '</td><td>' . dol_htmltextarea('note_public', $object->note_public, '', 5, 50) . '</td></tr>';
                print '<tr><td>' . $langs->trans('NotePrivate') . '</td><td>' . dol_htmltextarea('note_private', $object->note_private, '', 5, 50) . '</td></tr>';
                print '<tr><td>' . $langs->trans('SafraActivityPlannedCost') . '</td><td><span class="opacitymedium">' . price($object->planned_cost) . '</span></td></tr>';
                print '<tr><td>' . $langs->trans('SafraActivityActualCost') . '</td><td><span class="opacitymedium">' . price($object->actual_cost) . '</span></td></tr>';
                print '</table>';
                print '<div class="center">';
                print '<input type="submit" class="button" value="' . dol_escape_htmltag($langs->trans('Save')) . '">';
                print '&nbsp;';
                print '<a class="button" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">' . $langs->trans('Cancel') . '</a>';
                print '</div>';
                print '</form>';
        }

        if ($action != 'edit') {
                print '<div class="fichecenter">';
                print '<div class="fichehalfleft">';
                print '<h3>' . $langs->trans('SafraActivityInputs') . '</h3>';
                print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
                print '<input type="hidden" name="token" value="' . newToken() . '">';
                print '<input type="hidden" name="action" value="save_inputs">';
                print '<table class="noborder centpercent">';
                print '<tr class="liste_titre">';
                print '<th>' . $langs->trans('Product') . '</th>';
                print '<th>' . $langs->trans('Label') . '</th>';
                print '<th>' . $langs->trans('Qty') . '</th>';
                print '<th>' . $langs->trans('Unit') . '</th>';
                print '<th>' . $langs->trans('Warehouse') . '</th>';
                print '<th>' . $langs->trans('Movement') . '</th>';
                print '<th>' . $langs->trans('Note') . '</th>';
                print '<th></th>';
                print '</tr>';
                $movementOptions = SafraActivity::getMovementTypes();
                $lineIndex = 0;
                if (!empty($object->lines)) {
                        foreach ($object->lines as $line) {
                                print '<tr class="oddeven" data-row="input">';
                                print '<td><input type="number" name="inputs[' . $lineIndex . '][fk_product]" class="minwidth75" value="' . (int) $line->fk_product . '"></td>';
                                print '<td><input type="text" name="inputs[' . $lineIndex . '][label]" class="minwidth200" value="' . dol_escape_htmltag($line->label) . '"></td>';
                                print '<td><input type="text" name="inputs[' . $lineIndex . '][qty]" class="minwidth75" value="' . dol_escape_htmltag($line->qty) . '"></td>';
                                print '<td><input type="text" name="inputs[' . $lineIndex . '][unit]" class="minwidth75" value="' . dol_escape_htmltag($line->unit) . '"></td>';
                                print '<td><input type="number" name="inputs[' . $lineIndex . '][fk_warehouse]" class="minwidth75" value="' . (int) $line->fk_warehouse . '"></td>';
                                print '<td>' . $form->selectarray('inputs[' . $lineIndex . '][movement_type]', $movementOptions, $line->movement_type, 1, 0, 0, '', 0, 0, 0, '', 'minwidth150') . '</td>';
                                print '<td><input type="text" name="inputs[' . $lineIndex . '][note]" class="minwidth150" value="' . dol_escape_htmltag($line->note) . '"></td>';
                                print '<td class="center"><a href="#" class="safra-remove-row" data-target="input">' . img_delete() . '</a></td>';
                                print '</tr>';
                                $lineIndex++;
                        }
                }
                print '<tr class="oddeven" data-row="input">';
                print '<td><input type="number" name="inputs[' . $lineIndex . '][fk_product]" class="minwidth75"></td>';
                print '<td><input type="text" name="inputs[' . $lineIndex . '][label]" class="minwidth200"></td>';
                print '<td><input type="text" name="inputs[' . $lineIndex . '][qty]" class="minwidth75"></td>';
                print '<td><input type="text" name="inputs[' . $lineIndex . '][unit]" class="minwidth75"></td>';
                print '<td><input type="number" name="inputs[' . $lineIndex . '][fk_warehouse]" class="minwidth75"></td>';
                print '<td>' . $form->selectarray('inputs[' . $lineIndex . '][movement_type]', $movementOptions, SafraActivity::MOVEMENT_CONSUME, 1, 0, 0, '', 0, 0, 0, '', 'minwidth150') . '</td>';
                print '<td><input type="text" name="inputs[' . $lineIndex . '][note]" class="minwidth150"></td>';
                print '<td class="center"><a href="#" class="safra-remove-row" data-target="input">' . img_delete() . '</a></td>';
                print '</tr>';
                print '</table>';
                print '<div class="center">';
                print '<input type="submit" class="button" value="' . dol_escape_htmltag($langs->trans('Save')) . '">';
                print '</div>';
                print '</form>';

                print '<h3>' . $langs->trans('SafraActivityFleet') . '</h3>';
                print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
                print '<input type="hidden" name="token" value="' . newToken() . '">';
                print '<input type="hidden" name="action" value="save_fleet">';
                print '<table class="noborder centpercent">';
                print '<tr class="liste_titre">';
                print '<th>' . $langs->trans('Id') . '</th>';
                print '<th>' . $langs->trans('Type') . '</th>';
                print '<th>' . $langs->trans('SafraActivityPlannedHours') . '</th>';
                print '<th>' . $langs->trans('SafraActivityResponsible') . '</th>';
                print '<th>' . $langs->trans('SafraActivityResourceNote') . '</th>';
                print '<th></th>';
                print '</tr>';
                $fleetIndex = 0;
                if (!empty($object->fleet_resources)) {
                        foreach ($object->fleet_resources as $resource) {
                                print '<tr class="oddeven" data-row="fleet">';
                                print '<td><input type="number" name="fleet[' . $fleetIndex . '][fk_fleet_equipment]" class="minwidth100" value="' . (int) $resource->fk_fleet_equipment . '"></td>';
                                print '<td><input type="text" name="fleet[' . $fleetIndex . '][resource_type]" class="minwidth100" value="' . dol_escape_htmltag($resource->resource_type) . '"></td>';
                                print '<td><input type="text" name="fleet[' . $fleetIndex . '][planned_hours]" class="minwidth75" value="' . dol_escape_htmltag($resource->planned_hours) . '"></td>';
                                print '<td>' . $form->select_dolusers($resource->fk_user_responsible, 'fleet[' . $fleetIndex . '][fk_user_responsible]', 1, '', 0, '', '', 0, 0, 0, '', 0, '', '', 'minwidth200') . '</td>';
                                print '<td><input type="text" name="fleet[' . $fleetIndex . '][note]" class="minwidth200" value="' . dol_escape_htmltag($resource->note) . '"></td>';
                                print '<td class="center"><a href="#" class="safra-remove-row" data-target="fleet">' . img_delete() . '</a></td>';
                                print '</tr>';
                                $fleetIndex++;
                        }
                }
                print '<tr class="oddeven" data-row="fleet">';
                print '<td><input type="number" name="fleet[' . $fleetIndex . '][fk_fleet_equipment]" class="minwidth100"></td>';
                print '<td><input type="text" name="fleet[' . $fleetIndex . '][resource_type]" class="minwidth100" value="vehicle"></td>';
                print '<td><input type="text" name="fleet[' . $fleetIndex . '][planned_hours]" class="minwidth75"></td>';
                print '<td>' . $form->select_dolusers('', 'fleet[' . $fleetIndex . '][fk_user_responsible]', 1, '', 0, '', '', 0, 0, 0, '', 0, '', '', 'minwidth200') . '</td>';
                print '<td><input type="text" name="fleet[' . $fleetIndex . '][note]" class="minwidth200"></td>';
                print '<td class="center"><a href="#" class="safra-remove-row" data-target="fleet">' . img_delete() . '</a></td>';
                print '</tr>';
                print '</table>';
                print '<div class="center">';
                print '<input type="submit" class="button" value="' . dol_escape_htmltag($langs->trans('Save')) . '">';
                print '</div>';
                print '</form>';
                print '</div>';

                print '<div class="fichehalfright">';
                print '<h3>' . $langs->trans('SafraActivityTeam') . '</h3>';
                print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
                print '<input type="hidden" name="token" value="' . newToken() . '">';
                print '<input type="hidden" name="action" value="save_team">';
                print '<table class="noborder centpercent">';
                print '<tr class="liste_titre">';
                print '<th>' . $langs->trans('User') . '</th>';
                print '<th>' . $langs->trans('SafraActivityPlannedHours') . '</th>';
                print '<th>' . $langs->trans('SafraActivityResponsible') . '</th>';
                print '<th>' . $langs->trans('SafraActivityResourceNote') . '</th>';
                print '<th></th>';
                print '</tr>';
                $teamIndex = 0;
                if (!empty($object->team_members)) {
                        foreach ($object->team_members as $member) {
                                print '<tr class="oddeven" data-row="team">';
                                print '<td>' . $form->select_dolusers($member->fk_user, 'team[' . $teamIndex . '][fk_user]', 1, '', 0, '', '', 0, 0, 0, '', 0, '', '', 'minwidth200') . '</td>';
                                print '<td><input type="text" name="team[' . $teamIndex . '][planned_hours]" class="minwidth75" value="' . dol_escape_htmltag($member->planned_hours) . '"></td>';
                                print '<td class="center"><input type="checkbox" name="team[' . $teamIndex . '][is_responsible]" value="1"' . ($member->is_responsible ? ' checked' : '') . '></td>';
                                print '<td><input type="text" name="team[' . $teamIndex . '][note]" class="minwidth200" value="' . dol_escape_htmltag($member->note) . '"></td>';
                                print '<td class="center"><a href="#" class="safra-remove-row" data-target="team">' . img_delete() . '</a></td>';
                                print '</tr>';
                                $teamIndex++;
                        }
                }
                print '<tr class="oddeven" data-row="team">';
                print '<td>' . $form->select_dolusers('', 'team[' . $teamIndex . '][fk_user]', 1, '', 0, '', '', 0, 0, 0, '', 0, '', '', 'minwidth200') . '</td>';
                print '<td><input type="text" name="team[' . $teamIndex . '][planned_hours]" class="minwidth75"></td>';
                print '<td class="center"><input type="checkbox" name="team[' . $teamIndex . '][is_responsible]" value="1"></td>';
                print '<td><input type="text" name="team[' . $teamIndex . '][note]" class="minwidth200"></td>';
                print '<td class="center"><a href="#" class="safra-remove-row" data-target="team">' . img_delete() . '</a></td>';
                print '</tr>';
                print '</table>';
                print '<div class="center">';
                print '<input type="submit" class="button" value="' . dol_escape_htmltag($langs->trans('Save')) . '">';
                print '</div>';
                print '</form>';
                print '</div>';
                print '</div>';
        }

        print dol_get_fiche_end();
}

llxFooter();
$db->close();

