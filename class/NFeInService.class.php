<?php

require_once __DIR__ . '/FocusClient.class.php';
require_once __DIR__ . '/StockIntegrationService.class.php';
require_once __DIR__ . '/FinancePosting.class.php';

class NFeInService
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

    public function listReceived(array $params = array())
    {
        $query = http_build_query($params);
        return $this->client->get('/v2/nfe/entradas' . ($query ? '?' . $query : ''));
    }

    public function manifest($chave, $tipo)
    {
        $payload = array('tipo' => $tipo);
        return $this->client->post('/v2/nfe/' . urlencode($chave) . '/manifestar', $payload);
    }

    public function downloadXml($chave)
    {
        $status = $this->client->get('/v2/nfe/' . urlencode($chave));
        $link = $status['links']['xml'] ?? '';
        if (empty($link)) {
            throw new Exception('No XML link available');
        }
        $dest = $this->buildEcmPath($chave, 'xml');
        $this->client->downloadToFile($link, $dest);
        $this->updateInPath($chave, 'xml_path', $dest);
        return $dest;
    }

    public function downloadDanfe($chave)
    {
        $status = $this->client->get('/v2/nfe/' . urlencode($chave));
        $link = $status['links']['danfe'] ?? '';
        if (empty($link)) {
            throw new Exception('No DANFE link available');
        }
        $dest = $this->buildEcmPath($chave, 'pdf');
        $this->client->downloadToFile($link, $dest);
        $this->updateInPath($chave, 'danfe_pdf_path', $dest);
        return $dest;
    }

    public function createSupplierInvoice($nfeInId)
    {
        $sql = 'SELECT * FROM llx_fv_nfe_in WHERE rowid = ' . ((int) $nfeInId);
        $res = $this->db->query($sql);
        if (!$res || $this->db->num_rows($res) === 0) {
            throw new Exception('NF-e entrada não encontrada');
        }
        $data = $this->db->fetch_array($res);

        $finance = new FinancePosting($this->db);
        return $finance->createSupplierInvoiceFromNFeIn($nfeInId);
    }

    public function stockEntry($nfeInId)
    {
        $stock = new StockIntegrationService($this->db);
        return $stock->applyFromNFeIn($nfeInId);
    }

    protected function updateInPath($chave, $field, $path)
    {
        $sql = 'UPDATE llx_fv_nfe_in SET ' . $field . " = '" . $this->db->escape($path) . "', tms = NOW() WHERE chave = '" . $this->db->escape($chave) . "'";
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
        $file = $dir . '/' . dol_sanitizeFileName($identifier) . '.' . $ext;
        return $file;
    }
}
