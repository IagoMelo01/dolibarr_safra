<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/embrapaapi.class.php
 * \ingroup     safra
 * \brief       Helper to interact with Embrapa public APIs.
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';

/**
 * Class EmbrapaApi.
 */
class EmbrapaApi
{
    /**
     * @var DoliDB Database handler
     */
    private $db;

    /**
     * @var string
     */
    private $tokenUrl = 'https://api.cnptia.embrapa.br/token';

    /**
     * @var string
     */
    private $produtividadeUrl;

    /**
     * @var string
     */
    private $zoneamentoUrl;

    /**
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->clientId = getDolGlobalString('SAFRA_API_EMBRAPA_PUBLIC');
        $this->clientSecret = getDolGlobalString('SAFRA_API_EMBRAPA_PRIVATE');
        $baseUrl = getDolGlobalString('SAFRA_API_EMBRAPA_PRODUTIVIDADE_URL', 'https://api.cnptia.embrapa.br/agritec/v2/produtividade');
        $this->produtividadeUrl = rtrim($baseUrl, '/');

        $zoneamentoBaseUrl = getDolGlobalString('SAFRA_API_EMBRAPA_ZONEAMENTO_URL', 'https://api.cnptia.embrapa.br/agritec/v2/zoneamento');
        $this->zoneamentoUrl = rtrim($zoneamentoBaseUrl, '/');
    }

    /**
     * Fetch productivity estimation from Embrapa API.
     *
     * @param array  $parameters Query string parameters
     * @param string $error      Error message on failure
     *
     * @return array|null
     */
    public function fetchProdutividade(array $parameters, &$error = '')
    {
        $accessToken = $this->fetchToken($error);
        if (empty($accessToken)) {
            return null;
        }

        $queryString = http_build_query($parameters);
        $separator = strpos($this->produtividadeUrl, '?') === false ? '?' : '&';
        $url = $this->produtividadeUrl . $separator . $queryString;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ));

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($status >= 200 && $status < 300 && is_array($decoded)) {
            return $decoded;
        }

        if (!empty($decoded)) {
            if (isset($decoded['error'])) {
                $error = $this->formatErrorObject($decoded['error']);
            } else {
                $error = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        } else {
            $error = $this->translateHttpError($status, $response);
        }

        return null;
    }

    /**
     * Fetch climate risk zoning planting windows from Embrapa API.
     *
     * @param array  $parameters Query string parameters (expects idCultura, codigoIBGE, risco)
     * @param string $error      Error message on failure
     *
     * @return array|null
     */
    public function fetchZoneamento(array $parameters, &$error = '')
    {
        $accessToken = $this->fetchToken($error);
        if (empty($accessToken)) {
            return null;
        }

        $queryString = http_build_query($parameters);
        $separator = strpos($this->zoneamentoUrl, '?') === false ? '?' : '&';
        $url = $this->zoneamentoUrl . $separator . $queryString;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ));

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($status >= 200 && $status < 300 && is_array($decoded)) {
            return $decoded;
        }

        if (!empty($decoded)) {
            if (isset($decoded['error'])) {
                $error = $this->formatErrorObject($decoded['error']);
            } else {
                $error = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        } else {
            $error = $this->translateHttpError($status, $response);
        }

        return null;
    }

    /**
     * Retrieve access token for Embrapa API.
     *
     * @param string $error Error message on failure
     *
     * @return string|null
     */
    private function fetchToken(&$error = '')
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            $error = 'Missing Embrapa API credentials.';
            return null;
        }

        $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);

        $ch = curl_init($this->tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded'
        ));

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if ($status >= 200 && $status < 300 && is_array($decoded) && !empty($decoded['access_token'])) {
            return $decoded['access_token'];
        }

        if (isset($decoded['error_description'])) {
            $error = $decoded['error_description'];
        } elseif (isset($decoded['error'])) {
            $error = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
        } else {
            $error = $this->translateHttpError($status, $response);
        }

        return null;
    }

    /**
     * Build a readable message from Embrapa error structure.
     *
     * @param array|string $error Error structure
     *
     * @return string
     */
    private function formatErrorObject($error)
    {
        if (is_string($error)) {
            return $error;
        }

        if (!is_array($error)) {
            return '';
        }

        $parts = array();
        if (isset($error['summary'])) {
            $parts[] = $error['summary'];
        }
        if (isset($error['description'])) {
            $parts[] = $error['description'];
        }

        if (!empty($error['fieldErrors']) && is_array($error['fieldErrors'])) {
            foreach ($error['fieldErrors'] as $fieldError) {
                $fieldSummary = array();
                if (isset($fieldError['field'])) {
                    $fieldSummary[] = $fieldError['field'];
                }
                if (isset($fieldError['summary'])) {
                    $fieldSummary[] = $fieldError['summary'];
                }
                if (!empty($fieldSummary)) {
                    $parts[] = implode(' - ', $fieldSummary);
                }
            }
        }

        return implode('\n', array_filter($parts));
    }

    /**
     * Provide friendly message for HTTP errors.
     *
     * @param int    $status   HTTP status code
     * @param string $response Raw response body
     *
     * @return string
     */
    private function translateHttpError($status, $response)
    {
        if (empty($status)) {
            return empty($response) ? 'Unexpected error while contacting Embrapa API.' : $response;
        }

        return 'HTTP ' . $status . ' - ' . (!empty($response) ? $response : 'Unexpected error while contacting Embrapa API.');
    }
}
