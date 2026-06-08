<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/stubs/core/class/commonobjectline.class.php';
require_once dirname(__DIR__) . '/class/analisesolo.class.php';
require_once dirname(__DIR__) . '/class/recomendacaoadubo.class.php';
require_once dirname(__DIR__) . '/class/safra_recommendation_ai.class.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__);

$analysis = new AnaliseSolo($db);
foreach (array('fk_talhao', 'latitude', 'longitude') as $field) {
    $assert(isset($analysis->fields[$field]), 'AnaliseSolo must expose field '.$field);
}
$assert($analysis->fields['fk_talhao']['type'] === 'integer:talhao:safra/class/talhao.class.php:1', 'AnaliseSolo fk_talhao must link to Talhao');
$assert(strpos($analysis->fields['latitude']['type'], 'double') === 0, 'AnaliseSolo latitude must be numeric');
$assert(strpos($analysis->fields['longitude']['type'], 'double') === 0, 'AnaliseSolo longitude must be numeric');
$assert((int) $analysis->fields['localizacao']['notnull'] === -1, 'AnaliseSolo location text must be optional when coordinates are stored');

$recommendation = new RecomendacaoAdubo($db);
foreach (array('cultura', 'produtividade_alvo', 'area_ha', 'ai_model', 'ai_generated_at') as $field) {
    $assert(isset($recommendation->fields[$field]), 'RecomendacaoAdubo must expose field '.$field);
}

$analysisSql = file_get_contents($root.'/sql/llx_safra_analisesolo.sql');
$recommendationSql = file_get_contents($root.'/sql/llx_safra_recomendacaoadubo.sql');
$analysisKeySql = file_get_contents($root.'/sql/llx_safra_analisesolo.key.sql');
$recommendationKeySql = file_get_contents($root.'/sql/llx_safra_recomendacaoadubo.key.sql');
$migrationSql = file_get_contents($root.'/sql/migrations/20260530_soil_analysis_location_and_ai_recommendation.sql');

$assert($analysisSql !== false && stripos($analysisSql, 'fk_talhao integer') !== false, 'Soil analysis schema must include fk_talhao');
$assert($analysisSql !== false && stripos($analysisSql, 'latitude double(28,8)') !== false, 'Soil analysis schema must include latitude');
$assert($analysisSql !== false && stripos($analysisSql, 'longitude double(28,8)') !== false, 'Soil analysis schema must include longitude');
$assert($analysisKeySql !== false && stripos($analysisKeySql, 'idx_safra_analisesolo_fk_talhao') !== false, 'Soil analysis schema must index fk_talhao');

$assert($recommendationSql !== false && stripos($recommendationSql, 'cultura varchar(128)') !== false, 'Recommendation schema must include crop context');
$assert($recommendationSql !== false && stripos($recommendationSql, 'ai_model varchar(128)') !== false, 'Recommendation schema must include AI model metadata');
$assert($recommendationSql !== false && stripos($recommendationSql, 'ai_generated_at datetime') !== false, 'Recommendation schema must include AI generation date');
$assert($recommendationKeySql !== false && stripos($recommendationKeySql, 'idx_safra_recomendacaoadubo_analise_solo') !== false, 'Recommendation schema must index soil analysis link');

$assert($migrationSql !== false && stripos($migrationSql, '__MAIN_DB_PREFIX__safra_analisesolo') !== false, 'Migration must alter soil analysis table');
$assert($migrationSql !== false && stripos($migrationSql, '__MAIN_DB_PREFIX__safra_recomendacaoadubo') !== false, 'Migration must alter recommendation table');

$setupContent = file_get_contents($root.'/admin/setup.php');
$assert($setupContent !== false && stripos($setupContent, 'SAFRA_OPENAI_API_KEY') !== false, 'Setup must expose OpenAI API key');
$assert($setupContent !== false && stripos($setupContent, 'SAFRA_OPENAI_MODEL') !== false, 'Setup must expose OpenAI model');
$assert($setupContent !== false && stripos($setupContent, 'setAsSecureKey') !== false, 'OpenAI API key should use secure-key field when available');

$aiClass = file_get_contents($root.'/class/safra_recommendation_ai.class.php');
$assert($aiClass !== false && stripos($aiClass, 'https://api.openai.com/v1/responses') !== false, 'AI class must use OpenAI Responses API');
$assert($aiClass !== false && stripos($aiClass, 'Nao invente dose') !== false, 'AI prompt must prevent invented agronomic doses');
$assert($aiClass !== false && stripos($aiClass, 'produtor rural') !== false, 'AI prompt must target producer-facing language');
$assert($aiClass !== false && stripos($aiClass, 'maximo 250 palavras') !== false, 'AI prompt must keep recommendations concise');
$assert($aiClass !== false && stripos($aiClass, 'Preencha a produtividade alvo para melhorar a recomendacao') !== false, 'AI prompt must request missing data directly');
$assert($aiClass !== false && stripos($aiClass, '## Isencao de responsabilidade') !== false, 'AI prompt must end with a disclaimer section');
$assert($aiClass !== false && stripos($aiClass, 'se quiser') !== false, 'AI prompt must explicitly forbid interaction offers');

$recommendationCard = file_get_contents($root.'/recomendacaoadubo_card.php');
$assert($recommendationCard !== false && stripos($recommendationCard, 'safraRecommendationRenderInlineMarkdown') !== false, 'Recommendation card must render inline markdown');
$assert($recommendationCard !== false && stripos($recommendationCard, '<strong>') !== false, 'Recommendation card must convert markdown bold to HTML');

$ajaxTalhao = file_get_contents($root.'/ajax/talhao_geojson.php');
$mapJs = file_get_contents($root.'/js/analisesolo_location.js');
$assert($ajaxTalhao !== false && stripos($ajaxTalhao, 'safraAjaxFetchTalhao') !== false, 'Talhao geometry ajax must have a direct lookup fallback');
$assert($ajaxTalhao !== false && stripos($ajaxTalhao, 'CONCAT(ref, \' - \', label)') !== false, 'Talhao geometry ajax must match the select display label');
$assert($mapJs !== false && stripos($mapJs, '&label=') !== false, 'Soil analysis map must send the selected label to the geometry endpoint');
$assert($mapJs !== false && stripos($mapJs, 'select2:select') !== false, 'Soil analysis map must listen to Select2 changes');
$assert($mapJs !== false && stripos($mapJs, 'MutationObserver') !== false, 'Soil analysis map must rebind when Dolibarr replaces the field');
$assert($mapJs !== false && stripos($mapJs, 'setInterval') !== false, 'Soil analysis map must detect programmatic value changes');

return true;
