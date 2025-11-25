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
                        if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                                dol_include_once('/safra/class/safra_product_link.class.php');

                                SafraProductLink::capturePostedSelections();
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
                                $postedSelections = SafraProductLink::getPostedSelections();

                                if (!empty($user->rights->safra->produtoformulado->read)) {
                                        $formuladoSelection = array();
                                        $formuladoEnabled = null;

                                        if (isset($postedSelections[SafraProductLink::TYPE_FORMULADO])) {
                                                $formuladoSelection = $postedSelections[SafraProductLink::TYPE_FORMULADO]['ids'];
                                                $formuladoEnabled = $postedSelections[SafraProductLink::TYPE_FORMULADO]['enabled'] ? 1 : 0;
                                        } else {
                                                $formuladoSelection = GETPOST('safra_link_formulados', 'array:int');
                                                if (!is_array($formuladoSelection)) {
                                                        $formuladoSelection = array();
                                                }

                                                if (GETPOSTISSET('safra_link_enable_formulado_present')) {
                                                        $formuladoEnabled = GETPOSTISSET('safra_link_enable_formulado') ? 1 : 0;
                                                }
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
                                        $tecnicoSelection = array();
                                        $tecnicoEnabled = null;

                                        if (isset($postedSelections[SafraProductLink::TYPE_TECNICO])) {
                                                $tecnicoSelection = $postedSelections[SafraProductLink::TYPE_TECNICO]['ids'];
                                                $tecnicoEnabled = $postedSelections[SafraProductLink::TYPE_TECNICO]['enabled'] ? 1 : 0;
                                        } else {
                                                $tecnicoSelection = GETPOST('safra_link_produtostecnicos', 'array:int');
                                                if (!is_array($tecnicoSelection)) {
                                                        $tecnicoSelection = array();
                                                }

                                                if (GETPOSTISSET('safra_link_enable_tecnico_present')) {
                                                        $tecnicoEnabled = GETPOSTISSET('safra_link_enable_tecnico') ? 1 : 0;
                                                }
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

                                if (!empty($user->rights->safra->cultivar->read)) {
                                        $cultivarSelection = array();
                                        $cultivarEnabled = null;

                                        if (isset($postedSelections[SafraProductLink::TYPE_CULTIVAR])) {
                                                $cultivarSelection = $postedSelections[SafraProductLink::TYPE_CULTIVAR]['ids'];
                                                $cultivarEnabled = $postedSelections[SafraProductLink::TYPE_CULTIVAR]['enabled'] ? 1 : 0;
                                        } else {
                                                $cultivarSelection = GETPOST('safra_link_cultivares', 'array:int');
                                                if (!is_array($cultivarSelection)) {
                                                        $cultivarSelection = array();
                                                }

                                                if (GETPOSTISSET('safra_link_enable_cultivar_present')) {
                                                        $cultivarEnabled = GETPOSTISSET('safra_link_enable_cultivar') ? 1 : 0;
                                                }
                                        }

                                        if ((int) $object->id > 0 && $cultivarEnabled === null && empty($cultivarSelection)) {
                                                $cultivarSelection = SafraProductLink::fetchLinkedIds($db, $object->id, SafraProductLink::TYPE_CULTIVAR);
                                                $cultivarEnabled = count($cultivarSelection) > 0 ? 1 : 0;
                                        }

                                        if ($cultivarEnabled === null) {
                                                $cultivarEnabled = !empty($cultivarSelection) ? 1 : 0;
                                        }

                                        $options = array();
                                        foreach ($cultivarSelection as $selectedId) {
                                                $label = SafraProductLink::fetchOptionLabel($db, SafraProductLink::TYPE_CULTIVAR, $selectedId);
                                                if ($label !== null) {
                                                        $options[$selectedId] = $label;
                                                }
                                        }

                                        if (!empty($options)) {
                                                ksort($options);
                                        }

                                        $productRows[] = $this->renderProductLinkRow(
                                                'safra_link_enable_cultivar',
                                                'safra_link_cultivares',
                                                $langs->trans('SafraLinkCultivarCheckbox'),
                                                $langs->trans('SafraLinkCultivarHelp'),
                                                $options,
                                                $cultivarSelection,
                                                (bool) $cultivarEnabled,
                                                array(
                                                        'data-safra-link-type' => 'cultivar',
                                                        'data-safra-ajax-url' => dol_buildpath('/safra/ajax/product_links.php', 1) . '?type=cultivar',
                                                        'data-placeholder' => $langs->trans('SafraLinkCultivarPlaceholder'),
                                                        'data-safra-min-length' => 2,
                                                        'data-safra-page-size' => 25,
                                                )
                                        );
                                }
                        }

                        if (!empty($productRows)) {
                                $this->resprints .= implode('', $productRows);
                                $this->resprints .= '<script>jQuery(function($){function safraToggleLinkRow(cb,container){var checked=$(cb).is(\':checked\');$(container).toggle(checked);}function safraInitSelect2($el){if(!$el.length||!$.fn.select2){return;}var config={width:"resolve"};var placeholder=$el.data("placeholder");if(placeholder){config.placeholder=placeholder;}var allowClear=$el.data("allowClear");if(typeof allowClear!==\'undefined\'){config.allowClear=!!allowClear;}var linkType=$el.data("safraLinkType");if(linkType==="cultivar"){var ajaxUrl=$el.data("safraAjaxUrl");if(ajaxUrl){var minLength=parseInt($el.data("safraMinLength"),10);if(isNaN(minLength)||minLength<0){minLength=0;}if(minLength>0){config.minimumInputLength=minLength;}var pageSize=parseInt($el.data("safraPageSize"),10);if(isNaN(pageSize)||pageSize<1){pageSize=25;}config.ajax={url:ajaxUrl,dataType:"json",delay:250,cache:true,data:function(params){var page=params.page||1;return{term:params.term||"",page:page,limit:pageSize};},processResults:function(data,params){params.page=params.page||1;var results=[];if(data&&data.results){results=data.results;}var more=false;if(data&&data.pagination&&typeof data.pagination.more!==\'undefined\'){more=!!data.pagination.more;}return{results:results,pagination:{more:more}};}};config.escapeMarkup=function(markup){return markup;};config.templateResult=function(item){if(item.loading){return item.text;}return item.text||\'\';};config.templateSelection=function(item){return item.text||item.id;};}}$el.select2(config);}safraToggleLinkRow("#safra_link_enable_formulado","#safra_link_formulados_container");safraToggleLinkRow("#safra_link_enable_tecnico","#safra_link_produtostecnicos_container");safraToggleLinkRow("#safra_link_enable_cultivar","#safra_link_cultivares_container");$("#safra_link_enable_formulado").on("change",function(){safraToggleLinkRow(this,"#safra_link_formulados_container");});$("#safra_link_enable_tecnico").on("change",function(){safraToggleLinkRow(this,"#safra_link_produtostecnicos_container");});$("#safra_link_enable_cultivar").on("change",function(){safraToggleLinkRow(this,"#safra_link_cultivares_container");});$(".safra-select2").each(function(){safraInitSelect2($(this));});});</script>';
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

                        $activityLinks = array(
                                'list' => dol_buildpath('/safra/activity/activity_list.php', 1) . '?search_fk_project=' . $object->id,
                                'new' => dol_buildpath('/safra/activity/activity_card.php', 1) . '?action=create&fk_project=' . $object->id,
                                'labelList' => $langs->transnoentities('SafraActivityListTitle'),
                                'labelNew' => $langs->transnoentities('SafraMenuNewAplicacoes'),
                        );

                        $this->resprints .= '<script>jQuery(function($){var data=' . json_encode($activityLinks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';var $container=$(".tabsAction").first();if(!$container.length){$container=$("<div class=\"tabsAction\"></div>");$(".fichecenter").first().before($container);}if($container.find(".safra-project-activities").length){return;}var $wrap=$("<span class=\"safra-project-activities\"></span>");var $btnList=$("<a>").addClass("butAction").attr("href",data.list).text(data.labelList);var $btnNew=$("<a>").addClass("butAction").attr("href",data.new).text(data.labelNew);$wrap.append($btnList).append("&nbsp;").append($btnNew);$container.prepend($wrap);});</script>';
                }

                return 0;
        }

        private function renderProductLinkRow($checkboxName, $selectName, $label, $help, array $options, array $selection, $enabled, array $attributes = array())
        {
                global $form;

                if (!is_object($form)) {
                        require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
                        $form = new Form($this->db);
                }

                $checkboxId = dol_string_nospecial(trim($checkboxName));
                $containerId = $selectName . '_container';

                $out = "\t\t<tr class=\"oddeven\">";
                $out .= '<td class="titlefield">';
                $out .= '<input type="hidden" name="' . dol_escape_htmltag($checkboxName) . '_present" value="1">';
                $out .= '<label><input type="checkbox" id="' . dol_escape_htmltag($checkboxId) . '" name="' . dol_escape_htmltag($checkboxName) . '" value="1"' . ($enabled ? ' checked' : '') . '> ' . dol_escape_htmltag($label) . '</label>';
                $out .= '</td>';
                $out .= '<td>';
                $out .= '<div id="' . dol_escape_htmltag($containerId) . '"' . ($enabled ? '' : ' style="display:none"') . '>';
                $selectHtml = $form->multiselectarray(
                        $selectName,
                        $options,
                        $selection,
                        0,
                        0,
                        '',
                        '',
                        400,
                        0,
                        'minwidth300 select2 safra-select2'
                );

                if (!empty($attributes)) {
                        $selectHtml = $this->injectSelectAttributes($selectHtml, $attributes);
                }

                $out .= $selectHtml;
                $out .= '<div class="opacitymedium small">' . dol_escape_htmltag($help) . '</div>';
                $out .= '</div>';
                $out .= '</td>';
                $out .= '</tr>';

                return $out;
        }

        private function injectSelectAttributes($html, array $attributes)
        {
                if (strpos($html, '<select') === false) {
                        return $html;
                }

                $parts = array();
                foreach ($attributes as $name => $value) {
                        if ($value === false || $value === null) {
                                continue;
                        }

                        $attrName = preg_replace('/[^a-zA-Z0-9_\-:]/', '', (string) $name);
                        if ($attrName === '') {
                                continue;
                        }

                        if ($value === true) {
                                $parts[] = $attrName;
                        } else {
                                $parts[] = $attrName . '="' . dol_escape_htmltag((string) $value) . '"';
                        }
                }

                if (empty($parts)) {
                        return $html;
                }

                $injection = trim(implode(' ', $parts));

                if ($injection === '') {
                        return $html;
                }

                $updated = preg_replace('/<select(\s)/', '<select ' . $injection . '$1', $html, 1);
                if (is_string($updated) && $updated !== $html) {
                        return $updated;
                }

                $fallback = preg_replace('/<select>/', '<select ' . $injection . '>', $html, 1);
                if (is_string($fallback)) {
                        return $fallback;
                }

                return $html;
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
