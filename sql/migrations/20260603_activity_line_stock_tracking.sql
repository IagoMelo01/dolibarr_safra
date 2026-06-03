-- Add per-line stock movement tracking for Safra agricultural activity inputs.
-- Run only on databases that already have __MAIN_DB_PREFIX__safra_activity_line
-- without these columns. The rebuild migration already creates them.

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD COLUMN fk_stock_movement INTEGER NULL AFTER unit_cost,
    ADD COLUMN stock_movement_qty DOUBLE(24,8) DEFAULT 0 AFTER fk_stock_movement,
    ADD INDEX idx_safra_activity_line_fk_stock_movement (fk_stock_movement);

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD CONSTRAINT llx_safra_activity_line_fk_stock_movement
    FOREIGN KEY (fk_stock_movement) REFERENCES __MAIN_DB_PREFIX__stock_mouvement(rowid)
    ON DELETE SET NULL;
