# Safra Activity rebuild guide

## Summary
The agricultural activity workflow was rebuilt from scratch in May 2026. The new model is focused on grain operations and separates planning from execution for activity header data, input consumption, employees, vehicles and implements.

This rebuild intentionally discards previous `llx_safra_activity*` data. This is acceptable while Safra is in development without active client production data. After production go-live, new migrations must preserve client data or provide an explicit, tested data migration path. Take a database backup before running the upgrade assistant.

## Canonical Schema
The official Activity schema is defined by:

- `sql/llx_safra_activity.sql`
- `sql/llx_safra_activity_line.sql`
- `sql/llx_safra_activity_vehicle.sql`
- `sql/llx_safra_activity_implement.sql`
- `sql/llx_safra_activity_user.sql`
- `sql/mysql/activity.sql`
- `sql/migrations/20260504_rebuild_activity_schema.sql`

## Main Tables
- `llx_safra_activity`: header, operation type, status, priority, season, crop, field plot, planned/executed area, dates and persistent spray mixture calculation fields.
- `llx_safra_activity_line`: products/inputs with planned and executed dose/quantity. Each line tracks its active stock movement in `fk_stock_movement` and `stock_movement_qty`.
- `llx_safra_activity_user`: employee/user links with role and planned/executed hours.
- `llx_safra_activity_vehicle`: links to the Fleet module `Veiculo` class by id.
- `llx_safra_activity_implement`: links to the Fleet module `Implemento` class by id.

## Upgrade Execution
1. Put Dolibarr in maintenance mode.
2. Back up database and documents.
3. Run `custom/safra/upgrade.php` as administrator.
4. Confirm the assistant executes `20260504_rebuild_activity_schema.sql`.
5. Clear Dolibarr caches after the rebuild.

## Post-Upgrade Checks
1. Confirm schema:
   - `SHOW COLUMNS FROM llx_safra_activity LIKE 'season';`
   - `SHOW TABLES LIKE 'llx_safra_activity_vehicle';`
2. Validate UI:
   - create, save, start, complete, cancel and reopen an activity.
3. Validate stock:
   - save an activity with an input line, warehouse and used quantity.
   - confirm `llx_stock_mouvement.origintype = 'safra_activity'` and `llx_safra_activity_line.fk_stock_movement` points to the movement.
   - edit the used quantity and confirm the previous movement is reversed and a new movement is linked.
   - remove the input line and confirm the active movement is reversed.
4. Validate API:
   - `GET /api/index.php/sfactivities?limit=5`
   - `GET /api/index.php/sfactivities/{id}?include_lines=1`
   - `POST /api/index.php/sfactivities/{id}/complete`

## Integrator Notes
- REST resource remains `/sfactivities`.
- `lines[].id` updates an existing input line; omitted ids create new lines. Lines omitted from an update payload are removed and their active stock movement is reversed.
- `machine_ids` remains accepted as a compatibility alias, but it now stores vehicle links in `llx_safra_activity_vehicle`.
- Vehicle links use `vehicle_class = 'Veiculo'`.
- Implement links use `implement_class = 'Implemento'`.
