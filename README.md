# Phone Management API

REST API для управления телефонными номерами клиентов.

## Стек

- **PHP 8.3** + **Symfony 7.4**
- **PostgreSQL 16**
- **Redis 7** (кэширование с тегами через `TagAwareCacheInterface`)
- **Docker / Docker Compose**
- **Swagger UI** (NelmioApiDocBundle + Twig)
- **PHPUnit 12**

---

## Быстрый старт

### 1. Клонировать репозиторий

```bash
git clone <repo-url>
cd phone_management
```

### 2. Запуск (dev — из коробки)

```bash
docker compose up -d --build
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

API: **[http://localhost:8080/api/numbers](http://localhost:8080/api/numbers)**  
Swagger UI: **[http://localhost:8080/api/doc](http://localhost:8080/api/doc)**

---

### 3. Запуск в режиме preproduction

```bash
cp .env.preprod .env.local
docker compose up -d --build
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 4. Запуск в режиме production

```bash
cp .env.prod .env.local
docker compose up -d --build
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

---

## Переменные окружения

Все переменные задаются в `.env` (дефолтные) или `.env.local` (локальные переопределения).

| Переменная          | По умолчанию       | Описание                       |
|---------------------|--------------------|--------------------------------|
| `APP_ENV`           | `dev`              | Окружение Symfony              |
| `APP_SECRET`        | `changeme...`      | Секретный ключ (менять в prod) |
| `POSTGRES_DB`       | `phone_management` | Имя базы данных                |
| `POSTGRES_USER`     | `app`              | Пользователь БД                |
| `POSTGRES_PASSWORD` | `secret`           | Пароль БД                      |
| `NGINX_PORT`        | `8080`             | Внешний порт Nginx             |
| `POSTGRES_PORT`     | `5432`             | Внешний порт PostgreSQL        |
| `REDIS_PORT`        | `6379`             | Внешний порт Redis             |

---

## API Endpoints

### GET /api/numbers

Список номеров с пагинацией, фильтрацией, поиском и сортировкой.

| Параметр     | Тип    | Описание                                        |
|--------------|--------|-------------------------------------------------|
| `status`     | string | Фильтр: `active`, `blocked`, `archived`         |
| `tariff`     | string | Фильтр по тарифу                                |
| `search`     | string | Поиск по номеру (подстрока)                     |
| `sort_by`    | string | Поле сортировки: `created_at`, `updated_at`     |
| `sort_order` | string | Направление: `asc`, `desc` (по умолчанию `desc`)|
| `page`       | int    | Номер страницы (по умолчанию: `1`)              |
| `limit`      | int    | Элементов на странице (по умолчанию: `20`)      |

```bash
curl "http://localhost:8080/api/numbers?status=active&tariff=business&sort_by=created_at&sort_order=asc&page=1&limit=20"
```

### GET /api/numbers/{id}

```bash
curl "http://localhost:8080/api/numbers/c707ebc1-b8e6-4e13-b184-137df26e1f82"
```

### POST /api/numbers

Статус устанавливается `active` автоматически. Номер — только цифры, не более 15.

```bash
curl -X POST http://localhost:8080/api/numbers \
  -H "Content-Type: application/json" \
  -d '{"number": "46700000001", "tariff": "business"}'
```

### PATCH /api/numbers/{id}

Можно изменить только `status` и/или `tariff`. Номер в статусе `archived` изменить нельзя.

```bash
curl -X PATCH http://localhost:8080/api/numbers/c707ebc1-b8e6-4e13-b184-137df26e1f82 \
  -H "Content-Type: application/json" \
  -d '{"status": "blocked", "tariff": "premium"}'
```

---

## Коды ответов

| Код | Описание         |
|-----|------------------|
| 200 | OK               |
| 201 | Created          |
| 400 | Bad Request      |
| 404 | Not Found        |
| 422 | Validation Error |

### Формат ошибки валидации

```json
[
  {
    "error": "validation_error",
    "details": {
      "number": "number must contain only digits"
    }
  }
]
```

---

## Swagger / OpenAPI

### Интерактивный UI (в браузере)

Откройте в браузере: [http://localhost:8080/api/doc](http://localhost:8080/api/doc)

### Генерация JSON-спецификации (CLI)

```bash
# Внутри контейнера
docker compose exec php php bin/console nelmio:apidoc:dump --format=json > openapi.json

# Или локально (если PHP установлен)
php bin/console nelmio:apidoc:dump --format=json > openapi.json
```

---

## Кэширование

Используется `TagAwareCacheInterface` (Redis, пул `numbers.cache`):

| Что кэшируется   | TTL      | Тег инвалидации              |
|------------------|----------|------------------------------|
| Список номеров   | 60 сек   | `numbers_list`               |
| Отдельный номер  | 300 сек  | `numbers_list`, `number_{id}`|

При создании нового номера инвалидируется тег `numbers_list`.  
При обновлении номера инвалидируются теги `numbers_list` и `number_{id}`.

---

## Тесты

### Unit тесты (без БД, без Docker)

```bash
php vendor/bin/phpunit tests/Unit --no-coverage
```

### Функциональные тесты (требуют запущенных сервисов)

```bash
# Создать тестовую БД и применить миграции
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test --no-interaction

# Запустить все тесты
php vendor/bin/phpunit --no-coverage
```

Или внутри Docker:

```bash
docker compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction
docker compose exec php php vendor/bin/phpunit --no-coverage
```

---

## Структура проекта

```text
src/
├── Controller/NumberController.php   # Все API endpoints
├── Dto/
│   ├── CreateNumberDto.php           # Валидация POST
│   └── UpdateNumberDto.php           # Валидация PATCH
├── Entity/Number.php                 # Doctrine entity (UUID, lifecycle callbacks)
├── Enum/NumberStatus.php             # active | blocked | archived
├── Repository/NumberRepository.php   # Фильтрация, сортировка, пагинация
└── Service/NumberService.php         # Бизнес-логика создания и обновления

migrations/
└── Version20260523000001.php         # Создание таблицы numbers + индексы

tests/
├── Unit/
│   ├── Entity/NumberTest.php
│   └── Service/NumberServiceTest.php
└── Functional/
    └── Controller/NumberControllerTest.php  # Включает тесты инвалидации кэша
```
