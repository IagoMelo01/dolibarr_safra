<?php

/**
 * Simple HTTP client for Focus NFe API.
 */
class FocusClient
{
    /** @var DoliDB */
    protected $db;

    /** @var string */
    protected $token;

    /** @var string */
    protected $baseUrl;

    /** @var int */
    protected $timeout = 30;

    public function __construct($db, $token, $env = 'homolog', $baseUrl = '')
    {
        $this->db = $db;
        $this->token = trim($token);

        if (empty($baseUrl)) {
            $this->baseUrl = $env === 'prod' ? 'https://api.focusnfe.com.br' : 'https://homologacao.focusnfe.com.br';
        } else {
            $this->baseUrl = rtrim($baseUrl, '/');
        }
    }

    public function get($path)
    {
        return $this->request('GET', $path);
    }

    public function post($path, array $payload = array())
    {
        return $this->request('POST', $path, $payload);
    }

    public function delete($path, array $payload = array())
    {
        return $this->request('DELETE', $path, $payload);
    }

    public function downloadToFile($url, $destPath)
    {
        dol_syslog(__METHOD__ . ' download ' . $url, LOG_DEBUG);
        $attempts = 0;
        $exception = null;
        while ($attempts < 3) {
            $attempts++;
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $this->token . ':',
                CURLOPT_HTTPHEADER => array('Accept: application/json', 'User-Agent: Dolibarr-fv_fiscal'),
            ));

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                $exception = new Exception($err);
            } elseif ($httpCode >= 200 && $httpCode < 300) {
                dol_mkdir(dirname($destPath));
                file_put_contents($destPath, $content);
                return $destPath;
            } else {
                $exception = new Exception('HTTP ' . $httpCode . ' - ' . $content);
            }

            sleep($attempts);
        }

        if ($exception) {
            throw $exception;
        }
    }

    protected function request($method, $path, array $payload = array())
    {
        $url = $this->prepareUrl($path);
        $body = !empty($payload) ? json_encode($payload) : null;

        $attempts = 0;
        $lastException = null;

        while ($attempts < 3) {
            $attempts++;
            $ch = curl_init();
            $headers = array('Accept: application/json', 'User-Agent: Dolibarr-fv_fiscal', 'Content-Type: application/json');

            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $this->token . ':',
                CURLOPT_HTTPHEADER => $headers,
            ));

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $this->logJob($method, $url, $body, $httpCode, $curlError, $responseBody);

            if ($curlError) {
                $lastException = new Exception($curlError);
            } elseif ($httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($responseBody, true);
                return $decoded !== null ? $decoded : $responseBody;
            } elseif (in_array($httpCode, array(429, 500, 502, 503, 504))) {
                $lastException = new Exception('HTTP ' . $httpCode . ' - ' . $responseBody);
                sleep($attempts * 2);
                continue;
            } else {
                throw new Exception('HTTP ' . $httpCode . ' - ' . $responseBody);
            }
        }

        if ($lastException) {
            throw $lastException;
        }

        throw new Exception('Unknown error communicating with Focus API');
    }

    protected function prepareUrl($path)
    {
        if (preg_match('/^https?:/i', $path)) {
            return $path;
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    protected function logJob($method, $url, $payload, $httpCode, $error, $response)
    {
        if (empty($this->db)) {
            return;
        }

        $payloadSql = isset($payload) ? "'" . $this->db->escape($payload) . "'" : 'NULL';
        $errorSql = !empty($error) ? "'" . $this->db->escape($error) . "'" : "'" . $this->db->escape(substr((string) $response, 0, 255)) . "'";
        $statusSql = "'" . $this->db->escape($httpCode >= 200 && $httpCode < 300 ? 'ok' : 'error') . "'";

        $sql = "INSERT INTO llx_fv_focus_job(type, ref, method, url, payload_json, http_code, tries, last_error, status, tms) VALUES (";
        $sql .= "'API', '', '" . $this->db->escape($method) . "', '" . $this->db->escape($url) . "', " . $payloadSql . ', ';
        $sql .= ((int) $httpCode) . ', 1, ' . $errorSql . ', ' . $statusSql . ', NOW())';

        $this->db->query($sql);
    }
}

