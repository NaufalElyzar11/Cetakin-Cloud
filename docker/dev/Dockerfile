FROM composer:2.10.3 AS composer
FROM php:8.3.35-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libonig-dev libzip-dev \
    && docker-php-ext-install -j2 pdo_pgsql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/dev/entrypoint.sh /usr/local/bin/cetakin-dev
WORKDIR /workspace
ENTRYPOINT ["sh", "/usr/local/bin/cetakin-dev"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
