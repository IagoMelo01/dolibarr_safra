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
 *  \file       safraactivity_document.php
 *  \ingroup    safra
 *  \brief      Tab for documents linked to Safra activities
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/link.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

require_once __DIR__ . '/class/safraactivity.class.php';
dol_include_once('/safra/lib/safra_activity.lib.php');

$langs->loadLangs(array('safra@safra', 'companies'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$object = new SafraActivity($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->safra->dir_output . '/temp/massgeneration/' . $user->id;
$hookmanager->initHooks(array('safraactivitydocument', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

if ($id > 0 || !empty($ref)) {
        $object->fetch($id, $ref);
}

if (!isModEnabled('safra')) {
        accessforbidden();
}

$upload_dir = $conf->safra->multidir_output[isset($object->entity) ? $object->entity : $conf->entity] . '/safraactivity/' . dol_sanitizeFileName($object->ref);

include DOL_DOCUMENT_ROOT . '/core/actions_builddoc.inc.php';

$title = $langs->trans('SafraActivityCardTitle', $object->ref);
llxHeader('', $title);

$head = safraactivityPrepareHead($object);
print dol_get_fiche_head($head, 'document', $langs->trans('SafraActivityCardTitle', $object->ref), -1, $object->picto);

dol_banner_tab($object, 'ref');

$formfile = new FormFile($db);

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';

print '<a name="builddoc"></a>';
print $formfile->showdocuments('safra:SafraActivity', 'safraactivity/' . dol_sanitizeFileName($object->ref), $upload_dir, $_SERVER['PHP_SELF'] . '?id=' . $object->id, 1, 1, 0, 0, 0, 0, 0, '', '', '', $langs->defaultlang);

print '</div>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();

