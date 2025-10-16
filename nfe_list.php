<?php
require_once __DIR__ . '/../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

if (empty($user->rights->fv_fiscal->read) && !$user->admin) {
    accessforbidden();
}

$langs->load('companies');
$langs->load('fv_fiscal@fv_fiscal');

$form = new Form($db);
$action = GETPOST('action', 'aZ09');

$status = GETPOST('status', 'alpha');
$numero = GETPOST('numero', 'alpha');
$serie = GETPOST('serie', 'alpha');
$cfop = GETPOST('cfop', 'alpha');

$sql = 'SELECT n.rowid, n.ref_unique, n.serie, n.numero, n.status, n.chave, n.total_nf, n.dh_autorizacao, s.nom as emitente';
$sql .= ' FROM llx_fv_nfe_out n LEFT JOIN llx_societe s ON (s.rowid = n.socid_emit)';
$sql .= ' WHERE 1 = 1';
if (!empty($status)) {
    $sql .= " AND n.status = '" . $db->escape($status) . "'";
}
if (!empty($numero)) {
    $sql .= " AND n.numero LIKE '%" . $db->escape($numero) . "%'";
}
if (!empty($serie)) {
    $sql .= " AND n.serie LIKE '%" . $db->escape($serie) . "%'";
}
$sql .= ' ORDER BY n.rowid DESC';

$resql = $db->query($sql);

llxHeader('', $langs->trans('FvFiscalNFeList'));

print load_fiche_titre($langs->trans('FvFiscalNFeList'));

print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<table class="noborder">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans('Status') . '</th>';
print '<th>' . $langs->trans('Numero') . '</th>';
print '<th>' . $langs->trans('Serie') . '</th>';
print '<th></th>';
print '</tr>';
print '<tr class="oddeven">';
print '<td><input type="text" name="status" value="' . dol_escape_htmltag($status) . '"></td>';
print '<td><input type="text" name="numero" value="' . dol_escape_htmltag($numero) . '"></td>';
print '<td><input type="text" name="serie" value="' . dol_escape_htmltag($serie) . '"></td>';
print '<td><input type="submit" class="button" value="' . $langs->trans('Search') . '"></td>';
print '</tr>';
print '</table>';
print '</form>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans('Ref') . '</th>';
print '<th>' . $langs->trans('Numero') . '</th>';
print '<th>' . $langs->trans('Serie') . '</th>';
print '<th>' . $langs->trans('Status') . '</th>';
print '<th>' . $langs->trans('Customer') . '</th>';
print '<th>' . $langs->trans('AmountHT') . '</th>';
print '<th>' . $langs->trans('Date') . '</th>';
print '<th></th>';
print '</tr>';

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag($obj->ref_unique) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->numero) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->serie) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->status) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->emitente) . '</td>';
        print '<td class="right">' . price($obj->total_nf) . '</td>';
        print '<td>' . dol_print_date($db->jdate($obj->dh_autorizacao), 'dayhour') . '</td>';
        print '<td>'; 
        print '<a class="button small" href="nfe_view.php?ref=' . urlencode($obj->ref_unique) . '">' . $langs->trans('View') . '</a> ';
        print '<a class="button small" href="php/nfe_emit.php?action=download_xml&ref=' . urlencode($obj->ref_unique) . '&token=' . newToken() . '">' . $langs->trans('DownloadXML') . '</a> ';
        print '<a class="button small" href="php/nfe_emit.php?action=download_danfe&ref=' . urlencode($obj->ref_unique) . '&token=' . newToken() . '">' . $langs->trans('DownloadDANFE') . '</a>';
        print '</td>';
        print '</tr>';
    }
}
print '</table>';

llxFooter();
