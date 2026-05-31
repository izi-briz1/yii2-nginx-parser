FROM php:8.2-fpm-alpine

# Системные зависимости и сборочные инструменты
RUN apk add --no-cache \
        git \
        unzip \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        $PHPIZE_DEPS

# PHP-расширения: pdo_mysql, intl, zip, mbstring, bcmath
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) pdo_mysql intl zip bcmath

# Расширение redis через PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Настройки PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/yii2-nginx-parser

# Запуск под существующим пользователем www-data
RUN addgroup -g 1000 -S app 2>/dev/null || true \
    && adduser -u 1000 -S app -G www-data 2>/dev/null || true

CMD ["php-fpm"]