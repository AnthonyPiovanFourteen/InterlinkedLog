#!/bin/sh
set -e

APP_KEY_PUBLIC_DEV="base64:RznMgFB/a8l7NtPJYyrrQ9P6/YyWUfLFZ9BKjyyHZR8="

if [ "$APP_ENV" = "production" ] && { [ -z "$APP_KEY" ] || [ "$APP_KEY" = "$APP_KEY_PUBLIC_DEV" ]; }; then
    echo "ERRO: APP_ENV=production exige APP_KEY própria no ambiente." >&2
    echo "O default do docker-compose é público e serve apenas para desenvolvimento." >&2
    echo "Gere uma chave com: php -r \"echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;\"" >&2
    exit 1
fi

MAX_ATTEMPTS=30
attempt=0

until php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge "$MAX_ATTEMPTS" ]; then
        echo "MySQL indisponível após $((MAX_ATTEMPTS * 2))s — abortando"
        exit 1
    fi
    echo "Aguardando MySQL (tentativa $attempt/$MAX_ATTEMPTS)..."
    sleep 2
done

echo "MySQL disponível — rodando migrations"
php artisan migrate --force

EMPRESAS=$(php artisan tinker --execute="echo \App\Models\Company::count();" 2>/dev/null)

if [ "${EMPRESAS:-0}" = "0" ]; then
    if [ "$DEMO_MODE" = "true" ]; then
        echo "Banco vazio e DEMO_MODE=true — populando dados de demonstracao"
        php artisan db:seed --class=DatabaseSeeder --force || true
    elif [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
        echo "Banco vazio — criando empresa e administrador iniciais"
        php artisan app:create-admin || true
    else
        echo "Banco vazio. Nenhum dado criado."
        echo "  Para comecar com dados de demonstracao:  DEMO_MODE=true"
        echo "  Para criar apenas o administrador:       defina ADMIN_COMPANY, ADMIN_EMAIL e ADMIN_PASSWORD"
        echo "  Ou rode manualmente: docker compose exec backend php artisan app:create-admin"
    fi
else
    echo "Banco ja populado ($EMPRESAS empresa(s)) — nada a criar"
fi

echo "Iniciando php-fpm e nginx"
php-fpm -D
nginx -g "daemon off;"