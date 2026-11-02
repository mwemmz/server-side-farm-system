# FFMS server-side implementation report

## Delivered

- Added idempotent `db/features_schema.sql`, loaded by `db/migrate.php`.
- Added admin-only JSON database backup with CSRF and audit logging.
- Added `farm-owner` and `agronomist` RBAC constants, permissions, role seeds, and Security UI visibility.
- Made notifications work without Composer: simulator/SMS logging is the default and PHPMailer is optional.
- Added sale links to inventory, livestock, and fields; profitability JSON calculations for fields/animals.
- Added QR/tag resolution and printable label data, plus protected vaccination, stock-out, and equipment-service actions.
- Added confirmed assistant sale drafts and a daily SMS digest simulator.

## Delivered (this pass) — FFMS requirement completion

- Added idempotent `db/expansion_schema.sql` (35 new tables + additive columns on
  fields/crops/equipment/finance_records/sales_records/payment_records/health_incidents/
  stored_produce/suppliers/livestock/customers) registered in `db/migrate.php`.
- Field depth: `src/Helpers/GeoJsonHelper.php` (GeoJSON polygon normalisation,
  closing, area in hectares with hole subtraction, centroid); rewrote
  `src/Modules/Field/FieldController.php` and `public/views/field*.php` for
  boundary-aware add with lat/lng validation and a Leaflet click-to-draw editor,
  falling back to simulated circles when no boundary exists.
- Crop agronomy / livestock production / equipment costing / labour operations /
  pest-disease / harvest-storage-market / financial analytics / suppliers are
  exposed through a metadata-driven "Ops" engine:
  `src/Modules/Ops/OpsRegistry.php` (35 record definitions across 9 groups with
  FK option SQL, finance specs and per-definition hooks),
  `src/Modules/Ops/OpsModel.php` (joined list query, count, find, create, update,
  delete with column whitelisting) and `src/Modules/Ops/OpsController.php`
  (landing/records/add/edit/delete, RBAC guard, generic validation, cross-field
  date ordering, invoice paid-vs-total check, transactions, audit logs, finance
  sync/remove, hooks). Routes: `module=Ops` (landing), `module=Ops&view=<key>`
  (records), `action=add|edit|delete`. Views: `public/views/ops_landing.php`,
  `public/views/ops_records.php`. Nav entry + RBAC permission in
  `src/Helpers/RbacHelper.php`/`src/Helpers/RoleHelper.php`.
- Single costing ledger: extended `src/Modules/Finance/FinanceService.php` with
  dimension-aware `post()/postSafe()`, `syncSource()/syncSourceSafe()`,
  `removeSource()`, `findSource()`, `DIMENSION_COLUMNS`, `cleanDimensions()`.
  Ops cost-bearing records post via `FinanceService` with `source_module = 'Ops:<key>'`
  and `source_id`, so no double counting. `finance_records` carries dimension
  columns (`field_id`, `crop_id`, `livestock_id`, `equipment_id`, `customer_id`,
  `farm_id`).
- Analytics: `src/Intelligence/AnalyticsService.php` (overview, crop/livestock
  enterprise, equipment costs, yield/ha, inventory value, expiring stock, market
  prices with trend) and `public/views/analytics_landing.php` with a date-range
  filter and money-gated cards for finance roles. `src/Intelligence/AttentionService.php`
  now also surfaces expiring stock, sprays due, tasks due, outbreaks, treatment
  follow-ups, spoilage risk, weather risk, invoices due, contracts expiring and
  market opportunity alerts. Dashboard (`public/views/dashboard.php`) gained a
  profit / cost-per-hectare KPI card sourced from `AnalyticsService::overview()`.
- Reports: `src/Modules/Reports/ReportsController.php` now exposes ~47 whitelisted
  datasets as `private const DATASETS` plus `public static exportSets()` consumed
  by `public/views/reports.php` (grouped export picker); the UI can no longer drift
  from the SQL whitelist.
- Security/quality: CSRF on all state changes, RBAC on every new route, validated
  IDs/numbers/dates/arrays/FKs, transactions for multi-table operations, audit
  logs, defensive queries so a partially migrated DB never white-screens.
- Public traceability URLs (`module=Traceability&action=public`) never expose
  private farm data; no shell-based DB backups.

## Files changed/added

`public/index.php`, `public/views/security.php`, `public/views/sales.php`, `db/migrate.php`, `db/seed.php`, `db/features_schema.sql`, `db/expansion_schema.sql`, `.env.example`, `src/Helpers/CsrfHelper.php`, `src/Helpers/RbacHelper.php`, `src/Helpers/AuditHelper.php` (existing), `src/Helpers/GeoJsonHelper.php`, `src/Helpers/RoleHelper.php`, `src/Services/BackupService.php`, `src/Services/FarmMetricsService.php`, `src/Services/NotificationService.php`, `src/Modules/BaseModuleModel.php`, `src/Modules/Sales/SalesModel.php`, `src/Modules/Sales/SalesController.php`, `src/Modules/Field/FieldController.php`, `public/views/field.php`, `public/views/field_landing.php`, `src/Modules/Finance/FinanceService.php`, `src/Modules/Reports/ReportsController.php`, `public/views/reports.php`, `src/Modules/Ops/OpsRegistry.php`, `src/Modules/Ops/OpsModel.php`, `src/Modules/Ops/OpsController.php`, `public/views/ops_landing.php`, `public/views/ops_records.php`, `src/Intelligence/AnalyticsService.php`, `src/Intelligence/AttentionService.php`, `public/views/analytics_landing.php`, `public/views/dashboard.php`, `public/views/layout.php`, `src/Helpers/RbacHelper.php`, `tests/ops_registry_check.php`.

## New protected actions

- `Security/backup` (admin + CSRF)
- `Security/profitability` (profit permission)
- `Security/qr_resolve` and `Security/qr_label` (QR permission)
- `Security/quick_action` (QR permission + CSRF)
- `Insights/assistant_sale_draft` and `Insights/assistant_sale_confirm` (assistant permission; confirm uses CSRF)
- `Security/sms_digest` (alerts permission + CSRF)
- `Ops` landing/records/add/edit/delete (per-definition permission + CSRF on state changes)
- `Analytics` (analytics.read), `Reports` (reports.read)

## Wave completion (Waves 1–4)

- **Wave 1 — Foundations: COMPLETE.** 1A per-field/per-animal profit (API + report rows), 1B farm connections (mutual-accept graph + farm_members), 1C intranet Communication Hub (conversations, members, messages, broadcast, task integration).
- **Wave 2 — Flagship: COMPLETE.** 2A extranet (accepted-connection gating, message reports, admin remove), 2B Farm Credit Passport (practice scoring, share tokens, public scope-filtered page).
- **Wave 3 — Community: COMPLETE.** 3A farm-to-market traceability (harvest batches + batch events + public chain), 3B AI summarise/draft reply (access-checked, no auto-send).
- **Wave 4 — Experiments: 4A and 4B COMPLETE** (text-to-record assistant draft/confirm already existed; planting what-if planner added). **4C NOT BUILT** — offline-first sync is documented as future work in README only (deliberate scope discipline).

### Intranet privacy statement
A user can only access an intranet conversation when their `farm_members.farm_id` equals `conversations.farm_id`. Admins are **not** granted cross-farm intranet read access — this is a multi-tenant security property enforced in `CommsService::assertIntranetScope()`, not a limitation.

### Credit passport statement
The Farm Credit Passport is farmer-owned, consent-based, explainable and revocable. Scores are computed from the farmer's own records (record-keeping, treatment compliance, crop rotation, yield trend, sales regularity, expense discipline, traceability participation) with a published weight breakdown. Sharing requires an explicit, scoped, expiring token that the farmer can revoke; the public page shows only score, band, factors and an anonymised summary — never personal data. Every share create/revoke/view is audited.

### Deferred
- Offline-first sync (4C): documented only, no code.
- WebSockets/SSE/presence: not used; async poll on focus/load only.
- No new file uploader; objects linked via ref_type/ref_id.

### New endpoints (RBAC)
- GET /api/v1/analytics/profit/by-field, /by-animal (finance/owner/manager see money)
- GET/POST /api/v1/extranet/farms/search, /extranet/connections (owner/manager send/decide)
- GET/POST /api/v1/intranet/conversations, /messages, /read, /broadcast
- GET/POST /api/v1/extranet/conversations, /messages
- POST /api/v1/messages/{id}/report, /api/v1/admin/messages/{id}/remove
- GET /api/v1/credit-passport/me, POST /share, POST /share/{id}/revoke, GET history
- GET /credit/passport/{token} (public)
- POST /api/v1/traceability/batches, /batches/{id}/events, GET /batches/{id}, GET /trace/{code} (public)
- POST /api/v1/assistant/summarise/{id}, /draft-reply/{id}
- POST /api/v1/planner/planting-what-if

### Tests
- tests/ops_registry_check.php: pass
- tests/regression.sh: extended with wave endpoint checks
- tests/wave_smoke.sh: new end-to-end API smoke test
- tests/smoke_fuzz.py: unchanged, run against local server
- PHP lint: all files clean; git diff --check clean
columns verified against db/*.sql); PHP lint clean across all changed/new files;
`tests/regression.sh` syntax valid. PostgreSQL/pdo_pgsql are not available in this
checkout, so DB-backed runtime tests could not be executed; static validation
was performed instead.

## Deferred

None outstanding for the 10 priority areas. PHPMailer remains optional; the Docker
build does not require Composer/vendor; `.env`, secrets, `vendor/`, deployment
credentials and real API keys are excluded from commits and the release zip.
