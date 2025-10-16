<?php
require_once __DIR__ . '/../../../main.inc.php';
require_once __DIR__ . '/../class/FocusClient.class.php';
require_once __DIR__ . '/../class/NFeOutService.class.php';

$ref = GETPOST('ref', 'alpha');
$tokenPost = GETPOST('token', 'alphanohtml');
if (empty($tokenPost) || empty($_SESSION['newtoken']) || !hash_equals($_SESSION['newtoken'], $tokenPost)) {
    accessforbidden('Bad token');
}

$just = GETPOST('just', 'restricthtml');

$client = new FocusClient($db, $conf->global->FV_FISCAL_FOCUS_TOKEN, $conf->global->FV_FISCAL_ENV, $conf->global->FV_FISCAL_BASE_URL);
$service = new NFeOutService($db, $client);

try {
    $service->cancel($ref, $just);
    setEventMessages($langs->trans('NFeCancelled'), null, 'mesgs');
} catch (Exception $e) {
    setEventMessages($e->getMessage(), null, 'errors');
}

header('Location: ../nfe_view.php?ref=' . urlencode($ref));
