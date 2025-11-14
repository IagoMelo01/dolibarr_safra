-- Add cost tracking columns to Safra activities
ALTER TABLE __MAIN_DB_PREFIX__safra_activity
    ADD COLUMN planned_cost DOUBLE(24,8) DEFAULT 0 AFTER note_private,
    ADD COLUMN actual_cost DOUBLE(24,8) DEFAULT 0 AFTER planned_cost;
