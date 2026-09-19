# Yandex Maps Reviews Parser

Laravel API + Vue 3 SPA: подключение карточки организации на Яндекс.Картах, сбор отзывов и постраничный вывод (50 на страницу).

Ниже: как поднять проект, как устроен парсер и ответы на «Дополнительные требования» из ТЗ.

### Сдача

|                 |                                                 |
| --------------- | ----------------------------------------------- |
| Живой прототип  | `_заполнить: https://YOUR_DOMAIN_` |
| Git-репозиторий | `_заполнить: https://github.com/..._`           |
| Логин (сид)     | `test@example.com` / `password`                 |

---

## Быстрый старт (Docker — рекомендуется)

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

# Health-check:
curl -fsS http://localhost/up
```

Приложение: `http://localhost:8080` при `HTTP_PORT=8080` (по умолчанию в `.env.docker.example` — порт 8080, чтобы не конфликтовать с Laravel Herd на `:80`; для чистого VPS можно поставить `HTTP_PORT=80`).

| Сервис      | Роль                                                          |
| ----------- | ------------------------------------------------------------- |
| `nginx`     | HTTP(S), статика `public/build`, FastCGI → php-fpm            |
| `app`       | php-fpm; при старте: migrate + seed + config/route/view cache |
| `queue`     | `queue:work --queue=parsing` (Redis)                          |
| `scheduler` | `schedule:work` (`yandex:queue-parsing` каждые 5 мин)         |
| `mysql`     | MySQL 8.4 (порт наружу не публикуется)                        |
| `redis`     | очередь + кэш (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`) |
| `certbot`   | профиль `certbot`; запуск вручную для TLS (см. ниже)          |

Полезные команды:

```bash
docker compose logs -f app queue scheduler nginx
docker compose exec app php artisan about
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose down
```

---

## Быстрый старт (локально без Docker)

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

Приложение по умолчанию: `http://localhost:8000`. Без Docker очередь по умолчанию — `database` (см. `.env.example`); в Docker — Redis.

### Учётная запись (сид)

| Поле     | Значение по умолчанию | Переопределение      |
| -------- | --------------------- | -------------------- |
| Email    | `test@example.com`    | `SEED_USER_EMAIL`    |
| Password | `password`            | `SEED_USER_PASSWORD` |

### Парсинг и очередь

После `POST /api/organization` (или явного `POST /api/organization/parse-run`) создаётся запись в `parse_runs` и в очередь `parsing` ставится `ParseOrganizationJob`. Без воркера job не выполнится:

```bash
# локально:
php artisan queue:work --queue=parsing
# в Docker воркер уже запущен как сервис queue
```

Страховочная переочередь и reaping «зависших» runs:

```bash
php artisan yandex:queue-parsing
```

В расписании (`routes/console.php`): каждые 5 минут, `withoutOverlapping()`; еженедельно — `queue:prune-failed --hours=168`. Для локального cron:

```bash
php artisan schedule:work
```

В Docker планировщик уже работает как сервис `scheduler`.

---

## Развёртывание на VPS

Цель: публичный HTTPS-прототип на вашем домене (обязательный формат сдачи по ТЗ).

1. **Подготовка сервера.** Установите Docker Engine и Compose plugin. Откройте в файрволе только `80`/`443` (например `ufw allow 80,443/tcp`). Порты MySQL/Redis **не** публикуются наружу.
2. **DNS.** A-запись домена → публичный IP VPS.
3. **Код и env.**

```bash
git clone <REPO_URL> yandex-parser-app
cd yandex-parser-app
cp .env.docker.example .env
# Заполните: APP_URL=https://YOUR_DOMAIN,
# SANCTUM_STATEFUL_DOMAINS=YOUR_DOMAIN,
# DB_PASSWORD, DB_ROOT_PASSWORD, SEED_USER_* при желании
# APP_KEY можно оставить пустым (entrypoint сгенерирует) или задать явно
# SESSION_SECURE_COOKIE=true  (рекомендуется после включения HTTPS)
```

4. **Старт стека (пока HTTP):**

```bash
docker compose build
docker compose up -d
curl -fsS http://YOUR_DOMAIN/up
```

5. **Выпуск Let's Encrypt сертификата** (webroot через общий volume `certbot_www`):

```bash
docker compose --profile certbot run --rm certbot certonly \
  --webroot -w /var/www/certbot \
  -d YOUR_DOMAIN \
  --email YOUR_EMAIL \
  --agree-tos --no-eff-email
```

6. **Включение HTTPS в nginx:**

```bash
cp docker/nginx/conf.d/ssl.conf.example docker/nginx/conf.d/ssl.conf
# Замените YOUR_DOMAIN в ssl.conf на реальный хост.
# В app.conf оставьте ACME-location и добавьте редирект 80→443
# (шаблон комментарием лежит в ssl.conf.example).
docker compose exec nginx nginx -t
docker compose exec nginx nginx -s reload
```

Обновите `.env`: `APP_URL=https://YOUR_DOMAIN`, при необходимости `SESSION_SECURE_COOKIE=true`, затем:

```bash
docker compose up -d app queue scheduler
curl -fsS https://YOUR_DOMAIN/up
```

7. **Автопродление сертификата** — cron на хосте:

```cron
0 3 * * * cd /path/to/yandex-parser-app && docker compose --profile certbot run --rm certbot renew -q && docker compose exec nginx nginx -s reload
```

8. **Проверка сдачи.** Логин сидом → вставить ссылку организации Яндекс.Карт → дождаться прогресса парсинга → рейтинг/счётчики и пагинация отзывов по 50.

---

## Переменные окружения

Скопируйте `.env.example` → `.env` (локально) или `.env.docker.example` → `.env` (Docker / VPS). Ключевые параметры:

| Переменная                       | Назначение                                          | По умолчанию                  |
| -------------------------------- | --------------------------------------------------- | ----------------------------- |
| `APP_URL`                        | URL приложения (Sanctum / SPA)                      | `http://localhost:8000`       |
| `SANCTUM_STATEFUL_DOMAINS`       | Домены cookie-auth                                  | `localhost,...`               |
| `TRUSTED_PROXIES`                | IP/CIDR nginx (CSV) или `*`; пусто = не доверять `X-Forwarded-*` | пусто / в Docker приватные сети |
| `DB_*`                           | БД (локально SQLite; в Docker — MySQL)              | sqlite / mysql                |
| `CACHE_STORE`                    | Кэш листинга отзывов + locks                        | `database` / в Docker `redis` |
| `QUEUE_CONNECTION`               | Драйвер очереди                                     | `database` / в Docker `redis` |
| `DB_ROOT_PASSWORD`               | Root-пароль MySQL-контейнера (только Docker)        | —                             |
| `HTTP_PORT` / `HTTPS_PORT`       | Проброс портов nginx                                | `80` / `443`                  |
| `YANDEX_PARSER_MAX_PAGES`        | Макс. страниц `?page=N` (~600 отзывов)              | `12`                          |
| `YANDEX_PARSER_REQUEST_DELAY_MS` | Базовая пауза перед HTTP-запросом (с джиттером)     | `500`                         |
| `YANDEX_PARSER_TIMEOUT`          | Таймаут HTTP, сек                                   | `20`                          |
| `YANDEX_PARSER_USER_AGENT`       | User-Agent (опционально)                            | Chrome-like                   |
| `YANDEX_HTTP_PROXY`              | Опциональный HTTP(S)-прокси                         | —                             |
| `YANDEX_REVIEWS_CACHE_TTL`       | TTL кэша страниц отзывов, сек                       | `3600`                        |
| `YANDEX_REPARSE_INTERVAL_HOURS`  | Интервал повторного парсинга Ready/Failed           | `24`                          |
| `YANDEX_QUEUE_NAME`              | Имя очереди воркера                                 | `parsing`                     |
| `YANDEX_PARSER_TRIES`            | Число попыток job                                   | `3`                           |
| `YANDEX_PARSER_JOB_TIMEOUT`      | Таймаут одной попытки, сек                          | `900`                         |
| `YANDEX_PARSER_BACKOFF`          | Паузы между попытками, сек (CSV)                    | `60,300,900`                  |
| `YANDEX_QUEUE_DISPATCH_SPACING`  | Задержка между cron-диспатчами, сек                 | `10`                          |
| `YANDEX_STALE_RUN_MINUTES`       | Через сколько минут `processing` считается зависшим | `30`                          |

Полный список — в `.env.example`, конфиг парсера — `config/yandex.php`.

---

## Фронтенд: авторизация

SPA (Vue 3 + Pinia + Vue Router) обслуживается одним Blade-entrypoint `resources/views/app.blade.php`. Cookie-based Sanctum:

| Маршрут SPA | Доступ                                      | Назначение                  |
| ----------- | ------------------------------------------- | --------------------------- |
| `/login`    | только гость (`meta.guestOnly`)             | форма входа                 |
| `/`         | только авторизованный (`meta.requiresAuth`) | настройки + дашборд отзывов |

Поток:

1. `GET /sanctum/csrf-cookie` — `resources/js/api/csrf.ts` (сырой axios, не `/api`-клиент).
2. `POST /api/login` / `POST /api/logout` / `GET /api/me` — обёртки в `resources/js/api/auth.ts` через Axios-инстанс с `withCredentials` + `withXSRFToken`.
3. Pinia-стор `useAuthStore` (`resources/js/stores/auth.ts`) держит пользователя; `ensureInitialized()` один раз за сессию дергает `/api/me`.
4. `router.beforeEach` ждёт инициализацию и редиректит гостя с защищённых страниц на `/login?redirect=…`, а авторизованного с `/login` — на `/`.
5. При любом `401` Axios-интерцептор вызывает `setUnauthorizedHandler` из composition root (`app.ts`): чистит сессию и, если текущий route защищён, уводит на login.

Сидовые креды — в таблице выше (`test@example.com` / `password`).

---

## Фронтенд: дашборд организации

Экран `/` (`SettingsView.vue`) — единая страница настроек и данных. Три Pinia-стора соответствуют трём бэкенд-агрегатам; оркестрация переходов живёт во view.

| Слой   | Файлы                                                               | Роль                                                                             |
| ------ | ------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| API    | `api/organization.ts`, `api/parse-run.ts`, `api/reviews.ts`         | Обёртки над `/api/organization*`; пустой parse-run (`data: []`) мапится в `null` |
| Stores | `stores/organization.ts`, `stores/parseRun.ts`, `stores/reviews.ts` | Состояние формы, polling прогресса, постраничные отзывы                          |
| UI     | `components/organization/*`, `components/reviews/*`                 | Форма URL, баннер прогресса/ошибок, сводка, таблица, пагинация                   |

**Polling прогресса.** `useParseRunStore.refresh()` / `trigger()` дергают `GET`/`POST /api/organization/parse-run`, затем планируют следующий опрос через рекурсивный `setTimeout` (~2.5 с), пока статус `pending`/`processing`. На `completed`/`failed` поллинг останавливается; `onUnmounted` вызывает `stopPolling()`. После перехода из активного статуса во view перезагружаются организация и (при `completed`) первая страница отзывов.

**Ошибки парсинга.** `ParseProgressBanner` показывает `describeParseRunError(error_code)`: для `invalid_layout` — явный текст про смену разметки Яндекса; кнопка «Повторить» вызывает `POST /api/organization/parse-run`.

**Пагинация.** `GET /api/organization/reviews?page=N&per_page=50` без перезагрузки страницы; переключение через `PaginationControls` → `reviewsStore.fetchPage(page)`.

---

## API (кратко)

| Метод  | Путь                          | Описание                                           |
| ------ | ----------------------------- | -------------------------------------------------- |
| `POST` | `/api/login`                  | Вход (Sanctum cookie)                              |
| `POST` | `/api/logout`                 | Выход                                              |
| `GET`  | `/api/me`                     | Текущий пользователь                               |
| `GET`  | `/api/organization`           | Текущая организация                                |
| `POST` | `/api/organization`           | Сохранить ссылку Яндекс.Карт (+ enqueue parse run) |
| `GET`  | `/api/organization/reviews`   | Отзывы, `page` / `per_page` (до 50)                |
| `POST` | `/api/organization/parse-run` | Явно поставить парсинг в очередь (`202`)           |
| `GET`  | `/api/organization/parse-run` | Статус / прогресс последнего run (polling)         |

### Контракт polling (`GET /api/organization/parse-run`)

Ответ — `{"data": {...}}` либо `{"data": null}`, если run ещё не создавался.

| Поле                                       | Смысл                                                 |
| ------------------------------------------ | ----------------------------------------------------- |
| `status`                                   | `pending` \| `processing` \| `completed` \| `failed`  |
| `processed_reviews` / `total_reviews`      | Счётчики (total может быть `null` до первой страницы) |
| `processed_pages`                          | Сколько страниц уже обработано                        |
| `progress_percent`                         | `0..100` или `null`, пока неизвестен знаменатель      |
| `attempt` / `max_attempts`                 | Текущая попытка job и лимит                           |
| `error_code` / `error_message`             | Код/текст последней ошибки (если была)                |
| `queued_at` / `started_at` / `finished_at` | ISO-8601 метки жизненного цикла                       |

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

| Подход                              | Плюсы                                                                                                    | Минусы / риски                                                                               |
| ----------------------------------- | -------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| **Embedded JSON + `?page=N` (наш)** | Нет Chromium/Node; не нужен JS-токен `s`; простой деплой; до ~50 отзывов на страницу; короткий HTTP-цикл | Хрупкость к смене путей в JSON / класса скрипта; капча на HTML; лимит выдачи Яндекса (~600)  |
| **Signed `fetchReviews` XHR**       | Ближе к «живому» клиенту, потенциально те же данные                                                      | Нужен одноразовый `s`-токен из JS; сильнее rate-limit; сложнее и нестабильнее воспроизводить |
| **Headless-браузер**                | Устойчивее к JS-only UI, проще «как пользователь»                                                        | Тяжёлый рантайм, медленнее, дороже в ops; капча всё равно возможна                           |

Почему не XHR: токен и антибот вокруг `fetchReviews` делают решение хрупче без выигрыша по объёму (страница уже отдаёт до 50 отзывов).  
Почему не headless на этом этапе: избыточная инфраструктура для тестового объёма; интерфейс парсера позволяет подменить реализацию позже.

### 3. Масштаб и фоновая обработка (реализовано)

Синхронный парсинг внутри HTTP-запроса **не используется**. Сеть из ~50 филиалов × ~600 отзывов обрабатывается через очередь.

**Модель run / job / прогресс**

| Компонент                  | Роль                                                                                                    |
| -------------------------- | ------------------------------------------------------------------------------------------------------- |
| `parse_runs`               | Статус попытки (`pending`/`processing`/`completed`/`failed`), счётчики отзывов/страниц, attempt, ошибки |
| `ParseRunService::queue()` | Dedupe активного run + `ParseOrganizationJob::dispatch` под `Cache::lock`                               |
| `ParseOrganizationJob`     | `tries` / `backoff` / `timeout`, `WithoutOverlapping`, классификация retryable vs fatal                 |
| `YandexReviewsPageParsed`  | Событие на каждую страницу → listener пишет прогресс в `parse_runs`                                     |
| Polling API                | `GET /api/organization/parse-run` для SPA                                                               |

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

| `error_code`               | Retryable? | Поведение                                                                                                |
| -------------------------- | ---------- | -------------------------------------------------------------------------------------------------------- |
| `organization_unavailable` | да         | throw → Laravel retry с backoff `60 → 300 → 900` с                                                       |
| `captcha_required`         | да         | то же (пауза — правильный ответ на soft-ban)                                                             |
| `empty_yandex_response`    | да         | то же (возможен транзиентный degraded-ответ)                                                             |
| `invalid_layout`           | **нет**    | `$this->fail()` сразу → `failed_jobs`, run/org `failed`; backoff бесполезен при смене контракта разметки |
| прочее / timeout           | —          | по исчерпании `tries` → `failed()` терминально                                                           |

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
- API истории снимков (`organization_snapshots`) для сравнения «было → стало» в интерфейсе.
- Синхронизация номера страницы отзывов с query-параметром URL.
- E2E против живой карточки Яндекса в CI (сейчас — HTML-фикстуры, без сети).

---

## Тесты

```bash
composer test
# или: php artisan test
```

Фикстуры HTML/JSON: `tests/Fixtures/yandex/` — unit/feature не ходят в сеть.
