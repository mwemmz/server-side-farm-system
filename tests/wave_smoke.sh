#!/usr/bin/env bash
# Wave 1-4 smoke test for the new API endpoints (local server only).
set -u
BASE="${BASE:-http://localhost:8000}"
USER_NAME="${FFMS_USER:-admin}"
USER_PASS="${FFMS_PASS:-admin123}"
JAR="$(mktemp)"
PASS=0; FAIL=0
ok() { PASS=$((PASS+1)); echo "  PASS $1"; }
bad() { FAIL=$((FAIL+1)); echo "  FAIL $1"; }
check() { if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 (expected $3, got $2)"; fi; }

# login for JWT
JWT="$(curl -s -X POST -H 'Content-Type: application/json' -d "{\"username\":\"$USER_NAME\",\"password\":\"$USER_PASS\"}" "$BASE/api/v1/auth/token" | grep -o '"token":"[^"]*"' | head -1 | sed 's/.*"token":"//; s/"$//')"
if [ -n "$JWT" ]; then ok "JWT issued"; else bad "JWT missing"; echo "== $PASS passed, $FAIL failed =="; exit 1; fi
H="Authorization: Bearer $JWT"

# 1A analytics profit
for ep in analytics/profit/by-field analytics/profit/by-animal; do
  S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" "$BASE/api/v1/$ep")"
  check "GET $ep -> 200" "$S" "200"
done

# 1B farm connections
S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" "$BASE/api/v1/extranet/connections")"
check "GET extranet/connections -> 200" "$S" "200"
S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" "$BASE/api/v1/extranet/farms/search?q=a")"
check "GET extranet/farms/search -> 200" "$S" "200"

# 1C intranet
S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" "$BASE/api/v1/intranet/conversations")"
check "GET intranet/conversations -> 200" "$S" "200"
# create conversation
CID="$(curl -s -H "$H" -H 'Content-Type: application/json' -d '{"title":"smoke","members":[]}' "$BASE/api/v1/intranet/conversations" | grep -o '"conversation_id":[0-9]*' | grep -o '[0-9]*')"
if [ -n "$CID" ]; then ok "intranet conversation created (id=$CID)"; else bad "intranet conversation create failed"; fi
if [ -n "$CID" ]; then
  S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" -H 'Content-Type: application/json' -d '{"body":"hello"}' "$BASE/api/v1/intranet/conversations/$CID/messages")"
  check "POST intranet message -> 200" "$S" "200"
  S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" "$BASE/api/v1/intranet/conversations/$CID/messages")"
  check "GET intranet messages -> 200" "$S" "200"
fi

# 2A extranet (no connection -> create should be 403)
S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" -H 'Content-Type: application/json' -d '{"title":"x","members":[9999]}' "$BASE/api/v1/extranet/conversations")"
if [ "$S" = "403" ] || [ "$S" = "422" ]; then ok "extranet create without connection rejected ($S)"; else bad "extranet create without connection (got $S)"; fi

# 2B credit passport
S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" "$BASE/api/v1/credit-passport/me")"
check "GET credit-passport/me -> 200" "$S" "200"
SHARE="$(curl -s -H "$H" -H 'Content-Type: application/json' -d '{"scope":["score"],"expires_days":7}' "$BASE/api/v1/credit-passport/share" | grep -o '"token":"[^"]*"' | head -1 | sed 's/.*"token":"//; s/"$//')"
if [ -n "$SHARE" ]; then ok "credit passport share created"; else bad "credit passport share failed"; fi
if [ -n "$SHARE" ]; then
  S="$(curl -s -o /dev/null -w '%{http_code}' "$BASE/index.php?module=CreditPassport&token=$SHARE")"
  check "public credit passport page -> 200" "$S" "200"
fi

# 3A traceability
BC="SMOKE$(date +%s)"
S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" -H 'Content-Type: application/json' -d "{\"batch_code\":\"$BC\",\"quantity\":5,\"unit\":\"kg\"}" "$BASE/api/v1/traceability/batches")"
check "POST traceability/batches -> 200" "$S" "200"
BID="$(curl -s -H "$H" -H 'Content-Type: application/json' -d "{\"batch_code\":\"$BC\",\"quantity\":5,\"unit\":\"kg\"}" "$BASE/api/v1/traceability/batches" | grep -o '"id":[0-9]*' | head -1 | grep -o '[0-9]*')"
if [ -n "$BID" ]; then
  S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" -H 'Content-Type: application/json' -d '{"event_type":"storage","location":"shed"}' "$BASE/api/v1/traceability/batches/$BID/events")"
  check "POST batch event -> 200" "$S" "200"
  S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" "$BASE/api/v1/traceability/batches/$BID")"
  check "GET batch -> 200" "$S" "200"
  S="$(curl -s -o /dev/null -w '%{http_code}' "$BASE/index.php?module=Traceability&action=public&code=$BC")"
  check "public trace view -> 200" "$S" "200"
fi

# 3B assistant summarise/draft
if [ -n "$CID" ]; then
  S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" -X POST "$BASE/api/v1/assistant/summarise/$CID")"
  check "POST assistant/summarise -> 200" "$S" "200"
  S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" -X POST "$BASE/api/v1/assistant/draft-reply/$CID")"
  check "POST assistant/draft-reply -> 200" "$S" "200"
fi

# 4B planting what-if
S="$(curl -s -o /dev/null -w '%{http_code}' -H "$H" -H 'Content-Type: application/json' -d '{"crop_id":1,"planting_month":3,"area_ha":2,"input_cost_estimate":100}' "$BASE/api/v1/planner/planting-what-if")"
check "POST planner/planting-what-if -> 200" "$S" "200"

# 1A report export
S="$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/index.php?module=Reports&action=export&dataset=profit_by_field")"
check "report export profit_by_field -> 200" "$S" "200"

rm -f "$JAR"
echo "== $PASS passed, $FAIL failed =="
[ "$FAIL" -eq 0 ]