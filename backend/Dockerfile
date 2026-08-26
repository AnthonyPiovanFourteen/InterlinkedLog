FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache nginx sqlite-dev curl \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql

WORKDIR /app

COPY nginx.conf /etc/nginx/http.d/default.conf

RUN printf "[global]\nerror_log = /proc/self/fd/2\n" > /usr/local/etc/php-fpm.d/zz-dockerlog.conf

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer config --global policy.advisories.block false \
    && composer install --no-dev --no-interaction --optimize-autoloader

RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/app/public storage/logs bootstrap/cache database \
    && chown -R www-data:www-data storage bootstrap/cache database

RUN chmod +x docker-entrypoint.sh

EXPOSE 8000

CMD ["sh", "docker-entrypoint.sh"]

FROM base AS dev

RUN composer install --no-interaction --optimize-autoloader