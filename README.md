# Phone Management API

REST API для управления телефонными номерами клиентов.

## Стек

- **PHP 8.3** + **Symfony 7.4**
- **PostgreSQL 16**
- **Redis 7** (кэширование)
- **Docker / Docker Compose**
- **Swagger UI** (NelmioApiDocBundle)
- **PHPUnit 12**

---

## Быстрый старт

### 1. Клонировать репозиторий

```bash
git clone <repo-url>
cd phone_management
```

### 2. Запуск в режиме разработки (dev)

```bash
# Скопировать файл окружения (или использовать .env по умолчанию)
cp .env .env.local

# Поднять контейнеры
docker compose up -d --build

# Применить миграции
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

API доступно на: **http://localhost:8080/api/numbers**  
Swagger UI: **http://localhost:8080/api/doc**

---

### 3. Запуск в режиме preproduction

```bash
docker compose -f docker-compose.yml -f docker-compose.preprod.yml --env-file .env.preprod up -d --build

docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 4. Запуск в режиме production

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml --env-file .env.prod up -d --build

docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

---

## Переменные окружения

| Переменная        | По умолчанию        | Описание                  |
|-------------------|---------------------|---------------------------|
| `APP_ENV`         | `dev`               | Окружение Symfony         |
| `APP_SECRET`      | (обязательно)       | Секретный ключ Symfony    |
| `POSTGRES_DB`     | `phone_management`  | Имя базы данных           |
| `POSTGRES_USER`   | `app`               | Пользователь БД           |
| `POSTGRES_PASSWORD` | `secret`          | Пароль БД                 |
| `POSTGRES_HOST`   | `postgres`          | Хост PostgreSQL           |
| `REDIS_HOST`      | `redis`             | Хост Redis                |
| `NGINX_PORT`      | `8080`              | Внешний порт Nginx        |

---

## API Endpoints

### GET /api/numbers
Получить список номеров с пагинацией, фильтрацией и сортировкой.

| Параметр     | Тип    | Описание                                  |
|--------------|--------|-------------------------------------------|
| `status`     | string | Фильтр по статусу: `active`, `blocked`, `archived` |
| `tariff`     | string | Фильтр по тарифу                          |
| `search`     | string | Поиск по номеру (подстрока)               |
| `sort_by`    | string | Сортировка: `createdAt`, `updatedAt`      |
| `sort_order` | string | Направление: `ASC`, `DESC`               |
| `page`       | int    | Номер страницы (по умолчанию: 1)          |
| `limit`      | int    | Элементов на странице (по умолчанию: 20)  |

```bash
curl "http://localhost:8080/api/numbers?status=active&tariff=business&page=1&limit=20"
```

### GET /api/numbers/{id}
Получить номер по UUID.

```bash
curl "http://localhost:8080/api/numbers/c707ebc1-b8e6-4e13-b184-137df26e1f82"
```

### POST /api/numbers
Создать новый номер. Статус устанавливается `active` автоматически.

```bash
curl -X POST http://localhost:8080/api/numbers \
  -H "Content-Type: application/json" \
  -d '{"number": "46700000001", "tariff": "business"}'
```

### PATCH /api/numbers/{id}
Обновить статус и/или тариф. Номера в статусе `archived` изменить нельзя.

```bash
curl -X PATCH http://localhost:8080/api/numbers/c707ebc1-b8e6-4e13-b184-137df26e1f82 \
  -H "Content-Type: application/json" \
  -d '{"status": "blocked", "tariff": "premium"}'
```

---

## Коды ответов

| Код | Описание               |
|-----|------------------------|
| 200 | OK                     |
| 201 | Created                |
| 204 | No Content             |
| 400 | Bad Request            |
| 404 | Not Found              |
| 422 | Validation Error       |

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

## Запуск тестов

### Unit тесты (без БД, без Docker)

```bash
php vendor/bin/phpunit tests/Unit --no-coverage
```

### Функциональные тесты (требуют запущенных сервисов)

```bash
# Создать тестовую БД
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction

# Запустить все тесты
php vendor/bin/phpunit --no-coverage
```

---

## Кэширование

- Список номеров кэшируется в Redis на **60 секунд** (ключ включает все параметры запроса)
- Отдельный номер кэшируется на **300 секунд**
- Кэш инвалидируется при создании / обновлении номера

---

## Структура проекта

```
src/
├── Controller/
│   └── NumberController.php    # API endpoints
├── Dto/
│   ├── CreateNumberDto.php     # Валидация создания
│   └── UpdateNumberDto.php     # Валидация обновления
├── Entity/
│   └── Number.php              # Doctrine entity
├── Enum/
│   └── NumberStatus.php        # Статусы (active/blocked/archived)
├── Repository/
│   └── NumberRepository.php    # Запросы к БД с фильтрацией
└── Service/
    └── NumberService.php       # Бизнес-логика

migrations/
└── Version20260523000001.php   # Создание таблицы numbers

tests/
├── Unit/
│   ├── Entity/NumberTest.php
│   └── Service/NumberServiceTest.php
└── Functional/
    └── Controller/NumberControllerTest.php
```
