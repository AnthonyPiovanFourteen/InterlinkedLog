FROM php:8.4-cli-alpine

RUN apk add --no-cache sqlite-dev curl \
    && docker-php-ext-install pdo pdo_sqlite

WORKDIR /app

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer config --global policy.advisories.block false \
    && composer install --no-dev --no-interaction --optimize-autoloader

RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/app/public storage/logs bootstrap/cache database \
    && touch database/database.sqlite \
    && printf 'APP_NAME=InterlinkedLog\nAPP_ENV=local\nAPP_DEBUG=true\nAPP_URL=http://localhost:8000\nLOG_CHANNEL=stack\nDB_CONNECTION=sqlite\nCACHE_STORE=file\nSESSION_DRIVER=file\nQUEUE_CONNECTION=sync\n' > .env \
    && php artisan key:generate --force

EXPOSE 8000

CMD touch database/database.sqlite && php artisan migrate --force && (php artisan app:seed || true) && php artisan serve --host=0.0.0.0 --port=8000
