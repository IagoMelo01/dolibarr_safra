<?php
require_once __DIR__ . '/../../main.inc.php';

if (empty($user->rights->fv_fiscal->read) && !$user->admin) {
    accessforbidden();
}

$langs->load('fv_fiscal@fv_fiscal');

$sql = 'SELECT * FROM llx_fv_mdfe ORDER BY rowid DESC';
$resql = $db->query($sql);

llxHeader('', $langs->trans('FvFiscalMDFeList'));

print load_fiche_titre($langs->trans('FvFiscalMDFeList'));

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans('Chave') . '</th>';
print '<th>' . $langs->trans('Numero') . '</th>';
print '<th>' . $langs->trans('Serie') . '</th>';
print '<th>' . $langs->trans('Status') . '</th>';
print '<th></th>';
print '</tr>';

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag($obj->chave) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->numero) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->serie) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->status) . '</td>';
        print '<td>';
        print '<a class="button small" href="php/mdfe_action.php?action=status&ref=' . urlencode($obj->chave) . '&token=' . newToken() . '">' . $langs->trans('RefreshStatus') . '</a> ';
        print '<a class="button small" href="php/mdfe_action.php?action=download_xml&ref=' . urlencode($obj->chave) . '&token=' . newToken() . '">' . $langs->trans('DownloadXML') . '</a> ';
        print '<a class="button small" href="php/mdfe_action.php?action=download_damdfe&ref=' . urlencode($obj->chave) . '&token=' . newToken() . '">' . $langs->trans('DownloadDANFE') . '</a>';
        print '</td>';
        print '</tr>';
    }
}
print '</table>';

llxFooter();
