#!/usr/bin/env bash
# Cria as duas tabelas de frete de exemplo via API.
# Requer: sistema rodando (./start.sh), jq instalado.

BASE="http://localhost:8000/api/v1"
EMAIL="admin@interlinked.io"
SENHA="admin123"

echo "==> Login..."
TOKEN=$(curl -s -X POST "$BASE/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$SENHA\"}" \
  | jq -r '.data.token')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
  echo "ERRO: Login falhou. O sistema está rodando em :8000?"
  exit 1
fi
echo "    Token: ${TOKEN:0:16}..."

echo ""
echo "==> Buscando transportadoras ativas..."
CARRIERS=$(curl -s "$BASE/carriers" \
  -H "Authorization: Bearer $TOKEN")

echo "$CARRIERS" | jq -r '.data[] | "    [\(.id | .[0:8])...] \(.name)"'

# Pega o ID da primeira e da terceira transportadora (para variar)
CARRIER_1=$(echo "$CARRIERS" | jq -r '.data[0].id')
CARRIER_3=$(echo "$CARRIERS" | jq -r '.data[2].id')

echo ""
echo "==> Criando Tabela Nordeste para: $(echo "$CARRIERS" | jq -r '.data[0].name')..."
PAYLOAD_1=$(cat "$(dirname "$0")/tabela-frete-sp-nordeste.json" \
  | sed "s/CARRIER_ID/$CARRIER_1/g" \
  | jq 'del(._comment, ._uso)')

RESULT_1=$(curl -s -X POST "$BASE/freight-tables" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD_1")
echo "    Resultado: $(echo "$RESULT_1" | jq -r '.data.name // .message // .errors')"

echo ""
echo "==> Criando Tabela Centro-Oeste para: $(echo "$CARRIERS" | jq -r '.data[2].name')..."
PAYLOAD_2=$(cat "$(dirname "$0")/tabela-frete-sp-centro-oeste.json" \
  | sed "s/CARRIER_ID/$CARRIER_3/g" \
  | jq 'del(._comment, ._uso)')

RESULT_2=$(curl -s -X POST "$BASE/freight-tables" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD_2")
echo "    Resultado: $(echo "$RESULT_2" | jq -r '.data.name // .message // .errors')"

echo ""
echo "==> Feito. Acesse /transportadoras no painel para ver as tabelas criadas."
