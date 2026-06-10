<?php
/*
 * Documents and field photos linked to a Safra activity.
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
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
dol_include_once('/safra/class/FvActivity.class.php');
dol_include_once('/safra/lib/safra_activity.lib.php');

global $db, $langs, $user, $conf, $hookmanager;

$langs->loadLangs(array('safra@safra', 'other', 'mails', 'link'));

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$id = GETPOSTINT('id');
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'name';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'ASC';

$permissiontoread = $user->rights->safra->SafraActivity->read ?? 0;
$permissiontoadd = $user->rights->safra->SafraActivity->write ?? 0;
if (!$permissiontoread) {
    accessforbidden();
}

$object = new FvActivity($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
    accessforbidden();
}

$upload_dir = safra_activity_documents_upload_dir($object);
$backtopage = $_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id);
$hookmanager->initHooks(array('safra_activitydocument', 'globalcard'));

include DOL_DOCUMENT_ROOT . '/core/actions_linkedfiles.inc.php';

$form = new Form($db);
$formfile = new FormFile($db);
$relativepathwithnofile = safra_activity_documents_relative_path($object) . '/';
$filearray = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) === 'desc' ? SORT_DESC : SORT_ASC), 1);
$totalsize = 0;
foreach ($filearray as $file) {
    $totalsize += (int) $file['size'];
}

$title = $langs->trans('SafraActivityCardTitle', $object->ref ?: $object->id);
$linkback = '<a href="' . dol_buildpath('/safra/activity/activity_list.php', 1) . '">' . $langs->trans('BackToList') . '</a>';

llxHeader('', $title, '', '', 0, 0, array(), array('/safra/css/safra.css.php'));

$head = safra_activity_prepare_head($object, $langs);
print dol_get_fiche_head($head, 'documents', $title, -1, 'fa-tractor', 0, $linkback);

print '<div class="safra-document-intro">';
print '<div><span class="fas fa-paperclip"></span><strong>' . $langs->trans('SafraActivityDocuments') . '</strong><span>' . $langs->trans('SafraActivityDocumentsHelp') . '</span></div>';
print '<div><span class="fas fa-camera"></span><strong>' . $langs->trans('SafraActivityFieldPhotos') . '</strong><span>' . $langs->trans('SafraActivityFieldPhotosHelp') . '</span></div>';
print '<div><span class="fas fa-exclamation-triangle"></span><strong>' . $langs->trans('SafraActivityOccurrences') . '</strong><span>' . $langs->trans('SafraActivityOccurrencesHelp') . '</span></div>';
print '</div>';

print '<div class="safra-report-summary">';
print '<div><strong>' . count($filearray) . '</strong><span>' . $langs->trans('NbOfAttachedFiles') . '</span></div>';
print '<div><strong>' . dol_print_size($totalsize) . '</strong><span>' . $langs->trans('TotalSizeOfAttachedFiles') . '</span></div>';
print '<div><strong>' . dol_escape_htmltag($object->ref ?: ('#' . $object->id)) . '</strong><span>' . $langs->trans('SafraActivity') . '</span></div>';
print '</div>';

if ($permissiontoadd) {
    print '<div class="safra-camera-upload">';
    print '<div><strong><span class="fas fa-camera"></span> ' . $langs->trans('SafraActivityTakePhoto') . '</strong><div class="opacitymedium">' . $langs->trans('SafraActivityTakePhotoHelp') . '</div></div>';
    $formfile->form_attach_new_file(
        $_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id),
        'none',
        0,
        0,
        $permissiontoadd,
        40,
        $object,
        '',
        0,
        dol_sanitizeFileName($object->ref ?: $object->id) . '-foto-__file__',
        0,
        'activityphotofile',
        'image/*',
        '',
        0,
        1,
        1
    );
    print '</div>';
}

print dol_get_fiche_end();

$modulepart = 'safra';
$param = '&id=' . ((int) $object->id);
$savingdocmask = dol_sanitizeFileName($object->ref ?: $object->id) . '-__file__';
include DOL_DOCUMENT_ROOT . '/core/tpl/document_actions_post_headers.tpl.php';

llxFooter();
$db->close();
