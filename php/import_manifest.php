<?php
require_once __DIR__ . '/../../../main.inc.php';
require_once __DIR__ . '/../class/FocusClient.class.php';
require_once __DIR__ . '/../class/NFeInService.class.php';

$tokenPost = GETPOST('token', 'alphanohtml');
if (empty($tokenPost) || empty($_SESSION['newtoken']) || !hash_equals($_SESSION['newtoken'], $tokenPost)) {
    accessforbidden('Bad token');
}

$action = GETPOST('action', 'alpha');
$chave = GETPOST('chave', 'alpha');

$client = new FocusClient($db, $conf->global->FV_FISCAL_FOCUS_TOKEN, $conf->global->FV_FISCAL_ENV, $conf->global->FV_FISCAL_BASE_URL);
$service = new NFeInService($db, $client);

try {
    $service->manifest($chave, $action);
    setEventMessages($langs->trans('ManifestSent'), null, 'mesgs');
} catch (Exception $e) {
    setEventMessages($e->getMessage(), null, 'errors');
}

header('Location: ../import_list.php');
