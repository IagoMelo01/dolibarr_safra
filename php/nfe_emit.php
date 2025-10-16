<?php
require_once __DIR__ . '/../../../main.inc.php';
require_once __DIR__ . '/../class/FocusClient.class.php';
require_once __DIR__ . '/../class/NFeOutService.class.php';
require_once __DIR__ . '/../class/NFeInService.class.php';

$action = GETPOST('action', 'alpha');
$ref = GETPOST('ref', 'alpha');
$chave = GETPOST('chave', 'alpha');

$tokenPost = GETPOST('token', 'alphanohtml');
if (empty($tokenPost) || empty($_SESSION['newtoken']) || !hash_equals($_SESSION['newtoken'], $tokenPost)) {
    accessforbidden('Bad token');
}

$client = new FocusClient($db, $conf->global->FV_FISCAL_FOCUS_TOKEN, $conf->global->FV_FISCAL_ENV, $conf->global->FV_FISCAL_BASE_URL);
$outService = new NFeOutService($db, $client);
$inService = new NFeInService($db, $client);

try {
    switch ($action) {
        case 'refresh':
            $outService->getStatus($ref);
            setEventMessages($langs->trans('StatusUpdated'), null, 'mesgs');
            header('Location: ../nfe_view.php?ref=' . urlencode($ref));
            break;
        case 'download_xml':
            $path = $outService->downloadXml($ref);
            setEventMessages($langs->trans('FileDownloaded', $path), null, 'mesgs');
            header('Location: ../nfe_view.php?ref=' . urlencode($ref));
            break;
        case 'download_danfe':
            $path = $outService->downloadDanfe($ref);
            setEventMessages($langs->trans('FileDownloaded', $path), null, 'mesgs');
            header('Location: ../nfe_view.php?ref=' . urlencode($ref));
            break;
        case 'download_in_xml':
            $path = $inService->downloadXml($chave);
            setEventMessages($langs->trans('FileDownloaded', $path), null, 'mesgs');
            header('Location: ../import_list.php');
            break;
        case 'download_in_danfe':
            $path = $inService->downloadDanfe($chave);
            setEventMessages($langs->trans('FileDownloaded', $path), null, 'mesgs');
            header('Location: ../import_list.php');
            break;
        default:
            accessforbidden();
    }
} catch (Exception $e) {
    setEventMessages($e->getMessage(), null, 'errors');
    if (!empty($ref)) {
        header('Location: ../nfe_view.php?ref=' . urlencode($ref));
    } else {
        header('Location: ../import_list.php');
    }
}
