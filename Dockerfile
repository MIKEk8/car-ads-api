FROM php:8.3-cli

# Расширения для PostgreSQL.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev git unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Сначала зависимости — кешируется отдельным слоем.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-dev

COPY . .

EXPOSE 8080

# Применяем миграции, затем поднимаем встроенный сервер Yii.
CMD ["sh", "-c", "php yii migrate/up --interactive=0 && php yii serve 0.0.0.0:8080 --docroot=web"]
