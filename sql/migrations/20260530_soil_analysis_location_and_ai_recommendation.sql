-- Preserve existing records while adding soil sample location and AI recommendation metadata.

ALTER TABLE __MAIN_DB_PREFIX__safra_analisesolo
	ADD COLUMN fk_talhao integer NULL AFTER fk_project,
	ADD COLUMN latitude double(28,8) NULL AFTER localizacao,
	ADD COLUMN longitude double(28,8) NULL AFTER latitude,
	MODIFY COLUMN localizacao varchar(255) NULL;

ALTER TABLE __MAIN_DB_PREFIX__safra_analisesolo
	ADD INDEX idx_safra_analisesolo_fk_talhao (fk_talhao);

ALTER TABLE __MAIN_DB_PREFIX__safra_recomendacaoadubo
	ADD COLUMN cultura varchar(128) NULL AFTER analise_solo,
	ADD COLUMN produtividade_alvo double(28,4) NULL AFTER cultura,
	ADD COLUMN area_ha double(28,4) NULL AFTER produtividade_alvo,
	ADD COLUMN ai_model varchar(128) NULL AFTER recomendacao,
	ADD COLUMN ai_generated_at datetime NULL AFTER ai_model;

ALTER TABLE __MAIN_DB_PREFIX__safra_recomendacaoadubo
	ADD INDEX idx_safra_recomendacaoadubo_analise_solo (analise_solo);
