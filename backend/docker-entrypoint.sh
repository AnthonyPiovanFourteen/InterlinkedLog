#!/bin/sh
set -e

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

SEEDED=$(php artisan tinker --execute="echo \App\Models\Company::count();" 2>/dev/null)
if [ "${SEEDED:-0}" = "0" ]; then
    echo "Banco vazio — aplicando seed inicial"
    php artisan db:seed --class=DatabaseSeeder --force || true
else
    echo "Banco já populado ($SEEDED empresas) — seed ignorado"
fi

php artisan serve --host=0.0.0.0 --port=8000