-- Add persistent spray mixture calculation fields to Safra agricultural activities.
-- The destructive rebuild migration already creates these columns.

ALTER TABLE __MAIN_DB_PREFIX__safra_activity
    ADD COLUMN mixture_area DOUBLE(24,8) DEFAULT 0 AFTER area_total,
    ADD COLUMN mixture_rate DOUBLE(24,8) DEFAULT 0 AFTER mixture_area,
    ADD COLUMN mixture_tank_capacity DOUBLE(24,8) DEFAULT 0 AFTER mixture_rate,
    ADD COLUMN mixture_total_volume DOUBLE(24,8) DEFAULT 0 AFTER mixture_tank_capacity,
    ADD COLUMN mixture_tank_count INTEGER DEFAULT 0 AFTER mixture_total_volume,
    ADD COLUMN mixture_area_per_tank DOUBLE(24,8) DEFAULT 0 AFTER mixture_tank_count,
    ADD COLUMN mixture_updated_at DATETIME AFTER mixture_area_per_tank;
