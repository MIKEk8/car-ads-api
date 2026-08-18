FROM php:8.3-cli

# Расширения для PostgreSQL.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev git unzip tini \
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
# PID 1 — init-процесс, и это не украшательство. Ядро не применяет к первому
# процессу действия по умолчанию: сигнал доходит до него только если он сам
# поставил обработчик. Встроенный сервер PHP обработчика SIGTERM не ставит и
# в роли PID 1 просто игнорирует сигнал.
#
# Замерено, а не выведено из общих соображений: остановка контейнера занимала
# 30 секунд и заканчивалась SIGKILL одинаково во всех трёх вариантах — с
# шеллом, с `exec` и даже когда `php -S` запускался напрямую без шелла. С tini
# в роли PID 1 — 229 миллисекунд: сервер перестаёт быть первым процессом, и
# обычное действие по умолчанию снова работает.
ENTRYPOINT ["/usr/bin/tini", "--"]

# `exec` оставлен не ради сигналов, а ради самого дерева процессов: без него
# шелл висел бы лишним звеном между tini и сервером до конца жизни контейнера.
CMD ["sh", "-c", "php yii migrate/up --interactive=0 && exec php yii serve 0.0.0.0:8080 --docroot=web"]
