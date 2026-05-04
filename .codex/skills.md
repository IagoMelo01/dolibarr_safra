# Safra Module Session Notes

Last updated: 2026-05-04
Repository: `C:\wamp64\www\dolibarr_23\htdocs\custom\safra`

## Purpose

Operational notes learned while rebuilding the agricultural activity workflow in the Safra Dolibarr module. Use this file as a practical memory layer for future Codex sessions, especially when changing activities, stock consumption, fleet links, migrations, or validation.

## Activity Rebuild Direction

- The current strategic activity workflow is the new `FvActivity` stack, not legacy `Aplicacao` pages.
- The user explicitly approved discarding the old agricultural activity implementation and rebuilding activity tables/classes/pages from scratch.
- The rebuilt activity domain is focused first on grain production workflows:
  - soil preparation
  - seed treatment/preparation
  - planting
  - fertilization
  - input/product application
  - monitoring
  - harvest
  - maintenance
  - other operations
- Activity status model should be practical for farm execution:
  - draft
  - planned
  - in progress
  - completed
  - canceled
- Keep planning and execution data side by side:
  - planned and done dates
  - planned and done area
  - planned and done product quantities
  - planned and done employee/resource hours
  - progress percentage
  - notes and weather/field conditions

## Core Files For The Activity Domain

- Main object: `class/FvActivity.class.php`
- Product/input lines: `class/FvActivityLine.class.php`
- Stock movement service: `class/ActivityStockService.class.php`
- Main UI card: `activity/activity_card.php`
- Main list: `activity/activity_list.php`
- REST API: `class/api_sfactivities.class.php`
- Project task trigger: `core/triggers/interface_modSafra_ActivityTrigger.class.php`

## Stock And Warehouse Details

- Dolibarr native stock warehouse table is `llx_entrepot`.
- In Dolibarr stock/core, warehouse display name uses column `lieu`, not `label`.
- Any UI/helper loading warehouses must prefer `entrepot.lieu`.
- Safe fallback order for warehouse display:
  - `lieu`
  - `label`
  - `ref`
- In the new activity card this rule is applied when building `$warehouseOptions`.
- Activity line warehouse field in Safra schema is `fk_warehouse`, but Dolibarr stock movements use `fk_entrepot`; keep compatibility aliases where needed.
- Product selection can use `product.ref` and `product.label`; this note about `lieu` applies to warehouses/deposits, not products.

## Stock Movement Integration

- Product/input consumption is posted only when the activity is completed.
- Cancellation should reverse stock movements already linked to that activity.
- Use Dolibarr `MouvementStock`.
- For compatibility with Dolibarr versions, call:
  - `livraison($user, $productId, $warehouseId, $quantity, 0, $label)` for consumption
  - `reception($user, $productId, $warehouseId, $quantity, 0, $label)` for return/reversal
- Avoid passing extra origin arguments directly to `livraison()` or `reception()`; set origin metadata on the movement object instead:
  - `$movement->origin`
  - `$movement->origin_id`
  - `$movement->origintype = 'safra_activity'`
- Reversal should inspect `llx_stock_mouvement.qty`:
  - negative original movement => reverse with reception
  - positive original movement => reverse with livraison
- Completion must validate that stock-relevant lines have a warehouse before creating stock movements.

## Activity Lines

- Activity lines represent products/inputs used or returned by an activity.
- Important line fields:
  - `fk_activity`
  - `fk_product`
  - `fk_warehouse`
  - `movement_type`
  - `area_planned`
  - `area_done`
  - `dose_planned`
  - `dose_done`
  - `qty_planned`
  - `qty_done`
  - `unit_code`
  - `unit_cost`
  - `note`
- Keep legacy aliases for compatibility when useful:
  - `fk_entrepot` as alias of `fk_warehouse`
  - `area_applied`
  - `dose`
  - `total`
- Quantity for stock movement should prefer `qty_done`, then legacy `total`, then `qty_planned`.

## Employees And Resources

- Employees are linked through a dedicated activity-user link table.
- Store planned and done hours per employee, plus role and notes.
- Fleet module integration should be by lightweight links only.
- Link vehicles to future fleet class `Veiculo`.
- Link implements to future fleet class `Implemento`.
- The old activity-machine concept was replaced by vehicle links.
- Keep compatibility aliases:
  - `fetchMachines()` can map to vehicle links.
  - `setMachines()` can map to vehicle links.

## Fleet Links

- Vehicle table in Safra activity schema: `llx_safra_activity_vehicle`.
- Implement table in Safra activity schema: `llx_safra_activity_implement`.
- Link rows should keep:
  - linked object id
  - linked class name
  - planned hours
  - done hours
  - notes
- UI should attempt loading fleet classes from `/custom/frota/class/veiculo.class.php` and `/custom/frota/class/implemento.class.php` if present.
- If fleet classes are not available yet, fall back to direct table options where possible.

## Project Task Sync

- Activities may link to a Dolibarr project and task.
- Keep `fk_project` and `fk_task` on the activity.
- Project task synchronization should continue to use activity trigger behavior.
- Preserve extrafield linkage where existing trigger code expects `fk_activity`.

## SQL And Migrations

- When changing activity schema, align all SQL surfaces:
  - `sql/llx_safra_activity.sql`
  - `sql/llx_safra_activity_line.sql`
  - `sql/llx_safra_activity_user.sql`
  - `sql/llx_safra_activity_vehicle.sql`
  - `sql/llx_safra_activity_implement.sql`
  - `sql/mysql/activity.sql`
  - `sql/migrations/*.sql`
  - activity seed data under `sql/dados_sql/`
- The rebuild migration created during this session is destructive:
  - `sql/migrations/20260504_rebuild_activity_schema.sql`
  - it drops and recreates `safra_activity*` tables.
- Only use that migration when losing previous activity data is acceptable.
- `upgrade.php` was adjusted to run the rebuild migration for version `2.0.0`.

## API Notes

- `class/api_sfactivities.class.php` should expose the new activity fields and relations.
- Keep endpoint compatibility for:
  - list/index
  - get
  - create
  - update
  - delete
  - start
  - complete
  - cancel
- API payloads should accept:
  - `lines`
  - `user_links` or `user_ids`
  - `vehicle_links` or `vehicle_ids`
  - `implement_links` or `implement_ids`
  - `machine_ids` as a compatibility alias for vehicle ids

## UI Notes

- The activity card should be a real work screen, not a decorative landing page.
- Primary users are farmers and field employees with little time available; some may have limited schooling.
- Favor short labels, fewer visible fields, clear required/optional markers, and large obvious action buttons.
- Creation should collect only essential data:
  - activity name
  - activity type
  - field plot
  - planned start, area, season, crop when available
- Project records already carry Safra references through `projet_extrafields`:
  - `fk_talhao`
  - `fk_cultura`
  - `fk_cultivar`
- When an activity is linked to a project or to a task whose project has these extrafields, auto-fill field plot, crop, cultivar, and planned area from that project context.
- Use the field plot area only as the default planned area; the user must be able to edit it when the operation covers only part of the field.
- Keep advanced or less common fields behind optional sections.
- Do not make users manually edit status, progress, actual start, or actual end during creation.
- Completing an activity should be a clear card action that saves current execution data and then posts stock movements.
- Keep the user flow efficient:
  - header/status
  - planning and execution
  - products/stock
  - employees
  - vehicles
  - implements
  - notes
  - status actions
- Product lines should let the user select product, warehouse, movement type, planned/done dose, planned/done quantity, unit, cost, and note.
- Status action buttons should be available from the card:
  - start
  - complete
  - reopen
  - cancel
  - delete

## Language And Encoding

- Some project files have encoding/mojibake history.
- Prefer UTF-8 for new files.
- When possible, keep new operational docs ASCII-safe to avoid introducing encoding drift.
- If `apply_patch` fails because a file has invalid UTF-8, inspect carefully and use a controlled UTF-8 rewrite only for that file.

## Tooling Notes

- `rg.exe` may be unavailable or blocked in this local Windows/Wamp environment; PowerShell `Get-ChildItem` plus `Select-String` is a workable fallback.
- Avoid huge broad searches across seed SQL files because they produce excessive output.
- Prefer targeted searches in:
  - `activity/`
  - `class/FvActivity*.php`
  - `class/ActivityStockService.class.php`
  - `class/api_sfactivities.class.php`
  - `sql/llx_safra_activity*.sql`
  - `sql/migrations/`

## Validation Commands

- PHP syntax checks:
  - `php -l class\FvActivity.class.php`
  - `php -l class\FvActivityLine.class.php`
  - `php -l class\ActivityStockService.class.php`
  - `php -l class\api_sfactivities.class.php`
  - `php -l activity\activity_card.php`
  - `php -l activity\activity_list.php`
  - `php -l upgrade.php`
- Test runner:
  - `php tests\run.php`
- Diff whitespace check:
  - `git diff --check`
- Known local validation warnings:
  - Xdebug extension may fail loading from `E:/wamp64/bin/php/php8.4.0/...`.
  - Test stub `tests/stubs/api/class/api.class.php` may emit a PHP deprecated nullable warning.
  - These warnings did not prevent tests from passing in this session.

## Current Rebuild Outcome

- New activity workflow classes, pages, API, SQL, migration, docs, languages, and tests were added or updated.
- Old `llx_safra_activity_machine.sql` was removed.
- New `llx_safra_activity_vehicle.sql` was added.
- Tests passed after the rebuild.
- Future work should manually smoke-test in Dolibarr UI:
  - create planned activity
  - add product line with warehouse
  - add employee
  - add vehicle
  - add implement
  - start activity
  - complete activity and confirm stock movement
  - cancel/reopen behavior as needed
  - verify project task sync
