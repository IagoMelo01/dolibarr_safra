<?php
require_once __DIR__ . '/../../../main.inc.php';
require_once __DIR__ . '/../class/FocusClient.class.php';
require_once __DIR__ . '/../class/MDFeService.class.php';

$tokenPost = GETPOST('token', 'alphanohtml');
if (empty($tokenPost) || empty($_SESSION['newtoken']) || !hash_equals($_SESSION['newtoken'], $tokenPost)) {
    accessforbidden('Bad token');
}

$action = GETPOST('action', 'alpha');
$ref = GETPOST('ref', 'alpha');

$client = new FocusClient($db, $conf->global->FV_FISCAL_FOCUS_TOKEN, $conf->global->FV_FISCAL_ENV, $conf->global->FV_FISCAL_BASE_URL);
$service = new MDFeService($db, $client);

try {
    switch ($action) {
        case 'status':
            $service->getStatus($ref);
            break;
        case 'download_xml':
            $service->downloadXml($ref);
            break;
        case 'download_damdfe':
            $service->downloadDamdfe($ref);
            break;
        default:
            accessforbidden();
    }
    setEventMessages($langs->trans('ActionDone'), null, 'mesgs');
} catch (Exception $e) {
    setEventMessages($e->getMessage(), null, 'errors');
}

header('Location: ../mdfe_list.php');
