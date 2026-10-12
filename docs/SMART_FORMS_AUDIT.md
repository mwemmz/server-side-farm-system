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
- (d) ✅ `expected_harvest_date` captured; auto-estimate from crop name; harvest due = Phase 3 alert.
- (e) 🟡 Harvest → storage auto-post is Phase 3.
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
- (d) ✅ Vaccinations track repeat interval + `next_due_date`; list shows Overdue / Due soon / Scheduled.
- (e) 🟡 Treatment → inventory decrement + finance cost is Phase 3.
- (f) ✅ Breed help text; DOB max = today.
- (g) ✅ Farm + animal FK guards; DB errors caught.

## Inventory
- (a) ✅ Item name → open combo. Category → open combo. Unit → open combo.
- (b) ✅ Unit is a first-class combo.
- (c) ✅ Category combo (incl. pesticides, machinery, fuel).
- (d) 🟡 `min_level` / reorder rules + low-stock alerts are Phase 3.
- (e) 🟡 Procurement increments stock; usage decrements (Phase 3).
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
- (e) 🟡 Purchase → stock increment + finance expense (Phase 3).
- (f) ✅ Purchase date defaults to today.
- (g) ✅ Supplier FK guard; cost/quantity validated; DB errors caught.

## Sales
- (a) ✅ Product → open `crop_name` combo. Unit → open combo. Payment method → open combo.
- (b) ✅ Amount labelled K; unit combo present.
- (c) 🟡 Group by product/payment method (Phase 3).
- (d) ⬜ none.
- (e) 🟡 Sale → stock decrement + finance income (Phase 3).
- (f) ✅ Sale date defaults to today.
- (g) ✅ Amount positive; quantity ≥ 0 when present.

## Storage
- (a) ✅ Facility type → open combo. Unit → open combo.
- (b) ✅ Capacity + current stock numeric ≥ 0; unit combo.
- (c) 🟡 Group by facility type (Phase 3).
- (d) ⬜ none.
- (e) 🟡 Harvest → storage auto-post (Phase 3).
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
- (d) 🟡 `service_interval_days` + `last_service_date` reminders (Phase 3).
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

## Pest & Disease
- (a) ✅ Farm → closed combo. Pest name → open combo. Severity → open combo.
- (b) ⬜ n/a.
- (c) ✅ Severity combo.
- (d) ⬜ none.
- (e) 🟡 Merge into `health_incidents`, chemical decrement + cost (Phase 3).
- (f) ✅ Detected date defaults to today.
- (g) ✅ Farm FK guard; DB errors caught.

## Finance
- (a) ✅ Category → open combo. Farm → closed combo. Payment method → open combo.
- (b) ✅ Amount labelled K.
- (c) ✅ Category + payment method + farm combos.
- (d) 🟡 Unpaid/overdue tracking (Phase 3).
- (e) 🟡 Auto-post from purchase/sale/payroll (Phase 3).
- (f) ✅ Date defaults to today; farm validated.
- (g) ✅ Amount positive; farm-exists guard; DB errors caught.

## Labour / HR
- (a) ✅ Employee, department, status, contract, pay type all selects. Live-add forms included.
- (b) ✅ Pay amounts numeric.
- (c) ✅ Department filter.
- (d) ✅ Leave balances + upcoming leave.
- (e) 🟡 Payroll → finance expense (Phase 3).
- (f) ✅ Statutory deduction preview; period defaults.
- (g) ✅ Server-side required checks; gated to admin/manager.

## Catches (asked if it repeats → needs next-due + alert)
- **Vaccinations** — the model implementation (repeat interval, `next_due_date`, overdue/due-soon). ✅
- Equipment servicing, inventory reorder, harvest due dates — Phase 3.

## Known deferred (Phase 3)
1. Animal health / `vaccine_catalog` catalogue + reminders wiring.
2. Finance categories split by type + cross-module auto-posting (purchase→stock+expense, sale→stock−+income, harvest→storage, treatment→chemical−+cost, payroll→Labour expense).
3. Inventory `stock_movements` + reorder rules + low-stock alerts.
4. Merge Pest into `health_incidents`; remove/redirect `module=Pest`.
5. Cross-module links, "Needs attention today" dashboard, audit logs, list search/filter/pagination.
