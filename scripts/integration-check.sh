#!/bin/bash
# Integração contra a stack real (docker compose). Rode antes de deploy e
# após mexer em rede/proxy/trustProxies.
#
#   docker compose up -d
#   ./scripts/integration-check.sh
#
# Cobre: E2E (login -> cotação -> contratação), isolamento de throttle por
# cliente via :3000 (2 origens), e forja de X-Forwarded-For em requisição
# direta. Exit code != 0 quando algo regredir. Idempotente: zera os
# contadores de throttle no início (mantém os tokens de sessão).

set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR/.."

BASE="http://localhost:3000/api/v1"
PASS=0
FAIL=0
ok()   { echo "  OK   $1"; PASS=$((PASS+1)); }
fail() { echo "  FAIL $1 — $2"; FAIL=$((FAIL+1)); }

echo "=== Integração InterlinkedLog ==="

# As chaves do rate limiter sao hasheadas (md5 de 32 hex, com sufixo :timer)
# pelo Laravel 11.50+; os tokens de sessao ficam em texto (user_token:...).
docker compose exec -T mysql sh -c \
    'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "DELETE FROM cache WHERE \`key\` REGEXP \"^laravel_cache_[0-9a-f]{32}(:timer)?$\";"' > /dev/null 2>&1

echo "--- E2E via :3000 ---"
LOGIN=$(curl -s -X POST "$BASE/login" -H "Content-Type: application/json" \
    -d '{"email":"admin@interlinked.io","password":"admin123"}')
TOKEN=$(echo "$LOGIN" | python3 -c "
import sys,json
try:
    print(json.load(sys.stdin).get('token',''))
except Exception:
    print('')
")
if [ -n "$TOKEN" ]; then ok "login"; else fail "login" "$LOGIN"; fi

Q=$(curl -s -X POST "$BASE/quotations" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
    -d '{"nf_number":"INT001","sender_cnpj":"12.345.678/0001-99","receiver_cnpj":"98.765.432/0001-88","origin_cep":"01000-000","destination_cep":"86020-000","weight":45,"boxes":10,"volume":0.15,"cargo_value":5000}')
QID=$(echo "$Q" | python3 -c "
import sys,json
try:
    d=json.load(sys.stdin)
    print(d.get('data',{}).get('id',''))
except Exception:
    print('')
")
NRESULTS=$(echo "$Q" | python3 -c "
import sys,json
try:
    d=json.load(sys.stdin)
    print(len(d.get('data',{}).get('results',[])))
except Exception:
    print('0')
")
if [ -n "$QID" ] && [ "${NRESULTS:-0}" -ge 1 ]; then ok "cotação ($NRESULTS resultados)"; else fail "cotação" "$Q"; fi

CARRIER=$(echo "$Q" | python3 -c "
import sys,json
try:
    print(json.load(sys.stdin)['data']['results'][0]['carrier_id'])
except Exception:
    print('')
")
C=$(curl -s -X POST "$BASE/contracts" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
    -d "{\"quotation_id\":\"$QID\",\"carrier_id\":\"$CARRIER\"}")
CID=$(echo "$C" | python3 -c "
import sys,json
try:
    print(json.load(sys.stdin).get('data',{}).get('id',''))
except Exception:
    print('')
")
if [ -n "$CID" ]; then ok "contratação"; else fail "contratação" "$C"; fi

echo "--- Isolamento de throttle por cliente (via :3000) ---"
# O login E2E já consumiu 1 tentativa do balde do host; esgota o resto.
for _ in 1 2 3 4 5; do
    curl -s -o /dev/null -X POST "$BASE/login" -H "Content-Type: application/json" \
        -d '{"email":"admin@interlinked.io","password":"errada"}'
done
CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/login" -H "Content-Type: application/json" \
    -d '{"email":"admin@interlinked.io","password":"errada"}')
if [ "$CODE" = "429" ]; then ok "balde do host esgotado (429)"; else fail "balde do host" "esperado 429, veio $CODE"; fi

OTHER=$(docker compose exec -T backend sh -c \
    'curl -s -o /dev/null -w "%{http_code}" -X POST http://frontend:3000/api/v1/login -H "Content-Type: application/json" -d "{\"email\":\"marina@interlinked.io\",\"password\":\"admin123\"}"' \
    | tr -d '\r\n')
if [ "$OTHER" = "200" ]; then ok "login de outra origem via :3000 (200)"; else fail "outra origem" "esperado 200, veio $OTHER"; fi

echo "--- Forja de X-Forwarded-For (direto no backend) ---"
BIP=$(docker compose exec -T backend sh -c 'hostname -i' | tr -d ' \r\n')
for i in 1 2 3 4 5; do
    curl -s -o /dev/null -X POST "http://$BIP:8000/api/v1/login" \
        -H "X-Forwarded-For: 9.9.9.$i" -H "Content-Type: application/json" \
        -d '{"email":"admin@interlinked.io","password":"errada"}'
done
CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "http://$BIP:8000/api/v1/login" \
    -H "X-Forwarded-For: 9.9.9.250" -H "Content-Type: application/json" \
    -d '{"email":"admin@interlinked.io","password":"errada"}')
if [ "$CODE" = "429" ]; then ok "forja bloqueada (429 mesmo com XFF variado)"; else fail "forja" "esperado 429, veio $CODE"; fi

echo ""
echo "RESULTADO: $PASS passou / $FAIL falhou"
[ "$FAIL" -gt 0 ] && exit 1
exit 0