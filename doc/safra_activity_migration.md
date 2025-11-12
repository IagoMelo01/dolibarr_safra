# Safra Activity migration and compatibility guide

## Summary
This document validates the database structures for the new `llx_safra_activity` and `llx_safra_activity_line` tables, captures the alias/deprecation strategy for the migration from the legacy "Aplicação" implementation, and lists the compatibility steps required for downstream integrations.

## Table structure validation

The Activity entities must keep parity with the existing Application data model so that records can be migrated without loss and new features can evolve with consistent naming. The following tables describe the required columns, data types, and rationale. They mirror the DDL committed in `sql/llx_safra_activity.sql` and `sql/llx_safra_activity_line.sql`.

### `llx_safra_activity`

| Column | Type | Requirement | Notes |
| --- | --- | --- | --- |
| `rowid` | `integer AUTO_INCREMENT` | Primary key | Same semantics as `llx_safra_aplicacao.rowid` |
| `ref` | `varchar(128)` | Unique business reference | Migrates legacy `ref` values |
| `label` | `varchar(255)` | Optional descriptive title | Mirrors legacy `label` |
| `amount` | `double` | Financial amount (nullable) | Keeps compatibility for cost estimations |
| `qty` | `real` | Default quantity | Used by dashboards just like in the Application table |
| `fk_soc` | `integer` | Third-party linkage | References `llx_societe.rowid` |
| `fk_project` | `integer` | Project linkage | Allows timeline aggregation |
| `fk_task` | `integer` | Task linkage | Keeps task sync features |
| `activity_type` | `varchar(32)` | Type discriminator | Renamed from `operation_type`; default `application` ensures backwards compatibility |
| `date_activity` | `date` | Execution date | Renamed from `date_application` |
| `description` | `text` | Internal description | Same meaning |
| `note_public` | `text` | Public notes | Same meaning |
| `note_private` | `text` | Private notes | Same meaning |
| `mixture_note` | `text` | Observations about mixture | Renamed from `calda_observacao` to remove regionalism |
| `date_creation` | `datetime` | Creation timestamp | Required |
| `tms` | `timestamp` | Last modification | Uses MySQL auto update |
| `fk_user_creat` | `integer` | Creator user | FK to `llx_user` |
| `fk_user_modif` | `integer` | Last modifier | Nullable |
| `last_main_doc` | `varchar(255)` | Generated document path | Same behaviour |
| `import_key` | `varchar(14)` | Import guard | Supports dedupe |
| `model_pdf` | `varchar(255)` | Preferred PDF model | Same behaviour |
| `status` | `integer` | Workflow status | Keeps existing workflow expectations |

Supporting indexes and foreign keys are defined in `sql/llx_safra_activity.key.sql` to reproduce the performance profile that exists for Applications.

### `llx_safra_activity_line`

| Column | Type | Requirement | Notes |
| --- | --- | --- | --- |
| `rowid` | `integer AUTO_INCREMENT` | Primary key | Mirrors legacy `llx_safra_aplicacao_line.rowid` |
| `fk_activity` | `integer` | Mandatory parent | Renamed from `fk_aplicacao` |
| `fk_product` | `integer` | Linked product | Same behaviour |
| `fk_formulated_product` | `integer` | Optional formulated product | Renamed from `fk_produto_formulado` |
| `fk_technical_product` | `integer` | Optional technical product | Renamed from `fk_produtotecnico` |
| `fk_warehouse` | `integer` | Stock location | Renamed from `fk_entrepot` |
| `label` | `varchar(255)` | Line label | Same |
| `dose` | `double` | Applied dose | Same |
| `dose_unit` | `varchar(10)` | Unit of measure | Same |
| `area_ha` | `double` | Area covered | Same |
| `total_qty` | `double` | Calculated quantity | Same |
| `note` | `text` | Line note | Same |
| `movement` | `integer` | Stock movement flag | Same default (`1`) |
| `date_creation` | `datetime` | Creation timestamp | Auto timestamp |

Constraints and indexes are enforced through `sql/llx_safra_activity_line.key.sql` to guarantee referential integrity with the parent Activity table.

## Alias and deprecation strategy

| Legacy element | New alias | Deprecation notes |
| --- | --- | --- |
| `llx_safra_aplicacao` table | `llx_safra_activity` | Provide SQL migration (rename + column rename) and create backward-compatible view `llx_safra_aplicacao` pointing to the new table for one release cycle |
| `llx_safra_aplicacao_line` table | `llx_safra_activity_line` | Same migration approach as the header table |
| PHP class `Aplicacao` (`class/aplicacao.class.php`) | `SfActivity` (new class) | Introduce new class extending the legacy one, mark `Aplicacao` as deprecated via DocBlock, and add factory returning `SfActivity` |
| Service locator `FvApplication` | Alias to `SfActivity` service | Maintain old service identifier as alias until Q4/2025 |
| Language keys `SafraAplicacao*` | `SafraActivity*` | Add new keys while keeping legacy entries mapping to the same strings for compatibility |
| Hooks and triggers expecting `aplicacao` | Accept both identifiers | Add translation layer in hook dispatcher so modules can register either term |

Timeline:

1. **Release N (current)** – Add aliases and documentation. Keep legacy entry points fully operational.
2. **Release N+1** – Emit deprecation warnings when legacy classes/functions are used. Update documentation and examples.
3. **Release N+2** – Remove compatibility view and legacy service names after communicating in release notes.

## Migration and compatibility checklist

1. **Database migration**
   - Rename existing tables (`RENAME TABLE llx_safra_aplicacao TO llx_safra_activity`, same for lines).
   - Apply column renames using `ALTER TABLE` statements provided in the migration script.
   - Create SQL views with the legacy names during the transition period.
   - Rebuild indexes using the new key scripts to ensure query plans remain efficient.

2. **PHP layer**
   - Introduce `SfActivity` class mapping to the new table names while keeping `Aplicacao` extending/aliasing for backwards compatibility.
   - Update Data Transfer Objects and repositories to prefer the new naming while still hydrating legacy fields for integrations that expect them.

3. **Integrations and APIs**
   - Review REST endpoints and hooks that expose `aplicacao` payloads; publish a changelog describing the renamed attributes (`operation_type` → `activity_type`, etc.).
   - Provide sample payloads with both the old and new keys to aid consumers during migration.

4. **Front-end and translations**
   - Duplicate translation keys and UI labels, ensuring toggles or compatibility layers supply the correct wording depending on module configuration.
   - Adjust JavaScript helpers and templates to read both the legacy and new field names to avoid breaking cached bundles.

5. **Quality gates**
   - Extend PHPUnit/functional tests to cover the aliasing factories and SQL views.
   - Add schema validation to the deployment pipeline so environments failing to rename columns are detected early.

Following this checklist will allow the Safra module to adopt the neutral “Activity” terminology while maintaining compatibility with existing deployments and integrations.
