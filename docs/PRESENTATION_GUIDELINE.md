# FFMS — Presentation Guideline

A ready-to-use guide for presenting the **Intelligent Farm Management System (FFMS)**.
Use it as a script, slide outline, and demo checklist.

---

## 1. Elevator pitch (30 seconds)

> "FFMS is a single web application that runs an entire agribusiness — crops,
> livestock, inventory, sales, finance, labour, equipment, storage, weather and
> market intelligence — on one shared ledger, with a smart layer that tells the
> farmer what needs attention today, predicts yields and prices, and produces a
> farmer-owned credit passport that can be shared with lenders."

---

## 2. Slide outline (12–15 slides)

| # | Slide | Key message |
|---|-------|-------------|
| 1 | Title + team | FFMS — Intelligent Farm Management System |
| 2 | Problem | Farms run on paper/Excel; data is fragmented; no single source of truth |
| 3 | Solution | One PHP 8 + PostgreSQL app, deployed on Render via Docker |
| 4 | Architecture | MVC-ish: controllers → models → PDO/PostgreSQL; views + Tailwind; REST API under `/api/v1` |
| 5 | Core modules | Crops, livestock, inventory, procurement, sales, finance, labour, storage, equipment, weather, market |
| 6 | Smart layer | Pick-or-type comboboxes, auto tags, vaccine species rules, service clock, reorder, attention alerts |
| 7 | Single costing ledger | `finance_records` with dimension columns (`field_id`, `crop_id`, `livestock_id`, `equipment_id`, `customer_id`) — no double counting |
| 8 | Analytics | Profit, margin, ROI, cost/ha, yield/ha, enterprise profitability, market trends |
| 9 | Communication Hub | Intranet (farm-scoped, admins cannot read other farms) + Extranet (mutual-accept) |
| 10 | Farm Credit Passport | Farmer-owned, consent-based, explainable, revocable score 0–100 with 7 factors |
| 11 | Traceability | Harvest batches + chain-of-custody timeline, public QR-friendly page |
| 12 | Marketplace | Farmer-to-farmer search, list, buy/sell with auto finance posting |
| 13 | Security & quality | RBAC (6 roles), CSRF, validated IDs/numbers/dates/FKs, transactions, audit logs |
| 14 | Testing | `regression.sh` (400+ assertions), `smoke_fuzz.py`, `ops_registry_check.php`, PHP lint, `git diff --check` |
| 15 | Demo + Q&A | Live walkthrough |

---

## 3. Demo script (recommended order, ~8 minutes)

### A. Login & dashboard (1 min)
1. Log in as `admin` / `admin123`.
2. Show the **"Needs attention today"** card (overdue vaccinations, low stock, unpaid finance, outbreaks, tasks due, weather risk).
3. Point out the **profit / cost-per-hectare KPI** card (finance roles only).
4. Show the quick-action buttons and the weather panel.

### B. Livestock lifecycle (2 min)
1. **Livestock → Manage** — show the animal register with tags, filters, and the **"Jump to a form"** navigator.
2. Open an animal's **profile** — walk through Birth → Status → Sold/Death summary, then Weights, Breeding, Production, Feed, Vaccinations, Treatments, Health incidents.
3. Record a vaccination, a treatment, and a pest report; show the withdrawal-period badge.
4. Mark an animal **Sold** or **Dead** and show it leaves all pickers but keeps history.

### C. Crops & field depth (1 min)
1. **Field** — draw a boundary polygon on the Leaflet map; show computed hectares.
2. **Crop → Pests & Problems** — report a problem; show the "no `>Array<`" dropdowns.
3. **Ops → Soil Conditions** — add a soil sample; show required/number/date/FK validation.

### D. Operations engine (1 min)
1. **Ops landing** — show the 9 groups and record counts.
2. Open **Tasks**, **Invoices** (auto-numbering, overpayment guard), **Equipment repairs** (marks serviced).
3. Delete a record → confirm dialog → **Undo last delete** within 5 minutes.

### E. Analytics & reports (1 min)
1. **Analytics** — date-range filter, revenue/cost/profit/margin, crop & livestock enterprise tables, yield/ha, market prices with trend, expiring stock.
2. **Reports** — grouped export picker; download a CSV and open a printable (PDF) view.

### F. Communication Hub & connections (1 min)
1. **Communications** — create a conversation, post a message, send a broadcast (owner/manager).
2. **Connections** — search farms, request a connection, accept/decline.
3. Note the **intranet privacy rule**: admins cannot read another farm's intranet.

### G. Credit Passport & traceability (1 min)
1. **Credit Passport** — compute score + band + factor breakdown + 90-day trend chart; create a share link; revoke it.
2. Open the **public passport URL** (no login) — show only score, band, factors, anonymised summary.
3. **Traceability** — create a harvest batch; show the public chain-of-custody timeline.

### H. Marketplace (30 s)
1. **Marketplace** — search produce, create a listing, buy from another farmer's listing; show the finance records auto-posted.

---

## 4. Talking points per feature

### Single costing ledger
- All cost-bearing Ops records post to `finance_records` with `source_module = 'Ops:<key>'` and `source_id`, plus dimension columns (`field_id`, `crop_id`, `livestock_id`, `equipment_id`, `customer_id`).
- Revenue comes from `sales_records`; analytics sums both — **no double counting**.

### Metadata-driven Ops engine
- 35 record definitions in `OpsRegistry`, one generic controller/view/model.
- Adding a new record type = one registry entry + one migration table — no bespoke CRUD.

### Farm connections (mutual-accept graph)
- `farm_connections` with statuses pending/accepted/declined/blocked.
- Extranet conversations are **rejected unless** an accepted connection exists.

### Communication Hub privacy (multi-tenant)
- Intranet conversations are scoped to `farm_members.farm_id = conversations.farm_id`.
- Admins **cannot** read another farm's intranet — stated as a security property, not a limitation.

### Farm Credit Passport
- 7 weighted factors (record-keeping 20%, treatment compliance 15%, crop rotation 15%, yield trend 15%, sales regularity 15%, expense discipline 10%, traceability 10%).
- Score 0–100, bands bronze/silver/gold/platinum.
- Farmer-owned, consent-based, explainable, revocable; public page shows no personal data; every view is audited.

### Traceability
- `harvest_batches` + `batch_events` (harvest/storage/dispatch/sale).
- Public page shows the chain of custody timeline only — no personal data.

### Marketplace
- Search by item name/category; create listings; buy reduces quantity and posts finance (expense for buyer, income for seller).

### Security & quality
- 6 roles (admin, manager, accountant, worker, farm-owner, agronomist) with granular permissions.
- CSRF on every state change; validated IDs/numbers/dates/arrays/FKs; transactions for multi-table ops; audit logs.
- PHPMailer optional; Docker builds without Composer/vendor; no shell DB backups.

---

## 5. Test & validation summary (for the "quality" slide)

| Suite | Result |
|-------|--------|
| `tests/ops_registry_check.php` | Pass — 35 record types, 273 columns vs. `db/*.sql` |
| `tests/regression.sh` | 402 passed / 13 failed (pre-existing, non-Wave) |
| `tests/smoke_fuzz.py` | Run against local server |
| `tests/wave_smoke.sh` | All Wave endpoints 200 |
| PHP lint (`php -l`) | All files clean |
| `git diff --check` | Clean |

---

## 6. Demo accounts

| Username | Password | Role | Lands on |
|----------|----------|------|----------|
| `admin` | `admin123` | admin | Dashboard |
| `owner` | `demo123` | farm-owner | Dashboard |
| `agronomist` | `demo123` | agronomist | Insights |
| `chanda_banda` | `demo123` | farm-owner | Dashboard |
| `mwamba_zulu` | `demo123` | farm-owner | Dashboard |
| `rl_worker` | `abc12345` | worker | My day |

---

## 7. Tips for the presenter

- **Start with the problem, not the tech.** Farmers lose money because data is scattered.
- **Show, don't tell.** The "Needs attention today" card and the credit passport score are the wow moments.
- **Keep the demo to 8 minutes.** Deeper features are in the Q&A.
- **If asked about scale:** the Ops engine is metadata-driven — a new record type is one registry entry + one migration.
- **If asked about multi-tenancy:** intranet privacy is a hard rule, not a limitation — state it explicitly.
- **If asked about offline:** it's documented as future work (4C) — deliberate scope discipline.
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

## 9. Closing line

> "FFMS turns a farm's daily records into decisions, money, and trust —
> one ledger, one score, one passport."