#!/usr/bin/env bash
#
# FFMS regression test (curl-based).
#
# Runs against a LOCAL server + LOCAL PostgreSQL only. Never point BASE at the
# hosted database.
#
# Usage:
#   DB_HOST=localhost DB_PORT=5432 DB_NAME=farm_system DB_USER=postgres DB_PASS=postgres \
#   php -S localhost:8000 -t public &
#   BASE=http://localhost:8000 bash tests/regression.sh
#
# Exits non-zero if any assertion fails.

set -u
BASE="${BASE:-http://localhost:8000}"
USER_NAME="${FFMS_USER:-admin}"
USER_PASS="${FFMS_PASS:-admin123}"
JAR="$(mktemp)"
PHP_LOG="${PHP_LOG:-}"

PASS=0
FAIL=0

ok()   { PASS=$((PASS + 1)); printf '  \033[32mPASS\033[0m %s\n' "$1"; }
bad()  { FAIL=$((FAIL + 1)); printf '  \033[31mFAIL\033[0m %s\n' "$1"; }
check(){ if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 (expected '$3', got '$2')"; fi; }
contains(){ if printf '%s' "$1" | grep -qF -- "$2"; then ok "$3"; else bad "$3 (missing: $2)"; fi; }
notcontains(){ if printf '%s' "$1" | grep -qF -- "$2"; then bad "$3 (unexpected: $2)"; else ok "$3"; fi; }

# status: prints HTTP status code for a request (no body)
status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
# bodycode: prints "STATUS\nBODY"
bodycode() { curl -s -w '\n%{http_code}' "$@"; }

echo "== FFMS regression @ $BASE =="

# ---------------------------------------------------------------------------
# 1. Login (must fetch the form first to obtain the CSRF token)
# ---------------------------------------------------------------------------
LOGIN_PAGE="$(curl -s -c "$JAR" "$BASE/index.php?module=Security&action=login")"
TOKEN="$(printf '%s' "$LOGIN_PAGE" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"$//')"
if [ -n "$TOKEN" ]; then ok "login page issues a CSRF token"; else bad "login page CSRF token missing"; fi

LOGIN_STATUS="$(status -b "$JAR" -c "$JAR" \
  --data-urlencode "csrf_token=$TOKEN" \
  --data-urlencode "username=$USER_NAME" \
  --data-urlencode "password=$USER_PASS" \
  "$BASE/index.php?module=Security&action=login")"
check "login redirects on success" "$LOGIN_STATUS" "302"

# Refresh the session CSRF token from an authenticated page (it is regenerated on login).
DASH="$(curl -s -b "$JAR" -c "$JAR" "$BASE/index.php?module=Dashboard")"
TOKEN="$(printf '%s' "$DASH" | grep -o 'name="csrf-token" content="[^"]*"' | head -1 | sed 's/.*content="//; s/"$//')"
if [ -n "$TOKEN" ]; then ok "authenticated pages expose the CSRF meta token"; else bad "CSRF meta token missing"; fi

# Grab a couple of real record ids for FK tests.
FARM_ID="$(printf '%s' "$(curl -s -b "$JAR" "$BASE/index.php?module=Livestock&action=manage")" | grep -o "data-value='[0-9]*'" | head -1 | grep -o '[0-9]*')"

# ---------------------------------------------------------------------------
# 2. CSRF: a POST without a token must be refused (403) on several modules
# ---------------------------------------------------------------------------
echo "-- CSRF rejection on POST without token --"
for m in Finance Crop Sales Procurement Livestock; do
  S="$(status -b "$JAR" --data "amount=1" "$BASE/index.php?module=$m&action=add")"
  check "POST $m/add without token -> 403" "$S" "403"
done

# GET-reachable destructive actions must be refused (405)
echo "-- destructive actions must be POST-only --"
for a in "Notifications&action=mark_all_read" "Labour&action=approve&subsection=leave&id=1" "Notifications&action=delete_rule&id=1"; do
  S="$(status -b "$JAR" "$BASE/index.php?module=$a")"
  check "GET $a -> 405" "$S" "405"
done

# ---------------------------------------------------------------------------
# 3. Validation: bad input is rejected, never 500
# ---------------------------------------------------------------------------
echo "-- validation guards --"
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "type=banana&amount=100&description=x&date=2020-01-01" "$BASE/index.php?module=Finance&action=add")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "Finance type=banana -> 200 (form re-render)" "$S" "200"
contains "$B" "Type must be either Income or Expense" "Finance rejects bad type"

R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "farm_id=$FARM_ID&type=Cattle&breed=Boran&dob=2099-01-01" "$BASE/index.php?module=Livestock&action=add")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "future DOB -> 200 (form re-render)" "$S" "200"
notcontains "$B" "Internal Server Error" "future DOB is not a 500"

R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "livestock_id=1&vaccine_name=X&vaccination_date=2020-01-01&next_due_date=2019-01-01" "$BASE/index.php?module=Livestock&action=vaccinate")"
B="$(printf '%s' "$R" | sed '$d')"
contains "$B" "cannot be before the vaccination date" "next-due before vaccination rejected"

R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "livestock_id=1&vaccine_name=X&vaccination_date=2099-01-01" "$BASE/index.php?module=Livestock&action=vaccinate")"
B="$(printf '%s' "$R" | sed '$d')"
contains "$B" "cannot be in the future" "future vaccination date rejected"

# non-existent FK ids
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "supplier_id=999999&item_name=X&quantity=1&cost=1&purchase_date=2020-01-01" "$BASE/index.php?module=Procurement&action=add")"
B="$(printf '%s' "$R" | sed '$d')"
notcontains "$B" "Internal Server Error" "bad supplier id is not a 500"

# negative / text quantities & amounts
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "customer_name=X&amount=-5&sale_date=2020-01-01" "$BASE/index.php?module=Sales&action=add")"
B="$(printf '%s' "$R" | sed '$d')"
notcontains "$B" "Internal Server Error" "negative amount is not a 500"

# ---------------------------------------------------------------------------
# 4. Pest route redirects into Crop
# ---------------------------------------------------------------------------
echo "-- Pest redirect --"
S="$(status -b "$JAR" "$BASE/index.php?module=Pest")"
case "$S" in 301|302|303|307|308) ok "?module=Pest redirects ($S)";; *) bad "?module=Pest did not redirect (got $S)";; esac

# ---------------------------------------------------------------------------
# 4b. Phase 3 (B1–B7): new endpoints exist and never 500
# ---------------------------------------------------------------------------
echo "-- Phase 3 endpoints --"

# B1: the vaccine catalogue powers the Animal vaccination combo
R="$(bodycode -b "$JAR" "$BASE/index.php?module=Lookup&action=options&category=vaccine_name&parent=Cattle")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "Lookup vaccine_name -> 200" "$S" "200"
contains "$B" '"success"' "B1 vaccine catalogue responds with JSON"

# B2: finance category options depend on the record type
S="$(status -b "$JAR" "$BASE/index.php?module=Lookup&action=options&category=finance_category&parent=income")"
check "Lookup finance_category -> 200" "$S" "200"

# B3: a stock-out below zero is refused, never a 500
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "item_id=1&direction=out&quantity=999999&reason=Test" "$BASE/index.php?module=Inventory&action=move")"
B="$(printf '%s' "$R" | sed '$d')"
notcontains "$B" "Internal Server Error" "B3 stock-out below zero never 500s"

# B4: marking a health incident resolved never 500s
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "id=1&status=resolved" "$BASE/index.php?module=Crop&action=incidentStatus")"
case "$S" in 200|302|303) ok "B4 incident status accepted ($S)";; *) bad "B4 incident status (got $S)";; esac

# B5: Assistant "what needs attention today" responds
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "message=what needs attention today" "$BASE/index.php?module=Insights&action=chat")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "B5 assistant attention -> 200" "$S" "200"
contains "$B" '"success"' "B5 assistant returns JSON"

# B6: recording an equipment service never 500s
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "id=1&service_date=$(date +%F)" "$BASE/index.php?module=Equipment&action=recordService")"
case "$S" in 200|302|303) ok "B6 record service accepted ($S)";; *) bad "B6 record service (got $S)";; esac

# B7: a harvest pointing at a missing storage facility is refused, not a 500
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "crop_id=1&harvest_date=$(date +%F)&quantity=5&storage_record_id=999999" "$BASE/index.php?module=Harvest&action=add")"
B="$(printf '%s' "$R" | sed '$d')"
notcontains "$B" "Internal Server Error" "B7 harvest storage never 500s"

# ---------------------------------------------------------------------------
# 4c. Finish-pass Fix 1: inventory.quantity is authoritative.
#     Acceptance: (a) 120 -> out 50 -> 70; (b) out 71 refused, still 70;
#     (c) in 10 -> 80; (d) direction=sideways rejected, no row; (e) two parallel
#     stock-outs cannot overdraw; (f) low-stock badge/insight match the API
#     balance; (g) procurement of an existing item name increases it (no dup).
# ---------------------------------------------------------------------------
echo "-- Fix 1: stock balance honours opening quantity --"

# API helper: login for a JWT, then read the authoritative balance.
JWT="$(curl -s -X POST -H 'Content-Type: application/json' -d "{\"username\":\"$USER_NAME\",\"password\":\"$USER_PASS\"}" "$BASE/api/v1/auth/token" | grep -o '"token":"[^"]*"' | head -1 | sed 's/.*"token":"//; s/"$//')"
if [ -n "$JWT" ]; then ok "Fix1 API JWT issued"; else bad "Fix1 API JWT missing"; fi
apibal() { curl -s -H "Authorization: Bearer $JWT" "$BASE/api/v1/inventory-items/$IT_ID/balance"; }
apibal_num() { printf '%s' "$(apibal)" | grep -o '"balance":[0-9.]*' | sed 's/"balance"://; s/\.0$//'; }
inv_label() { curl -s -b "$JAR" "$BASE/index.php?module=Inventory&action=manage" | grep -F -- "$IT_NAME" | grep -F 'data-value=' | head -1 | grep -o '[0-9.]* bag' | tail -1 | sed 's/ bag//'; }

# create a dedicated item (opening quantity 120)
IT_NAME="BalTest$(date +%H%M%S)$RANDOM"
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "name=$IT_NAME" --data "quantity=120&unit=bag" "$BASE/index.php?module=Inventory&action=add")"
check "Fix1(a) create item -> redirect" "$S" "302"
PAGE="$(curl -s -b "$JAR" "$BASE/index.php?module=Inventory&action=manage")"
IT_ID="$(printf '%s' "$PAGE" | grep -F -- "$IT_NAME" | grep -o "data-value='[0-9]*'" | head -1 | grep -o '[0-9]*')"
if [ -n "$IT_ID" ]; then ok "Fix1(a) dedicated item created (id=$IT_ID)"; else bad "Fix1(a) could not find created item"; fi
check "Fix1(a) opening 120 is authoritative (API balance = 120)" "$(apibal_num)" "120"
check "Fix1(a) UI label shows 120 as well" "$(inv_label)" "120"

# (a) stock out 50 -> 70
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "item_id=$IT_ID&direction=out&quantity=50&reason=Test" "$BASE/index.php?module=Inventory&action=move")"
check "Fix1(a) out 50 -> redirect" "$S" "302"
check "Fix1(a) balance after out 50 -> 70" "$(apibal_num)" "70"
check "Fix1(a) UI label after out 50 -> 70" "$(inv_label)" "70"

# (b) stock out 71 refused, still 70
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "item_id=$IT_ID&direction=out&quantity=71&reason=Test" "$BASE/index.php?module=Inventory&action=move")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "Fix1(b) out 71 -> form re-render (200)" "$S" "200"
contains "$B" "Not enough stock" "Fix1(b) refusal message shown"
check "Fix1(b) balance still 70" "$(apibal_num)" "70"

# (c) stock in 10 -> 80
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "item_id=$IT_ID&direction=in&quantity=10&reason=Test" "$BASE/index.php?module=Inventory&action=move")"
check "Fix1(c) in 10 -> redirect" "$S" "302"
check "Fix1(c) balance after in 10 -> 80" "$(apibal_num)" "80"

# (d) sideways direction rejected, no row written
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "item_id=$IT_ID&direction=sideways&quantity=5&reason=Test" "$BASE/index.php?module=Inventory&action=move")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "Fix1(d) sideways -> form re-render (200)" "$S" "200"
contains "$B" "Direction must be" "Fix1(d) sideways rejected"
check "Fix1(d) balance unchanged at 80" "$(apibal_num)" "80"
H="$(curl -s -b "$JAR" "$BASE/index.php?module=Inventory&action=history&id=$IT_ID")"
notcontains "$H" "sideways" "Fix1(d) no sideways movement row written"

# (e) two parallel stock-outs of 45 against 80 cannot overdraw -> ends at 35
( curl -s -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "item_id=$IT_ID&direction=out&quantity=45&reason=Parallel" "$BASE/index.php?module=Inventory&action=move" > /tmp/ffms_c1.$$ ) &
P1=$!
( curl -s -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "item_id=$IT_ID&direction=out&quantity=45&reason=Parallel" "$BASE/index.php?module=Inventory&action=move" > /tmp/ffms_c2.$$ ) &
P2=$!
wait "$P1"; wait "$P2"
C1="$(cat /tmp/ffms_c1.$$)"; C2="$(cat /tmp/ffms_c2.$$)"
rm -f /tmp/ffms_c1.$$ /tmp/ffms_c2.$$
case "$C1$C2" in *"Not enough stock"*) ok "Fix1(e) one parallel stock-out refused";; *) bad "Fix1(e) neither parallel stock-out was refused";; esac
check "Fix1(e) final balance 35 (no overdraft)" "$(apibal_num)" "35"

# (f) low-stock reorder rule below current level -> badge, insight and API agree
R="$(curl -s -X POST -H "Authorization: Bearer $JWT" -H 'Content-Type: application/json' -d "{\"item_id\":$IT_ID,\"threshold_qty\":100,\"reorder_qty\":10}" "$BASE/api/v1/reorder-rules")"
contains "$R" '"success":true' "Fix1(f) reorder rule created"
R="$(curl -s -X POST -H "Authorization: Bearer $JWT" "$BASE/api/v1/reorder-check/run")"
contains "$R" '"balance":35' "Fix1(f) reorder check reads authoritative balance"
contains "$R" "$IT_NAME" "Fix1(f) reorder check flags the low-stock item"
PAGE="$(curl -s -b "$JAR" "$BASE/index.php?module=Inventory&action=manage")"
contains "$PAGE" "$IT_NAME" "Fix1(f) inventory page still lists item"
check "Fix1(f) UI badge number equals API balance (35)" "$(inv_label)" "35"
contains "$PAGE" "Low (min 100)" "Fix1(f) low-stock badge threshold shown"

# (g) procurement of the existing item name increases that item, no duplicate
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "supplier_id=1" --data-urlencode "item_name=${IT_NAME,,}" --data "quantity=5&cost=10&purchase_date=2020-01-01" "$BASE/index.php?module=Procurement&action=add")"
check "Fix1(g) procurement redirects" "$S" "302"
check "Fix1(g) balance after purchase of existing item -> 40" "$(apibal_num)" "40"
PAGE="$(curl -s -b "$JAR" "$BASE/index.php?module=Inventory&action=manage")"
# the closed picker lists each matched item exactly once
CNT="$(printf '%s' "$PAGE" | grep -F -- "$IT_NAME" | grep -F -c 'data-value=')"
check "Fix1(g) no duplicate inventory row for the item (count=1)" "$CNT" "1"

# ---------------------------------------------------------------------------
# 4d. Finish-pass Fix 2: "Report a problem" dropdowns render labels, not Array.
#     Acceptance: no >Array<, no numbered placeholder options, and picking an
#     issue saves the plain issue name rather than the "name (kind)" label.
# ---------------------------------------------------------------------------
echo "-- Fix 2: report-a-problem dropdowns --"
B="$(curl -s -b "$JAR" "$BASE/index.php?module=Crop&action=manage")"
notcontains "$B" '>Array<' "Fix2 crop page has no >Array<"
notcontains "$B" "value='0'>Pest" "Fix2 crop Type combo has no numbered options"
B="$(curl -s -b "$JAR" "$BASE/index.php?module=Livestock&action=manage")"
notcontains "$B" '>Array<' "Fix2 livestock page has no >Array<"
notcontains "$B" "value='0'>Disease" "Fix2 livestock Type combo has no numbered options"
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "crop_id=1" --data-urlencode "pest_name=Fall armyworm" --data "issue_kind=pest&severity=Moderate&detected_date=2020-01-01&action_taken=Scouting" "$BASE/index.php?module=Crop&action=addPest")"
check "Fix2 report problem -> redirect" "$S" "302"
B="$(curl -s -b "$JAR" "$BASE/index.php?module=Crop&action=manage")"
contains "$B" '>Fall armyworm<' "Fix2 saved issue name preserved (not the ' (kind)' label)"

# ---------------------------------------------------------------------------
# 4e. Finish-pass Fix 3: the equipment spare-part picker shows live quantity
#     (was selecting id,name,unit but reading ['quantity'] -> always 0).
# ---------------------------------------------------------------------------
echo "-- Fix 3: equipment spare-part picker --"
B="$(curl -s -b "$JAR" "$BASE/index.php?module=Equipment&action=manage")"
SP="$(printf '%s' "$B" | grep -F -- "$IT_NAME" | grep -o '[0-9.]* bag' | tail -1 | sed 's/ bag//')"
check "Fix3 spare picker shows live quantity (40)" "$SP" "40"
notcontains "$B" "Undefined array key" "Fix3 equipment page has no undefined-key warnings"

# ---------------------------------------------------------------------------
# 4f. Finish-pass Fix 4: storage facilities always carry a unit (kg default),
#     can be edited, and "Store in" harvest is refused cleanly on bad ids or
#     capacity (never a 500, never a partial stock change).
# ---------------------------------------------------------------------------
echo "-- Fix 4: storage unit + edit + harvest-to-storage --"
ST1="StorFix$(date +%H%M%S)$RANDOM"
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "name=$ST1" --data "capacity=100&current_stock=0" "$BASE/index.php?module=Storage&action=add")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "Fix4 storage add without unit -> 200 (form re-render)" "$S" "200"
contains "$B" "Unit is required." "Fix4 storage add demands a unit"
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "name=$ST1" --data "capacity=100&current_stock=0&unit=kg" "$BASE/index.php?module=Storage&action=add")"
S="$(printf '%s' "$R" | tail -1)"
check "Fix4 storage add with unit -> 302" "$S" "302"
STPG="$(curl -s -b "$JAR" "$BASE/index.php?module=Storage&action=manage")"
contains "$STPG" ">${ST1}<: 0 / 100 kg" "Fix4 facility created with kg unit"
SID="$(printf '%s' "$STPG" | awk -v n="$ST1" '/name="id" value="[0-9]+"/{id=$0} $0 ~ ("value=\"" n "\""){gsub(/[^0-9]/,"",id); print id; exit}')"
[ -n "$SID" ] && ok "Fix4 located storage facility id" || bad "Fix4 storage facility id missing"

CR_ID="$(printf '%s' "$(curl -s -b "$JAR" "$BASE/index.php?module=Harvest&action=manage")" | grep -o "data-value='[0-9]*'" | head -1 | grep -o '[0-9]*')"
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "crop_id=$CR_ID&harvest_date=2020-01-01&quantity=60&storage_record_id=$SID" "$BASE/index.php?module=Harvest&action=add")"
S="$(printf '%s' "$R" | tail -1)"
check "Fix4 harvest into storage -> 302" "$S" "302"
STPG="$(curl -s -b "$JAR" "$BASE/index.php?module=Storage&action=manage")"
contains "$STPG" ">${ST1}<: 60 / 100 kg" "Fix4 harvest 60kg lands in storage"

ST2="StorFixB$(date +%H%M%S)$RANDOM"
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "name=$ST2" --data "capacity=100&current_stock=0&unit=tonnes" "$BASE/index.php?module=Storage&action=add")"
check "Fix4 storage B add -> 302" "$(printf '%s' "$R" | tail -1)" "302"
STPG="$(curl -s -b "$JAR" "$BASE/index.php?module=Storage&action=manage")"
contains "$STPG" ">${ST2}<: 0 / 100 tonnes" "Fix4 facility B tonne unit"
SID2="$(printf '%s' "$STPG" | awk -v n="$ST2" '/name="id" value="[0-9]+"/{id=$0} $0 ~ ("value=\"" n "\""){gsub(/[^0-9]/,"",id); print id; exit}')"
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "crop_id=$CR_ID&harvest_date=2020-01-01&quantity=150&storage_record_id=$SID2" "$BASE/index.php?module=Harvest&action=add")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "Fix4 over-capacity harvest -> 200 (refused)" "$S" "200"
contains "$B" "That would exceed" "Fix4 capacity refusal message"
STPG="$(curl -s -b "$JAR" "$BASE/index.php?module=Storage&action=manage")"
contains "$STPG" ">${ST2}<: 0 / 100 tonnes" "Fix4 refused harvest did not change stock"
notcontains "$STPG" "Internal Server Error" "Fix4 over-capacity is not a 500"

R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "crop_id=$CR_ID&harvest_date=2020-01-01&quantity=5&storage_record_id=999999" "$BASE/index.php?module=Harvest&action=add")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "Fix4 unknown storage id -> 200 (refused)" "$S" "200"
contains "$B" "Storage facility not found." "Fix4 unknown storage id refused cleanly"
notcontains "$B" "Internal Server Error" "Fix4 unknown storage id is not a 500"

R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "id=$SID&name=${ST1}Renamed&capacity=200&unit=tonnes" "$BASE/index.php?module=Storage&action=edit")"
S="$(printf '%s' "$R" | tail -1)"
check "Fix4 storage edit -> 302" "$S" "302"
STPG="$(curl -s -b "$JAR" "$BASE/index.php?module=Storage&action=manage")"
contains "$STPG" ">${ST1}Renamed<: 60 / 200 tonnes" "Fix4 storage edit updates name/capacity/unit"

# ---------------------------------------------------------------------------
# 4g. Finish-pass Fix 5: a vaccine is only accepted for its species, a batch
#     is one species at a time (atomic), and single-dose vaccines (Marek's)
#     never carry a next due date.
# ---------------------------------------------------------------------------
echo "-- Fix 5: vaccine <-> species checks --"
RAND5="$(date +%H%M%S)$RANDOM"
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "farm_id=$FARM_ID" --data-urlencode "type=Cattle" --data-urlencode "breed=QCattle$RAND5" --data-urlencode "dob=2020-01-01" "$BASE/index.php?module=Livestock&action=add")"
check "Fix5 create cattle -> 302" "$S" "302"
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "farm_id=$FARM_ID" --data-urlencode "type=Goats" --data-urlencode "breed=QGoat$RAND5" --data-urlencode "dob=2020-01-01" "$BASE/index.php?module=Livestock&action=add")"
check "Fix5 create goat -> 302" "$S" "302"
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "farm_id=$FARM_ID" --data-urlencode "type=Poultry" --data-urlencode "breed=QPoultry$RAND5" --data-urlencode "dob=2020-01-01" "$BASE/index.php?module=Livestock&action=add")"
check "Fix5 create poultry -> 302" "$S" "302"
LX="$(curl -s -b "$JAR" "$BASE/index.php?module=Livestock&action=manage")"
CID="$(printf '%s' "$LX" | grep -o "#[0-9]* — Cattle QCattle$RAND5" | head -1 | grep -o '[0-9]*')"
GID="$(printf '%s' "$LX" | grep -o "#[0-9]* — Goats QGoat$RAND5" | head -1 | grep -o '[0-9]*')"
PID="$(printf '%s' "$LX" | grep -o "#[0-9]* — Poultry QPoultry$RAND5" | head -1 | grep -o '[0-9]*')"
[ -n "$CID" ] && ok "Fix5 cattle id found" || bad "Fix5 cattle id missing"
[ -n "$GID" ] && ok "Fix5 goat id found" || bad "Fix5 goat id missing"
[ -n "$PID" ] && ok "Fix5 poultry id found" || bad "Fix5 poultry id missing"

R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "livestock_id=$CID" --data-urlencode "vaccine_name=Newcastle (NCD) vaccine" --data-urlencode "vaccination_date=2020-01-01" "$BASE/index.php?module=Livestock&action=vaccinate")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "Fix5 cattle + Newcastle -> 200 (refused)" "$S" "200"
contains "$B" "Newcastle (NCD) vaccine is for Poultry" "Fix5 wrong-species message"
contains "$B" "not this Cattle" "Fix5 wrong-species names the animal"
notcontains "$B" "Internal Server Error" "Fix5 species mismatch not a 500"
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "livestock_id=$GID" --data-urlencode "vaccine_name=Newcastle (NCD) vaccine" --data-urlencode "vaccination_date=2020-01-01" "$BASE/index.php?module=Livestock&action=vaccinate")"
check "Fix5 goat + Newcastle -> 200 (refused)" "$(printf '%s' "$R" | tail -1)" "200"
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "livestock_id=$GID" --data-urlencode "vaccine_name=PPR vaccine" --data-urlencode "vaccination_date=2020-01-01" "$BASE/index.php?module=Livestock&action=vaccinate")"
check "Fix5 goat + PPR -> 302" "$(printf '%s' "$R" | tail -1)" "302"

LX="$(curl -s -b "$JAR" "$BASE/index.php?module=Livestock&action=manage")"
N1="$(printf '%s' "$LX" | grep -F -c '>PPR vaccine<')"
R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "animal_ids[]=$CID" --data-urlencode "animal_ids[]=$GID" --data-urlencode "vaccine_name=PPR vaccine" --data-urlencode "vaccination_date=2020-01-02" "$BASE/index.php?module=Livestock&action=batchVaccinate")"
S="$(printf '%s' "$R" | tail -1)"; B="$(printf '%s' "$R" | sed '$d')"
check "Fix5 mixed-species batch -> 200 (refused)" "$S" "200"
contains "$B" "One vaccine batch = one species" "Fix5 mixed batch refused"
notcontains "$B" "Internal Server Error" "Fix5 mixed batch not a 500"
LX="$(curl -s -b "$JAR" "$BASE/index.php?module=Livestock&action=manage")"
N2="$(printf '%s' "$LX" | grep -F -c '>PPR vaccine<')"
check "Fix5 refused batch inserted nothing (PPR count unchanged)" "$N2" "$N1"

R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "animal_ids[]=$CID" --data-urlencode "vaccine_name=PPR vaccine" --data-urlencode "vaccination_date=2020-01-02" "$BASE/index.php?module=Livestock&action=batchVaccinate")"
check "Fix5 cattle-only batch + PPR -> 200 (refused)" "$(printf '%s' "$R" | tail -1)" "200"

R="$(bodycode -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "animal_ids[]=$PID" --data-urlencode "vaccine_name=Marek's vaccine" --data-urlencode "vaccination_date=2020-01-03" --data-urlencode "next_due_date=2021-06-01" "$BASE/index.php?module=Livestock&action=batchVaccinate")"
check "Fix5 poultry batch + Marek's -> 302" "$(printf '%s' "$R" | tail -1)" "302"
LX="$(curl -s -b "$JAR" "$BASE/index.php?module=Livestock&action=manage")"
contains "$LX" "Marek's vaccine" "Fix5 Marek's recorded"
contains "$LX" "No next date" "Fix5 single-dose Marek's has no next due date"

# ---------------------------------------------------------------------------
# 4i. Finish-pass Fix 6: service reminders — the interval is defaulted from the
#     equipment type server-side, an overdue item is flagged, and recording a
#     service recomputes (and clears) the schedule.
# ---------------------------------------------------------------------------
echo "-- Fix 6: equipment service reminders --"
EQN="EQFix$(date +%H%M%S)$RANDOM"
EQPUR="$(date -d '300 days ago' +%F)"
EQSRV="$(date -d 'yesterday' +%F)"
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "name=$EQN" --data-urlencode "status=Operational" --data-urlencode "equipment_type=Tractor" --data-urlencode "purchase_date=$EQPUR" "$BASE/index.php?module=Equipment&action=add")"
check "Fix6 add tractor (no interval) -> 302" "$S" "302"
LX="$(curl -s -b "$JAR" "$BASE/index.php?module=Equipment&action=manage")"
BLK="$(printf '%s' "$LX" | grep -A45 -F -- ">$EQN<" | head -46)"
contains "$BLK" "Serviced every 90 days" "Fix6 interval defaulted from type"
contains "$BLK" "Service overdue" "Fix6 overdue tractor flagged"
EID="$(printf '%s' "$BLK" | grep -o 'name="id" value="[0-9]*"' | head -1 | grep -o '[0-9]*')"
[ -n "$EID" ] && ok "Fix6 equipment id found" || bad "Fix6 equipment id missing"
S="$(status -b "$JAR" --data-urlencode "csrf_token=$TOKEN" --data "id=$EID" --data-urlencode "service_date=$EQSRV" "$BASE/index.php?module=Equipment&action=recordService")"
check "Fix6 record service -> 302" "$S" "302"
LX="$(curl -s -b "$JAR" "$BASE/index.php?module=Equipment&action=manage")"
BLK="$(printf '%s' "$LX" | grep -A45 -F -- ">$EQN<" | head -46)"
notcontains "$BLK" "Service overdue" "Fix6 overdue badge cleared after service"
contains "$BLK" "Service OK" "Fix6 schedule recomputed after service"

# ---------------------------------------------------------------------------
# 5. Page sweep: every module x view returns 200
# ---------------------------------------------------------------------------
echo "-- page sweep --"
for url in \
  "module=Dashboard" \
  "module=Crop&action=manage" \
  "module=Livestock&action=manage" \
  "module=Finance&action=manage" \
  "module=Finance&action=loans" \
  "module=Finance&action=budgets" \
  "module=Sales&action=manage" \
  "module=Procurement&action=manage" \
  "module=Inventory&action=manage" \
  "module=Harvest&action=manage" \
  "module=Equipment&action=manage" \
  "module=Storage&action=manage" \
  "module=Irrigation&action=manage" \
  "module=Weather&action=manage" \
  "module=Market&action=manage" \
  "module=Supplier&action=manage" \
  "module=Field&action=manage" \
  "module=Farm&action=manage" \
  "module=Notifications&action=manage" \
  "module=Notifications&action=rules" \
  "module=Labour" \
  "module=Labour&subsection=employees" \
  "module=Labour&subsection=leave" \
  "module=Labour&subsection=shifts" \
  "module=Labour&subsection=payroll" \
  "module=Labour&subsection=training" \
  "module=Labour&subsection=grievances" \
  "module=Labour&subsection=departments" \
  "module=Security&action=change_password" \
  "module=Security&action=users" \
  "module=Security&action=audit" \
  "module=Reports" \
  "module=Analytics" \
  "module=Insights" \
  ; do
  S="$(status -b "$JAR" "$BASE/index.php?$url")"
  check "GET $url -> 200" "$S" "200"
done

# ---------------------------------------------------------------------------
# 6. Optional: no new PHP warnings (when PHP_LOG points at the server log)
# ---------------------------------------------------------------------------
if [ -n "$PHP_LOG" ] && [ -f "$PHP_LOG" ]; then
  echo "-- PHP log scan --"
  if grep -q "PHP Warning\|PHP Fatal" "$PHP_LOG"; then bad "PHP warnings/fatals found in $PHP_LOG"; else ok "no PHP warnings/fatals in $PHP_LOG"; fi
fi

rm -f "$JAR"
echo
echo "== $PASS passed, $FAIL failed =="
[ "$FAIL" -eq 0 ]
