<?php
// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
include_once DOL_DOCUMENT_ROOT.'/custom/safra/class/talhao.class.php';
include_once DOL_DOCUMENT_ROOT.'/custom/safra/class/ndvi.class.php';
include_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

// Load translation files required by the page
$langs->loadLangs(array("safra@safra"));

$action = GETPOST('action', 'aZ09');

$max = 5;
$now = dol_now();

// Security check - Protection if external user
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}
global $conf;
echo '<pre>';
// print_r($conf);
echo '</pre>';
echo '<hr>';



$user = new User($db);
$user->fetch(1);

echo '<pre>';
// print_r($user);
// echo '</pre>';


dol_include_once('/safra/class/janelaplantio.class.php', 'JanelaPlantio');
				dol_include_once('/projet/class/zoneamento.class.php', 'Zoneamento');
				dol_include_once('/projet/class/project.class.php', 'Project');
				dol_include_once('/safra/class/cultura.class.php', 'Cultura');
				dol_include_once('/safra/class/municipio.class.php', 'Municipio');


				$jp = new JanelaPlantio($db);
				$jp->fetch($object->id);
				$jp->ref = '' . $object->id . '_' . $object->label;

				$key_embrapa_public = $conf->global->SAFRA_API_EMBRAPA_PUBLIC;
				$key_embrapa_private = $conf->global->SAFRA_API_EMBRAPA_PRIVATE;
				$access_token = '';

				$token_url = 'https://api.cnptia.embrapa.br/token';

				// Credenciais Base64 (formato: client_id:client_secret)
				$client_credentials = base64_encode($key_embrapa_public . ':' . $key_embrapa_private);

				// Iniciando a sessão cURL
				$ch = curl_init();

				// Configurando a requisição cURL
				curl_setopt($ch, CURLOPT_URL, $token_url);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Authorization: Basic ' . $client_credentials,
					'Content-Type: application/x-www-form-urlencoded'
				));


				/*			RETIRAR EM PRODUÇÃO 	***********************************************/

				// Ignorando a verificação SSL (apenas para teste, NÃO recomendado em produção)
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

				/***********************************/


				// Dados para enviar com a requisição (grant_type)
				curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');

				// Executando a requisição e recebendo a resposta
				$response = curl_exec($ch);

				// Verificando erros
				if (curl_errno($ch)) {
					echo 'Erro na requisição: ' . curl_error($ch);
				} else {
					// Decodificando a resposta JSON
					$response_data = json_decode($response, true);
					if (isset($response_data['access_token'])) {
						// Exibindo o token de acesso
						// echo 'Access Token: ' . $response_data['access_token'];
						$access_token = $response_data['access_token'];
					} else {
						echo 'Erro ao obter o token de acesso: ' . $response;
						// break;
					}
				}

				// Fechando a sessão cURL
				curl_close($ch);

				$plano = new Project($db);
				$plano->fetch($jp->fk_project);

				$obj_cultura = new Cultura($db);
				$obj_municipio = new Municipio($db);
				$extrafields = new Project($db);
				// $extrafields->fetch_optionals($jp->fk_project);
				// $obj_cultura->fetch($extrafields->array_options['options_fk_cultura']);
				// $obj_municipio->fetch($extrafields->array_options['options_fk_municipio']);

				$idCultura = 60; // $obj_cultura->id; // Exemplo de idCultura
				$codigoIBGE = 3147006; // $obj_municipio->cod_ibge; // Código IBGE da cidade
				$risco = 20; // Exemplo de risco
				echo $access_token;

				// URL do endpoint com os parâmetros de consulta
				$url = 'https://api.cnptia.embrapa.br/agritec/v2/zoneamento?idCultura=' . $idCultura . '&codigoIBGE=' . $codigoIBGE . '&risco=' . $risco;

				// Inicializando a sessão cURL
				$ch = curl_init();

				// Definindo as opções do cURL
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Authorization: Bearer ' . $access_token, // Adicionando o token de acesso no cabeçalho
					'Content-Type: application/json'
				));


				/*			RETIRAR EM PRODUÇÃO 	***********************************************/

				// Ignorando a verificação SSL (apenas para teste, NÃO recomendado em produção)
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

				/***********************************/

				// Executando a requisição
				$response = curl_exec($ch);

				// Verificando se houve erro na requisição
				if (curl_errno($ch)) {
					echo 'Erro na requisição: ' . curl_error($ch);
				} else {
					// Decodificando a resposta JSON
					$response_data = json_decode($response, true);

					// Exibindo a resposta
					if (isset($response_data['error'])) {
						echo 'Erro ao obter os dados: ';
						print_r($response_data['error']);
					} else {
						echo "Dados retornados:\n";
						print_r($response_data);
					}
				}

				// Fechando a sessão cURL
				curl_close($ch);
				echo '</pre>';
phpinfo();
