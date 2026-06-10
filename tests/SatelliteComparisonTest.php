<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/safra_satellite_compare.lib.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$week = safra_satellite_comparison_periods('2026-06-10', 'week');
$assert($week['current']['range'] === '2026-06-04/2026-06-10', 'Current comparison window must cover seven days');
$assert($week['comparison']['range'] === '2026-05-28/2026-06-03', 'One-week shortcut must shift the full current window');

$month = safra_satellite_comparison_periods('2026-06-10', 'month');
$quarter = safra_satellite_comparison_periods('2026-06-10', 'quarter');
$year = safra_satellite_comparison_periods('2026-06-10', 'year');
$assert($month['comparison']['range'] === '2026-05-04/2026-05-10', 'One-month shortcut must shift both dates');
$assert($quarter['comparison']['range'] === '2026-03-04/2026-03-10', 'Three-month shortcut must shift both dates');
$assert($year['comparison']['range'] === '2025-06-04/2025-06-10', 'One-year shortcut must shift both dates');
$future = safra_satellite_comparison_periods('2999-01-01', 'year');
$assert($future['referenceDate'] <= date('Y-m-d'), 'Comparison reference date must not query a future period');
$custom = safra_satellite_comparison_periods('2026-06-10', 'year', '2026-02-15');
$assert($custom['comparisonMode'] === 'custom', 'Custom previous date must override shortcut comparison');
$assert($custom['comparisonReferenceDate'] === '2026-02-15', 'Custom previous date must remain selected');
$assert($custom['comparison']['range'] === '2026-02-09/2026-02-15', 'Custom previous date must define the end of a seven-day window');

$previousOutput = $conf->safra->dir_output;
$testRoot = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/safra_comparison_test_' . uniqid();
$conf->safra->dir_output = $testRoot;

$fileBase = '2026-06-04_2026-06-10_42';
$path = safra_satellite_json_path('ndvi', $fileBase);
safra_ensure_dir(dirname($path));
file_put_contents($path, '{"type":"FeatureCollection","features":[]}');

$cacheResult = safra_satellite_ensure_comparison_file(null, 'ndvi', '2026-06-04/2026-06-10', (object) array('id' => 42));
$assert(!empty($cacheResult['available']), 'Valid comparison file must be available');
$assert(!empty($cacheResult['cacheHit']), 'Valid comparison file must be reused without a provider request');
$assert(empty($cacheResult['generated']), 'Cache hit must not be reported as a generated provider result');

$page = file_get_contents(dirname(__DIR__) . '/satellite_compare.php');
$js = file_get_contents(dirname(__DIR__) . '/js/satellite_compare.js.php');
$css = file_get_contents(dirname(__DIR__) . '/css/satellite-analysis.css');
$assert($page !== false && $js !== false && $css !== false, 'Satellite comparison screen files must be readable');
$assert(strpos($page, 'satelliteCompareCurrentMap') !== false, 'Comparison screen must render the current map');
$assert(strpos($page, 'satelliteComparePreviousMap') !== false, 'Comparison screen must render the previous map');
$assert(strpos($page, 'satelliteCompareSeriesChart') !== false, 'Comparison screen must replicate the 12-week evolution chart');
$assert(strpos($page, 'satellite-legend') !== false, 'Comparison screen must replicate the selected index legend');
$assert(strpos($page, "checkToken(GETPOST('token', 'alphanohtml'))") !== false, 'Comparison provider requests must validate the CSRF token');
$assert(strpos($page, 'farmevo_logo_full.png') !== false, 'PDF report must use the official Farmevo logo');
$assert(strpos($page, 'satelliteCompareExportPdf') !== false, 'Comparison screen must expose the PDF export button');
$assert(strpos($page, '$selectedTalhaoId = (int) GETPOST') !== false, 'Selected field id must be normalized before option comparison');
$assert(strpos($page, 'id="satelliteComparePeriod"') !== false, 'Comparison form must persist the selected shortcut');
$assert(strpos($page, 'name="comparison_reference_date"') !== false, 'Comparison form must expose a custom previous date');
$assert(strpos($page, "\$periods['comparisonReferenceDate']") !== false, 'Custom previous date must remain selected after submit');
$assert(strpos($page, 'id="satelliteCompareCustomPeriod"') !== false, 'Comparison shortcuts must expose the custom option');
$assert(strpos($page, 'id="satelliteComparePreviousDateRow"') !== false, 'Custom previous date must use a conditional row');
$assert(strpos($page, 'satellite-compare-search-button') !== false, 'Comparison form must expose an explicit search button');
$assert(strpos($js, 'synchronizeMaps') !== false, 'Comparison maps must keep their navigation synchronized');
$assert(strpos($js, 'button.dataset.period') !== false, 'Shortcut buttons must persist their selected period before submit');
$assert(strpos($js, "comparisonReferenceDateInput.value = ''") !== false, 'Shortcut buttons must clear the custom previous date');
$assert(strpos($js, "previousDateRow.classList.add('is-visible')") !== false, 'Custom option must reveal the previous date field');
$assert(strpos($js, 'window.print()') !== false, 'PDF export must open the native printable report');
$assert(strpos($css, '@media print') !== false, 'Comparison screen must provide a dedicated print layout');
$assert(strpos($css, 'size: A4 landscape') !== false, 'PDF report must use A4 landscape orientation');

@unlink($path);
@rmdir(dirname($path));
@rmdir($testRoot . '/json');
@rmdir($testRoot);
$conf->safra->dir_output = $previousOutput;

return true;
