<?php
require_once __DIR__ . '/../../main.inc.php';

if (empty($user->rights->fv_fiscal->read) && !$user->admin) {
    accessforbidden();
}

$langs->load('fv_fiscal@fv_fiscal');

$action = GETPOST('action', 'alpha');

if ($action === 'generate') {
    $tokenPost = GETPOST('token', 'alphanohtml');
    if (empty($tokenPost) || empty($_SESSION['newtoken']) || !hash_equals($_SESSION['newtoken'], $tokenPost)) {
        accessforbidden('Bad token');
    }
    $type = GETPOST('type', 'alpha');
    $criteria = array('date_start' => GETPOST('date_start', 'alpha'), 'date_end' => GETPOST('date_end', 'alpha'));
    $zipPath = DOL_DATA_ROOT . '/fiscal/export_' . dol_print_date(dol_now(), 'dayhourlog') . '.zip';
    dol_mkdir(dirname($zipPath));
    $sql = "INSERT INTO llx_fv_batch_export(type, criteria_json, zip_path, created_by) VALUES ('" . $db->escape($type) . "', '" . $db->escape(json_encode($criteria)) . "', '" . $db->escape($zipPath) . "', " . ((int) $user->id) . ')";
    $db->query($sql);
    setEventMessages($langs->trans('ExportQueued'), null, 'mesgs');
}

llxHeader('', $langs->trans('FvFiscalBatchExport'));

echo load_fiche_titre($langs->trans('FvFiscalBatchExport'));

echo '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';

echo '<input type="hidden" name="token" value="' . newToken() . '">';

echo '<input type="hidden" name="action" value="generate">';

echo '<table class="noborder">';

echo '<tr><td>' . $langs->trans('Type') . '</td><td><select name="type"><option value="NFE_OUT">NF-e Saída</option><option value="NFE_IN">NF-e Entrada</option><option value="MDFE">MDFe</option></select></td></tr>';

echo '<tr><td>' . $langs->trans('DateStart') . '</td><td><input type="date" name="date_start"></td></tr>';

echo '<tr><td>' . $langs->trans('DateEnd') . '</td><td><input type="date" name="date_end"></td></tr>';

echo '</table>';

echo '<div class="center"><input type="submit" class="button" value="' . $langs->trans('Generate') . '"></div>';

echo '</form>';

$sql = 'SELECT * FROM llx_fv_batch_export ORDER BY rowid DESC LIMIT 20';
$resql = $db->query($sql);

echo '<h3>' . $langs->trans('LastExports') . '</h3>';

echo '<table class="noborder centpercent">';

echo '<tr class="liste_titre"><th>' . $langs->trans('Type') . '</th><th>' . $langs->trans('Criteria') . '</th><th>' . $langs->trans('File') . '</th><th>' . $langs->trans('Date') . '</th></tr>';

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        echo '<tr class="oddeven">';
        echo '<td>' . dol_escape_htmltag($obj->type) . '</td>';
        echo '<td>' . dol_escape_htmltag($obj->criteria_json) . '</td>';
        echo '<td>' . dol_escape_htmltag($obj->zip_path) . '</td>';
        echo '<td>' . dol_print_date($db->jdate($obj->tms), 'dayhour') . '</td>';
        echo '</tr>';
    }
}

echo '</table>';

llxFooter();
