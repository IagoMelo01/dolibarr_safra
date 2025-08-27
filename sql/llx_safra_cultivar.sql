-- Copyright (C) 2024 SuperAdmin
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE llx_safra_cultivar(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128) NOT NULL, 
	label varchar(255), 
	description text, 
	note_public text, 
	note_private text, 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_user_creat integer NOT NULL, 
	fk_user_modif integer, 
	last_main_doc varchar(255), 
	import_key varchar(14), 
	model_pdf varchar(255), 
	status integer DEFAULT 1 NOT NULL, 
	cultura integer NOT NULL, 
	obtentor_mantenedor varchar(255), 
	rnc varchar(128), 
	embrapa_id integer UNIQUE, 
	safra varchar(128), 
	potencial_produtivo double(28,4), 
	uf varchar(4), 
	grupo varchar(128), 
	floracao double(28,4), 
	maturacao_fisiologica double(28,4), 
	enchimento_graos double(28,4), 
	sistema_cultivo varchar(255), 
	genetica varchar(255), 
	regiao varchar(255), 
	grupo_bioclimatico varchar(255), 
	observacao text, 
	data_atualizacao date, 
	duracao_ciclo double(28,4), 
	cultivar varchar(255)
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
