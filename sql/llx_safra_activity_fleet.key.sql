ALTER TABLE llx_safra_activity_fleet ADD INDEX idx_safra_activity_fleet_entity (entity);
ALTER TABLE llx_safra_activity_fleet ADD INDEX idx_safra_activity_fleet_fk_activity (fk_activity);
ALTER TABLE llx_safra_activity_fleet ADD INDEX idx_safra_activity_fleet_fk_equipment (fk_fleet_equipment);
ALTER TABLE llx_safra_activity_fleet ADD INDEX idx_safra_activity_fleet_fk_user_responsible (fk_user_responsible);
ALTER TABLE llx_safra_activity_fleet ADD CONSTRAINT llx_safra_activity_fleet_fk_activity FOREIGN KEY (fk_activity) REFERENCES llx_safra_activity(rowid) ON DELETE CASCADE;
ALTER TABLE llx_safra_activity_fleet ADD CONSTRAINT llx_safra_activity_fleet_fk_user_responsible FOREIGN KEY (fk_user_responsible) REFERENCES llx_user(rowid) ON DELETE SET NULL;
