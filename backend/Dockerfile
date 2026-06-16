FROM php:8.4-cli-alpine

RUN apk add --no-cache sqlite-dev curl \
    && docker-php-ext-install pdo pdo_sqlite

WORKDIR /app

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --no-interaction --optimize-autoloader

EXPOSE 8000

CMD php artisan app:seed && php artisan serve --host=0.0.0.0 --port=8000
