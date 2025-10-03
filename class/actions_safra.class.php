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
 * \file    safra/class/actions_safra.class.php
 * \ingroup safra
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonhookactions.class.php';
dol_include_once('/safra/class/safra_product_link.class.php');

/**
 * Class ActionsSafra
 */
class ActionsSafra extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Execute action
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					Return integer <0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;
		$this->resprints = '';
		return 0;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             Return integer < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {	    // do something only for the context 'somecontext1' or 'somecontext2'
			// Do what you want here...
			// You can for example call global vars like $fieldstosearchall to overwrite them, or update database depending on $action and $_POST values.
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Overloading the doMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             Return integer < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			foreach ($parameters['toselect'] as $objectid) {
				// Do action on each object id
			}
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             Return integer < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			$this->resprints = '<option value="0"' . ($disabled ? ' disabled="disabled"' : '') . '>' . $langs->trans("SafraMassAction") . '</option>';
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}



	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$object		   	Object output on PDF
	 * @param   string	$action     	'add', 'update', 'view'
	 * @return  int 		        	Return integer <0 if KO,
	 *                          		=0 if OK but we want to process standard actions too,
	 *  	                            >0 if OK and we want to replace standard actions.
	 */
	public function beforePDFCreation($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0;
		$deltemp = array();
		dol_syslog(get_class($this) . '::executeHooks action=' . $action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}

	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$pdfhandler     PDF builder handler
	 * @param   string	$action         'add', 'update', 'view'
	 * @return  int 		            Return integer <0 if KO,
	 *                                  =0 if OK but we want to process standard actions too,
	 *                                  >0 if OK and we want to replace standard actions.
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0;
		$deltemp = array();
		dol_syslog(get_class($this) . '::executeHooks action=' . $action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
			// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}



	/**
	 * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             Return integer < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$langs->load("safra@safra");

		$this->results = array();

		$head = array();
		$h = 0;

		if ($parameters['tabfamily'] == 'safra') {
			$head[$h][0] = dol_buildpath('/module/index.php', 1);
			$head[$h][1] = $langs->trans("Home");
			$head[$h][2] = 'home';
			$h++;

			$this->results['title'] = $langs->trans("Safra");
			$this->results['picto'] = 'safra@safra';
		}

		$head[$h][0] = 'customreports.php?objecttype=' . $parameters['objecttype'] . (empty($parameters['tabfamily']) ? '' : '&tabfamily=' . $parameters['tabfamily']);
		$head[$h][1] = $langs->trans("CustomReports");
		$head[$h][2] = 'customreports';

		$this->results['head'] = $head;

		return 1;
	}



	/**
	 * Overloading the restrictedArea function : check permission on an object
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int 		      			  	Return integer <0 if KO,
	 *                          				=0 if OK but we want to process standard actions too,
	 *  	                            		>0 if OK and we want to replace standard actions.
	 */
	public function restrictedArea($parameters, &$action, $hookmanager)
	{
		global $user;

		if ($parameters['features'] == 'myobject') {
			if ($user->hasRight('safra', 'myobject', 'read')) {
				$this->results['result'] = 1;
				return 1;
			} else {
				$this->results['result'] = 0;
				return 1;
			}
		}

		return 0;
	}

	/**
	 * Execute action completeTabsHead
	 *
	 * @param   array           $parameters     Array of parameters
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         'add', 'update', 'view'
	 * @param   Hookmanager     $hookmanager    hookmanager
	 * @return  int                             Return integer <0 if KO,
	 *                                          =0 if OK but we want to process standard actions too,
	 *                                          >0 if OK and we want to replace standard actions.
	 */
        public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
        {
                global $langs, $user;

                if (empty($parameters['object']->element)) {
                        return 0;
                }

                if ($parameters['mode'] === 'remove') {
                        return 0;
                }

                if ($parameters['mode'] !== 'add') {
                        return -1;
                }

                if ($parameters['object']->element !== 'product' || !isModEnabled('safra')) {
                        return 0;
                }

                $langs->load('safra@safra');

                $counter = count($parameters['head']);
                $added = false;
                $id = (int) $parameters['object']->id;
                if ($id <= 0) {
                        return 0;
                }

                if (!empty($user->rights->safra->produtoformulado->read)) {
                        $count = count(SafraProductLink::fetchLinkedIds($this->db, $id, 'formulados'));
                        $parameters['head'][$counter][0] = dol_buildpath('/safra/product_safra_links.php', 1) . '?id=' . $id . '&type=formulados';
                        $parameters['head'][$counter][1] = $langs->trans('SafraProductTabFormulados');
                        if ($count > 0) {
                                $parameters['head'][$counter][1] .= '<span class="badge marginleftonlyshort">' . $count . '</span>';
                        }
                        $parameters['head'][$counter][2] = 'safra_formulados';
                        $counter++;
                        $added = true;
                }

                if (!empty($user->rights->safra->produtostecnicos->read)) {
                        $count = count(SafraProductLink::fetchLinkedIds($this->db, $id, 'tecnicos'));
                        $parameters['head'][$counter][0] = dol_buildpath('/safra/product_safra_links.php', 1) . '?id=' . $id . '&type=tecnicos';
                        $parameters['head'][$counter][1] = $langs->trans('SafraProductTabTecnicos');
                        if ($count > 0) {
                                $parameters['head'][$counter][1] .= '<span class="badge marginleftonlyshort">' . $count . '</span>';
                        }
                        $parameters['head'][$counter][2] = 'safra_tecnicos';
                        $counter++;
                        $added = true;
                }

                if ($added && (int) DOL_VERSION < 14) {
                        $this->results = $parameters['head'];
                        return 1;
                }

                return 0;
        }

	/* Add here any other hooked methods... */


	/**
	 * Exibe conteúdo extra no formulário dos objetos (inclui a tela de criação de Projeto)
	 *
	 * @param array         $parameters    Metadados do hook (contexto, etc.)
	 * @param CommonObject  $object        Objeto atual (no create de projeto, ainda sem id)
	 * @param string        $action        Ação corrente (esperamos 'create')
	 * @param HookManager   $hookmanager   Hook manager
	 * @return int          <0 erro, 0 segue fluxo padrão, 1 substitui código padrão
	 */
	// public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	// {
	// 	global $langs, $conf, $user;

	// 	// Queremos agir apenas na criação de Projetos
	// 	if (
	// 		!empty($parameters['currentcontext'])
	// 		&& $parameters['currentcontext'] === 'projectcard'
	// 		&& $action === 'create'
	// 	) {
	// 		// Carrega suas traduções (opcional)
	// 		$langs->load('safra@safra');

	// 		// HTML do botão (classe Dolibarr 'butAction' dá o estilo de botão de ação)
	// 		// type="button" pra não submeter o form; onclick só pra demonstrar
	// 		$html  = '<div class="clearfix" style="margin: 8px 0;">';
	// 		$html .= '  <button type="button" class="butAction" id="safra-test-btn" onclick="javascript:alert(\'Botão de teste do Safra!\');">';
	// 		$html .=        $langs->trans('TesteSafra'); // se quiser, adicione a string no seu arquivo de idioma
	// 		$html .= '  </button>';
	// 		$html .= '</div>';

	// 		// Imprime no formulário
	// 		$this->resprints .= $html;

	// 		// Retorna 0 para continuar com o fluxo padrão da página
	// 		return 0;
	// 	}

	// 	return 0;
	// }


        public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
        {
                global $langs, $user;

                if (!empty($parameters['currentcontext']) && $parameters['currentcontext'] === 'productcard') {
                        if (!isModEnabled('safra') || !in_array($action, array('create', 'edit'), true)) {
                                return 0;
                        }

                        $canFormulados = !empty($user->rights->safra->produtoformulado->read);
                        $canTecnicos = !empty($user->rights->safra->produtostecnicos->read);

                        if (!$canFormulados && !$canTecnicos) {
                                return 0;
                        }

                        $langs->load('safra@safra');

                        print '<input type="hidden" name="safra_product_links_submitted" value="1">';

                        $formuladosSelected = array();
                        $tecnicosSelected = array();

                        if (!empty($object->id)) {
                                $formuladosSelected = SafraProductLink::fetchLinkedIds($this->db, $object->id, 'formulados');
                                $tecnicosSelected = SafraProductLink::fetchLinkedIds($this->db, $object->id, 'tecnicos');
                        } else {
                                if (GETPOSTISSET('safra_fk_formulados')) {
                                        $formuladosSelected = GETPOST('safra_fk_formulados', 'array:int');
                                }
                                if (GETPOSTISSET('safra_fk_tecnicos')) {
                                        $tecnicosSelected = GETPOST('safra_fk_tecnicos', 'array:int');
                                }
                        }

                        if (!is_array($formuladosSelected)) {
                                $formuladosSelected = array();
                        }
                        if (!is_array($tecnicosSelected)) {
                                $tecnicosSelected = array();
                        }

                        $formuladosToggle = GETPOSTISSET('safra_link_formulados_flag') ? GETPOST('safra_link_formulados_flag', 'int') : (!empty($formuladosSelected) ? 1 : 0);
                        $tecnicosToggle = GETPOSTISSET('safra_link_tecnicos_flag') ? GETPOST('safra_link_tecnicos_flag', 'int') : (!empty($tecnicosSelected) ? 1 : 0);

                        if ($canFormulados) {
                                $options = SafraProductLink::fetchOptions($this->db, 'formulados');
                                print '<tr class="safra-product-link-row">';
                                print '<td class="titlefield">';
                                print '<label><input type="checkbox" id="safra_link_formulados_toggle" name="safra_link_formulados_flag" value="1"'.($formuladosToggle ? ' checked' : '').'> ';
                                print $langs->trans('SafraProductLinkFormulados');
                                print '</label>';
                                print '</td>';
                                print '<td>';
                                print '<div id="safra_link_formulados_container" class="safra-link-container"'.($formuladosToggle ? '' : ' style="display:none;"').'>';
                                print '<select name="safra_fk_formulados[]" class="flat minwidth300 select2" multiple data-placeholder="'.dol_escape_htmltag($langs->trans('SafraProductLinkPlaceholder')).'">';
                                foreach ($options as $id => $label) {
                                        $selected = in_array((int) $id, $formuladosSelected, true) ? ' selected' : '';
                                        print '<option value="'.((int) $id).'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
                                }
                                print '</select>';
                                print '<div class="opacitymedium small">'.$langs->trans('SafraProductLinkFormuladosHelp').'</div>';
                                print '</div>';
                                print '</td>';
                                print '</tr>';
                        }

                        if ($canTecnicos) {
                                $options = SafraProductLink::fetchOptions($this->db, 'tecnicos');
                                print '<tr class="safra-product-link-row">';
                                print '<td class="titlefield">';
                                print '<label><input type="checkbox" id="safra_link_tecnicos_toggle" name="safra_link_tecnicos_flag" value="1"'.($tecnicosToggle ? ' checked' : '').'> ';
                                print $langs->trans('SafraProductLinkTecnicos');
                                print '</label>';
                                print '</td>';
                                print '<td>';
                                print '<div id="safra_link_tecnicos_container" class="safra-link-container"'.($tecnicosToggle ? '' : ' style="display:none;"').'>';
                                print '<select name="safra_fk_tecnicos[]" class="flat minwidth300 select2" multiple data-placeholder="'.dol_escape_htmltag($langs->trans('SafraProductLinkPlaceholder')).'">';
                                foreach ($options as $id => $label) {
                                        $selected = in_array((int) $id, $tecnicosSelected, true) ? ' selected' : '';
                                        print '<option value="'.((int) $id).'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
                                }
                                print '</select>';
                                print '<div class="opacitymedium small">'.$langs->trans('SafraProductLinkTecnicosHelp').'</div>';
                                print '</div>';
                                print '</td>';
                                print '</tr>';
                        }

                        return 0;
                }

                if (
                        !empty($parameters['currentcontext'])
                        && $parameters['currentcontext'] === 'projectcard'
                        && !empty($object->id)
                ) {
                        $langs->loadLangs(array('safra@safra'));

                        $selectedTalhaoId = empty($object->array_options['options_fk_talhao']) ? null : (string) $object->array_options['options_fk_talhao'];

                        $config = array(
                                'ajaxTalhaoUrl' => dol_buildpath('/safra/ajax/talhao_geojson.php', 1),
                                'mapHint' => $langs->transnoentities('SafraMapHint'),
                                'mapMessages' => array(
                                        'loading' => $langs->transnoentities('SafraMapLoading'),
                                        'empty' => $langs->transnoentities('SafraMapEmpty'),
                                        'error' => $langs->transnoentities('SafraMapError'),
                                        'fetchError' => $langs->transnoentities('SafraMapFetchError'),
                                ),
                                'leafletCssLocal' => dol_buildpath('/safra/css/leaflet.css', 1),
                                'leafletJsLocal' => dol_buildpath('/safra/js/leaflet.js', 1),
                                'wellknownJs' => dol_buildpath('/safra/js/wellknown.js', 1),
                                'tileLayer' => array(
                                        'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                                        'options' => array(
                                                'attribution' => 'Tiles © Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
                                                'maxZoom' => 19,
                                        ),
                                ),
                        );

                        if ($selectedTalhaoId !== null) {
                                $config['initialTalhaoId'] = $selectedTalhaoId;
                        }

                        $this->resprints .= $this->getMapContainerOnce();

                        $this->resprints .= '<script>window.SAFRA = Object.assign({}, window.SAFRA || {}, '.json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).');</script>';

                        $this->resprints .= '<script src="' . dol_buildpath('/safra/js/hooks/talhao_map.js', 1) . '?v=' . urlencode(DOL_VERSION) . '"></script>';
                }

                return 0;
        }

        /**
         * Render hook map container only once per request.
         *
         * @return string
         */
        private function getMapContainerOnce()
        {
                static $done = false;
                if ($done) return '';
		$done = true;

		ob_start();
		global $langs; // usado no template
		$tpl = dol_buildpath('/safra/tpl/hooks/talhao_map.tpl.php', 0); // ✅ caminho no FS
		if (is_readable($tpl)) {
			include $tpl;
		} else {
			dol_syslog(__METHOD__ . ': Template não encontrado ou não legível: ' . $tpl, LOG_WARNING);
		}
		return ob_get_clean();
	}
}
