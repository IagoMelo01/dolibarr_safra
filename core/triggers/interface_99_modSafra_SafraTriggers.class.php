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

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


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
				"Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id
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

			// Products
			//case 'PRODUCT_CREATE':
			//case 'PRODUCT_MODIFY':
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
			//case 'PROJECT_CREATE':
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
						
			case 'RECOMENDACAOADUBO_CREATE':

				$rec = new RecomendacaoAdubo($object->db);
				$rec->fetch($object->id);
				
				$analise = new AnaliseSolo($object->db);
				$analise->fetch($rec->analise_solo);
				
				$plano = new PlanoCultivo($object->db);
				$plano->fetch($rec->plano_cultivo);
				
				$obj_cultura = new Cultura($object->db);
				$obj_cultura->fetch($plano->cultura);
				
				// Função para calcular a recomendação de NPK com base na cultura
				function recomendarNPK($cultura, $nitrogenio, $fosforo, $potassio) {
					$ideais = [
						'arroz' => ['N' => 100, 'P' => 30, 'K' => 30],
						'arrozirrigado' => ['N' => 100, 'P' => 30, 'K' => 30],
						'algodao' => ['N' => 110, 'P' => 60, 'K' => 60],
						'amendoim' => ['N' => 30, 'P' => 60, 'K' => 50],
						'cevada' => ['N' => 80, 'P' => 55, 'K' => 55],
						'feijao' => ['N' => 40, 'P' => 60, 'K' => 40],
						'feijaocaupi' => ['N' => 30, 'P' => 30, 'K' => 30],
						'girassol' => ['N' => 100, 'P' => 60, 'K' => 60],
						'mamona' => ['N' => 80, 'P' => 60, 'K' => 40],
						'milho' => ['N' => 120, 'P' => 60, 'K' => 60],
						'soja' => ['N' => 30, 'P' => 60, 'K' => 60],
						'sorgo' => ['N' => 80, 'P' => 45, 'K' => 45],
						'trigo' => ['N' => 90, 'P' => 60, 'K' => 60]
					];

					$idealN = $ideais[$cultura]['N'];
					$idealP = $ideais[$cultura]['P'];
					$idealK = $ideais[$cultura]['K'];

					$difN = $idealN - $nitrogenio;
					$difP = $idealP - $fosforo;
					$difK = $idealK - $potassio;

					$recN = max(0, $difN * 4);
					$recP = max(0, $difP * 2);
					$recK = max(0, $difK * 4);

					return array($recN, $recP, $recK);
				}

				// Função para sugerir uma formulação comercial e a dose necessária
				function sugerirFormulacao($recN, $recP, $recK) {
					$formulacoes = [
						['N' => 20, 'P' => 10, 'K' => 10, 'descricao' => '20-10-10'],
						['N' => 10, 'P' => 20, 'K' => 20, 'descricao' => '10-20-20'],
						['N' => 15, 'P' => 15, 'K' => 15, 'descricao' => '15-15-15'],
						['N' => 30, 'P' => 10, 'K' => 10, 'descricao' => '30-10-10'],
						['N' => 10, 'P' => 10, 'K' => 10, 'descricao' => '10-10-10'],
						['N' => 25, 'P' => 5,  'K' => 5,  'descricao' => '25-5-5'],
						['N' => 5,  'P' => 25, 'K' => 5,  'descricao' => '5-25-5'],
						['N' => 5,  'P' => 5,  'K' => 25, 'descricao' => '5-5-25'],
						['N' => 40, 'P' => 20, 'K' => 20, 'descricao' => '40-20-20'],
						['N' => 20, 'P' => 20, 'K' => 20, 'descricao' => '20-20-20'],
						['N' => 18, 'P' => 18, 'K' => 18, 'descricao' => '18-18-18'],
						['N' => 12, 'P' => 24, 'K' => 12, 'descricao' => '12-24-12'],
						['N' => 12, 'P' => 12, 'K' => 17, 'descricao' => '12-12-17'],
						['N' => 30, 'P' => 6,  'K' => 6,  'descricao' => '30-6-6'],
						['N' => 28, 'P' => 14, 'K' => 14, 'descricao' => '28-14-14'],
						['N' => 13, 'P' => 13, 'K' => 21, 'descricao' => '13-13-21'],
						['N' => 8,  'P' => 24, 'K' => 24, 'descricao' => '8-24-24'],
						['N' => 16, 'P' => 8,  'K' => 8,  'descricao' => '16-8-8'],
						['N' => 7,  'P' => 7,  'K' => 14, 'descricao' => '7-7-14'],
						['N' => 20, 'P' => 5,  'K' => 10, 'descricao' => '20-5-10'],
						['N' => 9,  'P' => 18, 'K' => 9,  'descricao' => '9-18-9'],
						['N' => 14, 'P' => 14, 'K' => 14, 'descricao' => '14-14-14'],
						['N' => 17, 'P' => 17, 'K' => 17, 'descricao' => '17-17-17'],
						['N' => 19, 'P' => 19, 'K' => 19, 'descricao' => '19-19-19'],
						['N' => 21, 'P' => 0,  'K' => 0,  'descricao' => '21-0-0'],
						['N' => 0,  'P' => 46, 'K' => 0,  'descricao' => '0-46-0'],
						['N' => 0,  'P' => 0,  'K' => 60, 'descricao' => '0-0-60'],
						['N' => 15, 'P' => 9,  'K' => 20, 'descricao' => '15-9-20'],
						['N' => 11, 'P' => 22, 'K' => 16, 'descricao' => '11-22-16'],
						['N' => 22, 'P' => 11, 'K' => 2,  'descricao' => '22-11-2']
					];


					$melhorFormulacao = null;
					$menorDiferenca = PHP_INT_MAX;

					foreach ($formulacoes as $formulacao) {
						$diferenca = abs($recN - $formulacao['N']) + abs($recP - $formulacao['P']) + abs($recK - $formulacao['K']);
						if ($diferenca < $menorDiferenca) {
							$menorDiferenca = $diferenca;
							$melhorFormulacao = $formulacao;
						}
					}

					// Calcula a quantidade de formulação necessária (em toneladas por hectare)
					$quantidade = max($recN / $melhorFormulacao['N'], $recP / $melhorFormulacao['P'], $recK / $melhorFormulacao['K']) / 10; // Ajuste conforme necessário

					return ['formulacao' => $melhorFormulacao['descricao'], 'quantidade' => $quantidade];
				}

				// Função para sugerir o método de aplicação baseado na cultura
				function metodoAplicacao($cultura) {
					$metodos = [
						'arroz' => 'Incorporação antes do plantio e cobertura na fase vegetativa',
						'algodao' => 'Incorporação antes do plantio e cobertura em fases de crescimento',
						'amendoim' => 'Incorporação antes do plantio',
						'cevada' => 'Incorporação antes do plantio e cobertura no início do perfilhamento',
						'feijao' => 'Incorporação antes do plantio e cobertura na fase de crescimento',
						'feijaocaupi' => 'Incorporação antes do plantio e cobertura durante o desenvolvimento vegetativo',
						'girassol' => 'Incorporação antes do plantio e cobertura no estágio de botão floral',
						'mamona' => 'Incorporação antes do plantio e cobertura no início da fase vegetativa',
						'milho' => 'Incorporação antes do plantio e cobertura na fase de crescimento',
						'soja' => 'Incorporação antes do plantio e cobertura no estágio de desenvolvimento vegetativo',
						'sorgo' => 'Incorporação antes do plantio e cobertura antes do perfilhamento',
						'trigo' => 'Incorporação antes do plantio e cobertura no início do perfilhamento'
					];

					return $metodos[$cultura] ?? 'Método não especificado; consulte um agrônomo';
				}

				// Receber dados de entrada via objetos
				$cultura = strtolower($obj_cultura->label);
				$nitrogenio = $analise->n_total ?? 0; // mg/kg
				$fosforo = $analise->fosforo ?? 0; // mg/kg
				$potassio = $analise->potassio ?? 0; // mg/kg

				// Chamar a função de recomendação
				$recomendacao = recomendarNPK($cultura, $nitrogenio, $fosforo, $potassio);
				$formulacao = sugerirFormulacao($recomendacao[0], $recomendacao[1], $recomendacao[2]);
				$metodo = metodoAplicacao($cultura);

				$res .= "Recomendação de fertilização NPK para $cultura (kg/ha): <br>";
				$res .= "Nitrogênio (N): " . $recomendacao[0] . " kg/ha <br>";
				$res .= "Fósforo (P2O5): " . $recomendacao[1] . " kg/ha <br>";
				$res .= "Potássio (K2O): " . $recomendacao[2] . " kg/ha <br>";
				$res .= "Formulação sugerida: " . $formulacao['formulacao'] . " <br>";
				$res .= "Quantidade necessária: " . round($formulacao['quantidade'], 2) . " toneladas por hectare <br>";
				$res .= "Método de aplicação recomendado para $cultura: $metodo <br>";


				function calcularCalcario($cultura, $pH_atual, $ctc) {
					// pH ideal para cada cultura
					$pH_ideais = [
						'arroz' => 6.0,
						'arroz irrigado' => 6.0,
						'algodao' => 6.0,
						'amendoim' => 6.0,
						'cevada' => 6.0,
						'cevada irrigada' => 6.0,
						'feijao' => 6.0,
						'feijaocaupi' => 5.5,
						'girassol' => 6.5,
						'mamona' => 6.0,
						'milho' => 6.0,
						'soja' => 6.5,
						'sorgo' => 6.0,
						'trigo' => 6.0
					];

					$pH_desejado = $pH_ideais[$cultura] ?? 6.5; // pH desejado padrão se a cultura não for especificada

					// Calcular o fator de correção baseado no pH desejado
					$fator = 0;
					if ($pH_desejado > $pH_atual) {
						$fator = ($pH_desejado - $pH_atual) * 100; // Ajuste este fator conforme a necessidade real e o tipo de solo
					}

					// Calcular a quantidade de calcário necessária (em toneladas por hectare)
					$necessidade_calcario = $ctc * $fator / 1000; // Ajuste conforme necessário

					return $necessidade_calcario;
				}
				
				// Receber dados de entrada via GET
				// $cultura = strtolower($obj_cultura->label);
				$pH_atual = $analise->ph ?? 5.5; // pH atual do solo
				$ctc = $analise->ctc ?? 10; // Capacidade de Troca Catiônica (mmolc/dm³)
				
				// Chamar a função de cálculo de calcário
				$quantidade_calcario = calcularCalcario($cultura, $pH_atual, $ctc);

				$res .= "<h3>Observações Adubação: </h3> 1. A análise de uma amostra de solo é instrumento importante para auxiliar no processo de recomendação de adubação, contudo requer a coleta de uma amostra representativa. Deve-se coletar uma amostra de cada gleba da propriedade, resultante da mistura de várias sub-amostras. (Sugere-se consultar arquivo WebAgritec-amostragem do solo); \n2. Adubação nitrogenada - A quantidade total de N recomendada pode ser reduzida em cerca de 30% quando o solo apresentar teor alto de matéria orgânica. A dosagem de N em cobertura, quando recomendada, deve ser aplicada durante o desenvolvimento da cultura. Caso esta dosagem seja superior a 60 kg/ha, pode-se parcelar em duas aplicações. A aplicação deve ser feita em solo úmido e, quando possível, com leve incorporação ao solo; \n3. Adubação potássica - Quando o solo for arenoso ou a recomendação de adubação exceder 60 kg/ha de K2O, sugere-se aplicar metade da dose no plantio e metade junto com a cobertura de nitrogênio; \n4. Em lavouras aonde vem sendo utilizadas fontes de fertilizantes ou formulações de alta concentração (soma de N+P2O5+K2O acima de 35), existe grande probabilidade de ocorrer deficiência de enxofre (S) (veja foto ilustrativa). Neste caso, aplicar de 15 a 30 kg/ha de S; \n5. Em solos da região do cerrado e em solos arenosos, tem sido comum a deficiência de micronutrientes (Zn, Mn, Cu e B), (veja fotos ilustrativas). Neste caso, avaliar a disponibilidade, mediante análise de amostra de solo e aplicar fórmula NPK, com micronutrientes; \n6. A eficiência da adubação pode ser limitada pela ocorrência de camada compactada no solo, problema mais freqüente em lavouras sob preparo convencional. A solução deste problema abrange desde o cultivo de plantas descompactadoras até a subsolagem. (Veja detalhes no arquivo WebAgritec-compactação); \n7. Informações adicionais podem ser obtidas:\nDocumentos:\n- Manual de adubação e calagem para os estados do Rio Grande do Sul e Santa Catarina, 2004.\n- CERRADO. Correção do solo e adubação, 2002.\n-Recomendações para o uso de corretivos e fertilizantes em Minas gerais, 1999.\n- Boletim Técnico 100, IAC, 1996.\nInstituições:\nEmbrapa.\nInstituições estaduais de pesquisa.<hr>";
				
				$res .= "Quantidade recomendada de calcário (ton/ha): " . round($quantidade_calcario, 2) . "\n<br>";

				$res .= "<h3>Observações Calagem: </h3> 1. A dose de calcário foi calculada visando atingir uma saturação por bases em torno de 50%. Para atingir valores diferentes é necessário recalcular a dose, utilizando-se a fórmula tradicional: Dose (t/ha) = {(V desejado – V atual) x T} / PRNT.\n2. Para solos com teor de magnésio (Mg) inferior a 0,5 cmolc/dm3, utilizar preferencialmente o calcário dolomítico ou magnesiano. Observe, também, a relação Ca/Mg do solo, que deve se situar em torno de 3/1;\n3. Para que o calcário produza os efeitos desejáveis, é necessário haver umidade suficiente no solo para promover sua reação;\n4. A forma mais comum de aplicação é aquela em que se distribui o calcário uniformemente na superfície do terreno, seguida de incorporação. Contudo, quando a lavoura vem adotando o sistema plantio/semeadura direto, deve-se evitar a incorporação, ou seja, deixar na superfície;\n5. Em solos com predominância de argilas de alta atividade (2:1) é recomendável calcular a necessidade de calcário pelo critério SMP.\n";

				
				$rec->recomendacao = $res;
				$rec->update($user, true);



								

			break;

			default:
				dol_syslog("Trigger '".$this->name."' for action '".$action."' launched by ".__FILE__.". id=".$object->id);
				break;
		}

		return 0;
	}
}
