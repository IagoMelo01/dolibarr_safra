<?php
require_once __DIR__ . '/../../../../master.inc.php';
require_once __DIR__ . '/../../class/FocusClient.class.php';
require_once __DIR__ . '/../../class/NFeInService.class.php';

$token = $conf->global->FV_FISCAL_FOCUS_TOKEN;
if (empty($token)) {
    dol_syslog('fv_fiscal cron aborted: no token', LOG_ERR);
    exit(1);
}

$client = new FocusClient($db, $token, $conf->global->FV_FISCAL_ENV, $conf->global->FV_FISCAL_BASE_URL);
$service = new NFeInService($db, $client);

try {
    $result = $service->listReceived(array('manifestadas' => 'false'));
    if (!empty($result['notas'])) {
        foreach ($result['notas'] as $note) {
            $sql = "INSERT INTO llx_fv_nfe_in (socid_emit, socid_dest, chave, status_manifestacao) VALUES (";
            $sql .= (int) ($note['emitente_id'] ?? 0) . ',' . (int) ($note['destinatario_id'] ?? 0) . ',';
            $sql .= "'" . $db->escape($note['chave']) . "', '" . $db->escape($note['status_manifestacao'] ?? '') . "')";
            $db->query($sql);

            if (!empty($conf->global->FV_FISCAL_IMPORT_SCIENCE_AUTO)) {
                $service->manifest($note['chave'], 'ciencia');
            }
        }
    }
} catch (Exception $e) {
    dol_syslog('fv_fiscal cron error: ' . $e->getMessage(), LOG_ERR);
    exit(1);
}

exit(0);
