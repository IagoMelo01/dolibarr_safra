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

                $contexts = array();
                if (!empty($parameters['context'])) {
                        $contexts = explode(':', $parameters['context']);
                } elseif (!empty($parameters['currentcontext'])) {
                        $contexts = array($parameters['currentcontext']);
                }

                if (in_array('productdao', $contexts, true)) {
                        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                                return 0;
                        }

                        $hasFormulado = GETPOSTISSET('safra_link_enable_formulado') || GETPOSTISSET('safra_link_enable_formulado_present');
                        $hasTecnico = GETPOSTISSET('safra_link_enable_tecnico') || GETPOSTISSET('safra_link_enable_tecnico_present');
                        $hasFormuladoSelection = GETPOSTISSET('safra_link_formulados');
                        $hasTecnicoSelection = GETPOSTISSET('safra_link_produtostecnicos');

                        if (!$hasFormulado && !$hasTecnico && !$hasFormuladoSelection && !$hasTecnicoSelection) {
                                return 0;
                        }

                        if (empty($object) || !is_object($object) || (int) $object->id <= 0) {
                                return 0;
                        }

                        $langs->loadLangs(array('safra@safra'));

                        dol_include_once('/safra/class/safra_product_link.class.php');

                        if (!SafraProductLink::ensureDatabaseSchema($this->db)) {
                                setEventMessages($langs->trans('SafraProductLinkSchemaError'), null, 'errors');

                                return 0;
                        }

                        $formuladoSelection = GETPOST('safra_link_formulados', 'array:int');
                        if (!is_array($formuladoSelection)) {
                                $formuladoSelection = array();
                        }

                        $tecnicoSelection = GETPOST('safra_link_produtostecnicos', 'array:int');
                        if (!is_array($tecnicoSelection)) {
                                $tecnicoSelection = array();
                        }

                        $formuladoRowPresent = $hasFormulado;
                        $tecnicoRowPresent = $hasTecnico;

                        $formuladoEnabled = $formuladoRowPresent ? (GETPOSTISSET('safra_link_enable_formulado') ? (bool) GETPOSTINT('safra_link_enable_formulado') : false) : null;
                        $tecnicoEnabled = $tecnicoRowPresent ? (GETPOSTISSET('safra_link_enable_tecnico') ? (bool) GETPOSTINT('safra_link_enable_tecnico') : false) : null;

                        if ($formuladoEnabled === false) {
                                $formuladoSelection = array();
                        }
                        if ($tecnicoEnabled === false) {
                                $tecnicoSelection = array();
                        }

                        if ($formuladoRowPresent && !empty($user->rights->safra->produtoformulado->write)) {
                                if (!SafraProductLink::replaceLinks($this->db, $object->id, SafraProductLink::TYPE_FORMULADO, $formuladoSelection)) {
                                        setEventMessages($langs->trans('SafraProductLinkSaveError'), null, 'errors');
                                }
                        }

                        if ($tecnicoRowPresent && !empty($user->rights->safra->produtostecnicos->write)) {
                                if (!SafraProductLink::replaceLinks($this->db, $object->id, SafraProductLink::TYPE_TECNICO, $tecnicoSelection)) {
                                        setEventMessages($langs->trans('SafraProductLinkSaveError'), null, 'errors');
                                }
                        }

                        return 0;
                }

                return 0;
        }

        public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
        {
                global $langs, $db, $user;

                $contexts = array();
                if (!empty($parameters['context'])) {
                        $contexts = explode(':', $parameters['context']);
                } elseif (!empty($parameters['currentcontext'])) {
                        $contexts = array($parameters['currentcontext']);
                }

                $langs->loadLangs(array('safra@safra'));

                $productRows = array();

                if (in_array('productcard', $contexts, true)) {
                        dol_include_once('/safra/class/safra_product_link.class.php');

                        if (!SafraProductLink::ensureDatabaseSchema($db)) {
                                $productRows[] = "\t\t<tr><td colspan=\"2\" class=\"error\">" . dol_escape_htmltag($langs->trans('SafraProductLinkSchemaError')) . '</td></tr>';
                        } else {
                                if (!empty($user->rights->safra->produtoformulado->read)) {
                                        $formuladoSelection = GETPOST('safra_link_formulados', 'array:int');
                                        if (!is_array($formuladoSelection)) {
                                                $formuladoSelection = array();
                                        }

                                        $formuladoEnabled = null;
                                        if (GETPOSTISSET('safra_link_enable_formulado_present')) {
                                                $formuladoEnabled = GETPOSTISSET('safra_link_enable_formulado') ? 1 : 0;
                                        }

                                        if ((int) $object->id > 0 && $formuladoEnabled === null && empty($formuladoSelection)) {
                                                $formuladoSelection = SafraProductLink::fetchLinkedIds($db, $object->id, SafraProductLink::TYPE_FORMULADO);
                                                $formuladoEnabled = count($formuladoSelection) > 0 ? 1 : 0;
                                        }

                                        if ($formuladoEnabled === null) {
                                                $formuladoEnabled = !empty($formuladoSelection) ? 1 : 0;
                                        }

                                        $options = SafraProductLink::fetchAvailableOptions($db, SafraProductLink::TYPE_FORMULADO);
                                        foreach ($formuladoSelection as $selectedId) {
                                                if (!isset($options[$selectedId])) {
                                                        $label = SafraProductLink::fetchOptionLabel($db, SafraProductLink::TYPE_FORMULADO, $selectedId);
                                                        if ($label !== null) {
                                                                $options[$selectedId] = $label;
                                                        }
                                                }
                                        }

                                        ksort($options);

                                        $productRows[] = $this->renderProductLinkRow(
                                                'safra_link_enable_formulado',
                                                'safra_link_formulados',
                                                $langs->trans('SafraLinkFormuladoCheckbox'),
                                                $langs->trans('SafraLinkFormuladoHelp'),
                                                $options,
                                                $formuladoSelection,
                                                (bool) $formuladoEnabled
                                        );
                                }

                                if (!empty($user->rights->safra->produtostecnicos->read)) {
                                        $tecnicoSelection = GETPOST('safra_link_produtostecnicos', 'array:int');
                                        if (!is_array($tecnicoSelection)) {
                                                $tecnicoSelection = array();
                                        }

                                        $tecnicoEnabled = null;
                                        if (GETPOSTISSET('safra_link_enable_tecnico_present')) {
                                                $tecnicoEnabled = GETPOSTISSET('safra_link_enable_tecnico') ? 1 : 0;
                                        }

                                        if ((int) $object->id > 0 && $tecnicoEnabled === null && empty($tecnicoSelection)) {
                                                $tecnicoSelection = SafraProductLink::fetchLinkedIds($db, $object->id, SafraProductLink::TYPE_TECNICO);
                                                $tecnicoEnabled = count($tecnicoSelection) > 0 ? 1 : 0;
                                        }

                                        if ($tecnicoEnabled === null) {
                                                $tecnicoEnabled = !empty($tecnicoSelection) ? 1 : 0;
                                        }

                                        $options = SafraProductLink::fetchAvailableOptions($db, SafraProductLink::TYPE_TECNICO);
                                        foreach ($tecnicoSelection as $selectedId) {
                                                if (!isset($options[$selectedId])) {
                                                        $label = SafraProductLink::fetchOptionLabel($db, SafraProductLink::TYPE_TECNICO, $selectedId);
                                                        if ($label !== null) {
                                                                $options[$selectedId] = $label;
                                                        }
                                                }
                                        }

                                        ksort($options);

                                        $productRows[] = $this->renderProductLinkRow(
                                                'safra_link_enable_tecnico',
                                                'safra_link_produtostecnicos',
                                                $langs->trans('SafraLinkTecnicoCheckbox'),
                                                $langs->trans('SafraLinkTecnicoHelp'),
                                                $options,
                                                $tecnicoSelection,
                                                (bool) $tecnicoEnabled
                                        );
                                }
                        }

                        if (!empty($productRows)) {
                                $this->resprints .= implode('', $productRows);
                                $this->resprints .= '<script>jQuery(function($){function safraToggleLinkRow(cb,container){var checked=$(cb).is(\':checked\');$(container).toggle(checked);}safraToggleLinkRow("#safra_link_enable_formulado","#safra_link_formulados_container");safraToggleLinkRow("#safra_link_enable_tecnico","#safra_link_produtostecnicos_container");$("#safra_link_enable_formulado").on("change",function(){safraToggleLinkRow(this,"#safra_link_formulados_container");});$("#safra_link_enable_tecnico").on("change",function(){safraToggleLinkRow(this,"#safra_link_produtostecnicos_container");});});</script>';
                        }
                }

                if (in_array('projectcard', $contexts, true) && !empty($object->id)) {
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

        private function renderProductLinkRow($checkboxName, $selectName, $label, $help, array $options, array $selection, $enabled)
        {
                $checkboxId = dol_string_nospecial(trim($checkboxName));
                $selectId = dol_string_nospecial(trim($selectName)) . '_select';
                $containerId = $selectName . '_container';

                $out = "\t\t<tr class=\"oddeven\">";
                $out .= '<td class="titlefield">';
                $out .= '<input type="hidden" name="' . dol_escape_htmltag($checkboxName) . '_present" value="1">';
                $out .= '<label><input type="checkbox" id="' . dol_escape_htmltag($checkboxId) . '" name="' . dol_escape_htmltag($checkboxName) . '" value="1"' . ($enabled ? ' checked' : '') . '> ' . dol_escape_htmltag($label) . '</label>';
                $out .= '</td>';
                $out .= '<td>';
                $out .= '<div id="' . dol_escape_htmltag($containerId) . '"' . ($enabled ? '' : ' style="display:none"') . '>';
                $out .= '<select id="' . dol_escape_htmltag($selectId) . '" name="' . dol_escape_htmltag($selectName) . '[]" class="flat minwidth300" multiple>';
                foreach ($options as $id => $optionLabel) {
                        $selected = in_array((int) $id, $selection, true) ? ' selected' : '';
                        $out .= '<option value="' . (int) $id . '"' . $selected . '>' . dol_escape_htmltag($optionLabel) . '</option>';
                }
                $out .= '</select>';
                $out .= '<div class="opacitymedium small">' . dol_escape_htmltag($help) . '</div>';
                $out .= '</div>';
                $out .= '</td>';
                $out .= '</tr>';

                return $out;
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
