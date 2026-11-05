# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md
@PRD.md
@design.md

Milestone scope (M0–M5) is in `INIT_PROMPT.md`; past decisions and demo logins are in `docs/assumptions.md`. Parts of the structure in AGENTS.md don't exist until their milestone, so check `git log` for progress. After a milestone, stop until the user says "lanjut".

## Commands not covered in AGENTS.md

```bash
composer dev                                  # all dev processes (see php artisan dev:list)
./vendor/bin/pest tests/Feature/M0SetupTest.php   # one file
./vendor/bin/pest --filter="assigns initial status"   # one test
./vendor/bin/pint --test                      # check formatting without changing files
```

Tests run on in-memory SQLite (`phpunit.xml`) while the app runs on MySQL, so avoid MySQL-only SQL. `RefreshDatabase` applies only to `tests/Feature`. Feature tests are grouped per milestone (`M1InventoryAndMasterDataTest.php`).

Run `npm run build` after changing Tailwind classes in Blade views or `resources/css/filament/admin/theme.css`. Locally `MAIL_MAILER=log`, so emails land in `storage/logs`.

## Architecture notes

**Equipment status.** `status` is not in the `#[Fillable]` list on `Equipment`, so `update(['status' => ...])` is silently dropped. Each public method on `EquipmentStatusService` is one trigger from the PRD §F4 table and declares the statuses it may start from; all of them go through the private `transition()`, which writes the status and the `status_histories` row. Checking from/to pairs alone is not enough: under-repair to fit-for-use is legal after a repair but not after a test result. On creation, the model's `creating` hook sets the initial status and its `created` hook calls `initializeStatus()`.

**Calibration.** The due date is `valid_until` of `Equipment::latestValidCalibration()`, the latest passed calibration; use `Equipment::dueDateQuery()` or `scopeDueBetween()` in SQL. `CalibrationService` holds the PRD §F2 rules and the status changes. The Filament form only guides input, and the create and edit pages call the service through `CalibrationResource::withFormErrors()`, which maps errors to `data.*` so they appear under the fields.

**Permissions.** Permissions use the `Action:Model` format (`ViewAny:Equipment`) and are hardcoded in `RoleAndPermissionSeeder`, which also creates the demo users. For a new Resource:
1. Add the model to the seeder's `$models`.
2. Grant its permissions to the roles there.
3. Add a Policy in `app/Policies` that checks them.

**Room scoping for `kepala_ruangan`** lives in two places that must stay in sync: `getEloquentQuery()` on the Resource (lists) and `view()` on the Policy (direct URLs). Any new room-scoped Resource needs both.

**Filament layout.** Each Resource folder holds `XResource.php` plus `Pages/`, `Schemas/` (`XForm`, `XInfolist`), `Tables/` (`XTable`), and optionally `RelationManagers/`. The Resource only delegates to these classes. Resources, pages, and widgets are auto-discovered; navigation group names are declared in `AdminPanelProvider`.

**Models** declare mass-assignable fields with the `#[Fillable([...])]` attribute rather than a `$fillable` property.
