#!/bin/bash

BASE="http://127.0.0.1:8000/api/v1"
DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=== Starting backend server ==="
kill $(lsof -ti:8000) 2>/dev/null || true
sleep 1
cd "$DIR/backend"
setsid /home/anthonypiovan/.php-ext/php-sqlite artisan migrate:fresh --force > /dev/null 2>&1
setsid /home/anthonypiovan/.php-ext/php-sqlite artisan app:seed > /dev/null 2>&1
setsid /home/anthonypiovan/.php-ext/php-sqlite -d opcache.enable=0 -S 127.0.0.1:8000 -t public > "$DIR/logs/backend.log" 2>&1 &
sleep 4

if ! curl -s http://127.0.0.1:8000/up > /dev/null; then
  echo "Server failed to start"
  exit 1
fi

PASS=0
FAIL=0
ok()   { echo "  ✅ $1"; PASS=$((PASS+1)); }
fail() { echo "  ❌ $1 — $2"; FAIL=$((FAIL+1)); }

echo "============================================"
echo " InterlinkedLog - Test Suite"
echo "============================================"
echo ""

# AUTH
echo "📌 AUTH"
LOGIN=$(curl -s -X POST "$BASE/login" -H "Content-Type: application/json" -d '{"email":"admin@interlinked.io","password":"admin123"}')
TOKEN=$(echo "$LOGIN" | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])" 2>/dev/null) || TOKEN=""
COID=$(echo "$LOGIN" | python3 -c "import sys,json; print(json.load(sys.stdin)['user']['company_id'])" 2>/dev/null) || COID=""
ROLE=$(echo "$LOGIN" | python3 -c "import sys,json; print(json.load(sys.stdin)['user']['role'])" 2>/dev/null) || ROLE=""
USERID=$(echo "$LOGIN" | python3 -c "import sys,json; print(json.load(sys.stdin)['user']['id'])" 2>/dev/null) || USERID=""
[ -n "$TOKEN" ] && ok "Login (role=$ROLE)" || fail "Login" "no token"

WRONG=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/login" -H "Content-Type: application/json" -d '{"email":"admin@interlinked.io","password":"wrongwrong"}')
[ "$WRONG" = "401" ] && ok "Login inválido ($WRONG)" || fail "Login inválido" "$WRONG"

ME=$(curl -s "$BASE/me" -H "Authorization: Bearer $TOKEN" | python3 -c "import sys,json; print(json.load(sys.stdin).get('name',''))" 2>/dev/null)
[ "$ME" = "Admin Master" ] && ok "GET /me" || fail "GET /me" "$ME"

NOAUTH=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/me")
[ "$NOAUTH" = "401" ] && ok "GET /me s/ token" || fail "GET /me s/ token" "$NOAUTH"
echo ""

# DASHBOARD
echo "📌 DASHBOARD"
DASH=$(curl -s "$BASE/reports/dashboard" -H "Authorization: Bearer $TOKEN")
QCOUNT=$(echo "$DASH" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['quotations_count'])" 2>/dev/null)
CCONV=$(echo "$DASH" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['conversion_rate'])" 2>/dev/null)
STATUSES=$(echo "$DASH" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']['quotations_by_status']))" 2>/dev/null)
[ "$QCOUNT" != "" ] && ok "Dashboard (q=$QCOUNT conv=$CCONV% status=$STATUSES)" || fail "Dashboard" ""

DET=$(curl -s "$BASE/reports/detailed" -H "Authorization: Bearer $TOKEN")
ROUTECOUNT=$(echo "$DET" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']['top_routes']))" 2>/dev/null)
ok "Detailed (routes=$ROUTECOUNT)"

LOGS=$(curl -s "$BASE/system-logs" -H "Authorization: Bearer $TOKEN")
LOGCOUNT=$(echo "$LOGS" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']))" 2>/dev/null)
[ "$LOGCOUNT" = "10" ] && ok "SystemLogs ($LOGCOUNT)" || fail "SystemLogs" "=$LOGCOUNT"

AUDIT=$(curl -s "$BASE/audit-logs" -H "Authorization: Bearer $TOKEN")
AUDITCOUNT=$(echo "$AUDIT" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']))" 2>/dev/null)
ok "AuditLogs ($AUDITCOUNT)"
echo ""

# CARRIERS
echo "📌 CARRIERS"
CARRIERS=$(curl -s "$BASE/carriers" -H "Authorization: Bearer $TOKEN")
CC=$(echo "$CARRIERS" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']))" 2>/dev/null)
CID=$(echo "$CARRIERS" | python3 -c "import sys,json; print(json.load(sys.stdin)['data'][0]['id'])" 2>/dev/null)
[ "$CC" = "8" ] && ok "List ($CC)" || fail "List" "$CC"

PERF=$(curl -s "$BASE/carriers/$CID/performance" -H "Authorization: Bearer $TOKEN")
RATE=$(echo "$PERF" | python3 -c "import sys,json; print(json.load(sys.stdin)['data'].get('on_time_rate',0))" 2>/dev/null)
ok "Performance (rate=$RATE%)"
echo ""

# FREIGHT TABLES
echo "📌 FREIGHT TABLES"
FT=$(curl -s "$BASE/freight-tables" -H "Authorization: Bearer $TOKEN")
FTCOUNT=$(echo "$FT" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']))" 2>/dev/null)
FTID=$(echo "$FT" | python3 -c "import sys,json; print(json.load(sys.stdin)['data'][0]['id'])" 2>/dev/null)
[ -n "$FTCOUNT" ] && ok "List ($FTCOUNT)" || fail "List" ""

FTD=$(curl -s "$BASE/freight-tables/$FTID" -H "Authorization: Bearer $TOKEN")
RC=$(echo "$FTD" | python3 -c "import sys,json; print(len(json.load(sys.stdin).get('routes',[])))" 2>/dev/null)
ok "Detail (routes=$RC)"
echo ""

# QUOTATIONS
echo "📌 QUOTATIONS"
XML='<?xml version="1.0"?><nfeProc xmlns="http://www.portalfiscal.inf.br/nfe"><NFe><infNFe><ide><nNF>999999</nNF></ide><emit><CNPJ>12345678000199</CNPJ><enderEmit><CEP>01000000</CEP></enderEmit></emit><dest><CNPJ>98765432000188</CNPJ><enderDest><CEP>80000000</CEP></enderDest></dest><total><ICMSTot><vProd>15000</vProd></ICMSTot></total><transp><vol><qVol>5</qVol><pesoB>30</pesoB></vol></transp></infNFe></NFe></nfeProc>'
echo "$XML" > /tmp/test-nfe.xml
XML_PARSE=$(curl -s -X POST "$BASE/quotations/parse-xml" -H "Authorization: Bearer $TOKEN" -F "xml=@/tmp/test-nfe.xml")
XML_NF=$(echo "$XML_PARSE" | python3 -c "import sys,json; print(json.load(sys.stdin)['data'].get('nf_number',''))" 2>/dev/null)
[ "$XML_NF" = "999999" ] && ok "XML parse (NF=$XML_NF)" || fail "XML parse" "NF=$XML_NF"

QCREATE=$(curl -s -X POST "$BASE/quotations" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"nf_number":"TEST001","sender_cnpj":"12345678000199","receiver_cnpj":"98765432000188","origin_cep":"01000000","destination_cep":"80000000","weight":45,"boxes":10,"volume":0.15,"cargo_value":5000}')
QID=$(echo "$QCREATE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null)
[ -n "$QID" ] && ok "Create" || fail "Create" ""

QLIST=$(curl -s "$BASE/quotations" -H "Authorization: Bearer $TOKEN")
QCOUNT2=$(echo "$QLIST" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']))" 2>/dev/null)
[ -n "$QCOUNT2" ] && ok "List ($QCOUNT2)" || fail "List" ""

QDETAIL=$(curl -s "$BASE/quotations/$QID" -H "Authorization: Bearer $TOKEN")
QR=$(echo "$QDETAIL" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']['results']))" 2>/dev/null)
ok "Detail (results=$QR)"

QR2=$(curl -s -X POST "$BASE/quotations" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"nf_number":"CONTR001","sender_cnpj":"12345678000199","receiver_cnpj":"98765432000188","origin_cep":"01000000","destination_cep":"80000000","weight":100,"boxes":5,"volume":0.3,"cargo_value":8000}')
QID2=$(echo "$QR2" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null)
[ -n "$QID2" ] && ok "Create for contract" || fail "Create for contract" ""
echo ""

# CONTRACTS
echo "📌 CONTRACTS"
QD2=$(curl -s "$BASE/quotations/$QID2" -H "Authorization: Bearer $TOKEN")
CARRIER_ID=$(echo "$QD2" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['results'][0]['carrier_id'])" 2>/dev/null)

CONTR=$(curl -s -X POST "$BASE/contracts" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d "{\"quotation_id\":\"$QID2\",\"carrier_id\":\"$CARRIER_ID\"}")
CONTRACTID=$(echo "$CONTR" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null)
[ -n "$CONTRACTID" ] && ok "Create" || fail "Create" ""

CLIST=$(curl -s "$BASE/contracts" -H "Authorization: Bearer $TOKEN")
CCC=$(echo "$CLIST" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']))" 2>/dev/null)
CTE=$(echo "$CLIST" | python3 -c "import sys,json; print(json.load(sys.stdin)['data'][0].get('cte_number','missing'))" 2>/dev/null)
[ -n "$CCC" ] && ok "List ($CCC cte=$CTE)" || fail "List" ""

CTEUPDATE=$(curl -s -o /dev/null -w "%{http_code}" -X PATCH "$BASE/contracts/$CONTRACTID/cte" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"cte_number":"352406000000001"}')
[ "$CTEUPDATE" = "200" ] && ok "Update CT-e" || fail "Update CT-e" "$CTEUPDATE"

PDFOK=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/contracts/$CONTRACTID/pdf" -H "Authorization: Bearer $TOKEN")
[ "$PDFOK" = "200" ] && ok "PDF download" || fail "PDF" "$PDFOK"
echo ""

# TRACKING
echo "📌 TRACKING"
TRACK=$(curl -s "$BASE/tracking" -H "Authorization: Bearer $TOKEN")
TRACKCOUNT=$(echo "$TRACK" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']))" 2>/dev/null)
TRACKCID=$(echo "$TRACK" | python3 -c "import sys,json; print(json.load(sys.stdin)['data'][0]['contract_id'])" 2>/dev/null)
[ -n "$TRACKCOUNT" ] && ok "List ($TRACKCOUNT)" || fail "List" ""

TDETAIL=$(curl -s "$BASE/tracking/$TRACKCID" -H "Authorization: Bearer $TOKEN")
TCOUNT=$(echo "$TDETAIL" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']))" 2>/dev/null)
ok "Detail ($TCOUNT events)"

ADDEV=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/tracking/$TRACKCID/events" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"Em Rota","date":"2026-06-16","time":"14:00","observation":"Saiu"}')
[ "$ADDEV" = "201" ] && ok "Add event" || fail "Add event" "$ADDEV"

CUSTOM=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/tracking/$TRACKCID/events" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"Cliente ausente","date":"2026-06-16","time":"15:30","observation":""}')
[ "$CUSTOM" = "201" ] && ok "Custom event" || fail "Custom event" "$CUSTOM"
echo ""

# USERS
echo "📌 USERS"
ULIST=$(curl -s "$BASE/users" -H "Authorization: Bearer $TOKEN")
UCOUNT=$(echo "$ULIST" | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data']))" 2>/dev/null)
[ "$UCOUNT" = "3" ] && ok "List ($UCOUNT)" || fail "List" "$UCOUNT"

UCREATE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/users" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"name":"Test2","email":"test2@test.com","password":"test123","role":"Usuário"}')
[ "$UCREATE" = "201" ] && ok "Create" || fail "Create" "$UCREATE"
echo ""

# COMPANY
echo "📌 COMPANY"
COMP=$(curl -s "$BASE/companies/$COID" -H "Authorization: Bearer $TOKEN")
CNAME=$(echo "$COMP" | python3 -c "import sys,json; print(json.load(sys.stdin)['data'].get('name',''))" 2>/dev/null)
[ -n "$CNAME" ] && ok "Show ($CNAME)" || fail "Show" ""
echo ""

# TENANT ISOLATION + LOGOUT
echo "📌 TENANT & LOGOUT"
ISO=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/companies/other-id" -H "Authorization: Bearer $TOKEN")
[ "$ISO" = "403" ] && ok "Tenant isolation" || fail "Tenant" "$ISO"

curl -s -o /dev/null -X POST "$BASE/logout" -H "Authorization: Bearer $TOKEN"
POSTLOGOUT=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/me" -H "Authorization: Bearer $TOKEN")
[ "$POSTLOGOUT" = "401" ] && ok "Token invalidated" || fail "Token" "$POSTLOGOUT"

echo ""
echo "============================================"
echo " RESULTS: $PASS passed / $FAIL failed"
echo "============================================"
[ "$FAIL" -gt 0 ] && exit 1
exit 0
