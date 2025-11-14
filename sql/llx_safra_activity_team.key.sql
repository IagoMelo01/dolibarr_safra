ALTER TABLE llx_safra_activity_team ADD INDEX idx_safra_activity_team_entity (entity);
ALTER TABLE llx_safra_activity_team ADD INDEX idx_safra_activity_team_fk_activity (fk_activity);
ALTER TABLE llx_safra_activity_team ADD INDEX idx_safra_activity_team_fk_user (fk_user);
ALTER TABLE llx_safra_activity_team ADD INDEX idx_safra_activity_team_is_responsible (is_responsible);
ALTER TABLE llx_safra_activity_team ADD CONSTRAINT llx_safra_activity_team_fk_activity FOREIGN KEY (fk_activity) REFERENCES llx_safra_activity(rowid) ON DELETE CASCADE;
ALTER TABLE llx_safra_activity_team ADD CONSTRAINT llx_safra_activity_team_fk_user FOREIGN KEY (fk_user) REFERENCES llx_user(rowid) ON DELETE CASCADE;
