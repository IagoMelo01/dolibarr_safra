-- BEGIN SAFRA ACTIVITY INDEXES
ALTER TABLE llx_safra_activity ADD UNIQUE INDEX uk_safra_activity_ref (entity, ref);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_entity (entity);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_fk_project (fk_project);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_fk_talhao (fk_talhao);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_status (status);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_date_planned_start (date_planned_start);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_date_real_start (date_real_start);
ALTER TABLE llx_safra_activity ADD CONSTRAINT llx_safra_activity_fk_project FOREIGN KEY (fk_project) REFERENCES llx_projet (rowid) ON DELETE SET NULL;
ALTER TABLE llx_safra_activity ADD CONSTRAINT llx_safra_activity_fk_talhao FOREIGN KEY (fk_talhao) REFERENCES llx_safra_talhao (rowid) ON DELETE SET NULL;
ALTER TABLE llx_safra_activity ADD CONSTRAINT llx_safra_activity_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user (rowid);
ALTER TABLE llx_safra_activity ADD CONSTRAINT llx_safra_activity_fk_user_modif FOREIGN KEY (fk_user_modif) REFERENCES llx_user (rowid) ON DELETE SET NULL;
-- END SAFRA ACTIVITY INDEXES
