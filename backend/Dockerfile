FROM php:8.4-cli-alpine

RUN apk add --no-cache sqlite-dev curl \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql

WORKDIR /app

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer config --global policy.advisories.block false \
    && composer install --no-dev --no-interaction --optimize-autoloader

RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/app/public storage/logs bootstrap/cache database

RUN chmod +x docker-entrypoint.sh

EXPOSE 8000

CMD ["sh", "docker-entrypoint.sh"]