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
