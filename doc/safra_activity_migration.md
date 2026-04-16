# Safra Activity migration guide (legacy cutover)

## Summary
This migration moves legacy `llx_safra_aplicacao*` data into canonical `llx_safra_activity*` tables and removes the legacy model in the same release.

## Canonical schema
The official Activity schema is defined by:

- `sql/llx_safra_activity.sql`
- `sql/llx_safra_activity_line.sql`
- `sql/llx_safra_activity_machine.sql`
- `sql/llx_safra_activity_implement.sql`
- `sql/llx_safra_activity_user.sql`
- `sql/mysql/activity.sql`

Legacy structures (`llx_safra_aplicacao`, `llx_safra_aplicacao_line`, resource tables/views) are no longer supported after migration.

## Upgrade execution
1. Put Dolibarr in maintenance mode.
2. Back up database and documents.
3. Run `custom/safra/upgrade.php` as administrator.
4. The assistant will:
   - ensure canonical `safra_activity*` tables exist,
   - migrate legacy header/line data to canonical tables,
   - drop legacy `safra_aplicacao*` objects.
5. Validate totals and reopen access.

## Post-upgrade checks
1. Compare migrated volumes:
   - `SELECT COUNT(*) FROM llx_safra_activity;`
   - `SELECT COUNT(*) FROM llx_safra_activity_line;`
2. Validate workflow in UI:
   - create/save/start/complete/cancel/delete.
3. Validate REST API:
   - `GET /api/index.php/sfactivities?limit=5`
   - `GET /api/index.php/sfactivities/{id}?include_lines=1`
   - `POST /api/index.php/sfactivities/{id}/start`
   - `POST /api/index.php/sfactivities/{id}/complete`
   - `POST /api/index.php/sfactivities/{id}/cancel`
4. Validate stock synchronization (`origintype = 'safra_activity'`).

## Rollback
1. Restore database backup.
2. Re-deploy previous Safra package.
3. Clear Dolibarr cache and retry only after root-cause analysis.

## Integrator notes
- Official REST resource: `/sfactivities`.
- Legacy `/aplicacoes` alias is removed.
- Legacy SQL/report dependencies on `safra_aplicacao*` must be updated to `safra_activity*`.
