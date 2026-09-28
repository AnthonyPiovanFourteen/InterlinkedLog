# Runbook Operacional — InterlinkedLog

Procedimentos operacionais do backend (MySQL 8 em Docker Compose).

## Stack de produção

- `mysql` — MySQL 8, volume nomeado `mysql-data`, credenciais via `.env` na raiz (defaults de dev no `docker-compose.yml`).
- `backend` — PHP 8.4, roda `migrate` no boot após aguardar o MySQL (retry com backoff no `docker-entrypoint.sh`).
- `frontend` — TanStack Start.

Nenhuma configuração é gravada em `.env` dentro da imagem — tudo vem do ambiente do compose.

## Subir do zero

```bash
docker compose down -v          # destrói volumes (mysql-data e backend-data)
docker compose up --build -d    # MySQL -> backend (aguarda healthcheck) -> frontend
```

## Backup

```bash
./scripts/backup-mysql.sh              # dump com data, em ./backups/
./scripts/backup-mysql.sh 14           # retenção de 14 dias
BACKUP_DIR=/mnt/backups ./scripts/backup-mysql.sh
```

O dump usa `mysqldump --single-transaction` (consistente sem travar escritas) e é compactado com gzip.

### Agendamento (cron)

```cron
30 2 * * *  cd /caminho/do/repositorio && ./scripts/backup-mysql.sh 7 >> logs/backup.log 2>&1
```

## Restauração

```bash
./scripts/restore-mysql.sh backups/interlinkedlog-20260824-020000.sql.gz
```

O mysqldump inclui `DROP TABLE IF EXISTS` antes de cada `CREATE`, então a restauração recria as tabelas do banco de destino.

### Verificação de restauração (não pule — backup não verificado não é backup)

```bash
# 1. Cria um banco de validação e restaura nele
docker compose exec -T mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" \
  -e 'CREATE DATABASE IF NOT EXISTS interlinkedlog_restore'

gunzip -c backups/interlinkedlog-XXX.sql.gz | docker compose exec -T mysql sh -c \
  'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" interlinkedlog_restore'

# 2. Confere contagens por tabela (devem ser idênticas às do banco principal)
docker compose exec -T mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e '
  SELECT table_name, table_rows FROM information_schema.tables
  WHERE table_schema IN ("interlinkedlog", "interlinkedlog_restore")
  ORDER BY table_name;'
```

## Migração de dados SQLite -> MySQL (uma única vez, para dados legados)

O schema do destino precisa existir primeiro (`php artisan migrate --force` no backend). Com o MySQL rodando e o backend parado (evita concorrência com o seed condicional do boot):

```bash
# 1. Copia o sqlite legado para dentro do container (volume backend-data)
docker compose cp backend/database/database.sqlite backend:/app/database/database.sqlite

# 2. Roda a migração idempotente (SKIP em tabelas já migradas; aborta em divergência)
docker compose run --rm backend php scripts/migrate-sqlite-to-mysql.php

# 3. Em caso de divergência deliberada, use --force (trunca e recopia)
#    docker compose run --rm backend php scripts/migrate-sqlite-to-mysql.php --force
```

O script confere a contagem de cada tabela antes e depois da cópia e aborta em divergência. A tabela `migrations` não é copiada — o schema é gerenciado pelo artisan.

## Restrição de fila (QUEUE_CONNECTION)

`QUEUE_CONNECTION` deve permanecer `sync`. O `TenantScope` lê o `company_id` do **request HTTP** (`app(Request::class)->attributes`) e fica **inerte em processos sem request** — incluindo workers de fila.

Se no futuro um worker for introduzido:

1. O `company_id` precisa **viajar no payload do job** (ex.: `new Job($companyId, ...)`).
2. O job deve **reaplicar o escopo manualmente** antes de tocar em models com `TenantScoped` — ex.: `app(Request::class)->attributes->set('company_id', $job->companyId)` no `handle()` (ou um middleware de job equivalente).
3. Jobs que dependem do contexto do usuário (quem disparou, empresa) devem serializar **tudo** que precisam; não há request HTTP no worker.

O mesmo vale para comandos artisan que tocam dados por tenant: passem `company_id` por argumento e reapliquem o escopo da mesma forma.

## Testes

**Decisão (Fase 4):** o SQLite foi removido da suíte e do default da aplicação — `phpunit.xml` e `config/database.php` apontam para MySQL. O banco `backend/database/database.sqlite` saiu do repositório (migração de dados consolidada na Fase 3, backups em `/tmp/opencode/`). A conexão `sqlite` permanece no `config/database.php` apenas como utilitário do `test-all.sh`, que usa um build PHP sem `pdo_mysql` e recria o arquivo local com `touch`.

O `interlinkedlog_test` é criado automaticamente na primeira subida do MySQL (`mysql-init/init.sql`).

```bash
# PRIMÁRIO — container (sem composer install em runtime; imagem com dev deps)
docker compose up -d
docker compose run --rm test            # roda os 38 testes contra interlinkedlog_test

# Local — exige PHP com pdo_mysql instalado no host, o que NÃO é o caso do
# ambiente atual (o PHP do host só tem pdo_sqlite). Use o container acima.
docker compose up -d
php artisan test

# Smoke test HTTP (usa o build PHP sqlite próprio; recria o arquivo local)
./test-all.sh
```

O serviço `test` vive em `profiles: ["test"]` — não sobe com `docker compose up -d`, e o `composer install` (com dependências de desenvolvimento) roda no build da imagem (`Dockerfile`, estágio `dev`).