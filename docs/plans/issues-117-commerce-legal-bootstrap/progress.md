# Issue #117 — прогресс

## Точка продолжения

- Ветка `codex/issues-117-commerce-legal-bootstrap` (worktree
  `/home/user/rabit-api-worktrees/issues-117-commerce-legal-bootstrap`), base `4fc9dce` (origin/main). Issue #117,
  PR https://github.com/rebit-pro/rabit-api/pull/134 (`Closes #117`), код — `76899d0`.
- Завершено: анализ, план, R1 (`include.php`), R3 (шаг в `prepare.php`), R4 (`CommerceBootstrapTest`),
  быстрые проверки T01–T07 PASS.
- Сейчас: независимое ревью без блокеров (неблокирующее support → media вынесено в #138), полный gate PASS на ветке с main `862deca` (T08). PR сливается.
- Следующий шаг: деплой backend по порядку из PR; до prod сверить `b_module` со списком `prepare.php`.
- Блокеры: нет. Открыто: риск порядка установки `morefoto.support` → `morefoto.media` (plan.md) — предложить
  отдельным issue.
- Рабочее дерево: изменения из scope плана, коммитятся в ветку.
- Следующая проверка (T08):
  `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`
  — `prepare.log` без ошибки, дальше весь gate зелёный.

## Журнал

### 2026-09-26

- Прочитаны `CLAUDE.md`, `AGENTS.md`, issue #117 (комментариев нет), `include.php` всех модулей, `init.php`,
  миграции с `registerModule`/`DoInstall`, `install/index.php` модулей, `api/tools/e2e/prepare.php`,
  фикстура `tools/fixtures/w02/bootstrap.php`, `Loader`/`ModuleManager` ядра.
- Вывод по DI: commerce зависит от legal только через `ConsentRecorderInterface` в ленивом `constructorParams`
  `CreateOrderUseCase`; `rebit.auth` уже так же использует контракт без require legal. Убрать legal из `include.php`
  безопасно (см. plan.md «Установленные факты»).
- Похожие круги: второго круга того же вида нет; найден риск порядка установки `morefoto.support` → `morefoto.media`
  на чистой установке — описан в плане, не исправляется.
- Реализовано:
  - `morefoto.commerce/include.php`: `morefoto.legal` убран из обязательных модулей, комментарий о причине.
  - `morefoto.commerce/tests/Unit/CommerceBootstrapTest.php`: `include.php` выполняется в отдельном PHP-процессе
    с минимальным `Bitrix\Main\Loader` по списку установленных модулей.
  - `api/tools/e2e/prepare.php`: миграция `Version20260925230001` применяется после установки
    commerce/media/handoff/payment/support; перед ней проверяется, что legal нет в `b_module`, и модули подключаются
    в порядке `init.php` (список читается регуляркой из `init.php`), как при bootstrap `migrate.sh`. Затем миграция
    legal и `DoInstall` legal. Порядок остальных миграций не изменился, `20260926120001` теперь идёт до legal
    (независимые таблицы).
- Негативный контроль T03: новый тест на старом `include.php` → FAIL
  `Uncaught RuntimeException: Required module is unavailable: morefoto.legal in …/morefoto.commerce/include.php:9`,
  exit 255 — ровно ошибка stage. После правки — PASS.
- Быстрые проверки (docker, worktree смонтирован в `/app`, vendor — `/home/user/rabit-api/api/vendor`):
  - `docker run --rm --network none -e XDEBUG_MODE=off -v $PWD/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app rabit-api-php-cli:d1-local php vendor/bin/phpunit public/local/modules/morefoto.commerce/tests/Unit`
    → OK (198 tests, 41623 assertions).
  - то же, `--testsuite=unit` → OK (766 tests, 45371 assertions).
  - то же, `--testsuite=functional` → OK (212 tests, 848 assertions).
  - `... php -d memory_limit=4G vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` → `[OK] No errors`.
  - `... php vendor/bin/php-cs-fixer fix --config=public/local/php-cs-fixer.php --dry-run --diff --allow-risky=yes --path-mode=intersection <include.php, CommerceBootstrapTest.php, tools/e2e/prepare.php>`
    → `Found 0 of 2 files that can be fixed` (`prepare.php` вне finder); отдельно `--path-mode=override tools/e2e/prepare.php`
    → `Found 0 of 1 files`.
  - `php -l tools/e2e/prepare.php`, `php -l …/morefoto.commerce/include.php` → без ошибок; регулярка по `init.php`
    возвращает 9 модулей в порядке `init.php`, включая `morefoto.commerce`.
- `make test-e2e` / `make e2e-up` не запускались: по правилу gate — после ревью, запускает координатор.
- Commit `76899d0` (код) и `1aa7718` (docs), push. Дублей по `gh pr list --state all --search "117 in:title"` нет.
  PR #134 в main. Не слит, не выкачен.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-26 | PHPUnit `CommerceBootstrapTest` | `testInstalledCommerceBootsBeforeLegalMigrationRegistersLegal`: exit 0, запрошены только 5 модулей-поставщиков |
| T02 | PASS | 2026-09-26 | то же | `testMissingProviderModuleStillStopsTheBootstrap` |
| T03 | PASS | 2026-09-26 | то же на старом `include.php` | тест FAIL с `Required module is unavailable: morefoto.legal` (ожидаемо) |
| T04 | PASS | 2026-09-26 | `phpunit --testsuite=unit` | OK (766 tests) |
| T05 | PASS | 2026-09-26 | `phpunit --testsuite=functional` | OK (212 tests) |
| T06 | PASS | 2026-09-26 | `phpstan analyse --configuration=phpstan.neon` | `[OK] No errors` |
| T07 | PASS | 2026-09-26 | php-cs-fixer `--dry-run` | 0 of 2 + 0 of 1 |
| T08 | PASS | 2026-09-26 | `make test-e2e …` (координатор) | `rabit-e2e-578a4b03889a`: шаг #117 в `prepare.php` (legal не в `b_module` → bootstrap модулей в порядке `init.php` → миграция `20260925230001`) прошёл на обоих стендах; 126 браузерных сценариев, exit 0 |

### 2026-09-26 — ревью и полный gate

- Независимое ревью PR #134: блокирующих нет; неблокирующее — #138 (регистрация Support раньше Media блокирует bootstrap чистой установки).
- Ветка обновлена от main `862deca` (merge `c222d24`).
- `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`
  — `rabit-e2e-578a4b03889a`: exit 0, Total 379.8 s, 126 браузерных сценариев (a 78, b 48). Шаг `prepare.php` не опционален (бросает исключение при нарушении предусловия), поэтому зелёная подготовка обоих стендов подтверждает T08.
