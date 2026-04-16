# AGENTS Guide for Safra Module

Last updated: 2026-04-09
Repository path: `C:\wamp64\www\dolibarr_23\htdocs\custom\safra`
Default branch: `main`
Current snapshot size (excluding `.git`): `54` directories and `479` files.

## Purpose
This file is the operational guide for AI agents working on the Safra custom module for Dolibarr.
It documents architecture, stack, libraries, directory structure, historical progress, and practical rules for safe future changes.

## Project Summary
- Product: Dolibarr custom module `safra` focused on agronomic operations and satellite monitoring.
- Core domains: field plots (`talhao`), crops/cultivars, pests, technical/formulated products, fertilizer recommendation, planting windows/zoning, events/harvest, satellite indices.
- Newer operational domain: agricultural Activity workflow (`FvActivity`) with stock movement integration and project task sync.

## Runtime Stack
- Backend language: PHP (Dolibarr module style, mostly procedural pages + `CommonObject` classes).
- ERP platform: Dolibarr (module descriptor in `core/modules/modSafra.class.php`).
- Module minimum requirements (from descriptor):
  - PHP >= 7.0
  - Dolibarr >= 11
- Database: MySQL/MariaDB (SQL files in `sql/`, InnoDB tables, Dolibarr `llx_` prefix model).
- Frontend: server-rendered PHP + JavaScript helpers.

## Main Libraries and External Services
- Mapping/geometry:
  - Leaflet (`js/leaflet.js`, `css/leaflet.css`)
  - Leaflet Draw (`js/leaflet.draw.js`, `css/leaflet.draw.css`)
  - Leaflet Geoman (`js/leafle-geoman.min.js`, `css/leaflet-geoman.css`)
  - Turf.js (`js/turf.js`)
  - Wellknown parser (`js/wellknown.js`)
- UI helpers:
  - jQuery + Select2 integration in hooks (`class/actions_safra.class.php`).
- Satellite providers:
  - Sentinel Hub OAuth token endpoint: `https://services.sentinel-hub.com/oauth/token`
  - Sentinel Hub statistics endpoint: `https://services.sentinel-hub.com/api/v1/statistics`
  - Managed by `class/safra_satellite_statistics.class.php` and index classes (`ndvi`, `ndmi`, `ndwi`, `evi`, `swir`).
- Agricultural APIs:
  - Embrapa token and domain APIs via `class/embrapaapi.class.php`.
- Weather:
  - Open-Meteo called by dashboard JS (`js/talhao_index.js.php`).
- Map tiles:
  - ArcGIS World Imagery used in map hook (`js/hooks/talhao_map.js`).

## Key Configuration Constants (Setup Page)
Managed in `admin/setup.php`:
- Farm/location:
  - `SAFRA_FAZENDA`, `SAFRA_LATITUDE`, `SAFRA_LONGITUDE`, `SAFRA_MUNICIPIO`, `SAFRA_HA_MAXIMO`
- Sentinel Hub:
  - `SAFRA_API_SENTINELHUB`
  - `SAFRA_API_SENTINELHUB_CLIENT_ID`
  - `SAFRA_API_SENTINELHUB_CLIENT_SECRET`
- Embrapa:
  - `SAFRA_API_EMBRAPA_PUBLIC`
  - `SAFRA_API_EMBRAPA_PRIVATE`
  - `SAFRA_API_EMBRAPA_PRODUTIVIDADE_URL`

## Architecture and Data Flow
### 1) Standard Dolibarr object model (legacy + active)
- Many entities follow ModuleBuilder CRUD pattern:
  - Class in `class/*.class.php`
  - List/card/contact/note/document pages at repo root
  - Helper libs in `lib/`
  - SQL tables and extrafields in `sql/`

### 2) Activity workflow (active strategic core)
- Main classes:
  - `class/FvActivity.class.php`
  - `class/FvActivityLine.class.php`
  - `class/ActivityStockService.class.php`
- Main pages:
  - `activity/activity_list.php`
  - `activity/activity_card.php`
- Status methods in `FvActivity`:
  - `start()`, `complete()`, `cancel()`
- Trigger events emitted by `FvActivity` include:
  - `SAFRA_ACTIVITY_CREATE`, `SAFRA_ACTIVITY_START`, `SAFRA_ACTIVITY_DONE`, `SAFRA_ACTIVITY_CLOSE`, `SAFRA_ACTIVITY_CANCEL`, `SAFRA_ACTIVITY_DELETE`
- Project task synchronization:
  - `core/triggers/interface_modSafra_ActivityTrigger.class.php`
- Stock movement integration:
  - Consumption/reversal in `ActivityStockService` using Dolibarr `MouvementStock`.

### 3) Satellite monitoring
- Unified page: `satellite_view.php` with per-index behavior and weekly period filters.
- Statistics and caching:
  - `class/safra_satellite_statistics.class.php`
  - cache folder `json/cache/`
- Combined health index:
  - `class/safra_satellite_health.class.php`
  - output folder `json/saude_geral/`
- Index request methods:
  - `NDVI::requestNDVIData()`
  - `NDMI::requestNDMIData()`
  - `NDWI::requestNDWIData()`
  - `EVI::requestEVIData()`
  - `SWIR::requestSWIRData()`
- Cron job currently registered in module descriptor:
  - daily sync via `NDVI::doScheduledJob()`.

### 4) Product linking hooks
- Hook class: `class/actions_safra.class.php`
- Contexts used: `productcard`, `productdao`, `projectcard`.
- Handles N-N link behavior across products, formulated products, technical products, and cultivars via `class/safra_product_link.class.php`.

## Database and SQL Organization
- Generic schema files for entities: `sql/llx_safra_*.sql` (+ `.key.sql` and extrafield variants).
- Activity-focused SQL variants exist in multiple places:
  - `sql/llx_safra_activity*.sql`
  - `sql/mysql/activity.sql`
  - `sql/migrations/20241106_reset_activity_schema.sql`
- Seed/reference data:
  - `sql/data_1.sql`, `sql/data_2.sql`, `sql/data_3.sql`
  - extended seeds in `sql/dados_sql/` (including `data_4_activity.sql`).

## Testing and Validation Reality
- Existing `tests/` directory currently provides bootstrap/stubs only.
- No runnable PHPUnit test suite was found in this module snapshot.
- Practical validation is mostly manual through UI and SQL checks.
- Useful manual references:
  - `doc/testing/activity_workflow_manual.md`
  - `doc/safra_activity_migration.md`

## Working Rules for Future AI Tasks
- Prefer editing `activity/`, `class/FvActivity*.php`, and trigger files for operational workflow changes.
- When touching menus/permissions, inspect `core/modules/modSafra.class.php` carefully:
  - it contains both legacy and newer menu blocks.
- Keep backward compatibility in mind where `Aplicacao` naming still appears.
- For satellite features:
  - keep cache read/write behavior stable (`json/cache/*`).
  - avoid hardcoding credentials; use Dolibarr global constants.
- For schema changes:
  - update all relevant SQL surfaces (`sql/llx_*`, `sql/mysql/activity.sql`, migration scripts, and docs).

## Progress and Important Milestones
- 2024-10-07:
  - `ChangeLog.md` records version `1.1.0`, upgrade assistant documentation, migration/compatibility guidance.
- 2025-11-14:
  - Activity stock integration and cost/stock sync introduced (commit `0170a72`).
- 2025-11-25:
  - Major Activity workflow wave:
    - foundation reset, create/edit flow, complete/cancel transitions, menu/link fixes, project-talhao sync.
- 2025-11-27 to 2025-11-28:
  - intensive UI and translation improvements for activity mixture modal.
- 2026-03-06:
  - Activity class refactor and trigger sync improvements.
- 2026-03-10:
  - Activity refactor + domain diagrams (`doc/uml-dominios/*`).
- 2026-03-16:
  - Unified satellite view + overall crop health map and charting updates.
- 2026-03-20:
  - latest visible commit updates activity card/domain internals.

## Known Gaps, Risks, and Technical Debt
- Naming transition not complete:
  - legacy `Aplicacao` naming still appears in menu labels/permissions/dashboard counts.
- Documentation vs implementation mismatch:
  - docs mention `/sfactivities` REST resource; no active API class implementing it was found in this module tree.
- Upgrade script drift:
  - `upgrade.php` references migration files `20241005_*` and `20241007_*` that are not present in `sql/migrations/` (only `20241106_reset_activity_schema.sql` exists).
- Dashboard legacy dependency:
  - `safraindex.php` still counts `safra_aplicacao` table.
- Sensitive/runtime artifacts tracked in repo snapshot:
  - `json/cache/token.json` exists and contains a bearer token payload.
- Tests/doc drift:
  - docs reference automated tests not present in this module snapshot.
- Encoding consistency:
  - some files show mojibake-style text artifacts; keep UTF-8 handling explicit when editing language/docs files.

## Recommended First Checks Before Any New Task
1. Confirm target flow belongs to legacy entity pages or new `activity/*` stack.
2. Check whether permission key should be `aplicacao` (legacy) or `SafraActivity` (newer).
3. If change touches data model, align all SQL flavors and migration docs.
4. If change touches satellite behavior, validate cache generation and map rendering in `satellite_view.php`.
5. Run smoke checks manually in Dolibarr UI (list, card, save, status transition, stock movement, project linkage).

## Full Project Structure Snapshot
```text
.
+-- .tx/
|   +-- config
+-- activity/
|   +-- activity_card.php
|   +-- activity_edit.php
|   +-- activity_list.php
+-- admin/
|   +-- about.php
|   +-- analisesolo_extrafields.php
|   +-- colheita_extrafields.php
|   +-- cultivar_extrafields.php
|   +-- cultura_extrafields.php
|   +-- evento_extrafields.php
|   +-- evi_extrafields.php
|   +-- expectativaprodutividade_extrafields.php
|   +-- janelaplantio_extrafields.php
|   +-- municipio_extrafields.php
|   +-- ndmi_extrafields.php
|   +-- ndvi_extrafields.php
|   +-- ndwi_extrafields.php
|   +-- pragas_extrafields.php
|   +-- produtostecnicos_extrafields.php
|   +-- recomendacaoadubo_extrafields.php
|   +-- setup.php
|   +-- swir_extrafields.php
|   +-- talhao_extrafields.php
|   +-- zoneamento_extrafields.php
+-- ajax/
|   +-- pragas.php
|   +-- product_links.php
|   +-- products_list.php
|   +-- produtividade.php
|   +-- produtostecnicos.php
|   +-- project_talhao.php
|   +-- talhao_geojson.php
+-- build/
|   +-- makepack-safra.conf
+-- class/
|   +-- actions_safra.class.php
|   +-- ActivityStockService.class.php
|   +-- analisesolo.class.php
|   +-- colheita.class.php
|   +-- cultivar.class.php
|   +-- cultura.class.php
|   +-- embrapaapi.class.php
|   +-- evento.class.php
|   +-- evi.class.php
|   +-- expectativaprodutividade.class.php
|   +-- FvActivity.class.php
|   +-- FvActivityLine.class.php
|   +-- janelaplantio.class.php
|   +-- municipio.class.php
|   +-- ndmi.class.php
|   +-- ndvi.class.php
|   +-- ndwi.class.php
|   +-- pragas.class.php
|   +-- produtostecnicos.class.php
|   +-- recomendacaoadubo.class.php
|   +-- safra_cultura.class.php
|   +-- safra_praga.class.php
|   +-- safra_product_link.class.php
|   +-- safra_produto_formulado.class.php
|   +-- safra_satellite_health.class.php
|   +-- safra_satellite_statistics.class.php
|   +-- swir.class.php
|   +-- talhao.class.php
|   +-- zoneamento.class.php
+-- core/
|   +-- boxes/
|   |   +-- safrawidget1.php
|   +-- modules/
|   |   +-- safra/
|   |   |   +-- doc/
|   |   |   |   +-- doc_generic_pragas_odt.modules.php
|   |   |   |   +-- doc_generic_produtostecnicos_odt.modules.php
|   |   |   |   +-- pdf_standard_pragas.modules.php
|   |   |   |   +-- pdf_standard_produtostecnicos.modules.php
|   |   |   +-- mod_pragas_advanced.php
|   |   |   +-- mod_pragas_standard.php
|   |   |   +-- modules_pragas.php
|   |   +-- modSafra.class.php
|   +-- triggers/
|   |   +-- interface_99_modSafra_SafraTriggers.class.php
|   |   +-- interface_modSafra_ActivityTrigger.class.php
|   +-- actions_produto_formulado.inc.php
+-- css/
|   +-- leaflet.css
|   +-- leaflet.draw.css
|   +-- leaflet-geoman.css
|   +-- produtividade.css
|   +-- safra.css.php
|   +-- satellite-analysis.css
|   +-- zoneamento.css
+-- doc/
|   +-- temp/
|   |   +-- ChangeLog.md
|   |   +-- README.md
|   |   +-- safra.asciidoc
|   +-- testing/
|   |   +-- activity_workflow_manual.md
|   +-- uml-dominios/
|   |   +-- 01_core_integracoes.mmd
|   |   +-- 02_atividade_agricola.mmd
|   |   +-- 03_estoque_insumos.mmd
|   |   +-- 04_projeto_tarefa_integracao.mmd
|   |   +-- 05_dominio_agronomico.mmd
|   |   +-- 06_monitoramento_satelital.mmd
|   |   +-- README.md
|   +-- Documentation.asciidoc
|   +-- safra_activity_migration.md
|   +-- safra_module_uml.mmd
+-- img/
|   +-- README.md
+-- js/
|   +-- evalscripts_sentinelhub/
|   |   +-- ndmi.js
|   |   +-- ndvi.js
|   |   +-- swir.js
|   +-- hooks/
|   |   +-- talhao_map.js
|   +-- evi_view.js.php
|   +-- leafle-geoman.min.js
|   +-- leaflet.draw.js
|   +-- leaflet.js
|   +-- ndmi_view.js.php
|   +-- ndvi_view.js.php
|   +-- ndwi_view.js.php
|   +-- produtividade.js
|   +-- safra.js.php
|   +-- satellite_chart.js.php
|   +-- satellite_view.js.php
|   +-- script.js
|   +-- swir_view.js.php
|   +-- talhao_create.js
|   +-- talhao_create.js.php
|   +-- talhao_edit.js
|   +-- talhao_edit.js.php
|   +-- talhao_index.js.php
|   +-- talhao_list.js.php
|   +-- talhao_show.js.php
|   +-- turf.js
|   +-- wellknown.js
+-- json/
|   +-- cache/
|   |   +-- evi/
|   |   |   +-- .gitignore
|   |   |   +-- talhao_1_w8_v2.json
|   |   +-- ndmi/
|   |   |   +-- .gitignore
|   |   |   +-- talhao_1.json
|   |   |   +-- talhao_1_w12_v2.json
|   |   |   +-- talhao_3.json
|   |   +-- ndvi/
|   |   |   +-- .gitignore
|   |   |   +-- talhao_1.json
|   |   |   +-- talhao_1_w12_v2.json
|   |   |   +-- talhao_3.json
|   |   |   +-- talhao_3_w8_v2.json
|   |   +-- ndwi/
|   |   |   +-- .gitignore
|   |   +-- swir/
|   |   |   +-- .gitignore
|   |   |   +-- talhao_1.json
|   |   |   +-- talhao_1_w12_v2.json
|   |   |   +-- talhao_3.json
|   |   +-- .gitignore
|   |   +-- token.json
|   +-- evi/
|   |   +-- 2026-03-15_2026-03-21_1.json
|   |   +-- 2026-03-15_2026-03-21_2.json
|   |   +-- 2026-03-15_2026-03-21_3.json
|   |   +-- 2026-03-15_2026-03-21_4.json
|   |   +-- 2026-03-15_2026-03-21_5.json
|   |   +-- 2026-03-15_2026-03-21_6.json
|   |   +-- t.txt
|   +-- ndmi/
|   |   +-- 2025-02-02_2025-02-08_1.json
|   |   +-- 2025-02-09_2025-02-15_1.json
|   |   +-- 2025-02-16_2025-02-22_1.json
|   |   +-- 2025-11-23_2025-11-29_1.json
|   |   +-- 2025-12-28_2026-01-03_1.json
|   |   +-- 2026-01-04_2026-01-10_1.json
|   |   +-- 2026-01-18_2026-01-24_1.json
|   |   +-- 2026-01-25_2026-01-31_1.json
|   |   +-- 2026-01-25_2026-01-31_3.json
|   |   +-- 2026-02-01_2026-02-07_3.json
|   |   +-- 2026-02-08_2026-02-14_1.json
|   |   +-- 2026-02-08_2026-02-14_3.json
|   |   +-- 2026-02-15_2026-02-21_1.json
|   |   +-- 2026-02-15_2026-02-21_3.json
|   |   +-- 2026-02-22_2026-02-28_1.json
|   |   +-- 2026-02-22_2026-02-28_3.json
|   |   +-- 2026-03-01_2026-03-07_3.json
|   |   +-- 2026-03-08_2026-03-14_1.json
|   |   +-- 2026-03-15_2026-03-21_1.json
|   |   +-- t.txt
|   +-- ndvi/
|   |   +-- 2025-02-02_2025-02-08_1.json
|   |   +-- 2025-02-09_2025-02-15_1.json
|   |   +-- 2025-02-16_2025-02-22_1.json
|   |   +-- 2025-11-23_2025-11-29_1.json
|   |   +-- 2025-12-28_2026-01-03_1.json
|   |   +-- 2026-01-04_2026-01-10_1.json
|   |   +-- 2026-01-18_2026-01-24_1.json
|   |   +-- 2026-01-25_2026-01-31_1.json
|   |   +-- 2026-02-08_2026-02-14_1.json
|   |   +-- 2026-02-15_2026-02-21_1.json
|   |   +-- 2026-02-15_2026-02-21_3.json
|   |   +-- 2026-02-22_2026-02-28_1.json
|   |   +-- 2026-03-01_2026-03-07_3.json
|   |   +-- 2026-03-08_2026-03-14_1.json
|   |   +-- 2026-03-15_2026-03-21_1.json
|   |   +-- t.txt
|   +-- ndwi/
|   |   +-- t.txt
|   +-- saude_geral/
|   |   +-- 2025-02-02_2025-02-08_1.json
|   |   +-- 2025-02-09_2025-02-15_1.json
|   |   +-- 2025-02-16_2025-02-22_1.json
|   |   +-- 2025-11-23_2025-11-29_1.json
|   |   +-- 2025-12-28_2026-01-03_1.json
|   |   +-- 2026-01-04_2026-01-10_1.json
|   |   +-- 2026-01-18_2026-01-24_1.json
|   |   +-- 2026-01-25_2026-01-31_1.json
|   |   +-- 2026-02-08_2026-02-14_1.json
|   |   +-- 2026-02-15_2026-02-21_1.json
|   |   +-- 2026-02-15_2026-02-21_3.json
|   |   +-- 2026-02-22_2026-02-28_1.json
|   |   +-- 2026-03-08_2026-03-14_1.json
|   |   +-- t.txt
|   +-- swir/
|       +-- 2025-02-02_2025-02-08_1.json
|       +-- 2025-02-09_2025-02-15_1.json
|       +-- 2025-02-16_2025-02-22_1.json
|       +-- 2025-11-23_2025-11-29_1.json
|       +-- 2025-11-30_2025-12-06_1.json
|       +-- 2025-12-07_2025-12-13_1.json
|       +-- 2025-12-14_2025-12-20_1.json
|       +-- 2025-12-21_2025-12-27_1.json
|       +-- 2025-12-28_2026-01-03_1.json
|       +-- 2026-01-04_2026-01-10_1.json
|       +-- 2026-01-18_2026-01-24_1.json
|       +-- 2026-02-08_2026-02-14_1.json
|       +-- 2026-02-15_2026-02-21_1.json
|       +-- 2026-02-15_2026-02-21_3.json
|       +-- 2026-02-22_2026-02-28_1.json
|       +-- 2026-02-22_2026-02-28_3.json
|       +-- 2026-03-08_2026-03-14_1.json
|       +-- 2026-03-15_2026-03-21_1.json
|       +-- t.txt
+-- langs/
|   +-- en_US/
|   |   +-- safra.lang
|   +-- pt_BR/
|       +-- safra.lang
+-- lib/
|   +-- safra.lib.php
|   +-- safra_analisesolo.lib.php
|   +-- safra_colheita.lib.php
|   +-- safra_cultivar.lib.php
|   +-- safra_cultura.lib.php
|   +-- safra_evento.lib.php
|   +-- safra_evi.lib.php
|   +-- safra_expectativaprodutividade.lib.php
|   +-- safra_janelaplantio.lib.php
|   +-- safra_municipio.lib.php
|   +-- safra_ndmi.lib.php
|   +-- safra_ndvi.lib.php
|   +-- safra_ndwi.lib.php
|   +-- safra_pragas.lib.php
|   +-- safra_produtostecnicos.lib.php
|   +-- safra_recomendacaoadubo.lib.php
|   +-- safra_rights.lib.php
|   +-- safra_swir.lib.php
|   +-- safra_talhao.lib.php
|   +-- safra_zoneamento.lib.php
|   +-- talhao_geo.lib.php
+-- produto_formulado/
|   +-- card.php
|   +-- list.php
+-- sql/
|   +-- dados_sql/
|   |   +-- data_1_cultura.sql
|   |   +-- data_2_cultivar.sql
|   |   +-- data_3_municipio.sql
|   |   +-- data_4_activity.sql
|   |   +-- data_old.sql
|   |   +-- produtos.json
|   +-- migrations/
|   |   +-- 20241106_reset_activity_schema.sql
|   +-- mysql/
|   |   +-- activity.sql
|   +-- data_1.sql
|   +-- data_2.sql
|   +-- data_3.sql
|   +-- dolibarr_allversions.sql
|   +-- llx_safra_activity.sql
|   +-- llx_safra_activity_extrafields.sql
|   +-- llx_safra_activity_implement.sql
|   +-- llx_safra_activity_line.sql
|   +-- llx_safra_activity_machine.sql
|   +-- llx_safra_activity_user.sql
|   +-- llx_safra_analisesolo.key.sql
|   +-- llx_safra_analisesolo.sql
|   +-- llx_safra_analisesolo_extrafields.key.sql
|   +-- llx_safra_analisesolo_extrafields.sql
|   +-- llx_safra_colheita.key.sql
|   +-- llx_safra_colheita.sql
|   +-- llx_safra_colheita_extrafields.key.sql
|   +-- llx_safra_colheita_extrafields.sql
|   +-- llx_safra_cultivar.key.sql
|   +-- llx_safra_cultivar.sql
|   +-- llx_safra_cultivar_extrafields.key.sql
|   +-- llx_safra_cultivar_extrafields.sql
|   +-- llx_safra_cultura.key.sql
|   +-- llx_safra_cultura.sql
|   +-- llx_safra_cultura_extrafields.key.sql
|   +-- llx_safra_cultura_extrafields.sql
|   +-- llx_safra_evento.key.sql
|   +-- llx_safra_evento.sql
|   +-- llx_safra_evento_extrafields.key.sql
|   +-- llx_safra_evento_extrafields.sql
|   +-- llx_safra_evi.key.sql
|   +-- llx_safra_evi.sql
|   +-- llx_safra_evi_extrafields.key.sql
|   +-- llx_safra_evi_extrafields.sql
|   +-- llx_safra_expectativaprodutividade.key.sql
|   +-- llx_safra_expectativaprodutividade.sql
|   +-- llx_safra_expectativaprodutividade_extrafields.key.sql
|   +-- llx_safra_expectativaprodutividade_extrafields.sql
|   +-- llx_safra_janelaplantio.key.sql
|   +-- llx_safra_janelaplantio.sql
|   +-- llx_safra_janelaplantio_extrafields.key.sql
|   +-- llx_safra_janelaplantio_extrafields.sql
|   +-- llx_safra_municipio.key.sql
|   +-- llx_safra_municipio.sql
|   +-- llx_safra_municipio_extrafields.key.sql
|   +-- llx_safra_municipio_extrafields.sql
|   +-- llx_safra_ndmi.key.sql
|   +-- llx_safra_ndmi.sql
|   +-- llx_safra_ndmi_extrafields.key.sql
|   +-- llx_safra_ndmi_extrafields.sql
|   +-- llx_safra_ndvi.key.sql
|   +-- llx_safra_ndvi.sql
|   +-- llx_safra_ndvi_extrafields.key.sql
|   +-- llx_safra_ndvi_extrafields.sql
|   +-- llx_safra_ndwi.key.sql
|   +-- llx_safra_ndwi.sql
|   +-- llx_safra_ndwi_extrafields.key.sql
|   +-- llx_safra_ndwi_extrafields.sql
|   +-- llx_safra_pragas.key.sql
|   +-- llx_safra_pragas.sql
|   +-- llx_safra_pragas_extrafields.key.sql
|   +-- llx_safra_pragas_extrafields.sql
|   +-- llx_safra_produto_cultura.key.sql
|   +-- llx_safra_produto_cultura.sql
|   +-- llx_safra_produto_formulado.key.sql
|   +-- llx_safra_produto_formulado.sql
|   +-- llx_safra_produto_praga.key.sql
|   +-- llx_safra_produto_praga.sql
|   +-- llx_safra_produtostecnicos.key.sql
|   +-- llx_safra_produtostecnicos.sql
|   +-- llx_safra_produtostecnicos_extrafields.key.sql
|   +-- llx_safra_produtostecnicos_extrafields.sql
|   +-- llx_safra_recomendacaoadubo.key.sql
|   +-- llx_safra_recomendacaoadubo.sql
|   +-- llx_safra_recomendacaoadubo_extrafields.key.sql
|   +-- llx_safra_recomendacaoadubo_extrafields.sql
|   +-- llx_safra_swir.key.sql
|   +-- llx_safra_swir.sql
|   +-- llx_safra_swir_extrafields.key.sql
|   +-- llx_safra_swir_extrafields.sql
|   +-- llx_safra_talhao.key.sql
|   +-- llx_safra_talhao.sql
|   +-- llx_safra_talhao_extrafields.key.sql
|   +-- llx_safra_talhao_extrafields.sql
|   +-- llx_safra_zoneamento.key.sql
|   +-- llx_safra_zoneamento.sql
|   +-- llx_safra_zoneamento_extrafields.key.sql
|   +-- llx_safra_zoneamento_extrafields.sql
+-- tests/
|   +-- stubs/
|   |   +-- core/
|   |   |   +-- class/
|   |   |   |   +-- commonobject.class.php
|   |   |   |   +-- commonobjectline.class.php
|   |   |   |   +-- dolibarrapi.class.php
|   |   |   +-- lib/
|   |   |       +-- files.lib.php
|   |   |       +-- price.lib.php
|   |   |       +-- rest.lib.php
|   |   +-- product/
|   |       +-- class/
|   |       |   +-- product.class.php
|   |       +-- stock/
|   |           +-- class/
|   |               +-- mouvementstock.class.php
|   +-- bootstrap.php
+-- tpl/
|   +-- hooks/
|   |   +-- talhao_map.tpl.php
|   +-- lines_culturas.tpl.php
|   +-- lines_pragas.tpl.php
+-- .gitignore
+-- AGENTS.md
+-- analisesolo_agenda.php
+-- analisesolo_card.php
+-- analisesolo_contact.php
+-- analisesolo_document.php
+-- analisesolo_list.php
+-- analisesolo_note.php
+-- ChangeLog.md
+-- colheita_agenda.php
+-- colheita_card.php
+-- colheita_contact.php
+-- colheita_document.php
+-- colheita_list.php
+-- colheita_note.php
+-- COPYING
+-- cultivar_agenda.php
+-- cultivar_card.php
+-- cultivar_contact.php
+-- cultivar_document.php
+-- cultivar_list.php
+-- cultivar_note.php
+-- cultura_agenda.php
+-- cultura_card.php
+-- cultura_contact.php
+-- cultura_document.php
+-- cultura_list.php
+-- cultura_note.php
+-- evento_agenda.php
+-- evento_card.php
+-- evento_contact.php
+-- evento_document.php
+-- evento_list.php
+-- evento_note.php
+-- evi_agenda.php
+-- evi_card.php
+-- evi_contact.php
+-- evi_document.php
+-- evi_list.php
+-- evi_note.php
+-- evi_view.php
+-- expectativaprodutividade_agenda.php
+-- expectativaprodutividade_card.php
+-- expectativaprodutividade_contact.php
+-- expectativaprodutividade_document.php
+-- expectativaprodutividade_list.php
+-- expectativaprodutividade_note.php
+-- info.php
+-- janelaplantio_agenda.php
+-- janelaplantio_card.php
+-- janelaplantio_contact.php
+-- janelaplantio_document.php
+-- janelaplantio_list.php
+-- janelaplantio_note.php
+-- modulebuilder.txt
+-- municipio_agenda.php
+-- municipio_card.php
+-- municipio_contact.php
+-- municipio_document.php
+-- municipio_list.php
+-- municipio_note.php
+-- ndmi_agenda.php
+-- ndmi_card.php
+-- ndmi_contact.php
+-- ndmi_document.php
+-- ndmi_list.php
+-- ndmi_note.php
+-- ndmi_view.php
+-- ndvi_agenda.php
+-- ndvi_card.php
+-- ndvi_contact.php
+-- ndvi_document.php
+-- ndvi_list.php
+-- ndvi_note.php
+-- ndvi_view.php
+-- ndwi_agenda.php
+-- ndwi_card.php
+-- ndwi_contact.php
+-- ndwi_document.php
+-- ndwi_list.php
+-- ndwi_note.php
+-- ndwi_view.php
+-- payload.json
+-- pragas_agenda.php
+-- pragas_card.php
+-- pragas_contact.php
+-- pragas_document.php
+-- pragas_list.php
+-- pragas_note.php
+-- pragas_produtos.php
+-- product_safra_links.php
+-- produtividade_view.php
+-- produtostecnicos_agenda.php
+-- produtostecnicos_card.php
+-- produtostecnicos_contact.php
+-- produtostecnicos_document.php
+-- produtostecnicos_list.php
+-- produtostecnicos_note.php
+-- project_debug.log
+-- README.md
+-- recomendacaoadubo_agenda.php
+-- recomendacaoadubo_card.php
+-- recomendacaoadubo_contact.php
+-- recomendacaoadubo_document.php
+-- recomendacaoadubo_list.php
+-- recomendacaoadubo_note.php
+-- safraindex.php
+-- satellite_view.php
+-- swir_agenda.php
+-- swir_card.php
+-- swir_contact.php
+-- swir_document.php
+-- swir_list.php
+-- swir_note.php
+-- swir_view.php
+-- talhao_agenda.php
+-- talhao_card.php
+-- talhao_contact.php
+-- talhao_document.php
+-- talhao_list.php
+-- talhao_note.php
+-- teste.php
+-- upgrade.php
+-- zoneamento_agenda.php
+-- zoneamento_card.php
+-- zoneamento_contact.php
+-- zoneamento_document.php
+-- zoneamento_list.php
+-- zoneamento_note.php
+-- zoneamento_view.php
```
