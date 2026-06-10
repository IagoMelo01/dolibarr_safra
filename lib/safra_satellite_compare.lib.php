<?php
/*
 * Helpers for temporal comparison of Safra satellite maps.
 */

dol_include_once('/safra/lib/safra_storage.lib.php');

/**
 * Return the satellite indexes supported by the comparison screen.
 *
 * @return array
 */
function safra_satellite_comparison_definitions()
{
    return array(
        'ndvi' => array(
            'folder' => 'ndvi',
            'labelKey' => 'SafraIndexNDVIShort',
            'classFile' => '/safra/class/ndvi.class.php',
            'className' => 'NDVI',
            'requestMethod' => 'requestNDVIData',
            'legendTitle' => 'Leitura NDVI',
            'legendSubtitle' => 'Escala normalizada de vigor vegetativo.',
            'legendGradient' => 'linear-gradient(to top, #0d0d0d 0%, #fef9c3 40%, #166534 100%)',
            'legendTicks' => array(
                array('bottom' => '0%', 'label' => '-1'),
                array('bottom' => '40%', 'label' => '0'),
                array('bottom' => '70%', 'label' => '0.4'),
                array('bottom' => '100%', 'label' => '1'),
            ),
            'legendDescription' => 'Valores baixos representam solo exposto, agua ou vegetacao em estresse. Valores altos indicam vegetacao densa e ativa.',
            'legendHighlights' => array(
                'Abaixo de 0: agua e areas sem cobertura.',
                'Entre 0.2 e 0.4: cobertura vegetal moderada.',
                'Acima de 0.6: vigor vegetativo alto.',
            ),
            'chart' => array('axis' => 'index', 'color' => '#16a34a', 'decimals' => 3),
        ),
        'ndmi' => array(
            'folder' => 'ndmi',
            'labelKey' => 'SafraIndexNDMIShort',
            'classFile' => '/safra/class/ndmi.class.php',
            'className' => 'NDMI',
            'requestMethod' => 'requestNDMIData',
            'legendTitle' => 'Leitura NDMI',
            'legendSubtitle' => 'Escala de umidade na vegetacao.',
            'legendGradient' => 'linear-gradient(to top, #7f1d1d 0%, #facc15 45%, #0ea5e9 100%)',
            'legendTicks' => array(
                array('bottom' => '0%', 'label' => '-1'),
                array('bottom' => '35%', 'label' => '-0.2'),
                array('bottom' => '65%', 'label' => '0.2'),
                array('bottom' => '100%', 'label' => '1'),
            ),
            'legendDescription' => 'Valores negativos tendem a indicar menor umidade. Valores positivos indicam maior disponibilidade de agua na planta.',
            'legendHighlights' => array(
                'Abaixo de -0.2: alerta de estresse hidrico.',
                'Entre -0.2 e 0.4: condicao intermediaria.',
                'Acima de 0.4: boa disponibilidade de agua.',
            ),
            'chart' => array('axis' => 'index', 'color' => '#2563eb', 'decimals' => 3),
        ),
        'swir' => array(
            'folder' => 'swir',
            'labelKey' => 'SafraIndexSWIRShort',
            'classFile' => '/safra/class/swir.class.php',
            'className' => 'SWIR',
            'requestMethod' => 'requestSWIRData',
            'legendTitle' => 'Leitura SWIR',
            'legendSubtitle' => 'Escala ajustada para leitura da lavoura.',
            'legendGradient' => 'linear-gradient(to top, #0a1f3d 0%, #855e29 40%, #a3a33d 70%, #1f7836 100%)',
            'legendTicks' => array(
                array('bottom' => '0%', 'label' => '-0.5'),
                array('bottom' => '36%', 'label' => '-0.1'),
                array('bottom' => '63%', 'label' => '0.2'),
                array('bottom' => '100%', 'label' => '0.6'),
            ),
            'legendDescription' => 'Valores muito baixos tendem a marcar agua, cinza ou solo muito exposto. Valores altos tendem a acompanhar vegetacao ativa.',
            'legendHighlights' => array(
                'Abaixo de -0.1: area com baixa resposta espectral.',
                'Entre -0.1 e 0.2: transicao entre solo exposto e cobertura intermediaria.',
                'Acima de 0.2: maior atividade espectral da vegetacao.',
            ),
            'chart' => array('axis' => 'index', 'color' => '#f97316', 'decimals' => 3),
        ),
        'health' => array(
            'folder' => 'saude_geral',
            'labelKey' => 'SafraIndexHealthShort',
            'legendTitle' => 'Leitura da saude geral',
            'legendSubtitle' => 'Classificacao consolidada da lavoura.',
            'legendGradient' => 'linear-gradient(to top, #dc2626 0%, #f59e0b 38%, #84cc16 58%, #15803d 78%, #15803d 100%)',
            'legendTicks' => array(
                array('bottom' => '0%', 'label' => '0'),
                array('bottom' => '38%', 'label' => '38'),
                array('bottom' => '58%', 'label' => '58'),
                array('bottom' => '78%', 'label' => '78'),
                array('bottom' => '100%', 'label' => '100'),
            ),
            'legendDescription' => 'Score de 0 a 100. Quanto maior, melhor a condicao espectral combinada da cultura no periodo.',
            'legendHighlights' => array(
                '0-37: critica.',
                '38-57: atencao.',
                '58-77: boa.',
                '78-100: excelente.',
            ),
            'chart' => array('axis' => 'health', 'color' => '#7c3aed', 'decimals' => 2),
        ),
    );
}

/**
 * Build the current seven-day window and the shifted comparison window.
 *
 * @param string $referenceDate          ISO date used as the end of the current window.
 * @param string $shortcut               week, month, quarter or year.
 * @param string $comparisonReferenceDate Optional ISO date used as the end of the previous window.
 *
 * @return array
 */
function safra_satellite_comparison_periods($referenceDate, $shortcut, $comparisonReferenceDate = '')
{
    $offsets = array(
        'week' => 'P1W',
        'month' => 'P1M',
        'quarter' => 'P3M',
        'year' => 'P1Y',
    );
    if (!isset($offsets[$shortcut])) {
        $shortcut = 'year';
    }

    $timezoneName = date_default_timezone_get();
    $timezone = new DateTimeZone($timezoneName ? $timezoneName : 'UTC');
    $reference = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $referenceDate, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$reference || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        $reference = new DateTimeImmutable('today', $timezone);
    }
    $today = new DateTimeImmutable('today', $timezone);
    if ($reference > $today) {
        $reference = $today;
    }

    $currentTo = $reference;
    $currentFrom = $currentTo->sub(new DateInterval('P6D'));
    $comparisonMode = 'shortcut';
    $comparisonTo = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $comparisonReferenceDate, $timezone);
    $comparisonErrors = DateTimeImmutable::getLastErrors();
    if (!$comparisonTo || (is_array($comparisonErrors) && ($comparisonErrors['warning_count'] > 0 || $comparisonErrors['error_count'] > 0))) {
        $comparisonTo = null;
    }
    if ($comparisonTo !== null) {
        if ($comparisonTo > $currentTo) {
            $comparisonTo = $currentTo;
        }
        $comparisonFrom = $comparisonTo->sub(new DateInterval('P6D'));
        $comparisonMode = 'custom';
    } else {
        $offset = new DateInterval($offsets[$shortcut]);
        $comparisonFrom = $currentFrom->sub($offset);
        $comparisonTo = $currentTo->sub($offset);
    }

    return array(
        'shortcut' => $shortcut,
        'comparisonMode' => $comparisonMode,
        'referenceDate' => $currentTo->format('Y-m-d'),
        'comparisonReferenceDate' => $comparisonMode === 'custom' ? $comparisonTo->format('Y-m-d') : '',
        'current' => array(
            'from' => $currentFrom->format('Y-m-d'),
            'to' => $currentTo->format('Y-m-d'),
            'range' => $currentFrom->format('Y-m-d') . '/' . $currentTo->format('Y-m-d'),
        ),
        'comparison' => array(
            'from' => $comparisonFrom->format('Y-m-d'),
            'to' => $comparisonTo->format('Y-m-d'),
            'range' => $comparisonFrom->format('Y-m-d') . '/' . $comparisonTo->format('Y-m-d'),
        ),
    );
}

/**
 * Ensure a satellite map exists in persistent storage without repeating a valid request.
 *
 * @param DoliDB $db
 * @param string $index
 * @param string $range
 * @param Talhao $talhao
 *
 * @return array
 */
function safra_satellite_ensure_comparison_file($db, $index, $range, $talhao)
{
    $definitions = safra_satellite_comparison_definitions();
    $talhaoId = !empty($talhao->id) ? (int) $talhao->id : 0;
    $result = array('available' => false, 'generated' => false, 'cacheHit' => false);

    if ($talhaoId <= 0 || !isset($definitions[$index]) || !preg_match('/^\d{4}-\d{2}-\d{2}\/\d{4}-\d{2}-\d{2}$/', (string) $range)) {
        return $result;
    }

    $definition = $definitions[$index];
    $fileBase = str_replace('/', '_', $range) . '_' . $talhaoId;
    $path = safra_resolve_satellite_json_path($definition['folder'], $fileBase);
    if (safra_satellite_json_is_valid_file($path)) {
        $result['available'] = true;
        $result['cacheHit'] = true;
        return $result;
    }

    if ($index === 'health') {
        foreach (array('ndvi', 'ndmi', 'swir') as $dependency) {
            $dependencyResult = safra_satellite_ensure_comparison_file($db, $dependency, $range, $talhao);
            if (empty($dependencyResult['available'])) {
                return $result;
            }
        }
        dol_include_once('/safra/class/safra_satellite_health.class.php');
        SafraSatelliteHealth::generateForRange($db, $range, $talhaoId);
    } else {
        dol_include_once($definition['classFile']);
        $className = $definition['className'];
        $method = $definition['requestMethod'];
        $object = new $className($db);
        $object->{$method}(null, $range, $talhao);
    }

    $result['available'] = safra_satellite_json_is_valid_file(safra_resolve_satellite_json_path($definition['folder'], $fileBase));
    $result['generated'] = $result['available'];

    return $result;
}
