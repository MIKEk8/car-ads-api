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
`yii\db\Connection` через autowiring. Зависимости нигде не создаются через `new`
внутри слоёв — только инъекция через конструктор.

Два вспомогательных класса стоят вне слоёв, но важны для целостности:

- **`src/Schema/CarSchema.php`** — единственное описание ограничений колонок
  (длины `varchar`, precision/scale `numeric`, диапазоны `integer`, границы года
  и пробега). Его используют и миграции при создании таблиц, и слой Service при
  валидации, поэтому проверка и схема не могут разойтись. Полноту покрытия
  сторожит `tests/unit/CarSchemaCoverageTest.php`: константа без
  соответствующей проверки валит тесты.
- **`src/Env.php`** — типизированное чтение переменных окружения. Отдельный
  метод на каждый тип нужен потому, что идиома `getenv('FLAG') ?: $default`
  для флагов нерабочая: строка `"0"` в PHP ложна и подставляется дефолт, а
  `"false"` — непустая строка и даёт `true`.
- **`src/Web/MediaType.php`** и **`src/Web/Request.php`** — нормализация
  `Content-Type`. По RFC 7231 media type регистронезависим и допускает пробел
  перед `;`, а Yii ищет парсер тела регистрозависимым сравнением по строке,
  обрезанной без `trim`. Без нормализации запрос с `APPLICATION/JSON` проходил
  бы проверку типа, но парсер для него не нашёлся бы — тело ушло бы в `$_POST`
  и потерялось. Нормализация вынесена в одно место и используется и подбором
  парсера, и проверкой в контроллере.

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

### Коды ответов

| Код | Когда |
|-----|-------|
| `200` | успешное чтение |
| `201` | объявление создано |
| `400` | битый JSON в теле или нечисловой `?page` |
| `404` | объявление не найдено либо неизвестный адрес |
| `405` | метод не разрешён для адреса (в ответе есть заголовок `Allow`) |
| `415` | тело передано не с `Content-Type: application/json` |
| `422` | ошибка валидации, в ответе карта `errors` по полям |

Все ответы, включая ошибки, — в JSON.

## Переменные окружения

| Переменная | По умолчанию | Назначение |
|------------|--------------|------------|
| `DB_DSN` | `pgsql:host=127.0.0.1;port=5432;dbname=cars` | строка подключения |
| `DB_USER` | `postgres` | пользователь БД |
| `DB_PASSWORD` | `postgres` | пароль; пустое значение сохраняется как заданный пустой пароль |
| `YII_DEBUG` | `true` | понимает `1/0`, `true/false`, `yes/no`, `on/off` |
| `YII_ENV` | `dev` | окружение Yii |
| `CAR_LIST_PER_PAGE` | `20` | объявлений на страницу |
| `COOKIE_VALIDATION_KEY` | dev-значение | обязательно задать вне разработки |

## База данных

PostgreSQL, две таблицы:
- **car** — `id`, `title`, `description`, `price`, `photo_url`, `contacts`, `created_at`;
- **car_option** — `id`, `car_id` (FK → car.id, has-one, уникальный), `brand`, `model`, `year`, `body`, `mileage`.

Схема создаётся миграциями (`migrations/`).

## Запуск через Docker (рекомендуется)

```bash
git clone https://github.com/MIKEk8/car-ads-api.git
cd car-ads-api
docker compose up --build
```

Поднимутся два контейнера: PostgreSQL и приложение. Миграции применяются
автоматически при старте. API доступен на `http://localhost:8080`.

## Локальный запуск

```bash
# 1. Клонировать и установить зависимости
git clone https://github.com/MIKEk8/car-ads-api.git
cd car-ads-api
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

Если порт 8080 занят, адрес передаётся первым аргументом — иначе команда
напечатает `…is taken by another process` и завершится:

```bash
php yii serve 127.0.0.1:8099 --docroot=web
```

## Тесты

Unit-тесты, БД не требуется — репозиторий мокается:

```bash
composer test
# или
vendor/bin/phpunit
```

| Файл | Что проверяет |
|------|---------------|
| `tests/unit/CarServiceTest.php` | создание объявления в слое Service: три состояния `options`, нормализация цены, сбор всех ошибок сразу |
| `tests/unit/CarSchemaCoverageTest.php` | что у каждого ограничения из `CarSchema` есть вход, отбиваемый как `422`, и что до хранилища такой запрос не доходит |
| `tests/unit/MediaTypeTest.php` | нормализацию `Content-Type` во всех начертаниях, допустимых по RFC 7231 |

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
