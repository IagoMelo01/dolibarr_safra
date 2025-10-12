CREATE TABLE llx_safra_aplicacao_line (
        rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
        fk_aplicacao integer NOT NULL,
        fk_product integer,
        fk_produto_formulado integer,
        fk_produtotecnico integer,
        fk_entrepot integer,
        label varchar(255),
        dose double,
        dose_unit varchar(10),
        area_ha double,
        total_qty double,
        note text,
        date_creation datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=innodb;
