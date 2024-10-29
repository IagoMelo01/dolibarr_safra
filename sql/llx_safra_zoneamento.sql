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


CREATE TABLE llx_safra_zoneamento(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128) NOT NULL, 
	label varchar(255), 
	amount double DEFAULT NULL, 
	qty real, 
	fk_soc integer, 
	fk_project integer, 
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
	status integer NOT NULL, 
	municipio varchar(255), 
	uf varchar(4), 
	cultura varchar(128), 
	ciclo varchar(255), 
	dia_ini integer, 
	mes_ini integer, 
	dia_fim integer, 
	mes_fim integer, 
	safra_ini integer, 
	safra_fim integer, 
	risco integer, 
	portaria text, 
	janela_plantio integer NOT NULL
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
