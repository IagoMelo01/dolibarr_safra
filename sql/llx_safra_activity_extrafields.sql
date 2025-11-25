CREATE TABLE llx_safra_activity_extrafields (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    tms TIMESTAMP,
    fk_object INTEGER NOT NULL,
    import_key VARCHAR(14)
) ENGINE=innodb;
