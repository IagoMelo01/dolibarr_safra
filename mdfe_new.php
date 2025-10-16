<?php
require_once __DIR__ . '/../../main.inc.php';
require_once __DIR__ . '/class/FocusClient.class.php';
require_once __DIR__ . '/class/MDFeService.class.php';

if (empty($user->rights->fv_fiscal->write) && !$user->admin) {
    accessforbidden();
}

$langs->load('fv_fiscal@fv_fiscal');

$action = GETPOST('action', 'alpha');
$ref = GETPOST('ref', 'alpha');

if ($action === 'send') {
    $tokenPost = GETPOST('token', 'alphanohtml');
    if (empty($tokenPost) || empty($_SESSION['newtoken']) || !hash_equals($_SESSION['newtoken'], $tokenPost)) {
        accessforbidden('Bad token');
    }

    $payload = json_decode(GETPOST('payload_json', 'restricthtml'), true);
    try {
        $client = new FocusClient($db, $conf->global->FV_FISCAL_FOCUS_TOKEN, $conf->global->FV_FISCAL_ENV, $conf->global->FV_FISCAL_BASE_URL);
        $service = new MDFeService($db, $client);
        $service->createAndSend($ref, $payload);
        setEventMessages($langs->trans('MDFeSent'), null, 'mesgs');
        header('Location: mdfe_list.php');
        exit;
    } catch (Exception $e) {
        setEventMessages($e->getMessage(), null, 'errors');
    }
}

llxHeader('', $langs->trans('FvFiscalMDFeNew'));

print load_fiche_titre($langs->trans('FvFiscalMDFeNew'));

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="send">';
print '<table class="noborder centpercent">';
print '<tr><td class="fieldrequired">' . $langs->trans('Reference') . '</td><td><input type="text" name="ref" value="' . dol_escape_htmltag($ref ?: dol_print_date(dol_now(), 'dayhourlog')) . '"></td></tr>';
print '<tr><td>' . $langs->trans('PayloadJSON') . '</td><td><textarea name="payload_json" rows="15" class="quatrevingtpercent"></textarea></td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button" value="' . $langs->trans('SendToSefaz') . '"></div>';
print '</form>';

llxFooter();
