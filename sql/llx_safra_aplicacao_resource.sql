CREATE TABLE llx_safra_aplicacao_resource (
        rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
        fk_aplicacao integer NOT NULL,
        element_type varchar(32) NOT NULL,
        fk_target integer,
        label varchar(255),
        note text
) ENGINE=innodb;
