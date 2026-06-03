# Safra Repository Skills

Last updated: 2026-06-03

This file is a repository-local skill index for agents working on `custom/safra`. It is not a packaged Codex skill bundle. Use it as the first procedural reference after `AGENTS.md`.

## Skill: Activity Workflow Change

Use when changing agricultural operations, planning/execution fields, stock consumption, optional task links, REST API payloads or Activity UI.

Read first:
- `class/FvActivity.class.php`
- `class/FvActivityLine.class.php`
- `class/ActivityStockService.class.php`
- `class/api_sfactivities.class.php`
- `activity/activity_card.php`
- `activity/activity_list.php`
- `core/triggers/interface_modSafra_ActivityTrigger.class.php`

Procedure:
1. Confirm the status transition and per-line stock rule before editing.
2. Check whether the change affects UI, API, optional task link, stock movement or all of them.
3. Keep `SafraActivity` permissions consistent across UI and API.
4. Update both PHP object fields and SQL schema surfaces when adding fields.
5. Add or update tests under `tests/` for domain, stock or API behavior.
6. Run `php tests\run.php`.

Acceptance checks:
- Create, save, start, complete, cancel and delete still behave coherently.
- Existing activities use Dolibarr-style tabs, and each tab saves only its own data.
- Selecting a project fills field plot, planned area, crop and cultivar from project extrafields when present.
- Saving an input line with product, warehouse and used quantity creates a stock movement.
- Editing product, warehouse, dose or quantity reverses the old line movement and posts a new one.
- Removing an input line reverses its active movement and deletes the line.
- Stock movements use `origintype = 'safra_activity'` and line `fk_stock_movement` tracks the current movement.
- The spray mixture tab calculates total spray volume, required tanks, area per tank and quantity per tank from saved inputs.
- Cancellation reverses active line movements without duplicate posting.
- Project task workflow is not synchronized; only the optional task extrafield link may be maintained.

## Skill: Activity Schema or Migration Change

Use when changing `safra_activity*` tables, upgrade behavior, seeds or release migration.

Read first:
- `sql/llx_safra_activity.sql`
- `sql/llx_safra_activity_line.sql`
- `sql/llx_safra_activity_user.sql`
- `sql/llx_safra_activity_vehicle.sql`
- `sql/llx_safra_activity_implement.sql`
- `sql/mysql/activity.sql`
- `sql/migrations/20260504_rebuild_activity_schema.sql`
- `upgrade.php`
- `tests/MigrationAndSchemaTest.php`

Procedure:
1. Decide whether the change is destructive or preservative. During development without active client data, destructive rebuilds are acceptable; after production go-live, prefer preservative migrations.
2. Update every SQL surface, not only the migration.
3. Update object `$fields` arrays and relation helpers.
4. Update tests that assert canonical schema behavior.
5. Update `doc/safra_activity_migration.md` and `plan.html` if the rollout path changes.

Acceptance checks:
- `php tests\run.php` passes.
- Upgrade path has backup, rollback and post-check instructions.
- There is no accidental dependency on removed `safra_aplicacao*` tables.
- `llx_safra_activity_line` exposes `fk_stock_movement` and `stock_movement_qty` whenever stock tracking is required.

## Skill: Satellite Monitoring Change

Use when changing Sentinel Hub statistics, NDVI/NDMI/NDWI/EVI/SWIR behavior, health index, cache files, cron jobs or satellite charts/maps.

Read first:
- `satellite_view.php`
- `class/safra_satellite_statistics.class.php`
- `class/safra_satellite_health.class.php`
- `class/ndvi.class.php`
- `class/ndmi.class.php`
- `class/ndwi.class.php`
- `class/evi.class.php`
- `class/swir.class.php`
- `js/satellite_view.js.php`
- `js/satellite_chart.js.php`

Procedure:
1. Preserve cache shape unless intentionally bumping cache version.
2. Keep credentials in Dolibarr constants only.
3. Validate geometry handling for Polygon, MultiPolygon, Feature and FeatureCollection.
4. Keep no-data and missing-credential behavior user-readable.
5. Harden cURL SSL verification before production work.
6. Re-check the cron registration in `core/modules/modSafra.class.php`.

Acceptance checks:
- No API secret is logged or committed.
- Cache writes remain under `json/cache/` or documented output folders.
- Weekly series and health index return stable empty-state messages.

## Skill: Product and Catalog Linking Change

Use when changing product links between Dolibarr products, formulated products, technical products, crops, pests or cultivars.

Read first:
- `class/actions_safra.class.php`
- `class/safra_product_link.class.php`
- `class/safra_produto_formulado.class.php`
- `product_safra_links.php`
- `ajax/product_links.php`
- `produto_formulado/card.php`
- `produto_formulado/list.php`
- `sql/llx_safra_produto_*.sql`

Procedure:
1. Identify whether the source is a Dolibarr product, formulated product, technical product, cultivar, crop or pest.
2. Preserve N-N linking semantics.
3. Update hooks and AJAX responses together.
4. Check permissions from `core/modules/modSafra.class.php`.

Acceptance checks:
- Product card tabs still load.
- Links can be added and removed without duplicate rows.
- UI labels work in `pt_BR` and `en_US`.

## Skill: Release Readiness and Implantacao

Use when preparing staging, production deployment, release notes or a go/no-go review.

Read first:
- `plan.html`
- `project-steps.md`
- `doc/deploy_runbook.md`
- `doc/testing/activity_workflow_manual.md`
- `build/ci/checks.ps1`
- `ChangeLog.md`

Procedure:
1. Run `git status --short`.
2. Run `php tests\run.php`.
3. Run `powershell -ExecutionPolicy Bypass -File build\ci\checks.ps1` when possible.
4. Confirm credential rotation and token-cache cleanup.
5. Confirm backup and rollback ownership.
6. Execute staging UAT before production.
7. Update `plan.html` after each accepted requirement.

Acceptance checks:
- There is a named release candidate and rollback point.
- Staging validates Activity, stock, project tasks, API and satellite views.
- No debug, token or generated cache payload is tracked.

## Skill: Documentation and Translation Cleanup

Use when cleaning stale docs, mojibake, language keys or operational guides.

Read first:
- `AGENTS.md`
- `SKILLS.md`
- `plan.html`
- `README.md`
- `doc/Documentation.asciidoc`
- `doc/temp/safra.asciidoc`
- `langs/pt_BR/safra.lang`
- `langs/en_US/safra.lang`

Procedure:
1. Keep operational docs aligned with actual files and tests.
2. Replace legacy `Aplicacao` object wording with Activity wording unless it is explicitly the operation type `aplicacao`.
3. Update both languages for visible UI strings.
4. Avoid broad rewrites unless the document is clearly stale.

Acceptance checks:
- New docs point to current files.
- No removed debug file is listed as active.
- Encoding artifacts are not introduced into edited sections.
