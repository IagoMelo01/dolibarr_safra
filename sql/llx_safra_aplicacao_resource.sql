CREATE TABLE llx_safra_aplicacao_resource (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_aplicacao INTEGER NOT NULL,
    resource_type VARCHAR(16) NOT NULL,
    fk_target INTEGER NOT NULL,
    label VARCHAR(255) NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP NULL,
    fk_user_creat INTEGER NULL,
    fk_user_modif INTEGER NULL
) ENGINE=innodb;
