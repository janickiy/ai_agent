FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        $PHPIZE_DEPS \
        ca-certificates \
        curl \
        curl-dev \
        icu-data-full \
        icu-dev \
        libxml2-dev \
        libzip-dev \
        linux-headers \
        mysql-client \
        oniguruma-dev \
        supervisor \
    && docker-php-ext-install -j"$(nproc)" bcmath curl dom intl mbstring opcache pcntl pdo_mysql zip

COPY docker/certs/*.crt /usr/local/share/ca-certificates/
RUN update-ca-certificates

RUN set -eux; \
    for attempt in 1 2 3; do \
        if pecl install redis-6.3.0; then break; fi; \
        if [ "$attempt" -eq 3 ]; then exit 1; fi; \
        sleep 5; \
    done; \
    docker-php-ext-enable redis; \
    apk del $PHPIZE_DEPS

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-progress

COPY . .
COPY docker/php/zz-healthcheck.conf /usr/local/etc/php-fpm.d/zz-healthcheck.conf
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
