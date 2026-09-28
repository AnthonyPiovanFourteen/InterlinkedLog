#!/bin/bash
# Verificação do fluxo de integração com transportadoras, contra a stack real.
#
#   docker compose up -d
#   ./scripts/carrier-api-check.sh
#
# Cobre: catálogo de transportadoras e quais têm adaptador, o contrato do
# endpoint de gateways, cadastro e remoção de credencial, as regras de
# validação e de sigilo dos segredos, e a cotação com resultado progressivo —
# incluindo o caminho de erro quando a credencial não é válida.
#
# Exit code != 0 quando algo regredir. Idempotente: remove ao final tudo o que
# criou, e limpa credencial remanescente de execução anterior.

set -uo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR/.."

BASE="http://localhost:3000/api/v1"
PASS=0
FAIL=0
ok()   { echo "  OK   $1"; PASS=$((PASS+1)); }
fail() { echo "  FAIL $1 — $2"; FAIL=$((FAIL+1)); }

# Lê um caminho do JSON vindo da entrada padrão, sem estourar em resposta ruim.
jget() { python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)
except Exception:
    print(''); raise SystemExit
for p in '$1'.split('.'):
    if p == '': continue
    if isinstance(d, list):
        d = d[int(p)] if p.isdigit() and int(p) < len(d) else ''
    elif isinstance(d, dict):
        d = d.get(p, '')
    else:
        d = ''
print(d if d is not None else '')
"; }

echo "=== Transportadoras e integração ==="

# ---------------------------------------------------------------- autenticação
TOKEN=$(curl -s -X POST "$BASE/login" -H "Content-Type: application/json" \
    -d '{"email":"admin@interlinked.io","password":"admin123"}' | jget token)

if [ -z "$TOKEN" ]; then
    echo "  FAIL login — sem token. A stack está no ar e com dados?"
    echo "       Use DEMO_MODE=true ou crie o acesso com app:create-admin."
    exit 1
fi
ok "login"
AUTH=(-H "Authorization: Bearer $TOKEN")

# Remove credencial deixada por execução anterior, para o script ser repetível.
curl -s "${AUTH[@]}" "$BASE/carrier-credentials" | python3 -c "
import sys, json
try:
    for c in json.load(sys.stdin).get('data', []):
        print(c['id'])
except Exception:
    pass
" | while read -r id; do
    [ -n "$id" ] && curl -s -X DELETE "${AUTH[@]}" "$BASE/carrier-credentials/$id" > /dev/null
done

echo "--- Catálogo ---"

CARRIERS=$(curl -s "${AUTH[@]}" "$BASE/carriers")
read -r TOTAL COM_API GW_ID GW_NOME <<< "$(echo "$CARRIERS" | python3 -c "
import sys, json
d = json.load(sys.stdin)['data']
api = [c for c in d if c.get('gateway')]
alvo = next((c for c in api if c['gateway'] == 'braspress'), api[0] if api else None)
print(len(d), len(api), alvo['id'] if alvo else '', alvo['gateway'] if alvo else '')
")"

if [ "${COM_API:-0}" -ge 1 ]; then
    ok "catálogo com adaptador ($COM_API de $TOTAL transportadoras)"
else
    fail "catálogo com adaptador" "nenhuma transportadora com gateway em $TOTAL"
fi

# O estado de integração é o que a tela usa para oferecer "Conectar".
INTEGRADA=$(echo "$CARRIERS" | python3 -c "
import sys, json
print(sum(1 for c in json.load(sys.stdin)['data'] if c.get('integrated')))
")
if [ "$INTEGRADA" = "0" ]; then
    ok "nenhuma integrada no início"
else
    fail "estado inicial" "$INTEGRADA já integrada(s) — limpeza anterior falhou"
fi

echo "--- Contrato do endpoint de gateways ---"

GATEWAYS=$(curl -s "${AUTH[@]}" "$BASE/carrier-credentials/gateways")
read -r N_GW SEM_HINT SEM_DOC <<< "$(echo "$GATEWAYS" | python3 -c "
import sys, json
d = json.load(sys.stdin)['data']
sem_hint = [g['name'] for g in d
            for k in g['required_secrets']
            if not (g.get('secret_hints') or {}).get(k, '').strip()]
sem_doc = [g['name'] for g in d if not g.get('documentation_url')]
print(len(d), ','.join(sem_hint) or '-', ','.join(sem_doc) or '-')
")"

[ "${N_GW:-0}" -ge 1 ] && ok "gateways declarados ($N_GW)" || fail "gateways declarados" "nenhum"
[ "$SEM_HINT" = "-" ] && ok "todo campo exigido tem explicação" \
                      || fail "explicação de campo" "faltando em: $SEM_HINT"
[ "$SEM_DOC" = "-" ]  && ok "todo gateway aponta a documentação" \
                      || fail "link de documentação" "faltando em: $SEM_DOC"

echo "--- Cadastro de credencial ---"

# Segredo obrigatório ausente deve ser recusado, dizendo qual falta.
INCOMPLETA=$(curl -s -o /dev/null -w "%{http_code}" -X POST "${AUTH[@]}" \
    -H "Content-Type: application/json" \
    -d "{\"carrier_id\":\"$GW_ID\",\"gateway\":\"$GW_NOME\",\"secrets\":{\"username\":\"u\"}}" \
    "$BASE/carrier-credentials")
[ "$INCOMPLETA" = "422" ] && ok "segredo obrigatório ausente é recusado (422)" \
                          || fail "validação de segredo" "esperado 422, veio $INCOMPLETA"

CRED=$(curl -s -X POST "${AUTH[@]}" -H "Content-Type: application/json" \
    -d "{\"carrier_id\":\"$GW_ID\",\"gateway\":\"$GW_NOME\",\"secrets\":{\"username\":\"teste\",\"password\":\"teste\"}}" \
    "$BASE/carrier-credentials")
CRED_ID=$(echo "$CRED" | jget data.id)

if [ -n "$CRED_ID" ]; then ok "credencial criada ($GW_NOME)"; else fail "criar credencial" "$CRED"; fi

# Regra que atravessa toda a API: segredo nunca volta numa resposta.
if echo "$CRED" | grep -q '"secrets"' || echo "$CRED" | grep -q 'teste'; then
    fail "sigilo do segredo" "a resposta de criação expôs o valor"
else
    ok "segredo não volta na criação"
fi

if curl -s "${AUTH[@]}" "$BASE/carrier-credentials" | grep -q 'teste'; then
    fail "sigilo do segredo" "a listagem expôs o valor"
else
    ok "segredo não volta na listagem"
fi

# Duplicar a mesma transportadora deve ser recusado.
DUP=$(curl -s -o /dev/null -w "%{http_code}" -X POST "${AUTH[@]}" \
    -H "Content-Type: application/json" \
    -d "{\"carrier_id\":\"$GW_ID\",\"gateway\":\"$GW_NOME\",\"secrets\":{\"username\":\"u\",\"password\":\"p\"}}" \
    "$BASE/carrier-credentials")
[ "$DUP" = "422" ] && ok "credencial duplicada é recusada (422)" \
                   || fail "duplicidade" "esperado 422, veio $DUP"

# A listagem de transportadoras deve refletir a conexão.
AGORA=$(curl -s "${AUTH[@]}" "$BASE/carriers" | python3 -c "
import sys, json
print(sum(1 for c in json.load(sys.stdin)['data'] if c.get('integrated')))
")
[ "$AGORA" = "1" ] && ok "transportadora aparece como integrada" \
                   || fail "estado de integração" "esperado 1, veio $AGORA"

echo "--- Cotação com resultado progressivo ---"

COT=$(curl -s -X POST "${AUTH[@]}" -H "Content-Type: application/json" \
    -d '{"nf_number":"CHK001","sender_cnpj":"12.345.678/0001-99","receiver_cnpj":"98.765.432/0001-88","origin_cep":"01000-000","destination_cep":"86020-000","weight":45,"boxes":10,"volume":0.15,"cargo_value":5000}' \
    "$BASE/quotations")
QID=$(echo "$COT" | jget data.id)
N_TABELA=$(echo "$COT" | python3 -c "
import sys, json
print(len(json.load(sys.stdin).get('data', {}).get('results', [])))
")

if [ -n "$QID" ]; then ok "cotação criada"; else fail "criar cotação" "$COT"; exit 1; fi
[ "${N_TABELA:-0}" -ge 1 ] && ok "tabelas respondem de imediato ($N_TABELA resultado(s))" \
                           || fail "resultado de tabela" "nenhum resultado síncrono"

# A transportadora conectada deve entrar como pendente e sair depois.
PEND=$(curl -s "${AUTH[@]}" "$BASE/quotations/$QID" | python3 -c "
import sys, json
print(','.join(json.load(sys.stdin)['data']['carriers']['pending']) or '-')
")
[ "$PEND" != "-" ] && ok "transportadora entra como pendente ($PEND)" \
                   || fail "despacho do job" "nenhuma pendente logo após criar"

# Espera o worker concluir.
STATUS="pendente"
for _ in $(seq 1 15); do
    STATUS=$(curl -s "${AUTH[@]}" "$BASE/quotations/$QID" | python3 -c "
import sys, json
a = [x for x in json.load(sys.stdin)['data']['carriers']['attempts'] if x['gateway'] == '$GW_NOME']
print(a[0]['status'] if a else 'ausente')
")
    [ "$STATUS" != "pendente" ] && break
    sleep 2
done

case "$STATUS" in
    indisponivel|erro)
        ok "credencial inválida vira '$STATUS', sem derrubar a cotação" ;;
    cotada)
        ok "credencial válida: transportadora cotou" ;;
    pendente)
        fail "consulta à transportadora" "continua pendente após 30s — o worker está no ar?" ;;
    *)
        fail "consulta à transportadora" "estado inesperado: $STATUS" ;;
esac

# Os resultados de tabela precisam sobreviver à falha da transportadora.
FINAL=$(curl -s "${AUTH[@]}" "$BASE/quotations/$QID" | python3 -c "
import sys, json
d = json.load(sys.stdin)['data']
r = d['results']
print(len(r), sum(1 for x in r if x.get('best_price')), d['benchmark']['sample'])
")
read -r N_FINAL N_MELHOR N_HIST <<< "$FINAL"
[ "${N_FINAL:-0}" -ge "${N_TABELA:-1}" ] && ok "cotação segue íntegra após a falha ($N_FINAL resultado(s))" \
                                         || fail "integridade da cotação" "resultados sumiram: $N_TABELA → $N_FINAL"
[ "${N_MELHOR:-0}" = "1" ] && ok "ranking aponta um melhor preço" \
                           || fail "ranking" "esperado 1 marcado, veio $N_MELHOR"
[ -n "${N_HIST:-}" ] && ok "referência histórica calculada (amostra: $N_HIST)" \
                     || fail "benchmark" "ausente na resposta"

echo "--- Remoção ---"

DEL=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "${AUTH[@]}" "$BASE/carrier-credentials/$CRED_ID")
[ "$DEL" = "200" ] && ok "credencial removida" || fail "remover credencial" "veio $DEL"

DEPOIS=$(curl -s "${AUTH[@]}" "$BASE/carriers" | python3 -c "
import sys, json
print(sum(1 for c in json.load(sys.stdin)['data'] if c.get('integrated')))
")
[ "$DEPOIS" = "0" ] && ok "transportadora volta a cotar só por tabela" \
                    || fail "estado após remoção" "ainda $DEPOIS integrada(s)"

echo
echo "RESULTADO: $PASS passou / $FAIL falhou"
[ "$FAIL" -eq 0 ]
