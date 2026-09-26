# Issue #123 — прогресс

## Точка продолжения

- Ветка `codex/issues-123-international-phone` (worktree `/home/user/rabit-api-worktrees/issues-123-international-phone`),
  base `4fc9dce` (origin/main). Issue #123, PR https://github.com/rebit-pro/rabit-api/pull/131 (`Closes #123`).
- Завершено: реализация backend и frontend, unit-тесты, быстрые проверки (T01–T09 PASS).
- Сейчас: независимое ревью без блокеров, полный gate PASS на ветке с main `bf3dfd8` (регрессия T10). PR сливается. Пользователь решил: `+8 9xx…` остаётся международным; `Phone.php` вынесен в #140.
- Следующий шаг: деплой backend и frontend вместе; после него ручная проверка на stage с `+852 9123 4567` (вторая часть T10).
- Блокеры: нет. Открыто: `rebit.share` `Shared/ValueObject/Phone` содержит тот же приём `8`→`7` после удаления `+`
  (вне scope, отдельный issue по решению пользователя).
- Рабочее дерево: всё закоммичено и запушено; игнорируемые `api/var/`, пустой `api/vendor/`
  от docker-монтирования.
- Следующая проверка:
  `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`

## Журнал

### 2026-09-26

- Прочитаны `CLAUDE.md`, `AGENTS.md`, issue #123, `field-values.ts`, `UiPhoneField.vue`, `BuyerPolicy.php`,
  demo-нормализация `orders/services/validation.ts` и `settlement/service.ts`.
- `UiPhoneField` форматирует значение только по blur через `displayPhone`: `+852 9123 4567` превращался в
  `+7 (529) 123-45-67` — та же причина, исправлено вместе с `displayPhone`. Маски по нажатиям нет.
- Найден тот же приём в `rebit.share` `Shared/ValueObject/Phone` (вне scope).
- Реализовано:
  - `BuyerPolicy`: `8`→`7` только если до первой цифры нет `+` (`/^\D*\+/`); phpDoc класса описывает правило.
  - `field-values.ts`: экспортируемая `phoneDigits` (то же правило); `russianPhoneDigits` считает российским только
    результат, начинающийся с `7`, — отсюда `displayPhone` (маска `UiPhoneField`) и `formatPhone`.
  - Demo: `normalizeBuyer` и правка контактов в `settlement/service.ts` используют `phoneDigits`.
  - Тесты: data provider `phones` в `OrderBuyerPolicyTest` (+852, скобки, `+8`, `+49`, `8…`, `+7…`, `7…`),
    недопустимые короткий международный и 16 цифр; node-кейсы в `ui-values.test.mjs` и `checkout.test.mjs`.
- Проверка «красного»: с исходным `BuyerPolicy` новый data provider падает на 4 кейсах (`+852…` ×3, `+8 900…`).
- Быстрые проверки backend (docker, `W=<worktree>`, `R="docker run --rm --network none -e XDEBUG_MODE=off -v $W/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app rabit-api-php-cli:d1-local"`):
  - `$R php vendor/bin/phpunit public/local/modules/morefoto.commerce/tests/Unit/OrderBuyerPolicyTest.php` → OK (21 tests, 35 assertions).
  - `$R php vendor/bin/phpunit --testsuite=unit` → OK (775 tests, 45378 assertions).
  - `$R php vendor/bin/phpunit --testsuite=functional` → OK (212 tests, 848 assertions).
  - `$R php -d memory_limit=4G vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` → `[OK] No errors`.
  - `$R php vendor/bin/php-cs-fixer fix --config=public/local/php-cs-fixer.php --dry-run --diff --allow-risky=yes --path-mode=intersection <2 изменённых .php>` → `Found 0 of 2 files that can be fixed`.
- Быстрые проверки frontend:
  - `docker run --rm -v $W/frontend:/app -v rabit-issues123-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy npm ci` (свежий том) → OK.
  - `docker run --rm --network none -v $W/frontend:/app -v rabit-issues123-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy sh -c 'npm run check && npm run test:commerce'`
    → exit 0: lint, stylelint, typecheck, typecheck:e2e, `test:ui` 56/56, `test:commerce` 211/211.
    Первый прогон упал на prettier (2 переноса строк) — исправлено вручную, повтор зелёный.
- `make test-e2e` / `make e2e-up` не запускались: gate после ревью запускает координатор.
- Commit `7d59f1e` (код) и `cb70bc8` (docs), push. Дублей по `gh pr list --state all --search "123 in:title"` нет
  (найден только слитый #118). Создан PR #131 в main: решение, проверки, ограничения, порядок деплоя
  (backend + frontend вместе), поведение уже искажённых номеров (не мигрируются). Не слит, не выкачен.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-26 | PHPUnit `OrderBuyerPolicyTest` | data provider `phones`: Hong Kong with plus / with spaces / in brackets |
| T02 | PASS | 2026-09-26 | то же | `phones`: Russian national eight (+ formatted), Russian plus seven, seven without plus |
| T03 | PASS | 2026-09-26 | то же | `phones`: plus eight is international, Germany |
| T04 | PASS | 2026-09-26 | то же | `invalid`: letters in phone, short phone, short international phone, long phone |
| T05 | PASS | 2026-09-26 | `npm run test:commerce` | `explicit international plus is never read as the Russian national 8` |
| T06 | PASS | 2026-09-26 | то же | `Russian formatting preserves digits…`, `stored phones read as +7 900 123-45-67…`, `stored phone digits follow the backend BuyerPolicy rule` |
| T07 | PASS | 2026-09-26 | то же | `checkout.test.mjs`: `buyer accepts formatted phones and normalizes contacts…` |
| T08 | PASS | 2026-09-26 | PHPUnit unit/functional, PHPStan, php-cs-fixer | 775 + 212 OK, `[OK] No errors`, 0 of 2 |
| T09 | PASS | 2026-09-26 | `npm run check` | exit 0 |
| T10 | PASS (регрессия) / PENDING (stage) | 2026-09-26 | `make test-e2e` + ручная проверка на stage | `rabit-e2e-ba174d2d32a4`: exit 0, 126 браузерных сценариев, checkout/staff orders без регрессий; сценария с `+852` в E2E нет — правило покрыто unit-тестами, ручная проверка на stage после деплоя |

### 2026-09-26 — ревью и полный gate

- Независимое ревью PR #131: блокирующих нет. Решения пользователя: `+8 9xx…` — международный (без исключения); `rebit.share` `Phone` VO — отдельный #140.
- Ветка обновлена от main `bf3dfd8` (merge `eceadc0`).
- `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`
  — `rabit-e2e-ba174d2d32a4`: exit 0, Total 397.3 s, 126 браузерных сценариев (a 78, b 48). Регрессия T10 PASS; ручная проверка `+852` на stage — после деплоя.
