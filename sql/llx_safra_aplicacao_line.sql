CREATE TABLE llx_safra_aplicacao_line (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_aplicacao INTEGER NOT NULL,
    fk_product INTEGER NOT NULL,
    product_type VARCHAR(20) NOT NULL DEFAULT 'product',
    fk_linked INTEGER NULL,
    label VARCHAR(255) NULL,
    dose DOUBLE(24,8) NOT NULL DEFAULT 0,
    dose_unit VARCHAR(8) NOT NULL DEFAULT 'l/ha',
    area_ha DOUBLE(24,8) NULL,
    qty_total DOUBLE(24,8) NOT NULL DEFAULT 0,
    fk_warehouse INTEGER NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP NULL,
    fk_user_creat INTEGER NULL,
    fk_user_modif INTEGER NULL
) ENGINE=innodb;
