# Smart Forms Audit

Per-module questionnaire from the FFMS Smart Upgrade master prompt. A module is
considered "done" only when every question below answers **"nothing left to fix"**.

Questions:

- **(a)** Which typed fields should be dropdowns?
- **(b)** Which numbers lack a unit?
- **(c)** What should be grouped/filtered by category or owner?
- **(d)** What repeats and therefore needs a next-due date + alert?
- **(e)** What should a module read/update in another module?
- **(f)** What helpful warning or smart default is missing?
- **(g)** What could still 500 with bad input?

Legend: ✅ done · 🟡 partial (deferred to Phase 3) · ⬜ not started.

---

## Crops
- (a) ✅ Farm → closed combo (`farmOptions`). Crop name → open `crop_name` combo. Variety → open `crop_variety` combo.
- (b) ✅ Size lives on Fields (ha). Crop has no bare quantity.
- (c) 🟡 Varieties grouped by crop name (future meta parent).
- (d) ✅ `expected_harvest_date` captured; auto-estimate from crop name; crop list now shows a Harvest due-in-N-days / overdue chip (B7), and the dashboard raises a "harvest due this week" item (B5).
- (e) ✅ Harvest → storage auto-post (B7): optional "Store in" adds the yield to the facility in the same transaction (kg/tonnes only, capacity-guarded).
- (f) ✅ Planting date defaults to today; harvest date auto-fills; harvest-before-planting blocked.
- (g) ✅ Farm must exist (`ValidationHelper::exists`); DB errors caught.

## Fields
- (a) ✅ Farm → closed combo. Soil type → closed select.
- (b) ✅ Size labelled hectares, numeric min 0.01.
- (c) 🟡 Group by farm on the list (Phase 3).
- (d) ⬜ none repeating.
- (e) 🟡 Crops link to a field (`crops.field_id`).
- (f) ✅ Farm preselected from `?farm_id=`.
- (g) ✅ Farm-exists + numeric size guards; DB errors caught.

## Livestock
- (a) ✅ Farm → closed combo. Type → open combo. Breed → open combo, filtered by type.
- (b) ✅ Flock size (poultry) unit is "birds" (Phase 3 field).
- (c) ✅ Breeds grouped by species via `meta->>'species'`.
- (d) ✅ Vaccinations track repeat interval + `next_due_date`; catalogue-driven auto next-due (B1); list shows Overdue / Due soon / Scheduled; dashboard + notifications raise overdue/due-soon (B5).
- (e) ✅ Treatment → inventory decrement + finance cost (B1): `animal_treatments` records dose, withdrawal window, optional stock-out and posts 'Veterinary & medicines' expense.
- (f) ✅ Breed help text; DOB max = today.
- (g) ✅ Farm + animal FK guards; DB errors caught.

## Inventory
- (a) ✅ Item name → open combo. Category → open combo. Unit → open combo.
- (b) ✅ Unit is a first-class combo.
- (c) ✅ Category combo (incl. pesticides, machinery, fuel).
- (d) ✅ `min_level` threshold drives a Low badge per item (B3) and a dashboard/notification low-stock alert (B5).
- (e) ✅ Procurement increments stock and usage decrements (B3): stock in/out with row-locked no-negative guard, movement history, reason codes; procurement purchases auto stock-in.
- (f) ✅ Quantity numeric ≥ 0.
- (g) ✅ Quantity validated server-side.

## Suppliers
- (a) ✅ Supplies category → open combo. Phone captured.
- (b) — n/a.
- (c) ✅ Category combo.
- (d) ⬜ none.
- (e) 🟡 Procurement reads suppliers (already via closed combo).
- (f) ✅ Contact info optional now.
- (g) ✅ Name-only required; DB errors caught.

## Procurement
- (a) ✅ Supplier → closed combo. Item name → open `inventory_item` combo.
- (b) ✅ Cost labelled K; quantity whole number.
- (c) 🟡 Group by supplier (Phase 3).
- (d) ⬜ none.
- (e) ✅ Purchase → stock increment + finance expense (B2/B3): expense posted via FinanceService and the item stocked in, in one flow.
- (f) ✅ Purchase date defaults to today.
- (g) ✅ Supplier FK guard; cost/quantity validated; DB errors caught.

## Sales
- (a) ✅ Product → open `crop_name` combo. Unit → open combo. Payment method → open combo.
- (b) ✅ Amount labelled K; unit combo present.
- (c) 🟡 Group by product/payment method (Phase 3).
- (d) ⬜ none.
- (e) 🟡 Sale → finance income ✅ (B2, via FinanceService). Automatic stock decrement on sale still deferred.
- (f) ✅ Sale date defaults to today.
- (g) ✅ Amount positive; quantity ≥ 0 when present.

## Storage
- (a) ✅ Facility type → open combo. Unit → open combo.
- (b) ✅ Capacity + current stock numeric ≥ 0; unit combo.
- (c) 🟡 Group by facility type (Phase 3).
- (d) ⬜ none.
- (e) ✅ Harvest → storage auto-post (B7, transactional, capacity-guarded).
- (f) ✅ Placeholder guidance.
- (g) ✅ Capacity/stock numeric guards; DB errors caught.

## Irrigation
- (a) ✅ Farm → closed combo. Type / status / water source → open combos.
- (b) ✅ (flow/area units Phase 3).
- (c) ✅ Status + type + water source combos.
- (d) 🟡 Service/maintenance interval reminder (Phase 3).
- (e) ⬜ none.
- (f) ✅ Water source now captured.
- (g) ✅ Farm FK guard; DB errors caught.

## Equipment
- (a) ✅ Status → open combo. Type → open combo. Farm → closed combo.
- (b) ⬜ n/a.
- (c) ✅ Equipment type + status combos.
- (d) ✅ `service_interval_days` + `last_service_date` drive `next_service_date`, OK/Due-soon/Overdue chips, and dashboard/notification alerts (B6). "Record service" resets the clock, can post cost + consume a spare part.
- (e) ⬜ none.
- (f) ✅ Purchase date captured.
- (g) ✅ Name/status required; DB errors caught. Whole Add/Manage flow verified.

## Weather
- (a) ✅ Farm → closed combo.
- (b) ✅ Temperature °C, humidity % (both bounded).
- (c) 🟡 Group by farm (Phase 3).
- (d) ⬜ none.
- (e) ⬜ none.
- (f) ✅ Bounds on temperature/humidity.
- (g) ✅ Numeric + range guards; `weather_date` defaults server-side.

## Pest & Disease → merged into health_incidents (B4)
- (a) ✅ Farm → closed combo. Issue name → catalogue-driven combo (health_issue_catalog) with allow-custom; Type → pest/disease/other. Reporting now lives inside Crop & Livestock ("Pests & Problems"); `module=Pest` redirects to Crop.
- (b) ⬜ n/a.
- (c) ✅ Severity combo.
- (d) ⬜ none.
- (e) ✅ Merged into `health_incidents` (B4); legacy pest_records copied over, readers updated, records can be marked treated/resolved. Chemical decrement + cost on a health incident remains deferred.
- (f) ✅ Detected date defaults to today.
- (g) ✅ Farm FK guard; DB errors caught.

## Finance
- (a) ✅ Category → open combo. Farm → closed combo. Payment method → open combo.
- (b) ✅ Amount labelled K.
- (c) ✅ Category + payment method + farm combos.
- (d) ✅ Unpaid/overdue tracking (B2): payment_status + due_date, unpaid section, dashboard/notification alert.
- (e) ✅ Auto-post from purchase/sale/payroll/harvest/treatments/loan repayments (B2) via idempotent FinanceService (source_module + source_id, never double-posts).
- (f) ✅ Date defaults to today; farm validated.
- (g) ✅ Amount positive; farm-exists guard; DB errors caught.

## Labour / HR
- (a) ✅ Employee, department, status, contract, pay type all selects. Live-add forms included.
- (b) ✅ Pay amounts numeric.
- (c) ✅ Department filter.
- (d) ✅ Leave balances + upcoming leave.
- (e) ✅ Payroll `paid` → finance expense 'Labour & wages' (B2).
- (f) ✅ Statutory deduction preview; period defaults.
- (g) ✅ Server-side required checks; gated to admin/manager.

## Catches (asked if it repeats → needs next-due + alert)
- **Vaccinations** — repeat interval, `next_due_date`, catalogue-driven auto next-due, overdue/due-soon. ✅ (B1)
- **Equipment servicing** — interval + `next_service_date` + OK/Due-soon/Overdue. ✅ (B6)
- **Inventory reorder** — `min_level` + Low badge + low-stock alert. ✅ (B3)
- **Harvest due dates** — crop-list chip + dashboard item. ✅ (B7)
- All of the above feed one shared "Needs attention today" card via `AttentionService`. ✅ (B5)

## Phase 3 completion (B1–B7)
1. ✅ **B1** Vaccine catalogue + computed next-due, batch vaccination, animal treatments with withdrawal tracking, catalogue-driven livestock insights.
2. ✅ **B2** Dependent finance categories + idempotent `FinanceService` cross-module auto-posting (purchase/sale/payroll/harvest/treatments/loan repayments), category backfill, grouped list with filters and an unpaid view.
3. ✅ **B3** Inventory stock in/out (row-locked, refuses below zero), movement history, low-stock badges + de-duplicated alerts, category/purpose grouping, procurement auto stock-in.
4. ✅ **B4** Pests/diseases unified into `health_incidents` (catalogue-driven, crop + livestock), mark treated/resolved, legacy `pest_records` migrated, insights/reports/seeds updated, `module=Pest` redirects.
5. ✅ **B5** Shared `AttentionService` → dashboard card + Assistant intent + de-duplicated notification scans.
6. ✅ **B6** Equipment service schedule + "Record service" (cost → finance, spare part → stock-out).
7. ✅ **B7** Harvest → storage link (transactional, kg/tonnes match, capacity guard) + crop harvest-due countdown.

## Still deferred
- Automatic stock decrement on a **sale** (income is posted; the matching stock-out is not).
- Chemical quantity + cost decrement when a **health incident** is treated.
- Crop-variety grouping by crop name; field grouping on the Fields list; procurement grouping by supplier; weather grouping by farm.
- Audit logs and list search/filter/pagination beyond what the above added.
- Catalogue values (`vaccine_catalog`, `health_issue_catalog`, lookup lists) are starter data — a vet/agronomist should confirm them (`needs_review = TRUE`).
