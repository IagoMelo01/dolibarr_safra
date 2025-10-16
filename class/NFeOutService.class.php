<?php

require_once __DIR__ . '/FocusClient.class.php';
require_once __DIR__ . '/FiscalRuleSet.class.php';
require_once __DIR__ . '/StockIntegrationService.class.php';
require_once __DIR__ . '/FinancePosting.class.php';

class NFeOutService
{
    /** @var DoliDB */
    protected $db;

    /** @var FocusClient */
    protected $client;

    /** @var FiscalRuleSet */
    protected $ruleSet;

    public function __construct(DoliDB $db, FocusClient $client)
    {
        $this->db = $db;
        $this->client = $client;
        $this->ruleSet = new FiscalRuleSet($db);
    }

    public function createAndSend($ref, array $payload)
    {
        $this->db->begin();

        if (!empty($payload['items'])) {
            foreach ($payload['items'] as &$line) {
                $this->ruleSet->resolveItemTaxes($line);
            }
        }

        $dbRes = $this->persistNFeOut($ref, $payload, 'processando');
        if ($dbRes < 0) {
            $this->db->rollback();
            throw new Exception('Unable to persist NF-e before sending');
        }

        $response = $this->client->post('/v2/nfe?ref=' . urlencode($ref), $payload);

        $this->updateFromResponse($ref, $response);

        $this->db->commit();

        return $response;
    }

    public function getStatus($ref)
    {
        $response = $this->client->get('/v2/nfe/' . urlencode($ref));
        $this->updateFromResponse($ref, $response);
        return $response;
    }

    public function cancel($ref, $justification)
    {
        $payload = array('justificativa' => $justification);
        $response = $this->client->delete('/v2/nfe/' . urlencode($ref), $payload);
        $this->updateFromResponse($ref, $response, 'cancelado');
        return $response;
    }

    public function cce($ref, $correction)
    {
        $payload = array('correcao' => $correction);
        return $this->client->post('/v2/nfe/' . urlencode($ref) . '/carta_correcao', $payload);
    }

    public function downloadXml($ref)
    {
        $info = $this->fetchNFeOut($ref);
        if (!$info) {
            throw new Exception('NF-e not found');
        }

        $status = $this->client->get('/v2/nfe/' . urlencode($ref));
        $link = $status['links']['xml'] ?? '';
        if (empty($link)) {
            throw new Exception('No XML link available');
        }

        $dest = $this->buildEcmPath($ref, 'xml');
        $this->client->downloadToFile($link, $dest);
        $this->updateFilePath($info['rowid'], 'xml_path', $dest);
        return $dest;
    }

    public function downloadDanfe($ref)
    {
        $info = $this->fetchNFeOut($ref);
        if (!$info) {
            throw new Exception('NF-e not found');
        }

        $status = $this->client->get('/v2/nfe/' . urlencode($ref));
        $link = $status['links']['danfe'] ?? '';
        if (empty($link)) {
            throw new Exception('No DANFE link available');
        }

        $dest = $this->buildEcmPath($ref, 'pdf');
        $this->client->downloadToFile($link, $dest);
        $this->updateFilePath($info['rowid'], 'danfe_pdf_path', $dest);
        return $dest;
    }

    protected function persistNFeOut($ref, array $payload, $status)
    {
        $socidEmit = $payload['emitente']['id'] ?? 0;
        $socidDest = $payload['destinatario']['id'] ?? 0;

        $sql = 'INSERT INTO llx_fv_nfe_out (socid_emit, socid_dest, serie, numero, status, ambiente, finalidade, natureza_op, total_prod, total_nf, dh_emissao, ref_unique) VALUES (';
        $sql .= (int) $socidEmit . ',' . (int) $socidDest . ',';
        $sql .= "'" . $this->db->escape($payload['serie'] ?? '') . "',";
        $sql .= "'" . $this->db->escape($payload['numero'] ?? '') . "',";
        $sql .= "'" . $this->db->escape($status) . "',";
        $sql .= "'" . $this->db->escape($payload['ambiente'] ?? 'homolog') . "',";
        $sql .= "'" . $this->db->escape($payload['finalidade'] ?? '') . "',";
        $sql .= "'" . $this->db->escape($payload['natureza_operacao'] ?? '') . "',";
        $sql .= (float) ($payload['totais']['total_produtos'] ?? 0) . ',';
        $sql .= (float) ($payload['totais']['total_nfe'] ?? 0) . ',';
        $sql .= $this->db->idate(dol_now()) . ',';
        $sql .= "'" . $this->db->escape($ref) . "')";

        if (!$this->db->query($sql)) {
            dol_syslog(__METHOD__ . ' error ' . $this->db->lasterror(), LOG_ERR);
            return -1;
        }

        $nfeId = $this->db->last_insert_id('llx_fv_nfe_out');

        $this->db->query('DELETE FROM llx_fv_nfe_out_line WHERE fk_nfe_out = ' . ((int) $nfeId));

        if (!empty($payload['items'])) {
            foreach ($payload['items'] as $line) {
                $lineSql = 'INSERT INTO llx_fv_nfe_out_line (fk_nfe_out, fk_product, descricao, ncm, cfop, cst_csosn, ucom, qcom, vuncom, vprod, vbc_icms, vicms, vipi, vpis, vcofins) VALUES (';
                $lineSql .= (int) $nfeId . ',' . (int) ($line['product_id'] ?? 0) . ',';
                $lineSql .= "'" . $this->db->escape($line['descricao'] ?? $line['descricao_produto'] ?? '') . "',";
                $lineSql .= "'" . $this->db->escape($line['ncm'] ?? '') . "',";
                $lineSql .= "'" . $this->db->escape($line['cfop'] ?? '') . "',";
                $lineSql .= "'" . $this->db->escape($line['cst_csosn'] ?? '') . "',";
                $lineSql .= "'" . $this->db->escape($line['unidade'] ?? $line['ucom'] ?? '') . "',";
                $lineSql .= (float) ($line['quantidade'] ?? $line['qcom'] ?? 0) . ',';
                $lineSql .= (float) ($line['valor_unitario'] ?? $line['vuncom'] ?? 0) . ',';
                $lineSql .= (float) ($line['valor_total'] ?? $line['vprod'] ?? 0) . ',';
                $lineSql .= (float) ($line['vbc_icms'] ?? 0) . ',';
                $lineSql .= (float) ($line['vicms'] ?? 0) . ',';
                $lineSql .= (float) ($line['vipi'] ?? 0) . ',';
                $lineSql .= (float) ($line['vpis'] ?? 0) . ',';
                $lineSql .= (float) ($line['vcofins'] ?? 0) . ')';
                $this->db->query($lineSql);
            }
        }

        return $nfeId;
    }

    protected function updateFromResponse($ref, $response, $forcedStatus = '')
    {
        if (!is_array($response)) {
            return;
        }

        $status = $forcedStatus ?: ($response['status'] ?? 'processando');
        $chave = $response['chave'] ?? '';
        $dhAut = empty($response['data_autorizacao']) ? null : strtotime($response['data_autorizacao']);

        $sql = 'UPDATE llx_fv_nfe_out SET status = \' . $this->db->escape($status) . '\',';
        if (!empty($chave)) {
            $sql .= " chave = '" . $this->db->escape($chave) . '\',';
        }
        if ($dhAut) {
            $sql .= ' dh_autorizacao = ' . $this->db->idate($dhAut) . ',';
        }
        $sql .= ' tms = NOW() WHERE ref_unique = \' . $this->db->escape($ref) . '\'';
        $this->db->query($sql);

        if ($status === 'autorizado') {
            $nfe = $this->fetchNFeOut($ref);
            if ($nfe && empty($nfe['fk_facture'])) {
                $finance = new FinancePosting($this->db);
                $invoiceId = $finance->createCustomerInvoiceFromNFeOut($nfe['rowid']);
                if ($invoiceId > 0) {
                    $this->db->query('UPDATE llx_fv_nfe_out SET fk_facture = ' . ((int) $invoiceId) . ' WHERE rowid = ' . ((int) $nfe['rowid']));
                }
                $stock = new StockIntegrationService($this->db);
                $stock->applyFromNFeOut($nfe['rowid']);
            }
        }
    }

    protected function fetchNFeOut($ref)
    {
        $sql = 'SELECT * FROM llx_fv_nfe_out WHERE ref_unique = \' . $this->db->escape($ref) . '\'';
        $res = $this->db->query($sql);
        if ($res && $this->db->num_rows($res) > 0) {
            return $this->db->fetch_array($res);
        }
        return null;
    }

    protected function buildEcmPath($ref, $ext)
    {
        global $conf;
        $now = dol_now();
        $year = dol_print_date($now, '%Y');
        $month = dol_print_date($now, '%m');
        $dir = !empty($conf->ecm->dir_output) ? $conf->ecm->dir_output : DOL_DATA_ROOT;
        $dir .= '/fiscal/' . $year . '/' . $month;
        dol_mkdir($dir);
        $file = $dir . '/' . dol_sanitizeFileName($ref) . '.' . $ext;
        return $file;
    }

    protected function updateFilePath($rowid, $field, $path)
    {
        $sql = 'UPDATE llx_fv_nfe_out SET ' . $field . " = '" . $this->db->escape($path) . "', tms = NOW() WHERE rowid = " . ((int) $rowid);
        $this->db->query($sql);
    }
}
