# CHANGELOG SAFRA FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.1.0 - 2024-10-07

- Added a guided upgrade assistant (`upgrade.php`) that executes the Activity migration SQL scripts, publishes deployment and rollback guidance, and tracks the installed version in Dolibarr constants.
- Documented the new Safra Activity events (`SAFRA_ACTIVITY_*`) and REST resources so integrators can plan the transition from the legacy Aplicação naming while keeping aliases and compatibility views.
- Captured deployment, migration and rollback procedures in the Safra Activity migration guide to ensure operations and partners share a single checklist.

## 1.0

Initial version
