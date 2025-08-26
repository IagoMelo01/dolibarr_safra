<?php
// header('Content-Type: application/json');

// Configurações
$clientId = 'dc95178b-7f86-4566-99b5-30efd58f45a0';
$clientSecret = 'QPyvZhNsHJdlhICsucdzY1Rqx1vD5N7B';
// 📍 Área do campo (longitude/latitude)
$bbox = [-47.89, -15.82, -47.88, -15.81];

// 🗓️ Últimas 8 semanas
$from = (new DateTime('-8 weeks'))->format('Y-m-d\T00:00:00\Z');
$to = (new DateTime())->format('Y-m-d\T23:59:59\Z');

function getToken($clientId, $clientSecret) {
    $url = 'https://services.sentinel-hub.com/oauth/token';
    $data = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


    $resp = curl_exec($ch);
	print_r($resp);
    curl_close($ch);

    $json = json_decode($resp, true);
    if (!isset($json['access_token'])) {
        throw new Exception('Token inválido ou não retornado: ' . $resp);
    }

    return $json['access_token'];
}

function getNDVI($token, $bbox, $from, $to) {
    $url = 'https://services.sentinel-hub.com/api/v1/statistics';

    $evalscript = <<<EOD
//VERSION=3
function setup() {
  return {
    input: [{
      bands: ["B04", "B08", "SCL", "dataMask"]
    }],
    output: [
      { id: "data", bands: 3 },
      { id: "scl", sampleType: "INT8", bands: 1 },
      { id: "dataMask", bands: 1 }
    ]
  };
}
function evaluatePixel(samples) {
  let index = (samples.B08 - samples.B04) / (samples.B08 + samples.B04);
  return {
    data: [index, samples.B08, samples.B04],
    dataMask: [samples.dataMask],
    scl: [samples.SCL]
  };
}
EOD;

    $body = [
        "input" => [
            "bounds" => [
                "bbox" => $bbox,
                "properties" => [
                    "crs" => "http://www.opengis.net/def/crs/EPSG/0/4326"
                ]
            ],
            "data" => [[
                "type" => "sentinel-2-l2a",
                "dataFilter" => []
            ]]
        ],
        "aggregation" => [
            "timeRange" => ["from" => $from, "to" => $to],
            "aggregationInterval" => ["of" => "P10D"],
            "width" => 512,
            "height" => 343.697,
            "evalscript" => $evalscript
        ],
        "calculations" => ["default" => new stdClass()] // obrigatório, mesmo vazio
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]
    ]);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


    $resp = curl_exec($ch);
	print_r($resp);
    curl_close($ch);

    $json = json_decode($resp, true);
    if (isset($json['error'])) {
        throw new Exception(json_encode($json['error']));
    }

    $result = [];
    foreach ($json['data'] as $item) {
        $date = substr($item['interval']['from'], 0, 10);
        $mean = $item['outputs']['data']['bands']['B0']['stats']['mean'] ?? null;
        if ($mean !== null) {
            $result[] = [
                'date' => $date,
                'ndvi' => round($mean, 4)
            ];
        }
    }

    return $result;
}

try {
    $token = getToken($clientId, $clientSecret);
    $ndviSeries = getNDVI($token, $bbox, $from, $to);
    echo json_encode($ndviSeries, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
