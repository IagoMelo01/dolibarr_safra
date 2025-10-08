<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 *      \file       safra/produtividade_view.php
 *      \ingroup    safra
 *      \brief      Embrapa productivity estimation page.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
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

dol_include_once('/safra/class/cultura.class.php');
dol_include_once('/safra/class/cultivar.class.php');
dol_include_once('/safra/class/municipio.class.php');
dol_include_once('/safra/class/embrapaapi.class.php');

// Load translation files required by the page
$langs->loadLangs(array('safra@safra', 'other')); // 'other' to leverage generic messages

$action = GETPOST('action', 'aZ09');

// Security check
if (!$user->hasRight('safra', 'produtividade', 'read')) {
    accessforbidden();
}

$parameters = array();
$errors = array();
$resultData = null;
$rawResponse = '';
$chartPayload = array();

$idCultura = GETPOST('idCultura', 'int');
$idCultivar = GETPOST('idCultivar', 'int');
$codigoIBGE = GETPOST('codigoIBGE', 'int');
$cad = GETPOST('cad', 'int');
$dataPlantio = trim(GETPOST('dataPlantio', 'alphanohtml'));
$expectativaInput = GETPOST('expectativaProdutividade', 'alphanohtml');
$latitudeInput = trim(GETPOST('latitude', 'alphanohtml'));
$longitudeInput = trim(GETPOST('longitude', 'alphanohtml'));

$expectativaProdutividade = $expectativaInput !== '' ? price2num($expectativaInput, '2') : '';
$latitude = $latitudeInput !== '' ? price2num($latitudeInput, '8') : '';
$longitude = $longitudeInput !== '' ? price2num($longitudeInput, '8') : '';

$embrapaCulturaId = null;
$embrapaCultivarId = null;

if ($action === 'calculate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $requiredFields = array(
        'idCultura' => $langs->trans('Cultura'),
        'idCultivar' => $langs->trans('Cultivar'),
        'codigoIBGE' => $langs->trans('Municipio'),
        'cad' => $langs->trans('ProdutividadeSoilWaterCapacity'),
        'dataPlantio' => $langs->trans('ProdutividadePlantingDate'),
        'expectativaProdutividade' => $langs->trans('ProdutividadeYieldExpectation')
    );

    if ($idCultura <= 0) {
        $errors[] = $langs->trans('ProdutividadeMissingField', $requiredFields['idCultura']);
    } else {
        $culturaRecord = new Cultura($db);
        if ($culturaRecord->fetch($idCultura) > 0) {
            $embrapaCulturaId = (int) $culturaRecord->embrapa_id;
            if ($embrapaCulturaId <= 0) {
                $errors[] = $langs->trans('ProdutividadeMissingEmbrapaCultura');
            }
        } else {
            $errors[] = $langs->trans('ProdutividadeCulturaNotFound');
        }
    }
    if ($idCultivar <= 0) {
        $errors[] = $langs->trans('ProdutividadeMissingField', $requiredFields['idCultivar']);
    } else {
        $cultivarRecord = new Cultivar($db);
        if ($cultivarRecord->fetch($idCultivar) > 0) {
            $embrapaCultivarId = (int) $cultivarRecord->embrapa_id;
            if ($embrapaCultivarId <= 0) {
                $errors[] = $langs->trans('ProdutividadeMissingEmbrapaCultivar');
            }
        } else {
            $errors[] = $langs->trans('ProdutividadeCultivarNotFound');
        }
    }
    if ($codigoIBGE <= 0) {
        $errors[] = $langs->trans('ProdutividadeMissingField', $requiredFields['codigoIBGE']);
    }
    if ($cad <= 0) {
        $errors[] = $langs->trans('ProdutividadeMissingField', $requiredFields['cad']);
    }
    if (empty($dataPlantio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPlantio)) {
        $errors[] = $langs->trans('ProdutividadeInvalidDate');
    }
    if ($expectativaProdutividade === '') {
        $errors[] = $langs->trans('ProdutividadeMissingField', $requiredFields['expectativaProdutividade']);
    }

    if (($latitude === '' && $longitude !== '') || ($latitude !== '' && $longitude === '')) {
        $errors[] = $langs->trans('ProdutividadeCoordinatesRequired');
    }

    if (empty($errors)) {
        $parameters = array(
            'idCultura' => (int) $embrapaCulturaId,
            'idCultivar' => (int) $embrapaCultivarId,
            'codigoIBGE' => (int) $codigoIBGE,
            'cad' => (int) $cad,
            'dataPlantio' => $dataPlantio,
            'expectativaProdutividade' => (float) $expectativaProdutividade
        );

        if ($latitude !== '' && $longitude !== '') {
            $parameters['latitude'] = (float) $latitude;
            $parameters['longitude'] = (float) $longitude;
        }

        $api = new EmbrapaApi($db);
        $apiError = '';
        $resultData = $api->fetchProdutividade($parameters, $apiError);

        if (is_array($resultData)) {
            $rawResponse = json_encode($resultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            setEventMessages($langs->trans('ProdutividadeSuccess'), null, 'mesgs');
            if (isset($resultData['data']) && is_array($resultData['data'])) {
                $seriesSource = $resultData['data'];
                $chartDefinitions = array(
                    'yield' => array(
                        'title' => $langs->trans('ProdutividadeChartYieldTitle'),
                        'unit' => $langs->trans('ProdutividadeChartYieldUnit'),
                        'series' => array(
                            'produtividadeAlmejada' => $langs->trans('ProdutividadeChartDesiredYield'),
                            'produtividadeMediaMunicipio' => $langs->trans('ProdutividadeChartMunicipalAverage')
                        )
                    ),
                    'temperature' => array(
                        'title' => $langs->trans('ProdutividadeChartTemperatureTitle'),
                        'unit' => $langs->trans('ProdutividadeChartTemperatureUnit'),
                        'series' => array(
                            'temperaturaMinima' => $langs->trans('ProdutividadeChartMinTemperature'),
                            'temperaturaMaxima' => $langs->trans('ProdutividadeChartMaxTemperature')
                        )
                    ),
                    'rain' => array(
                        'title' => $langs->trans('ProdutividadeChartRainTitle'),
                        'unit' => $langs->trans('ProdutividadeChartRainUnit'),
                        'series' => array(
                            'precipitacao' => $langs->trans('ProdutividadeChartRainfall')
                        )
                    ),
                    'water' => array(
                        'title' => $langs->trans('ProdutividadeChartWaterTitle'),
                        'unit' => $langs->trans('ProdutividadeChartWaterUnit'),
                        'series' => array(
                            'balancoHidrico' => $langs->trans('ProdutividadeChartWaterBalance'),
                            'deficienciaHidrica' => $langs->trans('ProdutividadeChartWaterDeficit'),
                            'excedenteHidrico' => $langs->trans('ProdutividadeChartWaterSurplus')
                        )
                    ),
                    'thermal' => array(
                        'title' => $langs->trans('ProdutividadeChartThermalTitle'),
                        'unit' => $langs->trans('ProdutividadeChartThermalUnit'),
                        'series' => array(
                            'grausDia' => $langs->trans('ProdutividadeChartDegreeDays'),
                            'isna' => $langs->trans('ProdutividadeChartISNA')
                        )
                    )
                );

                foreach ($chartDefinitions as $chartId => $definition) {
                    $chartSeries = array();
                    $maxPoints = 0;
                    foreach ($definition['series'] as $sourceKey => $seriesLabel) {
                        if (!isset($seriesSource[$sourceKey]) || !is_array($seriesSource[$sourceKey])) {
                            continue;
                        }
                        $values = array();
                        $hasValue = false;
                        foreach ($seriesSource[$sourceKey] as $value) {
                            if ($value === null || $value === '' || (!is_numeric($value) && !is_bool($value))) {
                                $values[] = null;
                                continue;
                            }
                            $floatValue = (float) $value;
                            $values[] = $floatValue;
                            if (!$hasValue) {
                                $hasValue = true;
                            }
                        }
                        if (!$hasValue) {
                            continue;
                        }
                        $maxPoints = max($maxPoints, count($values));
                        $chartSeries[] = array(
                            'key' => $sourceKey,
                            'name' => $seriesLabel,
                            'values' => $values
                        );
                    }
                    if ($maxPoints === 0 || empty($chartSeries)) {
                        continue;
                    }
                    $categories = array();
                    for ($iIndex = 0; $iIndex < $maxPoints; $iIndex++) {
                        $categories[] = $langs->trans('ProdutividadeChartPeriodLabel', $iIndex + 1);
                    }
                    $chartPayload[] = array(
                        'id' => $chartId,
                        'title' => $definition['title'],
                        'unit' => $definition['unit'],
                        'categories' => $categories,
                        'series' => $chartSeries
                    );
                }
            }
        } else {
            if (empty($apiError)) {
                $apiError = $langs->trans('ProdutividadeUnknownError');
            } elseif ($apiError === 'Missing Embrapa API credentials.') {
                $apiError = $langs->trans('ProdutividadeMissingCredentials');
            }
            setEventMessages($apiError, null, 'errors');
        }
    } else {
        setEventMessages(null, $errors, 'errors');
    }
}

// Load lists for selects
$culturaDao = new Cultura($db);
$culturas = $culturaDao->fetchAll('ASC', 'label');

$selectedCultivarOption = null;
if ($idCultivar > 0) {
    $prefillCultivar = new Cultivar($db);
    if ($prefillCultivar->fetch($idCultivar) > 0) {
        $selectedCultivarOption = array(
            'id' => (int) $prefillCultivar->id,
            'label' => $prefillCultivar->label ?: $prefillCultivar->ref,
            'cultura' => (int) $prefillCultivar->cultura,
            'embrapa' => $prefillCultivar->embrapa_id
        );
    }
}

$municipioDao = new Municipio($db);
$defaultMunicipio = getDolGlobalString('SAFRA_MUNICIPIO');

if (empty($codigoIBGE) && !empty($defaultMunicipio)) {
    $codigoIBGE = (int) $defaultMunicipio;
}

$selectedMunicipioLabel = '';
if (!empty($codigoIBGE)) {
    $municipioFilter = array('t.cod_ibge' => (string) $codigoIBGE);
    $selectedMunicipios = $municipioDao->fetchAll('ASC', 'label', 1, 0, $municipioFilter);
    if (is_array($selectedMunicipios) && !empty($selectedMunicipios)) {
        $municipio = reset($selectedMunicipios);
        $code = isset($municipio->cod_ibge) ? trim((string) $municipio->cod_ibge) : '';
        if ($code !== '') {
            $selectedMunicipioLabel = trim(($municipio->label ?: $municipio->ref) . (empty($municipio->uf) ? '' : ' / ' . $municipio->uf) . ' - ' . $code);
        }
    }
}

llxHeader('', $langs->trans('ProdutividadePageTitle'), '', '', 0, 0, '', '', '', 'mod-safra page-produtividade');

print '<link rel="stylesheet" href="' . dol_buildpath('/safra/css/produtividade.css', 1) . '?v=1">';

print load_fiche_titre($langs->trans('ProdutividadePageTitle'), '', 'safra.png@safra');

?>
<div class="productivity-page">
    <header class="productivity-header">
        <span class="productivity-header__eyebrow"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeHeaderEyebrow')); ?></span>
        <h2 class="productivity-header__title"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeHeaderTitle')); ?></h2>
        <p class="productivity-header__subtitle"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeHeaderSubtitle')); ?></p>
    </header>
    <div class="productivity-layout">
        <section class="productivity-card">
            <div class="productivity-card__header">
                <span class="productivity-card__eyebrow"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeFormEyebrow')); ?></span>
                <h3 class="productivity-card__title"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeFormTitle')); ?></h3>
                <p class="productivity-card__subtitle"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeFormSubtitle')); ?></p>
            </div>
            <div class="productivity-card__body">
                <form action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" method="post" class="productivity-form">
                    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                    <input type="hidden" name="action" value="calculate">
                    <div class="productivity-form__row">
                        <label class="productivity-form__label" for="idCultura"><?php echo dol_escape_htmltag($langs->trans('Cultura')); ?></label>
                        <select id="idCultura" name="idCultura" class="productivity-form__control" required>
                            <option value=""><?php echo dol_escape_htmltag($langs->trans('Select')); ?></option>
                            <?php
                            if (is_array($culturas)) {
                                foreach ($culturas as $cultura) {
                                    $label = $cultura->label ?: $cultura->ref;
                                    $selected = ($idCultura > 0 && (int) $cultura->id === (int) $idCultura) ? ' selected' : '';
                                    echo '<option value="' . (int) $cultura->id . '" data-embrapa="' . dol_escape_htmltag($cultura->embrapa_id) . '"' . $selected . '>' . dol_escape_htmltag($label) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="productivity-form__row">
                        <label class="productivity-form__label" for="idCultivar"><?php echo dol_escape_htmltag($langs->trans('Cultivar')); ?></label>
                        <select id="idCultivar" name="idCultivar" class="productivity-form__control" required data-default="<?php echo (int) $idCultivar; ?>" data-fetch-url="<?php echo dol_escape_htmltag(dol_buildpath('/safra/ajax/produtividade.php', 1)); ?>">
                            <?php
                            $hasSelectedCultivar = is_array($selectedCultivarOption) && (!empty($selectedCultivarOption['cultura']) ? ((int) $selectedCultivarOption['cultura'] === (int) $idCultura) : true);
                            $defaultSelected = $idCultivar <= 0 ? ' selected' : '';
                            ?>
                            <option value=""<?php echo $defaultSelected; ?>><?php echo dol_escape_htmltag($langs->trans('Select')); ?></option>
                            <?php if ($hasSelectedCultivar) { ?>
                                <option value="<?php echo (int) $selectedCultivarOption['id']; ?>" data-cultura="<?php echo (int) $selectedCultivarOption['cultura']; ?>"<?php echo !empty($selectedCultivarOption['embrapa']) ? ' data-embrapa="' . dol_escape_htmltag($selectedCultivarOption['embrapa']) . '"' : ''; ?> selected><?php echo dol_escape_htmltag($selectedCultivarOption['label']); ?></option>
                            <?php } elseif ($idCultura > 0) { ?>
                                <option value="" disabled><?php echo dol_escape_htmltag($langs->trans('ProdutividadeLoading')); ?></option>
                            <?php } else { ?>
                                <option value="" disabled><?php echo dol_escape_htmltag($langs->trans('ProdutividadeCultivarPrompt')); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="productivity-form__row">
                        <label class="productivity-form__label" for="municipioSearch"><?php echo dol_escape_htmltag($langs->trans('Municipio')); ?></label>
                        <input type="hidden" name="codigoIBGE" id="codigoIBGE" value="<?php echo $codigoIBGE ? (int) $codigoIBGE : ''; ?>">
                        <input type="text" id="municipioSearch" class="productivity-form__control" placeholder="<?php echo dol_escape_htmltag($langs->trans('ProdutividadeMunicipioPlaceholder')); ?>" value="<?php echo dol_escape_htmltag($selectedMunicipioLabel); ?>" autocomplete="off" list="municipioSuggestions" data-fetch-url="<?php echo dol_escape_htmltag(dol_buildpath('/safra/ajax/produtividade.php', 1)); ?>">
                        <datalist id="municipioSuggestions"></datalist>
                        <small class="productivity-form__help"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeMunicipioHelp')); ?></small>
                    </div>
                    <div class="productivity-form__row">
                        <label class="productivity-form__label" for="cad"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeSoilWaterCapacity')); ?></label>
                        <input type="number" name="cad" id="cad" class="productivity-form__control" value="<?php echo $cad > 0 ? dol_escape_htmltag($cad) : ''; ?>" min="0" step="1" required>
                        <span class="productivity-form__help"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeSoilWaterCapacityHelp')); ?></span>
                    </div>
                    <div class="productivity-form__row">
                        <label class="productivity-form__label" for="dataPlantio"><?php echo dol_escape_htmltag($langs->trans('ProdutividadePlantingDate')); ?></label>
                        <input type="date" name="dataPlantio" id="dataPlantio" class="productivity-form__control" value="<?php echo dol_escape_htmltag($dataPlantio); ?>" required>
                    </div>
                    <div class="productivity-form__row">
                        <label class="productivity-form__label" for="expectativaProdutividade"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeYieldExpectation')); ?></label>
                        <input type="number" name="expectativaProdutividade" id="expectativaProdutividade" class="productivity-form__control" value="<?php echo $expectativaProdutividade !== '' ? dol_escape_htmltag($expectativaProdutividade) : ''; ?>" step="0.01" min="0" required>
                        <span class="productivity-form__help"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeYieldExpectationHelp')); ?></span>
                    </div>
                    <div class="productivity-form__row">
                        <label class="productivity-form__label"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeOptionalCoordinates')); ?></label>
                        <div class="productivity-form__row" style="gap:0.6rem;">
                            <input type="number" name="latitude" id="latitude" class="productivity-form__control" value="<?php echo $latitude !== '' ? dol_escape_htmltag($latitude) : ''; ?>" step="0.000001" placeholder="<?php echo dol_escape_htmltag($langs->trans('Latitude')); ?>">
                            <input type="number" name="longitude" id="longitude" class="productivity-form__control" value="<?php echo $longitude !== '' ? dol_escape_htmltag($longitude) : ''; ?>" step="0.000001" placeholder="<?php echo dol_escape_htmltag($langs->trans('Longitude')); ?>">
                        </div>
                        <span class="productivity-form__help"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeOptionalCoordinatesHelp')); ?></span>
                    </div>
                    <div class="productivity-form__actions">
                        <button type="submit" class="productivity-form__submit"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeSubmit')); ?></button>
                    </div>
                </form>
            </div>
        </section>
        <aside class="productivity-card">
            <div class="productivity-card__header">
                <span class="productivity-card__eyebrow"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeResultsEyebrow')); ?></span>
                <h3 class="productivity-card__title"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeResultsTitle')); ?></h3>
                <p class="productivity-card__subtitle"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeResultsSubtitle')); ?></p>
            </div>
            <div class="productivity-card__body">
                <?php if (is_array($resultData)) { ?>
                    <div class="productivity-results">
                        <?php
                        $kpis = array();
                        if (!empty($resultData)) {
                            foreach ($resultData as $key => $value) {
                                if (is_scalar($value) && $value !== '') {
                                    $kpis[$key] = $value;
                                }
                                if (is_array($value)) {
                                    foreach ($value as $subKey => $subValue) {
                                        if (is_scalar($subValue) && $subValue !== '' && (stripos($subKey, 'produt') !== false || stripos($subKey, 'estim') !== false)) {
                                            $compoundKey = $key . ' ' . $subKey;
                                            $kpis[$compoundKey] = $subValue;
                                        }
                                    }
                                }
                            }
                        }
                        ?>
                        <?php if (!empty($kpis)) { ?>
                            <div class="productivity-kpis">
                                <?php foreach ($kpis as $label => $value) { ?>
                                    <div class="productivity-kpi">
                                        <div class="productivity-kpi__label"><?php echo dol_escape_htmltag(dol_trunc(ucwords(str_replace('_', ' ', $label)), 60)); ?></div>
                                        <div class="productivity-kpi__value"><?php echo dol_escape_htmltag(is_numeric($value) ? price($value) : $value); ?></div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                        <?php if (!empty($chartPayload)) { ?>
                            <div class="productivity-visuals">
                                <h4 class="productivity-visuals__title"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeVisualHighlights')); ?></h4>
                                <div class="productivity-visuals__grid">
                                    <?php foreach ($chartPayload as $chartData) { ?>
                                        <section class="productivity-chart" data-chart-id="<?php echo dol_escape_htmltag($chartData['id']); ?>" data-chart="<?php echo dol_escape_htmltag(json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)); ?>">
                                            <header class="productivity-chart__header">
                                                <h5 class="productivity-chart__title"><?php echo dol_escape_htmltag($chartData['title']); ?></h5>
                                                <?php if (!empty($chartData['unit'])) { ?>
                                                    <span class="productivity-chart__unit"><?php echo dol_escape_htmltag($chartData['unit']); ?></span>
                                                <?php } ?>
                                            </header>
                                            <div class="productivity-chart__canvas-wrapper">
                                                <canvas class="productivity-chart__canvas" width="480" height="240"></canvas>
                                            </div>
                                            <ul class="productivity-chart__legend"></ul>
                                        </section>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (!empty($resultData)) { ?>
                            <div class="productivity-details">
                                <h4 class="productivity-details__title"><?php echo dol_escape_htmltag($langs->trans('ProdutividadeDetailedBreakdown')); ?></h4>
                                <div class="productivity-details__grid">
                                    <?php
                                    $maxItems = 20;
                                    $countItems = 0;
                                    foreach ($resultData as $key => $value) {
                                        if (is_scalar($value) || empty($value)) {
                                            continue;
                                        }
                                        if ($countItems >= $maxItems) {
                                            break;
                                        }
                                        if (is_array($value)) {
                                            foreach ($value as $subKey => $subValue) {
                                                if ($countItems >= $maxItems) {
                                                    break;
                                                }
                                                if (is_array($subValue)) {
                                                    continue;
                                                }
                                                $countItems++;
                                                echo '<dl class="productivity-details__item">';
                                                echo '<dt>' . dol_escape_htmltag(ucwords(str_replace('_', ' ', $key . ' ' . $subKey))) . '</dt>';
                                                echo '<dd>' . dol_escape_htmltag(is_numeric($subValue) ? price($subValue) : $subValue) . '</dd>';
                                                echo '</dl>';
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (!empty($rawResponse)) { ?>
                            <details class="productivity-raw">
                                <summary><?php echo dol_escape_htmltag($langs->trans('ProdutividadeRawToggle')); ?></summary>
                                <pre><?php echo dol_escape_htmltag($rawResponse); ?></pre>
                            </details>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <p><?php echo dol_escape_htmltag($langs->trans('ProdutividadePlaceholder')); ?></p>
                <?php } ?>
            </div>
        </aside>
    </div>
</div>
<?php
$produtividadeConfig = array(
    'endpoint' => dol_buildpath('/safra/ajax/produtividade.php', 1),
    'cultivarPageSize' => 250,
    'selected' => array(
        'cultura' => $idCultura > 0 ? (int) $idCultura : null,
        'cultivar' => $idCultivar > 0 ? (int) $idCultivar : null,
        'municipio' => array(
            'code' => $codigoIBGE ? (int) $codigoIBGE : null,
            'label' => $selectedMunicipioLabel
        )
    ),
    'labels' => array(
        'select' => $langs->trans('Select'),
        'loading' => $langs->trans('ProdutividadeLoading'),
        'empty' => $langs->trans('ProdutividadeCultivarEmpty'),
        'placeholder' => $langs->trans('ProdutividadeCultivarPrompt'),
        'loadingMore' => $langs->trans('ProdutividadeLoadingMore')
    )
);

print '<script>window.safraProdutividadeConfig = ' . json_encode($produtividadeConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';</script>';
print '<script>window.safraProdutividadeCharts = ' . json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';</script>';
print '<script src="' . dol_buildpath('/safra/js/produtividade.js', 1) . '?v=1"></script>';

llxFooter();
$db->close();
