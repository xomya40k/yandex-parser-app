# Yandex Maps Reviews Parser

Laravel API + Vue 3 SPA: подключение карточки организации на Яндекс.Картах, сбор отзывов и постраничный вывод (50 на страницу).

Ниже: как поднять проект, как устроен парсер и ответы на «Дополнительные требования» из ТЗ.

---

## Быстрый старт (локально)

Требования: PHP 8.3+, Composer, Node.js 20+, SQLite (по умолчанию) или MySQL/PostgreSQL.

```bash
composer setup
# эквивалент: composer install → .env → key:generate → migrate → npm install → npm run build

php artisan db:seed
php artisan serve
# в другом терминале (для hot-reload фронта):
npm run dev
```

Либо одной командой разработки:

```bash
composer dev
```

Приложение по умолчанию: `http://localhost:8000`.

**Docker / Sail.** Отдельного `docker-compose.yml` в репозитории нет; при необходимости можно поднять стек через [Laravel Sail](https://laravel.com/docs/sail) (`composer require laravel/sail --dev` уже в `require-dev`, далее `php artisan sail:install`).

### Учётная запись (сид)

| Поле | Значение по умолчанию | Переопределение |
|------|----------------------|-----------------|
| Email | `test@example.com` | `SEED_USER_EMAIL` |
| Password | `password` | `SEED_USER_PASSWORD` |

### Синхронизация организаций

Парсинг **не** запускается из HTTP при сохранении ссылки. После `POST /api/organization` карточка остаётся в статусе `pending`, а данные подтягивает команда:

```bash
php artisan yandex:sync-organizations
```

В расписании (`routes/console.php`): каждые 5 минут, `withoutOverlapping()`. Для локального cron:

```bash
php artisan schedule:work
```

---

## Переменные окружения

Скопируйте `.env.example` → `.env`. Ключевые параметры:

| Переменная | Назначение | По умолчанию |
|------------|------------|--------------|
| `APP_URL` | URL приложения (Sanctum / SPA) | `http://localhost:8000` |
| `SANCTUM_STATEFUL_DOMAINS` | Домены cookie-auth | `localhost,...` |
| `DB_*` | БД (по умолчанию SQLite) | sqlite |
| `CACHE_STORE` | Кэш листинга отзывов | `database` |
| `QUEUE_CONNECTION` | Очередь (заготовка под следующий этап) | `database` |
| `YANDEX_PARSER_MAX_PAGES` | Макс. страниц `?page=N` (~600 отзывов) | `12` |
| `YANDEX_PARSER_REQUEST_DELAY_MS` | Базовая пауза перед HTTP-запросом (с джиттером) | `500` |
| `YANDEX_PARSER_TIMEOUT` | Таймаут HTTP, сек | `20` |
| `YANDEX_PARSER_USER_AGENT` | User-Agent (опционально) | Chrome-like |
| `YANDEX_HTTP_PROXY` | Опциональный HTTP(S)-прокси | — |
| `YANDEX_REVIEWS_CACHE_TTL` | TTL кэша страниц отзывов, сек | `3600` |
| `YANDEX_REPARSE_INTERVAL_HOURS` | Интервал повторного парсинга Ready/Failed | `24` |

Полный список — в `.env.example`, конфиг парсера — `config/yandex.php`.

---

## API (кратко)

| Метод | Путь | Описание |
|-------|------|----------|
| `POST` | `/api/login` | Вход (Sanctum cookie) |
| `POST` | `/api/logout` | Выход |
| `GET` | `/api/me` | Текущий пользователь |
| `GET` | `/api/organization` | Текущая организация |
| `POST` | `/api/organization` | Сохранить ссылку Яндекс.Карт |
| `GET` | `/api/organization/reviews` | Отзывы, `page` / `per_page` (до 50) |

---

## Архитектура парсинга

Слои (см. также правила проекта):

```
Command (yandex:sync-organizations)
  → OrganizationSyncService::sync()
    → YandexParserInterface (YandexMapParserService)
      → YandexMapsIntegrationClient (HTTP)
      → YandexOrganizationUrlResolver (Support)
    → ReviewRepository / OrganizationRepository / OrganizationSnapshotRepository
    → ReviewService::invalidateCache()

ReviewController → ReviewService (кэшированная пагинация) → ReviewRepository
```

Интерфейс `YandexParserInterface` — точка замены стратегии (например, headless) без смены оркестрации.

---

## Дополнительные требования ТЗ

### 1. Устойчивость к смене разметки — как парсер понимает, что сломался

Парсер **не** молча возвращает пустоту. На каждом ответе:

1. **Captcha / soft-ban** (`CaptchaRequiredException`) — HTTP `403`/`429` или тело с маркерами `showcaptcha` / `smartcaptcha` / `captcha.yandex` при отсутствии полезного payload отзывов.
2. **Смена вёрстки / JSON** (`InvalidLayoutException`) — нет `<script class="state-view|config-view">`, JSON не парсится, отсутствуют ожидаемые пути (`ratingData.*`, `reviewResults.reviews` и т.д.). Пути вынесены в константы в `DetectsYandexAntiBotTrait` — при сдвиге ключей правка точечная.
3. **Пустой/бесполезный ответ** (`EmptyYandexResponseException`) — на первой странице нет отзывов и нет рейтинга/счётчиков.
4. **Страница недоступна** (`OrganizationUnavailableException`) — сеть, таймаут, однозначные `404`/`410`/`5xx`.

При ошибке `OrganizationSyncService` ставит организации статус `failed`, пишет в лог `organization_id`, URL, `error_code` и класс исключения. Команда батча ловит сбой по одной организации и продолжает остальные.

### 2. Обоснование подхода к парсингу

**Выбрано:** разбор **server-rendered embedded JSON** на странице  
`https://yandex.ru/maps/org/{id}/reviews/?page=N`  
(тег `state-view` / `config-view`), обход страниц `1..max_pages` (по умолчанию 12).

| Подход | Плюсы | Минусы / риски |
|--------|-------|----------------|
| **Embedded JSON + `?page=N` (наш)** | Нет Chromium/Node; не нужен JS-токен `s`; простой деплой; до ~50 отзывов на страницу; короткий HTTP-цикл | Хрупкость к смене путей в JSON / класса скрипта; капча на HTML; лимит выдачи Яндекса (~600) |
| **Signed `fetchReviews` XHR** | Ближе к «живому» клиенту, потенциально те же данные | Нужен одноразовый `s`-токен из JS; сильнее rate-limit; сложнее и нестабильнее воспроизводить |
| **Headless-браузер** | Устойчивее к JS-only UI, проще «как пользователь» | Тяжёлый рантайм, медленнее, дороже в ops; капча всё равно возможна |

Почему не XHR: токен и антибот вокруг `fetchReviews` делают решение хрупче без выигрыша по объёму (страница уже отдаёт до 50 отзывов).  
Почему не headless на этом этапе: избыточная инфраструктура для тестового объёма; интерфейс парсера позволяет подменить реализацию позже.

### 3. Масштаб и фоновая обработка (cron сейчас → queue следующим этапом)

Синхронный парсинг внутри HTTP-запроса **не используется**.

**Сейчас реализовано:**

- Artisan-команда `yandex:sync-organizations` + schedule каждые 5 минут.
- Выбор организаций: `pending`, либо `ready`/`failed` старше `YANDEX_REPARSE_INTERVAL_HOURS`.
- Per-org `Cache::lock(...)`, чтобы параллельные запуски не дублировали работу.
- Ошибки по одной организации не роняют весь батч.

**Следующий этап (не в этом PR-срезе):** обернуть тот же `OrganizationSyncService::sync()` в `ParseOrganizationReviewsJob`, диспатчить из команды вместо прямого вызова, добавить индикацию прогресса (страница N из M / % отзывов) и retry политики очереди. Оркестрация синхронизации при этом не меняется — меняется только способ запуска.

### 4. Анти-бан на объёме

**Уже в коде:**

- Пауза перед каждым HTTP-запросом с джиттером вокруг `YANDEX_PARSER_REQUEST_DELAY_MS` (`YandexMapsIntegrationClient::throttle`).
- Реалистичные заголовки (`User-Agent`, `Accept-Language: ru`).
- Retry только на транспортных сбоях; `403`/`429` **не** ретраятся вслепую (их классифицирует парсер как captcha).
- Опциональный прокси: `YANDEX_HTTP_PROXY`.
- Пейсинг батча через cron + `withoutOverlapping` + per-org lock.
- При captcha/бане: статус `failed`, лог с кодом ошибки — без «тихого» пустого успеха.

**Описано / задел на прод (не реализовано полностью):**

- Ротация пула прокси и User-Agent.
- Экспоненциальный backoff и circuit-breaker при серии captcha.
- Отдельные воркеры/квоты на сеть филиалов (~50 карточек × ~12 страниц).

### 5. Идемпотентность и история изменений

**Идемпотентность отзывов:** уникальный ключ `(organization_id, external_id)` в таблице `reviews`; повторный парсинг идёт через `ReviewRepository::updateOrCreate` — дубликаты не создаются, поля обновляются.

**Снимки:** таблица `organization_snapshots` на каждый успешный sync:

- агрегаты: `rating`, `total_ratings`, `total_reviews`, `reviews_count`, `captured_at`;
- `payload` JSON вида `{ "before": {...}|null, "after": {...} }` — diff с предыдущим снимком («было → стало»).

После успешного persist инвалидируется version-keyed кэш листинга отзывов (`ReviewService`), чтобы API сразу отдавал актуальные страницы без зависимости от cache tags.

---

## Что доделали бы при большем запасе времени

- Queue job + прогресс парсинга по организации (п. 3 выше).
- Ротация прокси/UA и более жёсткий backoff при captcha.
- UI: явный индикатор статуса `parsing` / `failed` и кнопка «обновить сейчас» (dispatch job).
- API истории снимков (`organization_snapshots`) для сравнения «было → стало» в интерфейсе.
- Docker Compose «из коробки» для сдачи одной командой.
- E2E против живой карточки Яндекса в CI (сейчас — HTML-фикстуры, без сети).

---

## Тесты

```bash
composer test
# или: php artisan test
```

Фикстуры HTML/JSON: `tests/Fixtures/yandex/` — unit/feature не ходят в сеть.
