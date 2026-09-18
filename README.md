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
# в третьем терминале — воркер очереди парсинга:
php artisan queue:work --queue=parsing
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

### Парсинг и очередь

После `POST /api/organization` (или явного `POST /api/organization/parse-run`) создаётся запись в `parse_runs` и в очередь `parsing` ставится `ParseOrganizationJob`. Без воркера job не выполнится:

```bash
php artisan queue:work --queue=parsing
```

Страховочная переочередь и reaping «зависших» runs:

```bash
php artisan yandex:queue-parsing
```

В расписании (`routes/console.php`): каждые 5 минут, `withoutOverlapping()`; еженедельно — `queue:prune-failed --hours=168`. Для локального cron:

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
| `CACHE_STORE` | Кэш листинга отзывов + locks | `database` |
| `QUEUE_CONNECTION` | Драйвер очереди | `database` |
| `YANDEX_PARSER_MAX_PAGES` | Макс. страниц `?page=N` (~600 отзывов) | `12` |
| `YANDEX_PARSER_REQUEST_DELAY_MS` | Базовая пауза перед HTTP-запросом (с джиттером) | `500` |
| `YANDEX_PARSER_TIMEOUT` | Таймаут HTTP, сек | `20` |
| `YANDEX_PARSER_USER_AGENT` | User-Agent (опционально) | Chrome-like |
| `YANDEX_HTTP_PROXY` | Опциональный HTTP(S)-прокси | — |
| `YANDEX_REVIEWS_CACHE_TTL` | TTL кэша страниц отзывов, сек | `3600` |
| `YANDEX_REPARSE_INTERVAL_HOURS` | Интервал повторного парсинга Ready/Failed | `24` |
| `YANDEX_QUEUE_NAME` | Имя очереди воркера | `parsing` |
| `YANDEX_PARSER_TRIES` | Число попыток job | `3` |
| `YANDEX_PARSER_JOB_TIMEOUT` | Таймаут одной попытки, сек | `900` |
| `YANDEX_PARSER_BACKOFF` | Паузы между попытками, сек (CSV) | `60,300,900` |
| `YANDEX_QUEUE_DISPATCH_SPACING` | Задержка между cron-диспатчами, сек | `10` |
| `YANDEX_STALE_RUN_MINUTES` | Через сколько минут `processing` считается зависшим | `30` |

Полный список — в `.env.example`, конфиг парсера — `config/yandex.php`.

---

## API (кратко)

| Метод | Путь | Описание |
|-------|------|----------|
| `POST` | `/api/login` | Вход (Sanctum cookie) |
| `POST` | `/api/logout` | Выход |
| `GET` | `/api/me` | Текущий пользователь |
| `GET` | `/api/organization` | Текущая организация |
| `POST` | `/api/organization` | Сохранить ссылку Яндекс.Карт (+ enqueue parse run) |
| `GET` | `/api/organization/reviews` | Отзывы, `page` / `per_page` (до 50) |
| `POST` | `/api/organization/parse-run` | Явно поставить парсинг в очередь (`202`) |
| `GET` | `/api/organization/parse-run` | Статус / прогресс последнего run (polling) |

### Контракт polling (`GET /api/organization/parse-run`)

Ответ — `{"data": {...}}` либо `{"data": null}`, если run ещё не создавался.

| Поле | Смысл |
|------|--------|
| `status` | `pending` \| `processing` \| `completed` \| `failed` |
| `processed_reviews` / `total_reviews` | Счётчики (total может быть `null` до первой страницы) |
| `processed_pages` | Сколько страниц уже обработано |
| `progress_percent` | `0..100` или `null`, пока неизвестен знаменатель |
| `attempt` / `max_attempts` | Текущая попытка job и лимит |
| `error_code` / `error_message` | Код/текст последней ошибки (если была) |
| `queued_at` / `started_at` / `finished_at` | ISO-8601 метки жизненного цикла |

`OrganizationStatus` (карточка: `pending` / `parsing` / `ready` / `failed`) и `ParseRunStatus` (попытка) — разные сущности: run отражает конкретный прогон, статус организации — итоговое состояние карточки.

---

## Архитектура парсинга

Слои (см. также правила проекта):

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

Интерфейс `YandexParserInterface` — точка замены стратегии (например, headless) без смены оркестрации.

---

## Дополнительные требования ТЗ

### 1. Устойчивость к смене разметки — как парсер понимает, что сломался

Парсер **не** молча возвращает пустоту. На каждом ответе:

1. **Captcha / soft-ban** (`CaptchaRequiredException`, код `captcha_required`) — HTTP `403`/`429` или тело с маркерами `showcaptcha` / `smartcaptcha` / `captcha.yandex` при отсутствии полезного payload отзывов.
2. **Смена вёрстки / JSON** (`InvalidLayoutException`, код `invalid_layout`) — нет `<script class="state-view|config-view">`, JSON не парсится, отсутствуют ожидаемые пути (`ratingData.*`, `reviewResults.reviews` и т.д.). Пути вынесены в константы в `DetectsYandexAntiBotTrait` — при сдвиге ключей правка точечная.
3. **Пустой/бесполезный ответ** (`EmptyYandexResponseException`, код `empty_yandex_response`) — на первой странице нет отзывов и нет рейтинга/счётчиков.
4. **Страница недоступна** (`OrganizationUnavailableException`, код `organization_unavailable`) — сеть, таймаут, однозначные `404`/`410`/`5xx`.

Сигналы об ошибке:

- лог с `organization_id`, URL, `error_code`, `parse_run_id`, `attempt`;
- колонки `parse_runs.error_code` / `error_message` — SPA видит причину через polling без разбора логов;
- при терминальном сбое организация → `failed`, run → `failed` (см. матрицу retry в п. 3).

`invalid_layout` сразу терминален: повторные попытки с backoff не помогут, если контракт разметки изменился.

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

### 3. Масштаб и фоновая обработка (реализовано)

Синхронный парсинг внутри HTTP-запроса **не используется**. Сеть из ~50 филиалов × ~600 отзывов обрабатывается через очередь.

**Модель run / job / прогресс**

| Компонент | Роль |
|-----------|------|
| `parse_runs` | Статус попытки (`pending`/`processing`/`completed`/`failed`), счётчики отзывов/страниц, attempt, ошибки |
| `ParseRunService::queue()` | Dedupe активного run + `ParseOrganizationJob::dispatch` под `Cache::lock` |
| `ParseOrganizationJob` | `tries` / `backoff` / `timeout`, `WithoutOverlapping`, классификация retryable vs fatal |
| `YandexReviewsPageParsed` | Событие на каждую страницу → listener пишет прогресс в `parse_runs` |
| Polling API | `GET /api/organization/parse-run` для SPA |

**Триггеры**

1. `POST /api/organization` — после сохранения ссылки вызывается `ParseRunService::queue()`.
2. `POST /api/organization/parse-run` — явный re-parse (`202` + тело run).
3. `yandex:queue-parsing` (cron) — сначала `reapStale()`, затем queue для due-организаций с шагом `YANDEX_QUEUE_DISPATCH_SPACING` секунд между диспатчами (анти-бан для ~50 филиалов).

Повторный `queue()` при уже активном run возвращает тот же run и **не** ставит второй job.

**Воркер**

```bash
php artisan queue:work --queue=parsing
```

`QUEUE_CONNECTION=database` по умолчанию; таблицы `jobs` / `failed_jobs` обязательны.

**Матрица retry**

| `error_code` | Retryable? | Поведение |
|--------------|------------|-----------|
| `organization_unavailable` | да | throw → Laravel retry с backoff `60 → 300 → 900` с |
| `captcha_required` | да | то же (пауза — правильный ответ на soft-ban) |
| `empty_yandex_response` | да | то же (возможен транзиентный degraded-ответ) |
| `invalid_layout` | **нет** | `$this->fail()` сразу → `failed_jobs`, run/org `failed`; backoff бесполезен при смене контракта разметки |
| прочее / timeout | — | по исчерпании `tries` → `failed()` терминально |

Между попытками non-terminal failure: run снова `pending` (ждёт retry в очереди), организация остаётся `parsing` и не мигает в `failed`.

**Stale-run reaping**

Если воркер умер mid-flight, run мог остаться в `processing` и блокировать dedupe. `ParseRunService::reapStale()` (вызывается из cron) помечает такие runs как `failed` с `error_code=stale_run`, если `started_at` старше `YANDEX_STALE_RUN_MINUTES` (по умолчанию 30).

### 4. Анти-бан на объёме

**Уже в коде:**

- Пауза перед каждым HTTP-запросом с джиттером вокруг `YANDEX_PARSER_REQUEST_DELAY_MS` (`YandexMapsIntegrationClient::throttle`).
- Реалистичные заголовки (`User-Agent`, `Accept-Language: ru`).
- Retry только на транспортных сбоях; `403`/`429` **не** ретраятся вслепую в HTTP-клиенте (их классифицирует парсер как captcha → queue backoff).
- Опциональный прокси: `YANDEX_HTTP_PROXY`.
- **Dispatch spacing** в cron (`YANDEX_QUEUE_DISPATCH_SPACING`) — основной рычаг для сети филиалов: jobs не стартуют пачкой.
- Job `WithoutOverlapping` per organization — без лишних параллельных прогонов одной карточки.
- При captcha/бане: код в `parse_runs` + лог — без «тихого» пустого успеха.

**Описано / задел на прод (не реализовано полностью):**

- Ротация пула прокси и User-Agent.
- Circuit-breaker при серии captcha по всей сети.
- Отдельные квоты/воркеры на tenant.

### 5. Идемпотентность и история изменений

**Идемпотентность отзывов:** уникальный ключ `(organization_id, external_id)` в таблице `reviews`; повторный парсинг идёт через `ReviewRepository::updateOrCreate` — дубликаты не создаются, поля обновляются.

**Снимки:** таблица `organization_snapshots` на каждый успешный sync:

- агрегаты: `rating`, `total_ratings`, `total_reviews`, `reviews_count`, `captured_at`;
- `payload` JSON вида `{ "before": {...}|null, "after": {...} }` — diff с предыдущим снимком («было → стало»).

После успешного persist инвалидируется version-keyed кэш листинга отзывов (`ReviewService`), чтобы API сразу отдавал актуальные страницы без зависимости от cache tags.

---

## Что доделали бы при большем запасе времени

- Job batching / агрегированный прогресс по всей сети филиалов + Laravel Horizon для мониторинга очереди.
- WebSocket / SSE push прогресса вместо polling `GET /api/organization/parse-run`.
- Ротация прокси/UA и circuit-breaker при серии captcha.
- UI: индикатор статуса `parsing` / `failed`, прогресс-бар по `progress_percent`, кнопка «обновить сейчас».
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
