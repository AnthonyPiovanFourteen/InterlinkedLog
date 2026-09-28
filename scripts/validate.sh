#!/usr/bin/env bash
# Verificador dos critérios de aceite das fases de migração para MySQL.
# Ver DOC/PROMPT-Migracao-MySQL.md
#
# Uso: ./scripts/validate.sh [0|1|2|3|autoria|all]
set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BE="$REPO/backend"
FASE="${1:-all}"
BASE_COMMIT="${BASE_COMMIT:-b32c9cc}"
PASS=0; FAIL=0; WARN=0

g() { printf '  \033[32m✓\033[0m %s\n' "$1"; PASS=$((PASS+1)); }
r() { printf '  \033[31m✗\033[0m %s\n' "$1"; FAIL=$((FAIL+1)); }
y() { printf '  \033[33m!\033[0m %s\n' "$1"; WARN=$((WARN+1)); }
h() { printf '\n\033[1m%s\033[0m\n' "$1"; }

check_absent() {
  local desc="$1" pat="$2"; shift 2
  local n; n=$(grep -rnE -e "$pat" "$@" 2>/dev/null | grep -v '/vendor/' | wc -l)
  [ "$n" -eq 0 ] && g "$desc" || { r "$desc (encontrado $n)"; grep -rnE -e "$pat" "$@" 2>/dev/null | grep -v '/vendor/' | head -5 | sed 's/^/      /'; }
}

check_min() {
  local desc="$1" min="$2" pat="$3"; shift 3
  # -e é obrigatório: padrões que começam com '-' (ex.: '->index(') seriam
  # interpretados como opções do grep, e o erro morreria no 2>/dev/null.
  local n; n=$(grep -rnE -e "$pat" "$@" 2>/dev/null | grep -v '/vendor/' | wc -l)
  [ "$n" -ge "$min" ] && g "$desc ($n)" || r "$desc (tem $n, esperado >= $min)"
}

fase0() {
  h "FASE 0 — Rede de segurança e concorrência"

  # Conta MÉTODOS de teste, não arquivos.
  local nt; nt=$(grep -rhcE 'public function test|#\[Test\]' \
    $(find "$BE/tests/Feature" -name '*Test.php' 2>/dev/null | grep -v 'ExampleTest') 2>/dev/null \
    | paste -sd+ | bc 2>/dev/null)
  [ "${nt:-0}" -ge 5 ] && g "testes de feature além dos ExampleTest ($nt)" \
                       || r "testes de feature insuficientes (tem ${nt:-0}, esperado >= 5)"

  check_min "DB::transaction no código" 3 'DB::transaction|beginTransaction' "$BE/app"
  check_min "trava pessimista (lockForUpdate)" 1 'lockForUpdate' "$BE/app"
  # A trava vai na COTAÇÃO, não no contrato: a corrida é contratar 2x a mesma cotação.
  check_min "porta de leitura com trava no QuotationRepository" 1 'ForUpdate' "$BE/app/Domain/Repositories/QuotationRepository.php"

  # A transação de save() precisa cobrir a linha-mãe, não só os filhos.
  local leaky=0
  for f in EloquentQuotationRepository EloquentFreightTableRepository; do
    awk '/public function save/,/DB::transaction/' "$BE/app/Infrastructure/Repositories/Eloquent/$f.php" 2>/dev/null \
      | grep -qE 'updateOrCreate' && { r "$f::save — updateOrCreate da linha-mãe FORA da transação"; leaky=1; }
  done
  [ "$leaky" -eq 0 ] && g "transações de save() cobrem a linha-mãe"

  check_absent "uuid_create() eliminado" 'uuid_create\(' "$BE/app" "$BE/database"
  check_min "Str::orderedUuid em uso" 10 'orderedUuid' "$BE/app" "$BE/database"
}

fase1() {
  h "FASE 1 — Consistência e tenancy"

  check_min "constante de status de transportadora" 1 'class CarrierStatus|const ATIVA' "$BE/app/Domain"
  check_absent "literal 'Ativo' fora do contexto de usuário" "'Ativo'" \
    "$BE/app/Infrastructure/Services/QuotationEngine.php" \
    "$BE/app/Domain/Entities/Carrier.php" \
    "$BE/app/Infrastructure/Repositories/Eloquent/EloquentCarrierRepository.php"

  # Estado REAL do schema, não a intenção das migrations.
  local db="$BE/database/database.sqlite"
  if [ -f "$db" ]; then
    php -r '
      $db = new PDO("sqlite:'"$db"'");
      $cols = fn($t) => array_column($db->query("PRAGMA table_info($t)")->fetchAll(), "name");
      exit((in_array("company_id", $cols("carriers")) ? 1 : 0) + (in_array("company_id", $cols("freight_tables")) ? 0 : 2));
    ' 2>/dev/null
    case $? in
      0) g "schema: carriers sem company_id, freight_tables com company_id" ;;
      1) r "schema: carriers AINDA tem company_id" ;;
      2) r "schema: freight_tables SEM company_id — vazamento de preço entre tenants" ;;
      *) r "schema: carriers com company_id E freight_tables sem" ;;
    esac
    local bad; bad=$(php -r '$db=new PDO("sqlite:'"$db"'"); echo $db->query("SELECT COUNT(*) FROM carriers WHERE status<>\"Ativa\" AND status<>\"Inativa\"")->fetchColumn();' 2>/dev/null)
    [ "${bad:-1}" -eq 0 ] && g "dados: nenhum carrier com status legado" || r "dados: $bad carrier(s) com status fora de Ativa/Inativa"
  else
    y "database.sqlite ausente — checagens de schema puladas"
  fi

  check_min "unique(carrier_id, company_id, valid_from)" 1 "unique\(\[?'?carrier_id" "$BE/database/migrations"
  check_min "Global Scope de tenant" 1 'addGlobalScope' "$BE/app"

  local scoped; scoped=$(grep -l "TenantScoped" "$BE/app/Models/"*.php 2>/dev/null | wc -l)
  [ "$scoped" -ge 6 ] && g "TenantScoped aplicado em $scoped models" \
                      || r "TenantScoped em apenas $scoped models (esperado >= 6)"

  check_min "escrita de carrier restrita a Admin" 1 'Role::ADMIN' "$BE/app/Http/Controllers/Api/CarrierController.php"
  check_min "FreightTableController lê company_id" 1 "attributes->get\('company_id'\)" "$BE/app/Http/Controllers/Api/FreightTableController.php"
  check_min "TrackingController::show valida tenant" 1 "attributes->get\('company_id'\)" "$BE/app/Http/Controllers/Api/TrackingController.php"
}

fase2() {
  h "FASE 2 — Schema MySQL"
  check_min "colunas datetime em vez de timestamp" 1 "datetime\(" "$BE/database/migrations"
  check_min "índices declarados" 4 '->index\(' "$BE/database/migrations"

  # Na Fase 2 o banco ativo ainda é SQLite — trocar DB_CONNECTION é Fase 3.
  # Aqui só se exige que o bloco mysql tenha collation FIXA, sem env(),
  # para nunca herdar o default do servidor.
  if awk "/'mysql' =>/,/^        \],/" "$BE/config/database.php" 2>/dev/null \
     | grep -qE "'collation'\s*=>\s*'utf8mb4_(unicode|0900)"; then
    g "collation fixa no bloco mysql (sem env)"
  else
    r "collation do bloco mysql ausente ou vinda de env()"
  fi

  # Mensagens de erro do SQLite não sobrevivem ao MySQL; SQLSTATE sim.
  check_absent "asserções de teste não dependem de mensagem do SQLite" \
    'FOREIGN KEY constraint failed|NOT NULL constraint failed' "$BE/tests"
}

fase3() {
  h "FASE 3 — Infraestrutura"
  grep -q 'mysql' "$REPO/docker-compose.yml" 2>/dev/null \
    && g "serviço mysql no compose" || r "sem serviço mysql no docker-compose.yml"
  grep -qE 'mysqladmin ping' "$REPO/docker-compose.yml" 2>/dev/null \
    && g "healthcheck do mysql presente" || r "sem healthcheck do mysql (o do backend não conta)"
  if awk '/depends_on/,0' "$REPO/docker-compose.yml" 2>/dev/null \
     | grep -A2 -E '^\s+mysql:' | grep -q 'service_healthy'; then
    g "backend aguarda mysql saudável"
  else
    r "nenhum depends_on em mysql com service_healthy"
  fi
  check_absent ".env não é mais gravado no Dockerfile" "printf 'APP_NAME" "$BE/Dockerfile"
  grep -qE 'DB_CONNECTION=mysql' "$BE/.env.example" "$REPO/docker-compose.yml" 2>/dev/null \
    && g "DB_CONNECTION=mysql configurado" || r "DB_CONNECTION ainda não aponta para mysql"
  # A troca da conexão default pertence a esta fase, não à 2.
  local defconn; defconn=$(grep -oE "'default'\s*=>\s*env\('DB_CONNECTION',\s*'[a-z]+'" "$BE/config/database.php" 2>/dev/null | grep -oE "'[a-z]+'$" | tr -d "'")
  [ "${defconn:-}" = "mysql" ] && g "conexão default = mysql" || r "conexão default ainda é '${defconn:-?}'"
  find "$REPO" -maxdepth 3 -name '*backup*' -not -path '*/vendor/*' -not -path '*/node_modules/*' 2>/dev/null \
    | head -1 | grep -q . && g "script de backup existe" || r "sem script de backup"

  # A fila assíncrona torna o TenantScope inerte no worker (sem request HTTP).
  if grep -qE 'QUEUE_CONNECTION=(database|redis|sqs)' "$BE/.env.example" "$REPO/docker-compose.yml" 2>/dev/null; then
    grep -rqE 'company_id' "$BE/app/Jobs" 2>/dev/null \
      && g "jobs carregam company_id no payload" \
      || r "fila assíncrona ativa mas TenantScope fica inerte no worker — company_id precisa viajar no job"
  fi
}

autoria() {
  h "AUTORIA — commits sem marca de ferramenta"
  local base; base=$(git -C "$REPO" rev-parse "$BASE_COMMIT" 2>/dev/null)
  if [ -z "$base" ]; then y "commit base $BASE_COMMIT não encontrado — pulando"; return; fi

  local n; n=$(git -C "$REPO" log --format='%B' "${base}..HEAD" 2>/dev/null \
    | grep -icE 'co-authored-by|generated with|claude|anthropic|🤖' || true)
  if [ "${n:-0}" -eq 0 ]; then
    g "nenhuma marca de coautoria nos commits novos"
  else
    r "$n linha(s) com marca de coautoria/ferramenta"
    git -C "$REPO" log --format='%h %B' "${base}..HEAD" 2>/dev/null \
      | grep -inE 'co-authored-by|generated with|claude|anthropic|🤖' | head -5 | sed 's/^/      /'
  fi
}

case "$FASE" in
  0) fase0 ;; 1) fase1 ;; 2) fase2 ;; 3) fase3 ;; autoria) autoria ;;
  *) fase0; fase1; fase2; fase3; autoria ;;
esac

h "RESULTADO"
printf '  %d passou · %d falhou · %d aviso\n\n' "$PASS" "$FAIL" "$WARN"
[ "$FAIL" -eq 0 ]
