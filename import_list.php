<?php
require_once __DIR__ . '/../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

if (empty($user->rights->fv_fiscal->read) && !$user->admin) {
    accessforbidden();
}

$langs->load('companies');
$langs->load('fv_fiscal@fv_fiscal');

$sql = 'SELECT i.rowid, i.chave, i.status_manifestacao, i.autorizado_dt, s.nom as fornecedor';
$sql .= ' FROM llx_fv_nfe_in i LEFT JOIN llx_societe s ON (s.rowid = i.socid_emit)';
$sql .= ' ORDER BY i.rowid DESC';

$resql = $db->query($sql);

llxHeader('', $langs->trans('FvFiscalNFeInList'));

print load_fiche_titre($langs->trans('FvFiscalNFeInList'));

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans('Chave') . '</th>';
print '<th>' . $langs->trans('Supplier') . '</th>';
print '<th>' . $langs->trans('Status') . '</th>';
print '<th>' . $langs->trans('Date') . '</th>';
print '<th></th>';
print '</tr>';

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag($obj->chave) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->fornecedor) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->status_manifestacao) . '</td>';
        print '<td>' . dol_print_date($db->jdate($obj->autorizado_dt), 'dayhour') . '</td>';
        print '<td>';
        print '<a class="button small" href="php/import_manifest.php?action=ciencia&chave=' . urlencode($obj->chave) . '&token=' . newToken() . '">' . $langs->trans('Science') . '</a> ';
        print '<a class="button small" href="php/import_manifest.php?action=confirmacao&chave=' . urlencode($obj->chave) . '&token=' . newToken() . '">' . $langs->trans('Confirm') . '</a> ';
        print '<a class="button small" href="php/import_manifest.php?action=desconhecimento&chave=' . urlencode($obj->chave) . '&token=' . newToken() . '">' . $langs->trans('Disagree') . '</a> ';
        print '<a class="button small" href="php/import_manifest.php?action=operacao_nao_realizada&chave=' . urlencode($obj->chave) . '&token=' . newToken() . '">' . $langs->trans('OperationNotPerformed') . '</a> ';
        print '<a class="button small" href="php/nfe_emit.php?action=download_in_xml&chave=' . urlencode($obj->chave) . '&token=' . newToken() . '">' . $langs->trans('DownloadXML') . '</a> ';
        print '<a class="button small" href="php/nfe_emit.php?action=download_in_danfe&chave=' . urlencode($obj->chave) . '&token=' . newToken() . '">' . $langs->trans('DownloadDANFE') . '</a>';
        print '</td>';
        print '</tr>';
    }
}
print '</table>';

llxFooter();
