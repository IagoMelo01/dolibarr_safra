<?php
require_once __DIR__ . '/../../../main.inc.php';
require_once __DIR__ . '/../class/FocusClient.class.php';
require_once __DIR__ . '/../class/NFeOutService.class.php';

$secret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
if (empty($conf->global->FV_FISCAL_WEBHOOK_SECRET) || $secret !== $conf->global->FV_FISCAL_WEBHOOK_SECRET) {
    http_response_code(403);
    echo json_encode(array('error' => 'invalid secret'));
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (empty($payload['ref'])) {
    http_response_code(400);
    echo json_encode(array('error' => 'missing ref'));
    exit;
}

$client = new FocusClient($db, $conf->global->FV_FISCAL_FOCUS_TOKEN, $conf->global->FV_FISCAL_ENV, $conf->global->FV_FISCAL_BASE_URL);
$service = new NFeOutService($db, $client);

try {
    $status = $service->getStatus($payload['ref']);
    if (($status['status'] ?? '') === 'autorizado') {
        $service->downloadXml($payload['ref']);
        $service->downloadDanfe($payload['ref']);
    }
    echo json_encode(array('ok' => true));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()));
}
