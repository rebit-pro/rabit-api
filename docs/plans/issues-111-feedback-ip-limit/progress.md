# Issue #111 — прогресс

## Точка продолжения

- Ветка `codex/issues-111-feedback-ip-limit` (worktree `/home/user/rabit-api-worktrees/issues-111-feedback-ip-limit`),
  base `23642d4` (origin/main; далее в main только docs OPS-legal). Issue #111,
  PR https://github.com/rebit-pro/rabit-api/pull/121 (`Refs #111`: п. 2 закрыт только для лимита адреса,
  п. 3 не нужен новому запросу).
- Завершено: реализация, unit-тесты, быстрые проверки (PHPUnit, PHPStan, php-cs-fixer).
- Сейчас: ревью PR пользователем.
- Следующий шаг: после ревью без блокеров — полный gate (T11–T13).
- Блокеры: нет. Открыто:
  - значение лимита на адрес (5 в час) — на подтверждение пользователя;
  - у php-fpm stage/prod должен быть `REBIT_ENCRYPTION_KEY` ≥ 32 символов, иначе гостевая форма → 503.
    Проверить до переключения backend.
- Рабочее дерево: всё закоммичено (игнорируемые `api/var/`, пустой `api/vendor/` от docker-монтирования).
- Следующая проверка:
  `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`

## Журнал

### 2026-09-26

- Прочитаны `CLAUDE.md`, `AGENTS.md`, issue #111, код `morefoto.support`, nginx frontend/backend, E2E-раннер.
- Выбрано: адрес из `X-Forwarded-For` справа налево с доверием к непубличным звеньям; HMAC с ключом из
  `REBIT_ENCRYPTION_KEY`; счётчик — строка на хеш адреса в MySQL под блокировкой; лимит 5 в час (см. plan.md).
- Проверено в образе `rabit-api-php-cli:d1-local` (PHP 8.4.25): `filter_var(..., NO_PRIV|NO_RES)` считает
  публичными `203.0.113.0/24`, `198.51.100.0/24`, `2001:db8::/32`, `100.64.0.0/10`; частными — `10/8`, `172.16/12`,
  `127/8`, `169.254/16`, `::1`, `fd00::/8`, `::ffff:10.0.0.1`.
- Реализовано:
  - `rebit.share`: `#[ClientAddress]`, `ClientAddressResolver`, подстановка в `RequestTechnicalValues`
    (поле из body → `422 UNKNOWN_FIELD`); stub `HttpRequest::getRemoteAddress()` для PHPStan.
  - `morefoto.support`: `clientAddress` в request/input DTO и `FeedbackMapper`; порт `GuestAddressHasherInterface` +
    `GuestAddressHasher` (HKDF → HMAC-SHA256, IPv6 /64, mapped IPv4); репозиторий `forgetGuestAddresses`,
    `lockGuestAddress`, `addGuestAddressQuestion`; `SendGuestFeedbackUseCase` с
    `GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR = 5`; DI; `install/index.php` требует новую таблицу.
  - Миграция `Version20260926120001` (`mf_support_guest_address`), ID добавлен в `api/tools/e2e/prepare.php`.
  - E2E: `REBIT_ENCRYPTION_KEY` для php-fpm стенда (`tools/run-browser-e2e.py`), раздел 6 в `verify-support.php`
    (5×202, 429, подделка левого звена → 429, другой адрес → 202, в БД только hex-хеш).
  - `.env.example`: `REBIT_ENCRYPTION_KEY`.
- Быстрые проверки (docker, worktree смонтирован в `/app`, vendor — `/home/user/rabit-api/api/vendor`):
  - `docker run --rm --network none -e XDEBUG_MODE=off -v $PWD/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app rabit-api-php-cli:d1-local php vendor/bin/phpunit public/local/modules/morefoto.support/tests/Unit`
    → OK (43 tests, 461 assertions).
  - то же, `--testsuite=unit` → OK (747 tests, 45245 assertions).
  - то же, `--testsuite=functional` → OK (212 tests, 848 assertions), в т.ч. `ClientAddressResolverTest`.
  - `... php -d memory_limit=4G vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` → `[OK] No errors`.
  - PHPStan по изменённым файлам с миграцией → 1 ошибка окружения `class.notFound Sprint\Migration\Version`
    (та же у слитых миграций, `sprint.migration` исключён из анализа), по коду модулей ошибок нет.
  - `... php vendor/bin/php-cs-fixer fix --config=public/local/php-cs-fixer.php --dry-run --diff --allow-risky=yes --path-mode=intersection <изменённые .php>`
    → `Found 0 of 18 files that can be fixed`.
  - `php -l tools/e2e/verify-support.php` → без ошибок; `python3 -m py_compile tools/run-browser-e2e.py` → ok.
  - Frontend не менялся — фронтовые проверки не требуются.
- Полный `make test-e2e` / `make e2e-up` не запускались: по правилу пользователя gate — после ревью.
- Commit `e97ca11` (код) и `e88c45a` (docs), push, PR #121 в main. Дублей по
  `gh pr list --state all --search "111 in:title"` не было. Не слит, не выкачен.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-26 | PHPUnit `morefoto.support/tests/Unit` | `testOneAddressCannotTakeTheWholeSiteLimit` |
| T02 | PASS | 2026-09-26 | то же | `testOneAddressCannotTakeTheWholeSiteLimit` (другой адрес и IPv6 /64) |
| T03 | PASS | 2026-09-26 | то же | `testGuestFeedbackIsLimitedPerHourForTheWholeSite` (30 разных адресов) |
| T04 | PASS | 2026-09-26 | то же | `testAddressWindowRestartsAfterAnHourAndStoresOnlyTheHash` |
| T05 | PASS | 2026-09-26 | то же | `testRepeatIsNotLimitedByTheAddress` |
| T06 | PASS | 2026-09-26 | PHPUnit `--testsuite=functional` | `ClientAddressResolverTest` 7 тестов |
| T07 | PASS | 2026-09-26 | PHPUnit `morefoto.support/tests/Unit` | `GuestAddressHasherTest`, `testWithoutTheServerSecretNothingIsStored` |
| T08 | PASS | 2026-09-26 | то же | `SupportArchitectureTest` |
| T09 | PASS | 2026-09-26 | `phpstan analyse --configuration=phpstan.neon` | `[OK] No errors` |
| T10 | PASS | 2026-09-26 | php-cs-fixer `--dry-run` по изменённым | 0 of 18 |
| T11 | PENDING | 2026-09-26 | `make test-e2e` (`verify-support.php`) | пост-ревью gate |
| T12 | PENDING | 2026-09-26 | `make test-e2e` (`zz-questions.spec.ts`) | пост-ревью gate |
| T13 | PENDING | 2026-09-26 | `make test-e2e` (миграция на стенде) | пост-ревью gate |
