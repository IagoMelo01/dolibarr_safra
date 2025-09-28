<?php
// talhao_geojson.php
// Retorna o GeoJSON de um talhão em JSON

require_once __DIR__ . '/../../../main.inc.php';

// Dolibarr bootstrap
if (!defined('NOSCAN')) define('NOSCAN', 1);
// require '../../main.inc.php'; // ajuste o caminho relativo se necessário

// Segurança básica (opcional: verifique permissões específicas do seu módulo)
if (empty($user->id)) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('error' => 'Acesso negado.'), JSON_UNESCAPED_UNICODE);
    exit;
}

$id = GETPOST('id', 'int');
if (empty($id)) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('error' => 'ID inválido.'), JSON_UNESCAPED_UNICODE);
    exit;
}

// Carregue sua classe Talhao (ajuste o caminho)
dol_include_once('/safra/class/talhao.class.php');

$talhao = new Talhao($db);
$res = $talhao->fetch($id);
if ($res <= 0) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('error' => 'Talhão não encontrado.'), JSON_UNESCAPED_UNICODE);
    exit;
}

// Supondo que o atributo seja $talhao->geo_json contendo string JSON válida
$geojsonRaw = $talhao->geo_json;
// echo "<script>console.log(". $geojsonRaw . ");</script>";

// Se estiver armazenado como TEXT/JSON string, decodamos
$geojson = null;
if (!empty($geojsonRaw)) {
    $geojson = json_decode($geojsonRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('error' => 'GeoJSON inválido no talhão.'), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode(array('geojson' => $geojson), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
