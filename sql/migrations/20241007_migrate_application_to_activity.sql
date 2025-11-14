-- Migration: retire legacy Safra "aplicacao" schema artifacts.
-- This script removes tables/views that pointed to the old application module
-- so that the new activity tables are the single source of truth.

SET @schema = DATABASE();

-- Drop legacy header structure regardless of being a table or view
SET @sql := (
    SELECT CASE TABLE_TYPE
        WHEN 'VIEW' THEN 'DROP VIEW __MAIN_DB_PREFIX__safra_aplicacao'
        WHEN 'BASE TABLE' THEN 'DROP TABLE __MAIN_DB_PREFIX__safra_aplicacao'
    END
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_aplicacao'
    LIMIT 1
);
SET @sql := COALESCE(@sql, 'SELECT "Safra aplicacao header already removed" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop legacy line structure regardless of being a table or view
SET @sql := (
    SELECT CASE TABLE_TYPE
        WHEN 'VIEW' THEN 'DROP VIEW __MAIN_DB_PREFIX__safra_aplicacao_line'
        WHEN 'BASE TABLE' THEN 'DROP TABLE __MAIN_DB_PREFIX__safra_aplicacao_line'
    END
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_aplicacao_line'
    LIMIT 1
);
SET @sql := COALESCE(@sql, 'SELECT "Safra aplicacao line already removed" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Safra aplicacao schema references removed' AS status;
