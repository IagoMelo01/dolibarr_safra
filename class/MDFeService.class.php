<?php

require_once __DIR__ . '/FocusClient.class.php';

class MDFeService
{
    /** @var DoliDB */
    protected $db;

    /** @var FocusClient */
    protected $client;

    public function __construct(DoliDB $db, FocusClient $client)
    {
        $this->db = $db;
        $this->client = $client;
    }

    public function createAndSend($ref, array $payload)
    {
        $response = $this->client->post('/v2/mdfe?ref=' . urlencode($ref), $payload);
        $this->persist($ref, $payload, $response);
        return $response;
    }

    public function getStatus($ref)
    {
        $response = $this->client->get('/v2/mdfe/' . urlencode($ref));
        $this->updateStatus($ref, $response);
        return $response;
    }

    public function encerrar($ref, array $payload)
    {
        $response = $this->client->post('/v2/mdfe/' . urlencode($ref) . '/encerrar', $payload);
        $this->updateStatus($ref, $response);
        return $response;
    }

    public function cancelar($ref, array $payload)
    {
        $response = $this->client->post('/v2/mdfe/' . urlencode($ref) . '/cancelar', $payload);
        $this->updateStatus($ref, $response);
        return $response;
    }

    public function downloadXml($ref)
    {
        $status = $this->client->get('/v2/mdfe/' . urlencode($ref));
        $link = $status['links']['xml'] ?? '';
        if (empty($link)) {
            throw new Exception('No XML link available');
        }
        $dest = $this->buildEcmPath($ref, 'xml');
        $this->client->downloadToFile($link, $dest);
        $this->updatePath($ref, 'xml_path', $dest);
        return $dest;
    }

    public function downloadDamdfe($ref)
    {
        $status = $this->client->get('/v2/mdfe/' . urlencode($ref));
        $link = $status['links']['damdfe'] ?? '';
        if (empty($link)) {
            throw new Exception('No DAMDFE link available');
        }
        $dest = $this->buildEcmPath($ref, 'pdf');
        $this->client->downloadToFile($link, $dest);
        $this->updatePath($ref, 'damdfe_pdf_path', $dest);
        return $dest;
    }

    protected function persist($ref, array $payload, $response)
    {
        $socidEmit = $payload['emitente']['id'] ?? 0;
        $sql = 'INSERT INTO llx_fv_mdfe (socid_emit, numero, serie, status, chave) VALUES (';
        $sql .= (int) $socidEmit . ',';
        $sql .= "'" . $this->db->escape($payload['numero'] ?? '') . "',";
        $sql .= "'" . $this->db->escape($payload['serie'] ?? '') . "',";
        $sql .= "'" . $this->db->escape($response['status'] ?? 'processando') . "',";
        $sql .= "'" . $this->db->escape($response['chave'] ?? '') . "')";
        $this->db->query($sql);
    }

    protected function updateStatus($ref, $response)
    {
        if (!is_array($response)) {
            return;
        }
        $sql = 'UPDATE llx_fv_mdfe SET status = \' . $this->db->escape($response['status'] ?? '') . '\';
        if (!empty($response['chave'])) {
            $sql .= ", chave = '" . $this->db->escape($response['chave']) . "'";
        }
        if (!empty($response['data_cancelamento'])) {
            $sql .= ', cancelado_dt = ' . $this->db->idate(strtotime($response['data_cancelamento']));
        }
        if (!empty($response['data_encerramento'])) {
            $sql .= ', encerrado_dt = ' . $this->db->idate(strtotime($response['data_encerramento']));
        }
        $sql .= ' WHERE chave = \' . $this->db->escape($response['chave'] ?? $ref) . '\'';
        $this->db->query($sql);
    }

    protected function updatePath($ref, $field, $path)
    {
        $sql = 'UPDATE llx_fv_mdfe SET ' . $field . " = '" . $this->db->escape($path) . "', tms = NOW() WHERE chave = '" . $this->db->escape($ref) . "'";
        $this->db->query($sql);
    }

    protected function buildEcmPath($identifier, $ext)
    {
        global $conf;
        $now = dol_now();
        $year = dol_print_date($now, '%Y');
        $month = dol_print_date($now, '%m');
        $dir = !empty($conf->ecm->dir_output) ? $conf->ecm->dir_output : DOL_DATA_ROOT;
        $dir .= '/fiscal/' . $year . '/' . $month;
        dol_mkdir($dir);
        return $dir . '/' . dol_sanitizeFileName($identifier) . '.' . $ext;
    }
}
