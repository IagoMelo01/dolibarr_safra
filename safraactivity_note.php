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
 *  \file       safraactivity_note.php
 *  \ingroup    safra
 *  \brief      Tab for notes on Safra activities
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

require_once __DIR__ . '/class/safraactivity.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
dol_include_once('/safra/lib/safra_activity.lib.php');

$langs->loadLangs(array('safra@safra', 'companies'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$object = new SafraActivity($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->safra->dir_output . '/temp/massgeneration/' . $user->id;
$hookmanager->initHooks(array('safraactivitynote', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

if ($id > 0 || !empty($ref)) {
        $object->fetch($id, $ref);
}

if (!isModEnabled('safra')) {
        accessforbidden();
}

$permissiontoread = 1;
$permissiontoadd = 1;
$permissionnote = 1;

include DOL_DOCUMENT_ROOT . '/core/actions_setnotes.inc.php';

$title = $langs->trans('SafraActivityCardTitle', $object->ref);
llxHeader('', $title);

$head = safraactivityPrepareHead($object);
print dol_get_fiche_head($head, 'note', $langs->trans('SafraActivityCardTitle', $object->ref), -1, $object->picto);

dol_banner_tab($object, 'ref');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="setnote">';
print '<table class="border centpercent">';
if (isset($object->fields['note_public'])) {
        print '<tr><td class="titlefield">' . $langs->trans('NotePublic') . '</td><td>';
        print '<textarea class="centpercent" name="note_public" rows="6">' . dol_escape_htmltag($object->note_public) . '</textarea>';
        print '</td></tr>';
}
if (isset($object->fields['note_private'])) {
        print '<tr><td>' . $langs->trans('NotePrivate') . '</td><td>';
        print '<textarea class="centpercent" name="note_private" rows="6">' . dol_escape_htmltag($object->note_private) . '</textarea>';
        print '</td></tr>';
}
print '</table>';
print '<div class="center">';
print '<input type="submit" class="button" value="' . dol_escape_htmltag($langs->trans('Save')) . '">';
print '</div>';
print '</form>';

print '</div>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();

