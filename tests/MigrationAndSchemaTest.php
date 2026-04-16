<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__);
$migrationFile = $root . '/sql/migrations/20260409_migrate_aplicacao_to_activity.sql';
$upgradeFile = $root . '/upgrade.php';
$mysqlSchemaFile = $root . '/sql/mysql/activity.sql';
$llxSchemaFile = $root . '/sql/llx_safra_activity.sql';

$assert(is_file($migrationFile), 'Missing migration file 20260409_migrate_aplicacao_to_activity.sql');
$assert(is_file($upgradeFile), 'Missing upgrade.php');
$assert(is_file($mysqlSchemaFile), 'Missing sql/mysql/activity.sql');
$assert(is_file($llxSchemaFile), 'Missing sql/llx_safra_activity.sql');

$migrationSql = file_get_contents($migrationFile);
$assert($migrationSql !== false, 'Unable to read migration SQL file');
$assert(stripos($migrationSql, 'CREATE TABLE IF NOT EXISTS __MAIN_DB_PREFIX__safra_activity') !== false, 'Migration must create safra_activity table');
$assert(stripos($migrationSql, 'CREATE TABLE IF NOT EXISTS __MAIN_DB_PREFIX__safra_activity_line') !== false, 'Migration must create safra_activity_line table');

$upgradeContent = file_get_contents($upgradeFile);
$assert($upgradeContent !== false, 'Unable to read upgrade.php');
$assert(stripos($upgradeContent, '20260409_migrate_aplicacao_to_activity.sql') !== false, 'upgrade.php must execute current migration');
$assert(stripos($upgradeContent, "DROP TABLE IF EXISTS ' . MAIN_DB_PREFIX . 'safra_aplicacao") !== false, 'upgrade.php must cleanup safra_aplicacao table');
$assert(stripos($upgradeContent, '20241005_') === false, 'upgrade.php still references legacy missing migration 20241005');
$assert(stripos($upgradeContent, '20241007_') === false, 'upgrade.php still references legacy missing migration 20241007');

$mysqlSchema = file_get_contents($mysqlSchemaFile);
$llxSchema = file_get_contents($llxSchemaFile);
$assert($mysqlSchema !== false && $llxSchema !== false, 'Unable to read canonical schema files');
$assert(stripos($mysqlSchema, 'safra_aplicacao') === false, 'Canonical mysql schema must not depend on safra_aplicacao');
$assert(stripos($llxSchema, 'fk_task') !== false, 'Canonical llx schema must expose fk_task');

return true;
