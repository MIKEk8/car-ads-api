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
#
# `exec` перед сервером обязателен. Из-за `&&` шелл не может выполнить exec
# самостоятельно и остаётся PID 1, а сервер работает его потомком. SIGTERM от
# `docker stop` и от Kubernetes приходит первому процессу, шелл его никому не
# передаёт — и контейнер живёт до конца grace period, после чего добивается
# SIGKILL. Замерено на стенде: 31 секунда на остановку пода против 2 секунд у
# фронтенда, где CMD задан exec-формой. С `exec` сервер сам становится PID 1
# и получает сигнал напрямую.
CMD ["sh", "-c", "php yii migrate/up --interactive=0 && exec php yii serve 0.0.0.0:8080 --docroot=web"]
