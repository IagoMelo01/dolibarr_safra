CREATE TABLE llx_safra_activity_line (
        rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
        fk_activity integer NOT NULL,
        fk_product integer,
        fk_formulated_product integer,
        fk_technical_product integer,
        fk_warehouse integer,
        label varchar(255),
        dose double,
        dose_unit varchar(10),
        area_ha double,
        total_qty double,
        note text,
        movement integer NOT NULL DEFAULT 1,
        date_creation datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=innodb;
