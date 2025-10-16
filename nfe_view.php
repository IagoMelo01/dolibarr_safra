<?php
require_once __DIR__ . '/../../main.inc.php';
require_once __DIR__ . '/class/FocusClient.class.php';
require_once __DIR__ . '/class/NFeOutService.class.php';

if (empty($user->rights->fv_fiscal->read) && !$user->admin) {
    accessforbidden();
}

$langs->load('companies');
$langs->load('fv_fiscal@fv_fiscal');

$ref = GETPOST('ref', 'alpha');
if (empty($ref)) {
    accessforbidden();
}

$sql = "SELECT * FROM llx_fv_nfe_out WHERE ref_unique = '" . $db->escape($ref) . "'";
$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) === 0) {
    accessforbidden();
}
$nfe = $db->fetch_object($resql);

llxHeader('', $langs->trans('FvFiscalNFeView'));

print load_fiche_titre($langs->trans('FvFiscalNFeView') . ' ' . dol_escape_htmltag($ref));

print '<div class="fichecenter">';
print '<table class="border centpercent">';
print '<tr><td>' . $langs->trans('Status') . '</td><td>' . dol_escape_htmltag($nfe->status) . '</td></tr>';
print '<tr><td>' . $langs->trans('Numero') . '</td><td>' . dol_escape_htmltag($nfe->numero) . '</td></tr>';
print '<tr><td>' . $langs->trans('Serie') . '</td><td>' . dol_escape_htmltag($nfe->serie) . '</td></tr>';
print '<tr><td>' . $langs->trans('Chave') . '</td><td>' . dol_escape_htmltag($nfe->chave) . '</td></tr>';
print '<tr><td>' . $langs->trans('AmountHT') . '</td><td>' . price($nfe->total_nf) . '</td></tr>';
print '<tr><td>' . $langs->trans('Date') . '</td><td>' . dol_print_date($db->jdate($nfe->dh_autorizacao), 'dayhour') . '</td></tr>';
print '</table>';
print '</div>';

print '<div class="tabsAction">';
print '<a class="butAction" href="php/nfe_emit.php?action=refresh&ref=' . urlencode($ref) . '&token=' . newToken() . '">' . $langs->trans('RefreshStatus') . '</a>';
print '<a class="butAction" href="php/nfe_cancel.php?ref=' . urlencode($ref) . '&token=' . newToken() . '">' . $langs->trans('Cancel') . '</a>';
print '<a class="butAction" href="php/nfe_cce.php?ref=' . urlencode($ref) . '&token=' . newToken() . '">' . $langs->trans('SendCCE') . '</a>';
print '<a class="butAction" href="php/nfe_emit.php?action=download_xml&ref=' . urlencode($ref) . '&token=' . newToken() . '">' . $langs->trans('DownloadXML') . '</a>';
print '<a class="butAction" href="php/nfe_emit.php?action=download_danfe&ref=' . urlencode($ref) . '&token=' . newToken() . '">' . $langs->trans('DownloadDANFE') . '</a>';
print '</div>';

llxFooter();
