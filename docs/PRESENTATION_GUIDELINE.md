# FFMS — Presentation Guideline (4-Developer Division)

A ready-to-use guide for presenting the **Intelligent Farm Management System (FFMS)**,
divided among **4 developers** according to the 10 priority requirement areas.

---

## Team division

| Dev | Focus (requirement areas) | Owns |
|-----|---------------------------|------|
| **Dev 1** | Farm & Field depth + Crop Agronomy | Field boundary (GeoJSON/Leaflet), soil conditions, rotation/field history, land-use plans, seed, planting, fertilizer, growth stages, spray schedules, chemical applications, treatment follow-ups, AnalyticsService yield/crop enterprise |
| **Dev 2** | Livestock Production + Pest & Disease | Weights, breeding, production, mortality, feed, vaccination rules, lifecycle record, health incidents, outbreaks, animal treatments, AttentionService animal alerts |
| **Dev 3** | Equipment Costing + Labour + Harvest/Storage/Market | Equipment allocations, fuel, hours, repairs, depreciation, daily tasks, harvest schedules/losses, packaging, quality, dispatches, customers, markets, orders, invoices, contracts, suppliers, quotations, reviews |
| **Dev 4** | Financial Analytics + Dashboard/Notifications + Security/Quality + Platform | Analytics landing, profit KPI, AttentionService, RBAC, CSRF, audit, comms hub, farm connections, credit passport, traceability, marketplace, tests, docs, migrations, deploy |

Cross-cutting (shared): Ops engine (`OpsRegistry`/`OpsModel`/`OpsController`), `FinanceService` ledger, `db/migrate.php`, `README`/`IMPLEMENTATION_REPORT`, zip.

---

## 1. Elevator pitch (30 seconds) — Dev 4

> "FFMS is a single web application that runs an entire agribusiness — crops,
> livestock, inventory, sales, finance, labour, equipment, storage, weather and
> market intelligence — on one shared ledger, with a smart layer that tells the
> farmer what needs attention today, predicts yields and prices, and produces a
> farmer-owned credit passport that can be shared with lenders."

---

## 2. Slide outline (15 slides) with owner

| # | Slide | Owner | Key message |
|---|-------|-------|-------------|
| 1 | Title + team | All | FFMS — Intelligent Farm Management System |
| 2 | Problem | Dev 4 | Farms run on paper/Excel; data fragmented; no single source of truth |
| 3 | Solution + architecture | Dev 4 | PHP 8 + PostgreSQL, MVC, PDO, REST API `/api/v1`, deployed on Render via Docker |
| 4 | Farm & Field depth | Dev 1 | GeoJSON boundaries, computed hectares, soil, rotation, land-use plans |
| 5 | Crop agronomy | Dev 1 | Seed → planting → fertilizer → growth → spray → chemical → follow-up |
| 6 | Livestock production | Dev 2 | Weights, breeding, production, mortality, feed, vaccination rules, lifecycle record |
| 7 | Pest & disease | Dev 2 | Health incidents, outbreaks, chemical applications, treatment follow-ups |
| 8 | Equipment costing | Dev 3 | Allocations, fuel, hours, repairs, depreciation |
| 9 | Labour & tasks | Dev 3 | Daily tasks with assignee, hours, labour cost, finance posting |
| 10 | Harvest / storage / market | Dev 3 | Schedules, losses, packaging, quality, dispatches, customers, orders, invoices, contracts, suppliers |
| 11 | Single costing ledger | Dev 4 | `finance_records` dimension columns — no double counting |
| 12 | Analytics & dashboard | Dev 4 | Profit, margin, ROI, cost/ha, yield/ha, enterprise profitability, attention alerts, credit passport |
| 13 | Communication Hub & connections | Dev 4 | Intranet (farm-scoped), extranet (mutual-accept), broadcast |
| 14 | Security & quality | Dev 4 | RBAC 6 roles, CSRF, validated inputs, transactions, audit logs |
| 15 | Testing & demo | All | regression.sh (400+), smoke_fuzz.py, ops_registry_check.php, PHP lint |

---

## 3. Demo script (8 minutes) with owner

### A. Login & dashboard — Dev 4 (1 min)
1. Log in as `admin` / `admin123`.
2. Show **"Needs attention today"** card (overdue vaccinations, low stock, unpaid finance, outbreaks, tasks due, weather risk).
3. Point out the **profit / cost-per-hectare KPI** card (finance roles only).
4. Show quick actions + weather panel.

### B. Field & crop — Dev 1 (1.5 min)
1. **Field** — draw a boundary polygon on the Leaflet map; show computed hectares.
2. **Crop → Pests & Problems** — report a problem; show the "no `>Array<`" dropdowns.
3. **Ops → Soil Conditions** — add a soil sample; show required/number/date/FK validation.
4. **Ops → Planting / Fertilizer / Spray schedules** — show the crop agronomy pipeline.

### C. Livestock & health — Dev 2 (1.5 min)
1. **Livestock → Manage** — animal register, filters, **"Jump to a form"** navigator.
2. Open an animal **profile** — Birth → Status → Sold/Death summary, then Weights, Breeding, Production, Feed, Vaccinations, Treatments, Health incidents.
3. Record a vaccination, treatment, pest report; show withdrawal-period badge.
4. Mark an animal **Sold** or **Dead**; show it leaves pickers but keeps history.

### D. Equipment, labour & harvest — Dev 3 (1.5 min)
1. **Equipment** — service reminder badges, record a service, spare-part stock.
2. **Ops → Tasks** — assign a task; show labour cost finance posting.
3. **Ops → Equipment repairs/fuel/hours/depreciation** — costing pipeline.
4. **Harvest → store in facility** — stock rises; capacity guard.
5. **Ops → Invoices** — auto-numbering, overpayment guard, recalc on item changes.

### E. Analytics & reports — Dev 4 (1 min)
1. **Analytics** — date-range filter, revenue/cost/profit/margin, crop & livestock enterprise, yield/ha, market prices with trend, expiring stock.
2. **Reports** — grouped export picker; download a CSV and open a printable (PDF) view.

### F. Communication & connections — Dev 4 (1 min)
1. **Communications** — create a conversation, post a message, send a broadcast (owner/manager).
2. **Connections** — search farms, request a connection, accept/decline.
3. Note the **intranet privacy rule**: admins cannot read another farm's intranet.

### G. Credit Passport & traceability & marketplace — Dev 4 (1 min)
1. **Credit Passport** — compute score + band + factor breakdown + 90-day trend chart; create a share link; revoke it.
2. Open the **public passport URL** (no login).
3. **Traceability** — create a harvest batch; show the public chain-of-custody timeline.
4. **Marketplace** — search produce, create a listing, buy from another farmer's listing.

---

## 4. Talking points per dev

### Dev 1 — Farm & Field + Crop Agronomy
- GeoJSON boundary polygons with computed hectares; Leaflet click-to-draw editor; fallback to simulated circles.
- Soil conditions, field history/rotation, land-use plans.
- Seed → planting → fertilizer → growth stages → spray schedules → chemical applications → treatment follow-ups.
- Metadata-driven: a new record type = one registry entry + one migration table.

### Dev 2 — Livestock & Pest/Disease
- Animal lifecycle record: birth → sale/death, weights, breeding, production, feed, vaccinations, treatments, health incidents.
- Vaccine species rules; batch vaccination (mixed-species refused); withdrawal periods.
- Health incidents, outbreaks flagged, chemical applications, treatment follow-ups.
- Attention alerts: overdue vaccinations, open incidents, active withdrawals.

### Dev 3 — Equipment, Labour & Harvest/Market
- Equipment costing: allocations, fuel (updates meter), operating hours, repairs (marks serviced), depreciation.
- Daily tasks: assignee, field/crop/animal/equipment, hours, labour cost, auto finance posting on completion.
- Harvest: schedules, losses, packaging, quality grades, dispatches.
- Market: customers, markets, sales orders, invoices (auto-numbered, auto-status, recalc), contracts, suppliers, quotations, reviews (auto rating).

### Dev 4 — Analytics, Security & Platform
- Single costing ledger: `finance_records` with dimension columns; no double counting.
- Analytics: profit, margin, ROI, cost/ha, yield/ha, enterprise profitability, market trends.
- AttentionService: 10+ alert types, 5-min per-user cache.
- RBAC 6 roles; CSRF on all state changes; validated IDs/numbers/dates/FKs; transactions; audit logs.
- Communication Hub: farm-scoped intranet (admins can't read other farms), extranet (mutual-accept), broadcast.
- Farm Credit Passport: farmer-owned, consent-based, explainable, revocable.
- Traceability: harvest batches + chain-of-custody, public timeline.
- Marketplace: farmer-to-farmer search/buy/sell with auto finance posting.
- Tests: regression.sh (400+), smoke_fuzz.py, ops_registry_check.php, wave_smoke.sh, PHP lint.
- Migrations: version-gated `migrate.php`, DB indexes, no shell backups, PHPMailer optional, Docker without Composer.

---

## 5. Demo accounts

| Username | Password | Role | Lands on |
|----------|----------|------|----------|
| `admin` | `admin123` | admin | Dashboard |
| `owner` | `demo123` | farm-owner | Dashboard |
| `agronomist` | `demo123` | agronomist | Insights |
| `chanda_banda` | `demo123` | farm-owner | Dashboard |
| `mwamba_zulu` | `demo123` | farm-owner | Dashboard |
| `rl_worker` | `abc12345` | worker | My day |

---

## 6. Test & validation summary

| Suite | Result |
|-------|--------|
| `tests/ops_registry_check.php` | Pass — 35 record types, 273 columns |
| `tests/regression.sh` | 402 passed / 13 failed (pre-existing, non-Wave) |
| `tests/smoke_fuzz.py` | Run against local server |
| `tests/wave_smoke.sh` | All Wave endpoints 200 |
| PHP lint (`php -l`) | All files clean |
| `git diff --check` | Clean |

---

## 7. Tips for presenters

- **Start with the problem, not the tech.** Farmers lose money because data is scattered.
- **Show, don't tell.** The "Needs attention today" card and the credit passport score are the wow moments.
- **Keep the demo to 8 minutes.** Deeper features are in the Q&A.
- **If asked about scale:** the Ops engine is metadata-driven — a new record type is one registry entry + one migration.
- **If asked about multi-tenancy:** intranet privacy is a hard rule, not a limitation — state it explicitly.
- **If asked about offline:** documented as future work (Wave 4C) — deliberate scope discipline.
- **If asked about AI:** the assistant only drafts; the user must confirm before any write. No auto-send.
- **Backup plan if live demo fails:** screenshots in `docs/` and the recorded regression output.

---

## 8. Q&A anticipated questions

1. **"How does profit not double count?"** — Single ledger: Ops costs post to `finance_records` with `source_module='Ops:<key>'`; analytics sums `finance_records` (expenses) and `sales_records` (revenue).
2. **"Can admins read everything?"** — No. Intranet is farm-scoped; admins cannot read another farm's intranet.
3. **"How is the credit score computed?"** — 7 weighted factors, evidence counts shown, traceability factor is 0 until harvest batches exist.
4. **"Is the public passport safe?"** — Only score, band, factors, anonymised summary. No personal data. Every view audited. Revocable.
5. **"Does it work offline?"** — Not yet; documented as future work.
6. **"Does it need Composer?"** — No. PHPMailer is optional; Docker builds without vendor.

---

## 9. Closing line (all devs)

> "FFMS turns a farm's daily records into decisions, money, and trust —
> one ledger, one score, one passport."