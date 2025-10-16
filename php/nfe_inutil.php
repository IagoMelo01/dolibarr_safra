<?php
require_once __DIR__ . '/../../../main.inc.php';
require_once __DIR__ . '/../class/FocusClient.class.php';

$tokenPost = GETPOST('token', 'alphanohtml');
if (empty($tokenPost) || empty($_SESSION['newtoken']) || !hash_equals($_SESSION['newtoken'], $tokenPost)) {
    accessforbidden('Bad token');
}

$ref = GETPOST('ref', 'alpha');
$client = new FocusClient($db, $conf->global->FV_FISCAL_FOCUS_TOKEN, $conf->global->FV_FISCAL_ENV, $conf->global->FV_FISCAL_BASE_URL);

try {
    $client->post('/v2/nfe/inutilizacao', array('ref' => $ref));
    setEventMessages($langs->trans('NFeInutilizada'), null, 'mesgs');
} catch (Exception $e) {
    setEventMessages($e->getMessage(), null, 'errors');
}

header('Location: ../nfe_list.php');
