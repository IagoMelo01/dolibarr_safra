-- Migration: Safra Aplicacao -> Safra Activity consolidation
-- This script transforms the legacy application tables into the new activity schema,
-- copies historical data, reconciles stock movements, and creates compatibility views.
--
-- Column mapping summary:
--   legacy.llx_safra_aplicacao.ref             -> activity.ref
--   legacy.llx_safra_aplicacao.operation_type  -> activity.activity_type
--   legacy.llx_safra_aplicacao.date_application -> activity.date_activity
--   legacy.llx_safra_aplicacao.calda_observacao -> activity.mixture_note
--   status values are preserved (0=draft, 1=validated, 9=canceled).
--
--   legacy.llx_safra_aplicacao_line.fk_aplicacao          -> activity_line.fk_activity
--   legacy.llx_safra_aplicacao_line.fk_produto_formulado  -> activity_line.fk_formulated_product
--   legacy.llx_safra_aplicacao_line.fk_produtotecnico     -> activity_line.fk_technical_product
--   legacy.llx_safra_aplicacao_line.fk_entrepot           -> activity_line.fk_warehouse
--
-- Stock movements linked to Aplicacao records keep their fk_origin but
-- update origintype from 'safra_aplicacao' to 'safra_activity'.
--
-- The script is idempotent: it skips migration work once the legacy tables
-- have been replaced by compatibility views.

SET @schema = DATABASE();

-- Ensure the new activity tables exist before proceeding
SET @has_activity := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity'
);
SET @sql := IF(
    @has_activity = 1,
    'SELECT "Activity table detected" AS info',
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''Missing table __MAIN_DB_PREFIX__safra_activity. Execute structure migration first.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_activity_line := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity_line'
);
SET @sql := IF(
    @has_activity_line = 1,
    'SELECT "Activity line table detected" AS info',
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''Missing table __MAIN_DB_PREFIX__safra_activity_line. Execute structure migration first.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Detect whether the legacy Aplicacao tables still exist as physical tables
SET @legacy_header_is_table := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_aplicacao'
      AND TABLE_TYPE = 'BASE TABLE'
);
SET @legacy_line_is_table := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_aplicacao_line'
      AND TABLE_TYPE = 'BASE TABLE'
);

SET @sql := IF(
    @legacy_header_is_table = 1,
    'SELECT COUNT(*) FROM __MAIN_DB_PREFIX__safra_aplicacao',
    'SELECT 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt INTO @legacy_header_count;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @legacy_line_is_table = 1,
    'SELECT COUNT(*) FROM __MAIN_DB_PREFIX__safra_aplicacao_line',
    'SELECT 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt INTO @legacy_line_count;
DEALLOCATE PREPARE stmt;

-- Step 1: migrate header rows that do not yet exist in the activity table
SET @sql := IF(
    @legacy_header_is_table = 0,
    'SELECT "No legacy Aplicacao header table detected" AS info',
    'INSERT INTO __MAIN_DB_PREFIX__safra_activity (
        rowid,
        entity,
        ref,
        label,
        amount,
        qty,
        fk_soc,
        fk_project,
        fk_task,
        activity_type,
        date_activity,
        description,
        note_public,
        note_private,
        mixture_note,
        date_creation,
        tms,
        fk_user_creat,
        fk_user_modif,
        last_main_doc,
        import_key,
        model_pdf,
        status
    )
    SELECT
        a.rowid,
        COALESCE(s.entity, 1) AS entity,
        a.ref,
        a.label,
        a.amount,
        a.qty,
        a.fk_soc,
        a.fk_project,
        a.fk_task,
        a.operation_type,
        a.date_application,
        a.description,
        a.note_public,
        a.note_private,
        a.calda_observacao,
        a.date_creation,
        a.tms,
        a.fk_user_creat,
        a.fk_user_modif,
        a.last_main_doc,
        a.import_key,
        a.model_pdf,
        a.status
    FROM __MAIN_DB_PREFIX__safra_aplicacao AS a
    LEFT JOIN __MAIN_DB_PREFIX__societe AS s ON s.rowid = a.fk_soc
    LEFT JOIN __MAIN_DB_PREFIX__safra_activity AS existing ON existing.rowid = a.rowid
    WHERE existing.rowid IS NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: migrate line rows that do not yet exist in the activity line table
SET @sql := IF(
    @legacy_line_is_table = 0,
    'SELECT "No legacy Aplicacao line table detected" AS info',
    'INSERT INTO __MAIN_DB_PREFIX__safra_activity_line (
        rowid,
        entity,
        fk_activity,
        fk_product,
        fk_formulated_product,
        fk_technical_product,
        fk_warehouse,
        label,
        dose,
        dose_unit,
        area_ha,
        total_qty,
        note,
        movement,
        date_creation
    )
    SELECT
        l.rowid,
        COALESCE(act.entity, 1) AS entity,
        l.fk_aplicacao,
        l.fk_product,
        l.fk_produto_formulado,
        l.fk_produtotecnico,
        l.fk_entrepot,
        l.label,
        l.dose,
        l.dose_unit,
        l.area_ha,
        l.total_qty,
        l.note,
        l.movement,
        l.date_creation
    FROM __MAIN_DB_PREFIX__safra_aplicacao_line AS l
    INNER JOIN __MAIN_DB_PREFIX__safra_activity AS act ON act.rowid = l.fk_aplicacao
    LEFT JOIN __MAIN_DB_PREFIX__safra_activity_line AS existing ON existing.rowid = l.rowid
    WHERE existing.rowid IS NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: reconcile stock movements referencing Aplicacao headers
SET @sql := 'UPDATE __MAIN_DB_PREFIX__stock_mouvement
    SET origintype = ''safra_activity''
    WHERE origintype = ''safra_aplicacao''';
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 4: validations to guarantee row-level parity
SET @sql := IF(
    @legacy_header_is_table = 1,
    'SELECT COUNT(*)
        FROM __MAIN_DB_PREFIX__safra_activity AS act
        INNER JOIN __MAIN_DB_PREFIX__safra_aplicacao AS legacy ON legacy.rowid = act.rowid',
    CONCAT('SELECT ', @legacy_header_count)
);
PREPARE stmt FROM @sql;
EXECUTE stmt INTO @migrated_header_count;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @legacy_line_is_table = 1,
    'SELECT COUNT(*)
        FROM __MAIN_DB_PREFIX__safra_activity_line AS actline
        INNER JOIN __MAIN_DB_PREFIX__safra_aplicacao_line AS legacyline ON legacyline.rowid = actline.rowid',
    CONCAT('SELECT ', @legacy_line_count)
);
PREPARE stmt FROM @sql;
EXECUTE stmt INTO @migrated_line_count;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @legacy_header_is_table = 1 AND @migrated_header_count <> @legacy_header_count,
    CONCAT('SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''Safra activity header migration mismatch: expected ',
        @legacy_header_count,
        ' rows, migrated ',
        @migrated_header_count,
        ''''),
    'SELECT "Safra activity header migration validated" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @legacy_line_is_table = 1 AND @migrated_line_count <> @legacy_line_count,
    CONCAT('SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''Safra activity line migration mismatch: expected ',
        @legacy_line_count,
        ' rows, migrated ',
        @migrated_line_count,
        ''''),
    'SELECT "Safra activity line migration validated" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 5: retire legacy tables and replace them with compatibility views
SET @sql := IF(
    @legacy_line_is_table = 1,
    'DROP TABLE __MAIN_DB_PREFIX__safra_aplicacao_line',
    'SELECT "Legacy Aplicacao line table already retired" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @legacy_header_is_table = 1,
    'DROP TABLE __MAIN_DB_PREFIX__safra_aplicacao',
    'SELECT "Legacy Aplicacao header table already retired" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop any pre-existing views so we can recreate them with the up-to-date definition
SET @sql := 'DROP VIEW IF EXISTS __MAIN_DB_PREFIX__safra_aplicacao';
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := 'DROP VIEW IF EXISTS __MAIN_DB_PREFIX__safra_aplicacao_line';
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create backward-compatible views exposing the legacy column names
SET @sql := 'CREATE VIEW __MAIN_DB_PREFIX__safra_aplicacao AS
    SELECT
        rowid,
        ref,
        label,
        amount,
        qty,
        fk_soc,
        fk_project,
        fk_task,
        activity_type AS operation_type,
        date_activity AS date_application,
        description,
        note_public,
        note_private,
        mixture_note AS calda_observacao,
        date_creation,
        tms,
        fk_user_creat,
        fk_user_modif,
        last_main_doc,
        import_key,
        model_pdf,
        status
    FROM __MAIN_DB_PREFIX__safra_activity';
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := 'CREATE VIEW __MAIN_DB_PREFIX__safra_aplicacao_line AS
    SELECT
        rowid,
        fk_activity AS fk_aplicacao,
        fk_product,
        fk_formulated_product AS fk_produto_formulado,
        fk_technical_product AS fk_produtotecnico,
        fk_warehouse AS fk_entrepot,
        label,
        dose,
        dose_unit,
        area_ha,
        total_qty,
        note,
        movement,
        date_creation
    FROM __MAIN_DB_PREFIX__safra_activity_line';
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Safra Aplicacao migration completed' AS status;
