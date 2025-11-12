-- BEGIN SAFRA ACTIVITY INDEXES
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_rowid (rowid);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_ref (ref);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_fk_soc (fk_soc);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_fk_project (fk_project);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_fk_task (fk_task);
ALTER TABLE llx_safra_activity ADD CONSTRAINT llx_safra_activity_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user (rowid);
ALTER TABLE llx_safra_activity ADD INDEX idx_safra_activity_status (status);
-- END SAFRA ACTIVITY INDEXES
