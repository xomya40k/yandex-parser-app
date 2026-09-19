# Yandex Maps Reviews Parser

Laravel API + Vue 3 SPA: подключение карточки организации на Яндекс.Картах, сбор отзывов и постраничный вывод.

## Содержание

- [Yandex Maps Reviews Parser](#yandex-maps-reviews-parser)
  - [Содержание](#содержание)
  - [Сдача](#сдача)
  - [Быстрый старт](#быстрый-старт)
    - [Docker (рекомендуется)](#docker-рекомендуется)
    - [Локально без Docker](#локально-без-docker)
  - [Учётная запись и очередь](#учётная-запись-и-очередь)
    - [Сид](#сид)
    - [Парсинг](#парсинг)
  - [Переменные окружения](#переменные-окружения)
  - [Фронтенд](#фронтенд)
    - [Авторизация](#авторизация)
    - [Дашборд организации](#дашборд-организации)
  - [API](#api)
    - [Контракт опроса (`GET /api/organization/parse-run`)](#контракт-опроса-get-apiorganizationparse-run)
  - [Архитектура парсинга](#архитектура-парсинга)
  - [Дополнительные требования](#дополнительные-требования)
    - [1. Устойчивость к смене разметки](#1-устойчивость-к-смене-разметки)
    - [2. Масштаб и фоновая обработка](#2-масштаб-и-фоновая-обработка)
    - [4. Анти-бан на объёме](#4-анти-бан-на-объёме)
    - [5. Идемпотентность и история изменений](#5-идемпотентность-и-история-изменений)
  - [Что доделали бы при большем запасе времени](#что-доделали-бы-при-большем-запасе-времени)
  - [Тесты](#тесты)

---

## Сдача

|                 |                                       |
| --------------- | ------------------------------------- |
| Живой прототип  | `_заполнить: https://YOUR_DOMAIN_`    |
| Git-репозиторий | `_заполнить: https://github.com/..._` |
| Логин (сид)     | `test@example.com` / `password`       |

---

## Быстрый старт

### Docker (рекомендуется)

Требования: Docker Engine + Docker Compose plugin.

```bash
cp .env.docker.example .env
# 1) Задайте DB_PASSWORD / DB_ROOT_PASSWORD
# 2) При необходимости APP_URL / SANCTUM_STATEFUL_DOMAINS
# APP_KEY можно оставить пустым — entrypoint сгенерирует его при первом старте
#    и сохранит в storage volume (общий для app/queue/scheduler).
#    Либо задайте явно: php artisan key:generate --show

docker compose build
docker compose up -d
```

Приложение: `http://localhost:8080` при `HTTP_PORT=8080`.

| Сервис      | Роль                                                          |
| ----------- | ------------------------------------------------------------- |
| `nginx`     | HTTP(S), статика `public/build`, FastCGI → php-fpm            |
| `app`       | php-fpm; при старте: migrate + seed + config/route/view cache |
| `queue`     | `queue:work --queue=parsing` (Redis)                          |
| `scheduler` | `schedule:work` (`yandex:queue-parsing` каждые 5 мин)         |
| `mysql`     | MySQL 8.4 (порт наружу не публикуется)                        |
| `redis`     | очередь + кэш (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`) |
| `certbot`   | профиль `certbot`; запуск вручную для TLS                     |

```bash
docker compose logs -f app queue scheduler nginx
docker compose exec app php artisan about
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose down
```

Тот же Compose-стек используется для публичного HTTPS-прототипа (nginx + Let's Encrypt через профиль `certbot`).

### Локально без Docker

Требования: PHP 8.3+, Composer, Node.js 20+, SQLite (по умолчанию) или MySQL/PostgreSQL.

```bash
composer setup
# composer install → .env → key:generate → migrate → npm install → npm run build

php artisan db:seed
php artisan serve
```

В отдельных терминалах:

```bash
npm run dev                              # hot-reload фронта
php artisan queue:work --queue=parsing  # воркер парсинга
```

Или одной командой разработки:

```bash
composer dev
```

Приложение: `http://localhost:8000`. Очередь по умолчанию — `database` (см. `.env.example`); в Docker — Redis.

---

## Учётная запись и очередь

### Сид

| Поле     | Значение по умолчанию | Переопределение      |
| -------- | --------------------- | -------------------- |
| Email    | `test@example.com`    | `SEED_USER_EMAIL`    |
| Password | `password`            | `SEED_USER_PASSWORD` |

### Парсинг

После `POST /api/organization` (или `POST /api/organization/parse-run`) создаётся запись в `parse_runs` и в очередь `parsing` ставится `ParseOrganizationJob`. Без воркера job не выполнится.

```bash
# локально:
php artisan queue:work --queue=parsing
# в Docker воркер уже запущен как сервис queue
```

Страховочная переочередь и удаление «зависших» запусков:

```bash
php artisan yandex:queue-parsing
```

В расписании (`routes/console.php`): каждые 5 минут, `withoutOverlapping()`; еженедельно — `queue:prune-failed --hours=168`.

```bash
php artisan schedule:work   # локальный cron
# в Docker — сервис scheduler
```

---

## Переменные окружения

Скопируйте `.env.example` → `.env` (локально) или `.env.docker.example` → `.env` (Docker).

| Переменная                       | Назначение                                          | По умолчанию                            |
| -------------------------------- | --------------------------------------------------- | --------------------------------------- |
| `APP_URL`                        | URL приложения (Sanctum / SPA)                      | `http://localhost:8000`                 |
| `SANCTUM_STATEFUL_DOMAINS`       | Домены cookie-auth                                  | `localhost,...`                         |
| `TRUSTED_PROXIES`                | IP/CIDR nginx (CSV) или `*`; пусто = не доверять `X-Forwarded-*` | пусто / в Docker приватные сети |
| `DB_*`                           | БД (локально SQLite; в Docker — MySQL)              | sqlite / mysql                          |
| `CACHE_STORE`                    | Кэш листинга отзывов + locks                        | `database` / в Docker `redis`           |
| `QUEUE_CONNECTION`               | Драйвер очереди                                     | `database` / в Docker `redis`           |
| `DB_ROOT_PASSWORD`               | Root-пароль MySQL-контейнера (только Docker)        | —                                       |
| `HTTP_PORT` / `HTTPS_PORT`       | Проброс портов nginx                                | `80` / `443`                            |
| `YANDEX_PARSER_MAX_PAGES`        | Макс. страниц `?page=N` (~600 отзывов)              | `12`                                    |
| `YANDEX_PARSER_REQUEST_DELAY_MS` | Базовая пауза перед HTTP-запросом                   | `500`                                   |
| `YANDEX_PARSER_TIMEOUT`          | Таймаут HTTP, сек                                   | `20`                                    |
| `YANDEX_PARSER_USER_AGENT`       | User-Agent (опционально)                            | Chrome-like                             |
| `YANDEX_HTTP_PROXY`              | Опциональный HTTP(S)-прокси                         | —                                       |
| `YANDEX_REVIEWS_CACHE_TTL`       | TTL кэша страниц отзывов, сек                       | `3600`                                  |
| `YANDEX_REPARSE_INTERVAL_HOURS`  | Интервал повторного парсинга Ready/Failed           | `24`                                    |
| `YANDEX_QUEUE_NAME`              | Имя очереди воркера                                 | `parsing`                               |
| `YANDEX_PARSER_TRIES`            | Число попыток job                                   | `3`                                     |
| `YANDEX_PARSER_JOB_TIMEOUT`      | Таймаут одной попытки, сек                          | `900`                                   |
| `YANDEX_PARSER_BACKOFF`          | Паузы между попытками, сек (CSV)                    | `60,300,900`                            |
| `YANDEX_QUEUE_DISPATCH_SPACING`  | Задержка между cron-диспатчами, сек                 | `10`                                    |
| `YANDEX_STALE_RUN_MINUTES`       | Через сколько минут `processing` считается зависшим | `30`                                    |

Полный список — в `.env.example`, конфиг парсера — `config/yandex.php`.

---

## Фронтенд

### Авторизация

SPA (Vue 3 + Pinia + Vue Router) — один Blade-entrypoint `resources/views/app.blade.php`. Cookie-based Sanctum.

| Маршрут | Доступ                    | Назначение                  |
| ------- | ------------------------- | --------------------------- |
| `/login`| гость (`meta.guestOnly`)  | форма входа                 |
| `/`     | авторизован (`meta.requiresAuth`)| настройки + дашборд отзывов |

Поток:

1. `GET /sanctum/csrf-cookie` — `resources/js/api/csrf.ts` (сырой axios, не `/api`-клиент).
2. `POST /api/login` / `POST /api/logout` / `GET /api/me` — `resources/js/api/auth.ts` (`withCredentials` + `withXSRFToken`).
3. Pinia `useAuthStore` (`stores/auth.ts`) — `ensureInitialized()` один раз дергает `/api/me`.
4. `router.beforeEach` ждёт инициализацию; гостя с защищённых страниц → `/login?redirect=…`, авторизованного с `/login` → `/`.
5. При `401` Axios-интерцептор чистит сессию и уводит на login, если маршрут защищён.

Учётные данные сида: `test@example.com` / `password`.

### Дашборд организации

Экран `/` (`SettingsView.vue`) — настройки и данные на одной странице. Три Pinia-хранилища = три бэкенд-агрегата; оркестрация переходов — во view.

| Слой   | Файлы                                                               | Роль                                                    |
| ------ | ------------------------------------------------------------------- | ------------------------------------------------------- |
| API    | `api/organization.ts`, `api/parse-run.ts`, `api/reviews.ts`         | Обёртки над `/api/organization*`; пустой run → `null`   |
| Stores | `stores/organization.ts`, `stores/parseRun.ts`, `stores/reviews.ts` | Форма, опрос прогресса, постраничные отзывы             |
| UI     | `components/organization/*`, `components/reviews/*`                 | URL, баннер прогресса/ошибок, сводка, таблица, пагинация|

**Опрос прогресса.** `useParseRunStore.refresh()` / `trigger()` → `GET`/`POST /api/organization/parse-run`, затем повторный запрос через `setTimeout` (~2.5 с), пока статус `pending`/`processing`. На `completed`/`failed` — стоп; `onUnmounted` → `stopPolling()`. После активного статуса view перезагружает организацию и (при `completed`) первую страницу отзывов.

**Ошибки.** `ParseProgressBanner` показывает `describeParseRunError(error_code)`; для `invalid_layout` — явный текст про смену разметки; «Повторить» → `POST /api/organization/parse-run`.

**Пагинация.** `GET /api/organization/reviews?page=N&per_page=50` без перезагрузки; `PaginationControls` → `reviewsStore.fetchPage(page)`.

---

## API

| Метод  | Путь                          | Описание                                           |
| ------ | ----------------------------- | -------------------------------------------------- |
| `POST` | `/api/login`                  | Вход (Sanctum cookie)                              |
| `POST` | `/api/logout`                 | Выход                                              |
| `GET`  | `/api/me`                     | Текущий пользователь                               |
| `GET`  | `/api/organization`           | Текущая организация                                |
| `POST` | `/api/organization`           | Сохранить ссылку Яндекс.Карт (+ enqueue parse run) |
| `GET`  | `/api/organization/reviews`   | Отзывы, `page` / `per_page` (до 50)                |
| `POST` | `/api/organization/parse-run` | Явно поставить парсинг в очередь (`202`)           |
| `GET`  | `/api/organization/parse-run` | Статус / прогресс последнего run (опрос)           |

### Контракт опроса (`GET /api/organization/parse-run`)

Ответ — `{"data": {...}}` либо `{"data": null}`, если run ещё не создавался.

| Поле                                       | Смысл                                                 |
| ------------------------------------------ | ----------------------------------------------------- |
| `status`                                   | `pending` \| `processing` \| `completed` \| `failed`  |
| `processed_reviews` / `total_reviews`      | Счётчики (total может быть `null` до первой страницы) |
| `processed_pages`                          | Сколько страниц уже обработано                        |
| `progress_percent`                         | `0..100` или `null`, пока неизвестен знаменатель      |
| `attempt` / `max_attempts`                 | Текущая попытка job и лимит                           |
| `error_code` / `error_message`             | Код/текст последней ошибки                            |
| `queued_at` / `started_at` / `finished_at` | ISO-8601 метки жизненного цикла                       |

`OrganizationStatus` (карточка: `pending` / `parsing` / `ready` / `failed`) и `ParseRunStatus` (попытка) — разные сущности: run отражает конкретный прогон, статус организации — итог по карточке.

---

## Архитектура парсинга

```
POST /api/organization | POST /api/organization/parse-run | yandex:queue-parsing
  → ParseRunService::queue()
    → ParseOrganizationJob (ShouldQueue, queue=parsing)
      → OrganizationSyncService::sync()
        → YandexParserInterface (YandexMapParserService)
          → YandexMapsIntegrationClient (HTTP)
          → YandexOrganizationUrlResolver (Support)
          → event YandexReviewsPageParsed (на каждую страницу)
            → UpdateParseRunProgress → ParseRunService::recordProgress()
        → ReviewRepository / OrganizationRepository / OrganizationSnapshotRepository
        → ReviewService::invalidateCache()

GET /api/organization/parse-run → ParseRunService::latest() → ParseRunResource
ReviewController → ReviewService (кэшированная пагинация) → ReviewRepository
```

`YandexParserInterface` — точка замены стратегии (например, headless) без смены оркестрации.

---

## Дополнительные требования

### 1. Устойчивость к смене разметки

Парсер **не** молча возвращает пустоту. На каждом ответе:

1. **Captcha / soft-ban** (`CaptchaRequiredException`, `captcha_required`) — HTTP `403`/`429` или тело с маркерами `showcaptcha` / `smartcaptcha` / `captcha.yandex` при отсутствии полезной нагрузки.
2. **Смена вёрстки / JSON** (`InvalidLayoutException`, `invalid_layout`) — нет `<script class="state-view|config-view">`, JSON не парсится, отсутствуют ожидаемые пути (`ratingData.*`, `reviewResults.reviews` и т.д.). Пути — константы в `DetectsYandexAntiBotTrait`.
3. **Пустой ответ** (`EmptyYandexResponseException`, `empty_yandex_response`) — на первой странице нет отзывов и нет рейтинга/счётчиков.
4. **Страница недоступна** (`OrganizationUnavailableException`, `organization_unavailable`) — сеть, таймаут, `404`/`410`/`5xx`.

Сигналы:

- лог с `organization_id`, URL, `error_code`, `parse_run_id`, `attempt`;
- `parse_runs.error_code` / `error_message` — SPA видит причину через опрос статуса;
- при терминальном сбое: организация и попытка парсинга → `failed`.

`invalid_layout` сразу терминален: повторный запуск не поможет, если контракт разметки изменился.

### 2. Масштаб и фоновая обработка

Синхронный парсинг внутри HTTP **не используется**.

| Компонент                  | Роль                                                                                                    |
| -------------------------- | ------------------------------------------------------------------------------------------------------- |
| `parse_runs`               | Статус попытки, счётчики, ошибки                                                                        |
| `ParseRunService::queue()` | Дедупликация активной попытки парсинга + `ParseOrganizationJob::dispatch` под `Cache::lock`             |
| `ParseOrganizationJob`     | `tries` / `backoff` / `timeout`, `WithoutOverlapping`                                                   |
| `YandexReviewsPageParsed`  | Событие на каждую страницу → прогресс в `parse_runs`                                                    |
| API для опроса             | `GET /api/organization/parse-run`                                                                       |

**Триггеры**

1. `POST /api/organization` — после сохранения ссылки → `ParseRunService::queue()`.
2. `POST /api/organization/parse-run` — явный повторный парсинг `202`.
3. `yandex:queue-parsing` (cron) — сначала `reapStale()`, затем постановка в очередь организаций, которым пора обновиться, с шагом `YANDEX_QUEUE_DISPATCH_SPACING` между ними (анти-бан для ~50 филиалов).

Повторный `queue()` при активном run возвращает тот же run и **не** ставит второй job.

```bash
php artisan queue:work --queue=parsing
```

`QUEUE_CONNECTION=database` по умолчанию; таблицы `jobs` / `failed_jobs` обязательны.

**Матрица повторных попыток**

| `error_code`               | Повторяем? | Поведение                                                                 |
| -------------------------- | ---------- | ------------------------------------------------------------------------- |
| `organization_unavailable` | да         | Laravel повторяет попытку с задержками `60 → 300 → 900` секунд            |
| `captcha_required`         | да         | то же (пауза — правильный ответ на мягкую блокировку)                    |
| `empty_yandex_response`    | да         | то же (возможен временный ухудшенный ответ Яндекса)                       |
| `invalid_layout`           | **нет**    | `$this->fail()` сразу → `failed_jobs`, run/org `failed`                   |
| прочее / timeout           | —          | по исчерпании `tries` → `failed()` терминально                            |

После неуспешной, но не терминальной попытки: run снова становится `pending`, организация остаётся `parsing` (не мигает в `failed`).

**Очистка зависших запусков.** Если воркер умер посреди выполнения, run мог остаться в `processing` и блокировать дедупликацию. `ParseRunService::reapStale()` (вызывается из cron) помечает такие runs как `failed` с `error_code=stale_run`, если `started_at` старше `YANDEX_STALE_RUN_MINUTES` (по умолчанию 30).

### 4. Анти-бан на объёме

Уже в коде:

- пауза перед каждым HTTP с джиттером вокруг `YANDEX_PARSER_REQUEST_DELAY_MS`;
- реалистичные заголовки (`User-Agent`, `Accept-Language: ru`);
- повторные запросы — только при транспортных сбоях; `403`/`429` не повторяются вслепую в HTTP-клиенте (дальше парсер сам классифицирует их как captcha и откладывает через очередь);
- опциональный прокси: `YANDEX_HTTP_PROXY`;
- распределение запуска задач по времени в cron (`YANDEX_QUEUE_DISPATCH_SPACING`) — задачи не стартуют пачкой;
- `WithoutOverlapping` у job — не более одной параллельной попытки на организацию;
- при captcha/бане: код в `parse_runs` + лог, без «тихого» пустого успеха.

Задел на будущее (не реализовано полностью): ротация пула прокси/UA, circuit-breaker при серии captcha, отдельные квоты и воркеры на каждого клиента.

### 5. Идемпотентность и история изменений

**Отзывы:** уникальный ключ `(organization_id, external_id)`; повторный парсинг — `ReviewRepository::updateOrCreate` (без дубликатов).

**Снимки:** таблица `organization_snapshots` на каждый успешный sync:

- агрегаты: `rating`, `total_ratings`, `total_reviews`, `reviews_count`, `captured_at`;
- `payload` JSON `{ "before": {...}|null, "after": {...} }` — diff с предыдущим снимком.

После успешного persist инвалидируется version-keyed кэш листинга отзывов (`ReviewService`).

---

## Что доделали бы при большем запасе времени

- Группировка job (batching) / агрегированный прогресс по сети филиалов.
- WebSocket / SSE вместо пулинга(опрос) — сервер сам присылает прогресс.
- Ротация прокси/UA и circuit-breaker при серии captcha.
- API истории снимков (`organization_snapshots`) в интерфейсе.
- Синхронизация номера страницы отзывов с query-параметром URL.
- E2E против живой карточки Яндекса в CI (сейчас — HTML-фикстуры, без сети).

---

## Тесты

```bash
composer test
# или: php artisan test
```

Фикстуры HTML/JSON: `tests/Fixtures/yandex/` — unit/feature не ходят в сеть.
