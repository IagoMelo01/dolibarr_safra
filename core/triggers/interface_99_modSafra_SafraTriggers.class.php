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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modSafra_SafraTriggers.class.php
 * \ingroup safra
 * \brief   Example trigger.
 *
 * Put detailed description here.
 *
 * \remarks You can create other triggers by copying this one.
 * - File name should be either:
 *      - interface_99_modSafra_MyTrigger.class.php
 *      - interface_99_all_MyTrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMytrigger
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
dol_include_once('/safra/class/aplicacao.class.php');


/**
 *  Class of triggers for Safra module
 */
class InterfaceSafraTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "Safra triggers.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'safra@safra';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{

		global $conf;
		if (!isModEnabled('safra')) {
			return 0; // If module is not enabled, we do nothing
		}

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		// You can isolate code for each action in a separate method: this method should be named like the trigger in camelCase.
		// For example : COMPANY_CREATE => public function companyCreate($action, $object, User $user, Translate $langs, Conf $conf)
		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		}

		// Or you can execute some code here
		switch ($action) {
			// Users
			//case 'USER_CREATE':
			//case 'USER_MODIFY':
			//case 'USER_NEW_PASSWORD':
			//case 'USER_ENABLEDISABLE':
			//case 'USER_DELETE':

			// Actions
			//case 'ACTION_MODIFY':
			//case 'ACTION_CREATE':
			//case 'ACTION_DELETE':

			// Groups
			//case 'USERGROUP_CREATE':
			//case 'USERGROUP_MODIFY':
			//case 'USERGROUP_DELETE':

			// Companies
			//case 'COMPANY_CREATE':
			//case 'COMPANY_MODIFY':
			//case 'COMPANY_DELETE':

			// Contacts
			//case 'CONTACT_CREATE':
			//case 'CONTACT_MODIFY':
			//case 'CONTACT_DELETE':
			//case 'CONTACT_ENABLEDISABLE':

                        case 'MYOBJECT_CREATE':
                                return $this->handleAplicacaoCreate($object, $user, $langs);
                        case 'MYOBJECT_DELETE':
                                return $this->handleAplicacaoDelete($object, $user);
                        case 'TASK_MODIFY':
                                return $this->handleTaskModify($object, $user);
                        // Products
                        case 'PRODUCT_CREATE':
                        case 'PRODUCT_MODIFY':
                                return $this->persistSafraProductLinks($object, $user, $langs);
                        //case 'PRODUCT_DELETE':
			//case 'PRODUCT_PRICE_MODIFY':
			//case 'PRODUCT_SET_MULTILANGS':
			//case 'PRODUCT_DEL_MULTILANGS':

			//Stock mouvement
			//case 'STOCK_MOVEMENT':

			//MYECMDIR
			//case 'MYECMDIR_CREATE':
			//case 'MYECMDIR_MODIFY':
			//case 'MYECMDIR_DELETE':

			// Sales orders
			//case 'ORDER_CREATE':
			//case 'ORDER_MODIFY':
			//case 'ORDER_VALIDATE':
			//case 'ORDER_DELETE':
			//case 'ORDER_CANCEL':
			//case 'ORDER_SENTBYMAIL':
			//case 'ORDER_CLASSIFY_BILLED':
			//case 'ORDER_SETDRAFT':
			//case 'LINEORDER_INSERT':
			//case 'LINEORDER_UPDATE':
			//case 'LINEORDER_DELETE':

			// Supplier orders
			//case 'ORDER_SUPPLIER_CREATE':
			//case 'ORDER_SUPPLIER_MODIFY':
			//case 'ORDER_SUPPLIER_VALIDATE':
			//case 'ORDER_SUPPLIER_DELETE':
			//case 'ORDER_SUPPLIER_APPROVE':
			//case 'ORDER_SUPPLIER_REFUSE':
			//case 'ORDER_SUPPLIER_CANCEL':
			//case 'ORDER_SUPPLIER_SENTBYMAIL':
			//case 'ORDER_SUPPLIER_RECEIVE':
			//case 'LINEORDER_SUPPLIER_DISPATCH':
			//case 'LINEORDER_SUPPLIER_CREATE':
			//case 'LINEORDER_SUPPLIER_UPDATE':
			//case 'LINEORDER_SUPPLIER_DELETE':

			// Proposals
			//case 'PROPAL_CREATE':
			//case 'PROPAL_MODIFY':
			//case 'PROPAL_VALIDATE':
			//case 'PROPAL_SENTBYMAIL':
			//case 'PROPAL_CLOSE_SIGNED':
			//case 'PROPAL_CLOSE_REFUSED':
			//case 'PROPAL_DELETE':
			//case 'LINEPROPAL_INSERT':
			//case 'LINEPROPAL_UPDATE':
			//case 'LINEPROPAL_DELETE':

			// SupplierProposal
			//case 'SUPPLIER_PROPOSAL_CREATE':
			//case 'SUPPLIER_PROPOSAL_MODIFY':
			//case 'SUPPLIER_PROPOSAL_VALIDATE':
			//case 'SUPPLIER_PROPOSAL_SENTBYMAIL':
			//case 'SUPPLIER_PROPOSAL_CLOSE_SIGNED':
			//case 'SUPPLIER_PROPOSAL_CLOSE_REFUSED':
			//case 'SUPPLIER_PROPOSAL_DELETE':
			//case 'LINESUPPLIER_PROPOSAL_INSERT':
			//case 'LINESUPPLIER_PROPOSAL_UPDATE':
			//case 'LINESUPPLIER_PROPOSAL_DELETE':

			// Contracts
			//case 'CONTRACT_CREATE':
			//case 'CONTRACT_MODIFY':
			//case 'CONTRACT_ACTIVATE':
			//case 'CONTRACT_CANCEL':
			//case 'CONTRACT_CLOSE':
			//case 'CONTRACT_DELETE':
			//case 'LINECONTRACT_INSERT':
			//case 'LINECONTRACT_UPDATE':
			//case 'LINECONTRACT_DELETE':

			// Bills
			//case 'BILL_CREATE':
			//case 'BILL_MODIFY':
			//case 'BILL_VALIDATE':
			//case 'BILL_UNVALIDATE':
			//case 'BILL_SENTBYMAIL':
			//case 'BILL_CANCEL':
			//case 'BILL_DELETE':
			//case 'BILL_PAYED':
			//case 'LINEBILL_INSERT':
			//case 'LINEBILL_UPDATE':
			//case 'LINEBILL_DELETE':

			//Supplier Bill
			//case 'BILL_SUPPLIER_CREATE':
			//case 'BILL_SUPPLIER_UPDATE':
			//case 'BILL_SUPPLIER_DELETE':
			//case 'BILL_SUPPLIER_PAYED':
			//case 'BILL_SUPPLIER_UNPAYED':
			//case 'BILL_SUPPLIER_VALIDATE':
			//case 'BILL_SUPPLIER_UNVALIDATE':
			//case 'LINEBILL_SUPPLIER_CREATE':
			//case 'LINEBILL_SUPPLIER_UPDATE':
			//case 'LINEBILL_SUPPLIER_DELETE':

			// Payments
			//case 'PAYMENT_CUSTOMER_CREATE':
			//case 'PAYMENT_SUPPLIER_CREATE':
			//case 'PAYMENT_ADD_TO_BANK':
			//case 'PAYMENT_DELETE':

			// Online
			//case 'PAYMENT_PAYBOX_OK':
			//case 'PAYMENT_PAYPAL_OK':
			//case 'PAYMENT_STRIPE_OK':

			// Donation
			//case 'DON_CREATE':
			//case 'DON_UPDATE':
			//case 'DON_DELETE':

			// Interventions
			//case 'FICHINTER_CREATE':
			//case 'FICHINTER_MODIFY':
			//case 'FICHINTER_VALIDATE':
			//case 'FICHINTER_DELETE':
			//case 'LINEFICHINTER_CREATE':
			//case 'LINEFICHINTER_UPDATE':
			//case 'LINEFICHINTER_DELETE':

			// Members
			//case 'MEMBER_CREATE':
			//case 'MEMBER_VALIDATE':
			//case 'MEMBER_SUBSCRIPTION':
			//case 'MEMBER_MODIFY':
			//case 'MEMBER_NEW_PASSWORD':
			//case 'MEMBER_RESILIATE':
			//case 'MEMBER_DELETE':

			// Categories
			//case 'CATEGORY_CREATE':
			//case 'CATEGORY_MODIFY':
			//case 'CATEGORY_DELETE':
			//case 'CATEGORY_SET_MULTILANGS':

			// Projects
			case 'PROJECT_CREATE':

				dol_include_once('/projet/class/project.class.php', 'Project');
				dol_include_once('/safra/class/janelaplantio.class.php', 'JanelaPlantio');
				dol_include_once('/safra/class/zoneamento.class.php', 'Zoneamento');

				$projeto = new Project($object->db);
				$projeto->fetch($object->id);


				break;


			case 'JANELAPLANTIO_CREATE':
				dol_include_once('/safra/class/janelaplantio.class.php', 'JanelaPlantio');
				dol_include_once('/projet/class/zoneamento.class.php', 'Zoneamento');
				dol_include_once('/projet/class/project.class.php', 'Project');
				dol_include_once('/safra/class/cultura.class.php', 'Cultura');
				dol_include_once('/safra/class/municipio.class.php', 'Municipio');


				$jp = new JanelaPlantio($object->db);
				$jp->fetch($object->id);
				$jp->ref = '' . $jp->id . '_' . $jp->label;
				$jp->update($user, true);

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
						echo 'Access Token: ' . $response_data['access_token'];
						$access_token = $response_data['access_token'];
					} else {
						echo 'Erro ao obter o token de acesso: ' . $response;
						break;
					}
				}

				// Fechando a sessão cURL
				curl_close($ch);

				$plano = new Project($object->db);
				$plano->fetch($jp->fk_project);

				$obj_cultura = new Cultura($object->db);
				$obj_municipio = new Municipio($object->db);
				$extrafields = new Project($object->db);
				$extrafields->fetch_optionals($jp->fk_project);
				$obj_cultura->fetch($extrafields->array_options['options_fk_cultura']);
				$obj_municipio->fetch($extrafields->array_options['options_fk_municipio']);

				$idCultura = $obj_cultura->id; // Exemplo de idCultura
				$codigoIBGE = $obj_municipio->cod_ibge; // Código IBGE da cidade
				$risco = 20; // Exemplo de risco

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
						echo 'Erro ao obter os dados: ' . $response_data['error'];
					} else {
						echo "Dados retornados:\n";
						print_r($response_data);
						$count = 0;
						foreach ($response_data as $key) {
							$zoneamento_obj = new Zoneamento($object->db);
							$zoneamento_obj->municipio = $key['municipio'];
							$zoneamento_obj->uf = $key['uf'];
							$zoneamento_obj->ciclo = $key['ciclo'];
							$zoneamento_obj->dia_ini = $key['diaIni'];
							$zoneamento_obj->mes_ini = $key['mes_ni'];
							$zoneamento_obj->dia_fim = $key['diaFim'];
							$zoneamento_obj->mes_fim = $key['mesFim'];
							$zoneamento_obj->safra_ini = $key['safraIni'];
							$zoneamento_obj->safra_fim = $key['safraFim'];
							$zoneamento_obj->risco = $key['risco'];
							$zoneamento_obj->portaria = $key['portaria'];
							$zoneamento_obj->label = 'zon_' . $count . '_' . $jp->ref;
							$zoneamento_obj->ref = 'zon_' . $count . '_' . $jp->ref;
							$zoneamento_obj->create($user, true);
						}
					}
				}

				// Fechando a sessão cURL
				curl_close($ch);



				//case 'PROJECT_MODIFY':
				//case 'PROJECT_DELETE':

				// Project tasks
				//case 'TASK_CREATE':
				//case 'TASK_MODIFY':
				//case 'TASK_DELETE':

				// Task time spent
				//case 'TASK_TIMESPENT_CREATE':
				//case 'TASK_TIMESPENT_MODIFY':
				//case 'TASK_TIMESPENT_DELETE':
				//case 'PROJECT_ADD_CONTACT':
				//case 'PROJECT_DELETE_CONTACT':
				//case 'PROJECT_DELETE_RESOURCE':

				// Shipping
				//case 'SHIPPING_CREATE':
				//case 'SHIPPING_MODIFY':
				//case 'SHIPPING_VALIDATE':
				//case 'SHIPPING_SENTBYMAIL':
				//case 'SHIPPING_BILLED':
				//case 'SHIPPING_CLOSED':
				//case 'SHIPPING_REOPEN':
				//case 'SHIPPING_DELETE':

				// and more...

			// case 'TALHAO_CREATE':


			// 	// constrói URL absoluta do Dolibarr
			// 	$url = dol_buildpath('/custom/safra/talhao_card.php', 2) . '?id=' . $object->id;

			// 	// limpa qualquer saída acidental e redireciona com 303 (POST-redirect-GET)
			// 	if (function_exists('ob_get_length') && ob_get_length()) {
			// 		@ob_end_clean();
			// 	}
			// 	header('Location: ' . $url, true, 303);
			// 	exit;


			// 	break;

                        default:
				dol_syslog("Trigger '" . $this->name . "' for action '" . $action . "' launched by " . __FILE__ . ". id=" . $object->id);
				break;
		}

                return 0;
        }

        /**
         * Persist Safra product links submitted from the Dolibarr product form.
         *
         * @param Product   $object
         * @param User      $user
         * @param Translate $langs
         *
         * @return int
         */
        /**
         * Handle creation of aplicacao to create linked task.
         *
         * @param Aplicacao $object
         * @param User      $user
         * @param Translate $langs
         * @return int
         */
        protected function handleAplicacaoCreate($object, User $user, Translate $langs)
        {
                if (!$object instanceof Aplicacao) {
                        return 0;
                }

                if (empty($object->fk_project) || !isModEnabled('project')) {
                        return 0;
                }

                $task = new Task($this->db);
                $task->fk_project = $object->fk_project;
                $task->label = $langs->trans('Aplicacao').' '.$object->ref;
                $task->description = $object->buildTaskDescription($langs);
                $task->fk_user_creat = $user->id;

                $res = $task->create($user);
                if ($res > 0) {
                        $sql = 'UPDATE '.MAIN_DB_PREFIX."safra_aplicacao SET fk_task = ".$task->id." WHERE rowid = ".$object->id;
                        $this->db->query($sql);
                        return 1;
                }

                dol_syslog(__METHOD__.' failed to create task: '.$task->error, LOG_ERR);
                return -1;
        }

        /**
         * Cleanup hook when aplicacao is deleted.
         *
         * @param Aplicacao $object
         * @param User      $user
         * @return int
         */
        protected function handleAplicacaoDelete($object, User $user)
        {
                if (!$object instanceof Aplicacao) {
                        return 0;
                }

                if (!empty($object->fk_task)) {
                        $sql = 'UPDATE '.MAIN_DB_PREFIX."safra_aplicacao SET fk_task = NULL WHERE rowid = ".$object->id;
                        $this->db->query($sql);
                }

                return 1;
        }

        /**
         * Handle stock deduction when task marked completed.
         *
         * @param Task $task
         * @param User $user
         * @return int
         */
        protected function handleTaskModify($task, User $user)
        {
                if (!$task instanceof Task) {
                        return 0;
                }

                if ((int) $task->progress < 100) {
                        return 0;
                }

                $sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX."safra_aplicacao WHERE fk_task = ".$task->id." AND stock_processed = 0";
                $resql = $this->db->query($sql);
                if (!$resql) {
                        return 0;
                }

                $row = $this->db->fetch_object($resql);
                if (!$row) {
                        return 0;
                }

                $aplicacao = new Aplicacao($this->db);
                if ($aplicacao->fetch($row->rowid) <= 0) {
                        return 0;
                }

                $res = $aplicacao->processStockMovements($user);
                if ($res < 0) {
                        dol_syslog(__METHOD__.' error on stock movement', LOG_ERR);
                        return -1;
                }

                return 1;
        }

        private function persistSafraProductLinks($object, User $user, Translate $langs)
        {
                if (empty($object->id) || empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
                        return 0;
                }

                dol_include_once('/safra/class/safra_product_link.class.php');

                $selections = SafraProductLink::getPostedSelections();
                if (empty($selections)) {
                        return 0;
                }

                $langs->loadLangs(array('safra@safra'));

                if (!SafraProductLink::ensureDatabaseSchema($this->db)) {
                        setEventMessages($langs->trans('SafraProductLinkSchemaError'), null, 'errors');

                        return -1;
                }

                $handled = false;
                $hasError = false;

                if (isset($selections[SafraProductLink::TYPE_FORMULADO]) && !empty($user->rights->safra->produtoformulado->write)) {
                        $handled = true;
                        if (!SafraProductLink::replaceLinks($this->db, $object->id, SafraProductLink::TYPE_FORMULADO, $selections[SafraProductLink::TYPE_FORMULADO]['ids'])) {
                                setEventMessages($langs->trans('SafraProductLinkSaveError'), null, 'errors');
                                $hasError = true;
                        }
                }

                if (isset($selections[SafraProductLink::TYPE_TECNICO]) && !empty($user->rights->safra->produtostecnicos->write)) {
                        $handled = true;
                        if (!SafraProductLink::replaceLinks($this->db, $object->id, SafraProductLink::TYPE_TECNICO, $selections[SafraProductLink::TYPE_TECNICO]['ids'])) {
                                setEventMessages($langs->trans('SafraProductLinkSaveError'), null, 'errors');
                                $hasError = true;
                        }
                }

                if ($hasError) {
                        return -1;
                }

                return $handled ? 1 : 0;
        }

        /**
         * Build fertiliser recommendation when a recomendacaoadubo record is created.
         */
        public function recomendacaoaduboCreate($action, $object, User $user, Translate $langs, Conf $conf)
        {
                dol_include_once('/projet/class/project.class.php');
                dol_include_once('/safra/class/recomendacaoadubo.class.php');
                dol_include_once('/safra/class/cultura.class.php');
                dol_include_once('/safra/class/analisesolo.class.php');

                $recommendation = new RecomendacaoAdubo($object->db);
                if ($recommendation->fetch($object->id) <= 0) {
                        return 0;
                }

                $analysis = null;
                if (!empty($recommendation->analise_solo)) {
                        $analysisObject = new AnaliseSolo($object->db);
                        if ($analysisObject->fetch($recommendation->analise_solo) > 0) {
                                $analysis = $analysisObject;
                        }
                }

                $culture = null;
                if (!empty($recommendation->fk_project)) {
                        $project = new Project($object->db);
                        if ($project->fetch($recommendation->fk_project) > 0) {
                                $project->fetch_optionals($recommendation->fk_project);
                                $cultureId = !empty($project->array_options['options_fk_cultura']) ? (int) $project->array_options['options_fk_cultura'] : 0;
                                if ($cultureId > 0) {
                                        $cultureObject = new Cultura($object->db);
                                        if ($cultureObject->fetch($cultureId) > 0) {
                                                $culture = $cultureObject;
                                        }
                                }
                        }
                }

                $guide = $this->buildCultureGuide($culture);

                if (empty($analysis)) {
                        $recommendation->recomendacao = $this->renderMissingAnalysisMessage($guide);
                        $recommendation->update($user, true);

                        return 1;
                }

                $recommendation->recomendacao = $this->generateFertilizationRecommendation($guide, $analysis);
                $recommendation->update($user, true);

                return 1;
        }

        /**
         * Create a configuration array for a culture combining defaults and database values.
         *
         * @param Cultura|null $culture
         * @return array
         */
        private function buildCultureGuide($culture)
        {
                $defaults = $this->getCultureDefaults();

                $guide = $defaults['default'];
                $guide['key'] = 'default';

                if ($culture instanceof Cultura) {
                        $key = $this->normaliseCultureKey($culture->ref ? $culture->ref : $culture->label);
                        $guide['key'] = $key;
                        $guide['display'] = $culture->label ? $culture->label : $culture->ref;

                        if (!empty($defaults[$key])) {
                                $guide = array_replace_recursive($guide, $defaults[$key]);
                        }

                        if (!empty($culture->necessidade_n)) {
                                $guide['npk']['N']['base'] = (float) $culture->necessidade_n;
                        }

                        if (!empty($culture->necessidade_p)) {
                                $guide['npk']['P'] = $this->scaleNutrientRecommendation((float) $culture->necessidade_p);
                        }

                        if (!empty($culture->necessidade_k)) {
                                $guide['npk']['K'] = $this->scaleNutrientRecommendation((float) $culture->necessidade_k);
                        }

                        if (!empty($culture->saturacao_bases_ideal)) {
                                $guide['target_base_saturation'] = (float) $culture->saturacao_bases_ideal;
                        }
                }

                if (empty($guide['npk']['P'])) {
                        $guide['npk']['P'] = $this->scaleNutrientRecommendation($guide['npk']['N']['base'] * 0.5);
                }

                if (empty($guide['npk']['K'])) {
                        $guide['npk']['K'] = $this->scaleNutrientRecommendation($guide['npk']['N']['base'] * 0.5);
                }

                return $guide;
        }

        /**
         * Produce the HTML recommendation block.
         *
         * @param array       $guide
         * @param AnaliseSolo $analysis
         * @return string
         */
        private function generateFertilizationRecommendation($guide, AnaliseSolo $analysis)
        {
                $npk = $this->calculateNpkRecommendation($guide, $analysis);
                $formulation = $this->suggestCommercialFormulation($npk);
                $liming = $this->calculateLimingNeed($guide, $analysis);
                $micronutrients = $this->buildMicronutrientAlerts($analysis, $guide);

                $html = '<h3>Recomendação de adubação para ' . dol_escape_htmltag($guide['display']) . '</h3>';
                $html .= '<p><strong>Resumo da análise de solo:</strong></p>';
                $html .= '<ul>';
                $html .= '<li>pH: ' . $this->formatNumber($analysis->ph, 1) . '</li>';
                $html .= '<li>Matéria orgânica: ' . $this->formatNumber($analysis->materia_organica, 1) . ' %</li>';
                $html .= '<li>N total: ' . $this->formatNumber($analysis->n_total, 1) . ' mg/kg</li>';
                $html .= '<li>Fósforo disponível: ' . $this->formatNumber($analysis->fosforo, 1) . ' mg/kg</li>';
                $html .= '<li>Potássio disponível: ' . $this->formatNumber($analysis->potassio, 1) . ' mg/kg</li>';
                $html .= '<li>Saturação por bases (V%): ' . $this->formatNumber($analysis->saturacao_bases, 1) . '</li>';
                $html .= '</ul>';

                $html .= '<p><strong>Recomendação de nutrientes (kg/ha)</strong></p>';
                $html .= '<ul>';
                $html .= '<li>Nitrogênio (N): ' . $this->formatNumber($npk['N']['quantity'], 1) . ' &mdash; nível do solo ' . $npk['N']['level'] . '</li>';
                $html .= '<li>Fósforo (P<sub>2</sub>O<sub>5</sub>): ' . $this->formatNumber($npk['P']['quantity'], 1) . ' &mdash; nível do solo ' . $npk['P']['level'] . '</li>';
                $html .= '<li>Potássio (K<sub>2</sub>O): ' . $this->formatNumber($npk['K']['quantity'], 1) . ' &mdash; nível do solo ' . $npk['K']['level'] . '</li>';
                $html .= '</ul>';

                if (!empty($formulation)) {
                        $html .= '<p><strong>Formulação sugerida:</strong> ' . dol_escape_htmltag($formulation['label']) . ' &mdash; aplicar aproximadamente ' . $this->formatNumber($formulation['ton_per_ha'], 2) . ' t/ha.</p>';
                }

                if (!empty($guide['method'])) {
                        $html .= '<p><strong>Método de aplicação recomendado:</strong> ' . dol_escape_htmltag($guide['method']) . '</p>';
                }

                if (!empty($liming)) {
                        $html .= '<p><strong>Calagem estimada:</strong> ';
                        $html .= 'dose aproximada de ' . $this->formatNumber($liming['ton_per_ha'], 2) . ' t/ha para atingir V% de ' . $this->formatNumber($liming['target_v'], 0) . ' (atual ' . $this->formatNumber($liming['current_v'], 1) . ').</p>';
                }

                if (!empty($micronutrients)) {
                        $html .= '<p><strong>Alertas complementares:</strong></p><ul>';
                        foreach ($micronutrients as $alert) {
                                $html .= '<li>' . dol_escape_htmltag($alert) . '</li>';
                        }
                        $html .= '</ul>';
                }

                $html .= '<p><strong>Boas práticas adicionais:</strong></p>';
                $html .= '<ul>';
                $html .= '<li>Realizar a adubação com o solo úmido e, quando possível, incorporar levemente os fertilizantes.</li>';
                $html .= '<li>Parcelar aplicações de N quando a dose superar 60 kg/ha e monitorar possíveis perdas por volatilização.</li>';
                $html .= '<li>Revisar o plano de adubação após novas análises ou mudanças expressivas na produtividade esperada.</li>';
                $html .= '<li>Consultar um profissional habilitado para ajustes finos e inclusão de micronutrientes específicos.</li>';
                $html .= '</ul>';

                return $html;
        }

        /**
         * Message displayed when the recommendation has no soil analysis linked.
         *
         * @param array $guide
         * @return string
         */
        private function renderMissingAnalysisMessage($guide)
        {
                $html = '<h3>Recomendação de adubação para ' . dol_escape_htmltag($guide['display']) . '</h3>';
                $html .= '<p>Associe uma análise de solo válida para gerar recomendações automáticas de adubação e calagem.</p>';
                $html .= '<p>Utilize análises realizadas preferencialmente nos últimos 12 meses para garantir um diagnóstico preciso.</p>';

                return $html;
        }

        private function normaliseCultureKey($value)
        {
                $clean = strtolower(dol_string_unaccent($value));
                $clean = preg_replace('/[^a-z0-9]+/', '_', $clean);

                return trim($clean, '_');
        }

        private function getCultureDefaults()
        {
                return array(
                        'default' => array(
                                'display' => 'Cultura',
                                'ideal_ph' => 6.0,
                                'target_base_saturation' => 55,
                                'method' => 'Distribuição uniforme no sulco ou a lanço com incorporação leve antes do plantio.',
                                'npk' => array(
                                        'N' => array(
                                                'base' => 90,
                                                'mo_adjustments' => array(
                                                        array('min' => 3, 'percent' => -0.2),
                                                        array('min' => 5, 'percent' => -0.35),
                                                ),
                                        ),
                                        'P' => array(),
                                        'K' => array(),
                                ),
                        ),
                        'milho' => array(
                                'ideal_ph' => 6.0,
                                'target_base_saturation' => 60,
                                'method' => 'Aplicar no sulco de plantio e complementar em cobertura no estádio vegetativo.',
                                'npk' => array(
                                        'N' => array(
                                                'base' => 140,
                                                'mo_adjustments' => array(
                                                        array('min' => 3, 'percent' => -0.15),
                                                        array('min' => 5, 'percent' => -0.3),
                                                ),
                                        ),
                                ),
                        ),
                        'soja' => array(
                                'ideal_ph' => 6.2,
                                'target_base_saturation' => 60,
                                'method' => 'Aplicar preferencialmente a lanço antes da semeadura e incorporar levemente.',
                                'npk' => array(
                                        'N' => array(
                                                'base' => 40,
                                                'mo_adjustments' => array(
                                                        array('min' => 3, 'percent' => -0.25),
                                                        array('min' => 5, 'percent' => -0.4),
                                                ),
                                        ),
                                ),
                        ),
                        'arroz' => array(
                                'ideal_ph' => 5.8,
                                'target_base_saturation' => 55,
                                'method' => 'Aplicar metade na base e metade em cobertura no perfilhamento inicial.',
                                'npk' => array(
                                        'N' => array('base' => 100),
                                ),
                        ),
                        'sorgo' => array(
                                'ideal_ph' => 5.8,
                                'target_base_saturation' => 55,
                                'method' => 'Aplicar no plantio e fracionar cobertura antes do perfilhamento.',
                                'npk' => array(
                                        'N' => array('base' => 80),
                                ),
                        ),
                        'trigo' => array(
                                'ideal_ph' => 6.0,
                                'target_base_saturation' => 60,
                                'method' => 'Aplicar no plantio e parcelar o N entre perfilhamento e alongamento.',
                                'npk' => array(
                                        'N' => array('base' => 100),
                                ),
                        ),
                        'feijao' => array(
                                'ideal_ph' => 6.0,
                                'target_base_saturation' => 55,
                                'method' => 'Aplicar no sulco e complementar em cobertura leve aos 20-25 dias.',
                                'npk' => array(
                                        'N' => array('base' => 60),
                                ),
                        ),
                        'girassol' => array(
                                'ideal_ph' => 6.3,
                                'target_base_saturation' => 60,
                                'method' => 'Aplicar a lanço antes do plantio com incorporação rasa.',
                                'npk' => array(
                                        'N' => array('base' => 100),
                                ),
                        ),
                );
        }

        private function scaleNutrientRecommendation($base)
        {
                $baseValue = max(0, (float) $base);
                if ($baseValue <= 0) {
                        $baseValue = 60;
                }

                return array(
                        'very_low' => round($baseValue * 1.4),
                        'low' => round($baseValue * 1.2),
                        'medium' => round($baseValue),
                        'high' => round($baseValue * 0.6),
                        'very_high' => 0,
                );
        }

        private function calculateNpkRecommendation($guide, AnaliseSolo $analysis)
        {
                $ranges = $this->getDefaultNutrientRanges();

                $npk = array();

                $nitrogen = $guide['npk']['N']['base'];
                $adjustPercent = 0;
                if (!empty($guide['npk']['N']['mo_adjustments']) && $analysis->materia_organica !== null) {
                        foreach ($guide['npk']['N']['mo_adjustments'] as $rule) {
                                if ($analysis->materia_organica >= $rule['min']) {
                                        $adjustPercent = min($adjustPercent, $rule['percent']);
                                }
                        }
                }
                if ($adjustPercent !== 0) {
                        $nitrogen += $nitrogen * $adjustPercent;
                }

                $nLevel = $this->classifyNutrientLevel($analysis->n_total, $ranges['n_total']);
                if ($nLevel === 'high') {
                        $nitrogen *= 0.7;
                } elseif ($nLevel === 'very_high') {
                        $nitrogen *= 0.5;
                }

                $npk['N'] = array(
                        'quantity' => max(0, round($nitrogen)),
                        'level' => $this->describeLevel($nLevel),
                );

                $pLevel = $this->classifyNutrientLevel($analysis->fosforo, $ranges['fosforo']);
                $pKey = isset($guide['npk']['P'][$pLevel]) ? $pLevel : 'medium';
                $npk['P'] = array(
                        'quantity' => max(0, round($guide['npk']['P'][$pKey])),
                        'level' => $this->describeLevel($pLevel),
                );

                $kLevel = $this->classifyNutrientLevel($analysis->potassio, $ranges['potassio']);
                $kKey = isset($guide['npk']['K'][$kLevel]) ? $kLevel : 'medium';
                $npk['K'] = array(
                        'quantity' => max(0, round($guide['npk']['K'][$kKey])),
                        'level' => $this->describeLevel($kLevel),
                );

                return $npk;
        }

        private function getDefaultNutrientRanges()
        {
                return array(
                        'n_total' => array(
                                'very_low' => 8,
                                'low' => 12,
                                'medium' => 20,
                                'high' => 30,
                                'very_high' => null,
                        ),
                        'fosforo' => array(
                                'very_low' => 8,
                                'low' => 15,
                                'medium' => 25,
                                'high' => 40,
                                'very_high' => null,
                        ),
                        'potassio' => array(
                                'very_low' => 50,
                                'low' => 80,
                                'medium' => 120,
                                'high' => 180,
                                'very_high' => null,
                        ),
                );
        }

        private function classifyNutrientLevel($value, $range)
        {
                if ($value === null) {
                        return 'unknown';
                }

                foreach ($range as $label => $maxValue) {
                        if ($maxValue === null || $value <= $maxValue) {
                                return $label;
                        }
                }

                return array_key_last($range);
        }

        private function describeLevel($key)
        {
                $labels = array(
                        'very_low' => 'muito baixo',
                        'low' => 'baixo',
                        'medium' => 'médio',
                        'high' => 'alto',
                        'very_high' => 'muito alto',
                        'unknown' => 'não informado',
                );

                return isset($labels[$key]) ? $labels[$key] : $key;
        }

        private function suggestCommercialFormulation($npk)
        {
                if (empty($npk['N']['quantity']) && empty($npk['P']['quantity']) && empty($npk['K']['quantity'])) {
                        return array();
                }

                $formulations = array(
                        array('label' => '20-10-10', 'N' => 20, 'P' => 10, 'K' => 10),
                        array('label' => '10-20-20', 'N' => 10, 'P' => 20, 'K' => 20),
                        array('label' => '15-15-15', 'N' => 15, 'P' => 15, 'K' => 15),
                        array('label' => '25-5-5', 'N' => 25, 'P' => 5, 'K' => 5),
                        array('label' => '08-28-16', 'N' => 8, 'P' => 28, 'K' => 16),
                        array('label' => '04-30-10', 'N' => 4, 'P' => 30, 'K' => 10),
                        array('label' => '05-25-25', 'N' => 5, 'P' => 25, 'K' => 25),
                        array('label' => '30-00-10', 'N' => 30, 'P' => 0, 'K' => 10),
                );

                $best = array();
                $bestDelta = null;

                foreach ($formulations as $formula) {
                        $kgPerHa = 0;
                        $components = array('N', 'P', 'K');
                        foreach ($components as $component) {
                                if ($npk[$component]['quantity'] <= 0 || empty($formula[$component])) {
                                        continue;
                                }
                                $kgPerHa = max($kgPerHa, $npk[$component]['quantity'] / ($formula[$component] / 100));
                        }

                        if ($kgPerHa <= 0) {
                                continue;
                        }

                        $delivered = array(
                                'N' => $kgPerHa * $formula['N'] / 100,
                                'P' => $kgPerHa * $formula['P'] / 100,
                                'K' => $kgPerHa * $formula['K'] / 100,
                        );

                        $delta = abs($delivered['N'] - $npk['N']['quantity']) + abs($delivered['P'] - $npk['P']['quantity']) + abs($delivered['K'] - $npk['K']['quantity']);

                        if ($bestDelta === null || $delta < $bestDelta) {
                                $bestDelta = $delta;
                                $best = array(
                                        'label' => $formula['label'],
                                        'ton_per_ha' => $kgPerHa / 1000,
                                );
                        }
                }

                return $best;
        }

        private function calculateLimingNeed($guide, AnaliseSolo $analysis)
        {
                if ($analysis->ctc === null || $analysis->saturacao_bases === null) {
                        return array();
                }

                $target = !empty($guide['target_base_saturation']) ? $guide['target_base_saturation'] : 55;
                $current = (float) $analysis->saturacao_bases;
                $delta = max(0, $target - $current);

                if ($delta <= 0) {
                        return array();
                }

                $prnt = 80; // percentual de PRNT considerado.
                $dose = ($analysis->ctc * $delta / 100) * (100 / $prnt);

                return array(
                        'ton_per_ha' => max(0, round($dose, 2)),
                        'target_v' => $target,
                        'current_v' => $current,
                );
        }

        private function buildMicronutrientAlerts(AnaliseSolo $analysis, $guide)
        {
                $alerts = array();

                $thresholds = array(
                        'enxofre' => array('limit' => 12, 'message' => 'Teor baixo de enxofre; considerar fonte contendo S (15 a 30 kg/ha).'),
                        'zinco' => array('limit' => 1.2, 'message' => 'Zinco abaixo do ideal; avaliar aplicação foliar ou no sulco.'),
                        'boro' => array('limit' => 0.5, 'message' => 'Boro reduzido; considerar 1-2 kg/ha de B, evitando superdosagens.'),
                        'manganes' => array('limit' => 5, 'message' => 'Manganês em nível baixo; monitorar sintomas e aplicar fontes específicas se necessário.'),
                        'cobre' => array('limit' => 0.6, 'message' => 'Cobre limitado; considerar fontes cúpricas se persistirem deficiências.'),
                );

                foreach ($thresholds as $field => $data) {
                        if ($analysis->$field !== null && $analysis->$field > 0 && $analysis->$field < $data['limit']) {
                                $alerts[] = $data['message'];
                        }
                }

                if ($analysis->materia_organica !== null && $analysis->materia_organica < 2) {
                        $alerts[] = 'Matéria orgânica muito baixa; avalie incorporar resíduos ou adubos orgânicos.';
                }

                if ($analysis->ph !== null && $guide['ideal_ph'] !== null && $analysis->ph < $guide['ideal_ph'] - 0.5) {
                        $alerts[] = 'pH abaixo do ideal para a cultura; confirme a necessidade de calagem e monitore alumínio trocável.';
                }

                return $alerts;
        }

        private function formatNumber($value, $decimals)
        {
                if ($value === null || $value === '') {
                        return '-';
                }

                return number_format((float) $value, $decimals, ',', '.');
        }
}
