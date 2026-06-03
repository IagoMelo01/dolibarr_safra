# Safra Module History

## 2026-06-03 - Activity inputs drive Dolibarr stock

- Reworked agricultural activity stock integration to track one active stock movement per `safra_activity_line`.
- Added `fk_stock_movement` and `stock_movement_qty` to the activity line schema.
- Changed input save behavior so adding an input posts stock immediately with the logged-in Dolibarr user.
- Changed input edit behavior so product, warehouse, movement type, dose or quantity changes reverse the previous movement and post a new one.
- Changed input removal behavior so the active line movement is reversed before the line is deleted.
- Kept Dolibarr REST API documentation as a reference, but selected internal `MouvementStock` integration for transactional consistency and to avoid local API credential handling.
- Decoupled Safra activity workflow from Dolibarr project task lifecycle; optional task extrafield linking remains supported when a task id is explicitly provided.
- Reduced hard module dependencies to Product, Stock and Cron.
- Realigned the agricultural activity card with standard Dolibarr visual patterns: `fichecenter`, `tableforfield`, `liste`, native buttons and only small custom CSS for readability.
- Reworked the activity card into Dolibarr-style tabs: main data, inputs, spray mixture calculation, team, vehicles and implements.
- Changed project selection to force-fill field plot, planned area, crop and cultivar from project extrafields, following the rule that a project represents one season for one field plot.
- Added the spray mixture tab to calculate total spray volume, tank count, area per tank and input quantity per tank from saved input lines.
- Documented that destructive activity migrations are acceptable during development and must become preservative only after a client production database exists.
