# AGENTS Guide for Safra Module

Last updated: 2026-05-21
Repository path: `C:\wamp64\www\dolibarr_23\htdocs\custom\safra`
Default branch: `main`
Latest local commit inspected: `031312e 2026-05-04 modulo atividades`
Current snapshot size excluding `.git`: `58` directories and `489` files.

## Purpose
This file is the operational guide for AI agents working on the Safra custom module for Dolibarr.
For the current requirements backlog and deployment plan, read `plan.html` first. For repeatable work patterns, read `SKILLS.md`.

## Project Summary
- Product: Dolibarr custom module `safra` for agronomic operations, field plots, products, crop monitoring, satellite indicators and agricultural activities.
- Runtime: PHP/Dolibarr custom module style, mostly procedural pages plus `CommonObject` classes.
- Database: MySQL/MariaDB using Dolibarr `llx_` prefix conventions.
- Current strategic core: `FvActivity` workflow with stock movement integration, project task sync and REST API.
- Current branch state: Activity rebuild, API, local tests and deploy runbook exist. Staging homologation and production readiness are still pending.

## Current Key Files
- Activity UI:
  - `activity/activity_list.php`
  - `activity/activity_card.php`
  - `activity/activity_edit.php`
- Activity domain:
  - `class/FvActivity.class.php`
  - `class/FvActivityLine.class.php`
  - `class/ActivityStockService.class.php`
  - `class/api_sfactivities.class.php`
- Activity integration:
  - `core/triggers/interface_modSafra_ActivityTrigger.class.php`
  - `core/triggers/interface_99_modSafra_SafraTriggers.class.php`
- Module descriptor, menus and permissions:
  - `core/modules/modSafra.class.php`
  - `lib/safra_rights.lib.php`
- Satellite monitoring:
  - `satellite_view.php`
  - `class/safra_satellite_statistics.class.php`
  - `class/safra_satellite_health.class.php`
  - `class/ndvi.class.php`
  - `class/ndmi.class.php`
  - `class/ndwi.class.php`
  - `class/evi.class.php`
  - `class/swir.class.php`
- Setup and external APIs:
  - `admin/setup.php`
  - `class/embrapaapi.class.php`
- SQL and migrations:
  - `sql/llx_safra_activity*.sql`
  - `sql/mysql/activity.sql`
  - `sql/migrations/20260504_rebuild_activity_schema.sql`
  - `upgrade.php`
- Tests and checks:
  - `tests/run.php`
  - `tests/ActivityDomainTest.php`
  - `tests/ActivityStockServiceTest.php`
  - `tests/MigrationAndSchemaTest.php`
  - `tests/ApiSfactivitiesTest.php`
  - `build/ci/checks.ps1`
- Planning and operations:
  - `plan.html`
  - `project-steps.md`
  - `doc/deploy_runbook.md`
  - `doc/testing/activity_workflow_manual.md`
  - `doc/safra_activity_migration.md`
  - `SKILLS.md`

## Runtime Stack
- Backend language: PHP.
- ERP platform: Dolibarr custom module.
- Descriptor minimums currently declared:
  - PHP >= 7.0
  - Dolibarr >= 11
- Local CLI inspected during this update:
  - PHP 8.2.26
- Frontend: server-rendered PHP, JavaScript helpers, Leaflet maps and chart helpers.

## Main Libraries and External Services
- Mapping and geometry:
  - Leaflet, Leaflet Draw, Leaflet Geoman, Turf.js, Wellknown parser.
- UI helpers:
  - jQuery and Select2 integration through hooks.
- Satellite providers:
  - Sentinel Hub OAuth token endpoint.
  - Sentinel Hub statistics endpoint.
  - Cache folders under `json/cache/`.
- Agricultural APIs:
  - Embrapa API helper in `class/embrapaapi.class.php`.
- Weather:
  - Open-Meteo called from dashboard JavaScript.
- Map tiles:
  - ArcGIS World Imagery in `js/hooks/talhao_map.js`.

## Activity Workflow Status
- Canonical object: `FvActivity`.
- Main statuses:
  - draft: `0`
  - planned: `1`
  - in progress: `2`
  - completed: `3`
  - canceled: `9`
- Main transitions:
  - `start()`
  - `complete()`
  - `cancel()`
  - `reopen()`
- Trigger events:
  - `SAFRA_ACTIVITY_CREATE`
  - `SAFRA_ACTIVITY_START`
  - `SAFRA_ACTIVITY_DONE`
  - `SAFRA_ACTIVITY_CLOSE`
  - `SAFRA_ACTIVITY_CANCEL`
  - `SAFRA_ACTIVITY_DELETE`
- Stock integration:
  - Completion posts Dolibarr stock movements through `ActivityStockService`.
  - Movements use `origintype = 'safra_activity'`.
  - Cancellation reverses posted stock movements.
- Project task sync:
  - Activity creation/update syncs to Dolibarr project tasks.
  - Task extrafield link uses `fk_activity`.
- Resource links:
  - users in `safra_activity_user`
  - vehicles in `safra_activity_vehicle`
  - implements in `safra_activity_implement`

## REST API Status
- Class: `class/api_sfactivities.class.php`.
- Resource: `/api/index.php/sfactivities`.
- Implemented operations:
  - list with `include_lines`
  - get by id
  - create
  - update
  - delete
  - `POST {id}/start`
  - `POST {id}/complete`
  - `POST {id}/cancel`
- Rights:
  - read: `safra->SafraActivity->read`
  - write: `safra->SafraActivity->write`
  - delete: `safra->SafraActivity->delete`

## Database and Migration Reality
- Current canonical Activity schema is the `safra_activity*` table family.
- The May 2026 rebuild migration is destructive for existing `safra_activity*` data:
  - `sql/migrations/20260504_rebuild_activity_schema.sql`
- `upgrade.php` currently executes the rebuild migration and sets `SAFRA_VERSION`.
- Legacy migration helper functions still exist in `upgrade.php`, but the local tests assert the legacy migration is not called during the rebuild.
- Before production, decide explicitly whether the target database can accept destructive rebuild or requires a preservation migration.

## Testing and Validation
- Local command that passed during this update:
  - `php tests\run.php`
- Current local test coverage is useful but limited:
  - domain status behavior
  - stock service behavior with stubs
  - migration/schema assertions
  - API permission/payload assertions with stubs
- Preferred full local check:
  - `powershell -ExecutionPolicy Bypass -File build\ci\checks.ps1`
- Manual checks remain mandatory in a real Dolibarr instance:
  - Activity list/card/create/save/start/complete/cancel/delete.
  - Stock movement and reversal in `llx_stock_mouvement`.
  - Project task creation/update/deletion sync.
  - API smoke calls.
  - Satellite map, chart and cache behavior.

## Current Known Gaps and Risks
- Production readiness is not complete. The plan in `plan.html` is the canonical pending backlog.
- Staging/UAT with a database mirror is still required.
- `class/safra_satellite_statistics.class.php` still disables cURL peer verification in Sentinel calls; this must be hardened before production.
- Runtime token cache exists at `json/cache/token.json`; it is ignored by Git, but operational credential rotation is still required.
- `core/modules/modSafra.class.php` declares a very broad dependency list, including modules that may not be true hard requirements. This can block activation in lean Dolibarr instances.
- Several translation and documentation keys still use `Aplicacao` wording even after the `safra_activity*` cutover.
- `doc/Documentation.asciidoc` and `doc/temp/safra.asciidoc` still document legacy `Aplicacao` objects.
- Activity UI has list and card pages, but no dedicated kanban/calendar/mass-action implementation despite translation keys suggesting those concepts.
- Activity cost fields exist at line level, but planned/actual cost rollups and reports are not fully operationalized.
- Reopening completed activities needs a formal business rule when stock movements already exist.
- Satellite integration needs real credential, geometry, no-data, cloud-cover and scheduled-job validation in staging.
- Seed/reference data contains encoding artifacts in some files and should be sanitized before a release package.

## Working Rules for Future AI Tasks
1. Read `plan.html` before starting any non-trivial implementation. Treat it as the active requirements backlog.
2. Read `SKILLS.md` and pick the relevant workflow before editing.
3. For Activity changes, inspect `class/FvActivity.class.php`, `class/FvActivityLine.class.php`, `class/ActivityStockService.class.php`, `class/api_sfactivities.class.php`, `activity/activity_card.php`, and `activity/activity_list.php`.
4. For menus, permissions, cron and dependencies, inspect `core/modules/modSafra.class.php`.
5. For schema changes, update all relevant surfaces:
   - `sql/llx_safra_activity*.sql`
   - `sql/mysql/activity.sql`
   - migration scripts
   - tests
   - docs
6. For satellite changes, preserve cache compatibility unless the plan explicitly calls for a cache version change.
7. Never hardcode API credentials. Use Dolibarr global constants from setup.
8. Avoid reading or printing runtime token contents.
9. Keep old `Aplicacao` wording only when it is a business operation type, not when it implies the removed legacy object model.
10. Before finishing, run at least `php tests\run.php`; run `build\ci\checks.ps1` for release-sensitive changes.

## Recommended First Checks Before Any New Task
1. Confirm whether the requested work is Activity, satellite, catalog/product linking, setup/API, or documentation.
2. Check `git status --short` and protect unrelated user changes.
3. Search with `rg` before editing.
4. If touching Activity data model or migration, verify the destructive migration assumption.
5. If touching Sentinel/Embrapa behavior, validate credential handling and SSL settings.
6. If touching UI labels, update both `langs/pt_BR/safra.lang` and `langs/en_US/safra.lang`.
7. Update `plan.html` if a pending requirement is completed or re-scoped.

## Deployment References
- Main plan: `plan.html`
- Runbook: `doc/deploy_runbook.md`
- Activity migration notes: `doc/safra_activity_migration.md`
- Manual Activity tests: `doc/testing/activity_workflow_manual.md`
- Progress tracker: `project-steps.md`

## Final Notes
- This module is close to a controlled staging cycle, not a production-ready release.
- The next best engineering move is to harden Sentinel SSL, rotate credentials, confirm the migration strategy, and run a full staging UAT over Activity, stock, project tasks, API and satellite views.
