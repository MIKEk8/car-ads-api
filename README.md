# Car Ads API

REST API сервис для управления объявлениями автомобилей.
**Стек:** PHP 8, Yii2, PostgreSQL.

Код организован по многослойной архитектуре с паттернами **Service / Repository /
Entity / DataMapper**, следует принципам **SOLID** и использует **Dependency Injection**
(DI-контейнер Yii2).

## Архитектура

```
HTTP  →  Controller  →  Service  →  Repository (interface)  →  DataMapper  →  PostgreSQL
                         (валидация,   (транзакции,            (Yii2 DAO,
                          бизнес-       сборка агрегата)        SQL ↔ Entity)
                          логика)
```

| Слой | Назначение | Файлы |
|------|------------|-------|
| **Entity** | Чистые доменные объекты (POPO) | `src/Entity/Car.php`, `src/Entity/CarOption.php` |
| **DataMapper** | Перенос данных Entity ↔ таблица (без ActiveRecord) | `src/Mapper/*` |
| **Repository** | Контракт хранилища + реализация, транзакции | `src/Repository/*` |
| **Service** | Валидация, бизнес-логика, оркестрация | `src/Service/CarService.php` |
| **Controller** | Разбор HTTP-запроса и сериализация в JSON | `src/Controller/CarController.php` |

DI настраивается в `config/container.php`: интерфейс `CarRepositoryInterface`
связан с реализацией `CarRepository`; мапперы и репозиторий получают
`yii\db\Connection` через автbowiring. Зависимости нигде не создаются через `new`
внутри слоёв — только инъекция через конструктор.

## REST API

### `POST /car/create`
Создаёт объявление. Тело запроса (`application/json`):

```json
{
  "title": "BMW X5 2020",
  "description": "Отличное состояние",
  "price": 4500000,
  "photo_url": "https://example.com/car.jpg",
  "contacts": "+7 999 123-45-67",
  "options": {
    "brand": "BMW",
    "model": "X5",
    "year": 2020,
    "body": "SUV",
    "mileage": 35000
  }
}
```

Поле `options` **необязательное**:
- ключ отсутствует — характеристики не добавляются;
- `"options": null` — характеристики не добавляются;
- `"options": { ... }` — добавляются, **все поля объекта обязательны**.

**Ответ:** `201 Created` с данными объявления.
При ошибке валидации — `422` с картой ошибок по полям.

### `GET /car/{id}`
Возвращает одно объявление с характеристиками (если есть).
**Ответ:** `200 OK` либо `404 Not Found`.

### `GET /car/list?page=1`
Список объявлений с пагинацией.
**Ответ:** `200 OK`, `{ "data": [...], "pagination": { page, perPage, total, pages } }`.

## База данных

PostgreSQL, две таблицы:
- **car** — `id`, `title`, `description`, `price`, `photo_url`, `contacts`, `created_at`;
- **car_option** — `id`, `car_id` (FK → car.id, has-one, уникальный), `brand`, `model`, `year`, `body`, `mileage`.

Схема создаётся миграциями (`migrations/`).

## Запуск через Docker (рекомендуется)

```bash
git clone <url-репозитория>
cd car-api
docker compose up --build
```

Поднимутся два контейнера: PostgreSQL и приложение. Миграции применяются
автоматически при старте. API доступен на `http://localhost:8080`.

## Локальный запуск

```bash
# 1. Клонировать и установить зависимости
git clone <url-репозитория>
cd car-api
composer install

# 2. Настроить PostgreSQL (создать БД `cars`) и параметры подключения.
#    По умолчанию: host=127.0.0.1, port=5432, dbname=cars, user/pass=postgres.
#    Переопределяются переменными окружения DB_DSN / DB_USER / DB_PASSWORD.

# 3. Применить миграции
php yii migrate/up

# 4. Запустить приложение
php yii serve --docroot=web
```

Приложение поднимется на `http://localhost:8080`.

## Тесты

Unit-тесты слоя Service (создание объявления, без обращения к БД):

```bash
composer test
# или
vendor/bin/phpunit
```

## Примеры запросов (curl)

```bash
# Создание
curl -X POST http://localhost:8080/car/create \
  -H "Content-Type: application/json" \
  -d '{"title":"BMW X5","description":"ok","price":4500000,"photo_url":"https://ex.com/1.jpg","contacts":"+7 999 1234567","options":{"brand":"BMW","model":"X5","year":2020,"body":"SUV","mileage":35000}}'

# Получение
curl http://localhost:8080/car/1

# Список
curl "http://localhost:8080/car/list?page=1"
```
