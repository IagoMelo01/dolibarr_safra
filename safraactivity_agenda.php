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
 *  \file       safraactivity_agenda.php
 *  \ingroup    safra
 *  \brief      Tab of events on Safra activities
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

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

require_once __DIR__ . '/class/safraactivity.class.php';
dol_include_once('/safra/lib/safra_activity.lib.php');

$langs->loadLangs(array('safra@safra', 'companies', 'agenda'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

$object = new SafraActivity($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('safraactivityagenda', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

if ($id > 0 || !empty($ref)) {
        $object->fetch($id, $ref);
}

if (!isModEnabled('safra')) {
        accessforbidden();
}

$title = $langs->trans('SafraActivityCardTitle', $object->ref);
llxHeader('', $title);

$head = safraactivityPrepareHead($object);
print dol_get_fiche_head($head, 'agenda', $langs->trans('SafraActivityCardTitle', $object->ref), -1, $object->picto);

$linkback = '<a href="' . dol_buildpath('/safra/safraactivity_list.php', 1) . '?restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
$object->info($object->id);
dol_print_object_info($object, 1);
print '</div>';

print dol_get_fiche_end();

if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
        $morehtmlright = '';
        $out = '&origin=' . urlencode($object->element . (property_exists($object, 'module') ? '@' . $object->module : '')) . '&originid=' . urlencode($object->id);
        $out .= '&backtopage=' . urlencode($_SERVER['PHP_SELF'] . '?id=' . $object->id);
        if ($user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create')) {
                $morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT . '/comm/action/card.php?action=create' . $out);
        } else {
                $morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT . '/comm/action/card.php?action=create' . $out, '', 0);
        }

        $formactions = new FormActions($db);
        print '<br>';
        print_barre_liste($langs->trans('ActionsOnObject'), 0, $_SERVER['PHP_SELF'], '', '', '', '', 0, -1, '', 0, $morehtmlright, '', 0, 1, 0);
        $formactions->showactions($object, $object->element . '@' . $object->module, 0, 1);
} else {
        print '<div class="opacitymedium center">' . $langs->trans('NoAgendaModuleOrNoPermissions') . '</div>';
}

llxFooter();
$db->close();

