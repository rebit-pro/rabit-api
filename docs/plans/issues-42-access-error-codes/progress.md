# Issue #42 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-42-access-error-codes`.
- Worktree: `/home/user/rabit-api-worktrees/issues-42-access-error-codes`. Общий checkout `/home/user/rabit-api` занят другой сессией, в нём не работать.
- Base: `origin/main` `49f40f9` (merge PR #35).
- Issue: [#42](https://github.com/rebit-pro/rabit-api/issues/42). PR: [#70](https://github.com/rebit-pro/rabit-api/pull/70): review без блокеров (пользователь, 2026-09-25), gate PASS, сливается в `main`. Head с кодом — `2989af8`.
- Документация: [план](plan.md), контракт ошибок [E2](../../waves/e2/README.md), порядок E2E [A8](../../waves/a8/README.md).
- Завершено: план, пункт 1 (коды в источниках, удаление обходов, unit-тесты, E2E-сценарий в `staff.spec.ts`), пункт 2 (`CalendarCommandValidator`, пассивный DTO, архитектурный тест), подтверждение пункта 3, быстрые проверки.
- Сейчас: ожидание review PR #70.
- Base обновлён: в ветку влит `origin/main` `f989aeb` (merge PR #67) merge-коммитом `72d3177`.
- Следующий шаг: деплой — отдельно, после результатов E2E волны E6 (решение пользователя 2026-09-25).
- Блокеров нет. Открытое решение: follow-up для `CatalogController`/`ConditionsController` (R4).
- Рабочее дерево: изменения ветки коммитятся; пустые `api/vendor`, `api/var`, `frontend/node_modules` — точки монтирования docker-проверок, в git не попадают.
- Команды проверок:
  - backend: vendor-том `rabit-issues42-vendor` (засеян из `/home/user/rabit-api/api/vendor`, `composer.lock` совпадает), затем `docker run --rm --network none --memory 1536m --cpus 2 --env XDEBUG_MODE=off --mount type=bind,source=<worktree>/api,target=/app,readonly --mount type=volume,source=rabit-issues42-vendor,target=/app/vendor --tmpfs /app/var:rw,size=256m --workdir /app --entrypoint php rabit-api-php-cli:d1-local vendor/bin/phpunit --colors=never` (так же `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` и `vendor/bin/phplint`);
  - php-cs-fixer: тот же образ с записываемым bind-монтированием, `vendor/bin/php-cs-fixer fix --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=intersection --dry-run <изменённые .php>`;
  - frontend: том `rabit-issues42-node` (`npm ci` текущего lockfile; общий `rabit-e5-node` устарел — нет `stylelint`), затем `docker run --rm --network none -v <worktree>/frontend:/app -v rabit-issues42-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`.

## Хронология

### 2026-09-25 — разведка и план

- Worktree создан от `origin/main` `49f40f9`.
- Прочитаны `CLAUDE.md` и `AGENTS.md` (идентичны), текст #42.
- Grep по `lib`: текстовые отказы найдены в 7 файлах (см. план, «Установленные факты»), дополнительно к списку issue — `CatalogAccessGuard`. Обходы по статусу: `OrderStaffAccess`, `StaffTransferGuard`, `TransferChildUseCase`, legacy `CatalogController`/`ConditionsController` и «загрязнённые» контроллеры со своим exception mapping.
- Frontend `src/` не сравнивает текст сообщений: решения принимаются по статусу или машинному коду.
- Пункт 3 подтверждён без изменений (T20): `docs/plans/OPS-stage-media-recovery/` есть, `docs/plans/D3_stage-media-recovery/` нет, `docs/waves/d3/` принадлежит волне D3 (PR #46), правило OPS записано в `CLAUDE.md:130` и `AGENTS.md` (коммит `90584de`).
- Среди 25 DTO `rebit.share/lib/Contracts/*/Dto` форму нарушает только `CalendarCommandInputDto`.

### 2026-09-25 — реализация

- Пункт 1:
  - коды в источниках: `BearerTokenFilter`, `AuthenticatedControllerTrait`, `TokenResolver`, `AuthController::logoutAction` → `UNAUTHORIZED`; `StaffAuthorization` → `UNAUTHORIZED`/`FORBIDDEN`/`NOT_FOUND`; `InstitutionAccess`, `AccessGuard` → `FORBIDDEN`/`UNAUTHORIZED`; `CatalogAccessGuard` → `UNAUTHORIZED`/`FORBIDDEN`/`ACCESS_UNAVAILABLE`;
  - удалены обходы `OrderStaffAccess` и `StaffTransferGuard`; `TransferChildUseCase` переводит только 404 `NOT_FOUND` → `GROUP_NOT_FOUND`;
  - `StaffAuthorization` и `InstitutionAccess` получили class-level phpDoc (правило CLAUDE.md для изменяемых сервисов);
  - runtime-стабы Bitrix в `api/tests/stubs/bitrix.php`: `Response`, `HttpResponse`, `HttpRequest`, `Event`, `EventResult`, `Engine\Response\Json`, `Engine\ActionFilter\Base`;
  - тесты: новые `ApiJsonExceptionResponseTest`, `BearerTokenFilterTest` (включая трейт), `AccessRefusalCodesTest`; обновлены `TokenResolverTest`, `OrderReceiptsAndAccessTest`, `StaffTransferUseCaseTest`, `TransferChildUseCaseTest`;
  - E2E: сценарий «B2: teacher не читает управление…» в `staff.spec.ts` (группа `a`) проверяет 401 `UNAUTHORIZED` для `GET /api/v1/users` и `GET /api/v1/staff-requests` без Bearer и 403 `FORBIDDEN` для учителя на `GET /api/v1/users`.
- Пункт 2:
  - `CalendarCommandInputDto` — только свойства и пустой конструктор;
  - `CalendarCommandValidator` (Organization, Application/Calendar/Service, singleton DI) вызывается в начале `ChangeGroupCalendarUseCase::execute` (до транзакции) и `GroupCalendar::confirmLinkSent/extend` (до `findOperation`);
  - обновлены прямые конструкторы: `di/calendar.php`, `api/tools/e2e/verify-links.php`, `api/tools/fixtures/c3/calendar-checks.php`;
  - тесты ключа и UUID перенесены из `GroupCalendarTest` в `CalendarCommandValidatorTest`, добавлены actor/revision/reason и «отказ до БД» для UseCase и `GroupCalendar`;
  - `StaffRequestDtoArchitectureTest` обходит все `rebit.share/lib/Contracts/**/Dto/*Dto.php`; проверено, что прежний DTO не проходит его регулярное выражение.
- Frontend `src/` не менялся.

### 2026-09-25 — быстрые проверки

- `vendor/bin/phpunit --colors=never` — OK (684 тестов, 3370 assertions). Две строки `todo.WARNING` о кешировании `DtoClassMetadata` есть и на `origin/main` (проверено отдельным прогоном), к ветке не относятся.
- `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` — `[OK] No errors`.
- `vendor/bin/phplint` — `[OK] 972 files`; `php -l` для `tools/e2e/verify-links.php` и `tools/fixtures/c3/calendar-checks.php` — без ошибок.
- php-cs-fixer dry-run по 29 изменённым PHP (26 попадают в finder конфига; `tools/*` и `tests/stubs` вне его) — сначала 1 файл (перенос `;` в `CalendarCommandValidatorTest`), после правки 0 из 26.
- Frontend: `npm run check && npm run test:commerce` — exit 0 (lint, stylelint, typecheck, typecheck:e2e, test:ui 27/27, test:commerce 183/183).
- T10 grep — совпадений нет.

### 2026-09-25 — публикация

- Коммиты: `1a15b0b` (коды отказа), `ed602bf` (валидатор календаря), `2989af8` (план и журнал).
- `origin/main` перед push — `49f40f9`, base не менялся.
- Push `codex/issues-42-access-error-codes`, создан PR [#70](https://github.com/rebit-pro/rabit-api/pull/70) в `main`.

### 2026-09-25 — review и полный gate

- Пользователь провёл review, блокирующих замечаний нет. Разрешил полный E2E и merge. Деплой — позже, после E2E волны E6.
- Base обновлён до `f989aeb` (PR #67 слит раньше): `git merge origin/main` → `72d3177`, конфликтов нет.
- Полный gate `rabit-e2e-12fc8335ddd7` (309.6 с) — PASS, exit 0:
  - php-lint, phpstan, phpunit, frontend lint/typecheck/test/build — все PASS;
  - группа `a` 64/64, группа `b` 39/39;
  - `verify-storefront/orders/links/transfers/avatar/access` PASS.
- Команда: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|----|--------|------|---------|----------------|
| T01–T02 | PASS | 2026-09-25 | PHPUnit `ApiJsonExceptionResponseTest` | 9 тестов в полном прогоне 684/684 |
| T03–T04 | PASS | 2026-09-25 | PHPUnit `BearerTokenFilterTest` | 7 тестов |
| T05 | PASS | 2026-09-25 | PHPUnit `TokenResolverTest` | `testEmptyTokenNeverQueriesRepository` с `UNAUTHORIZED` |
| T06–T08 | PASS | 2026-09-25 | PHPUnit `AccessRefusalCodesTest`, `TeacherAuthorizationTest` | 8 + 5 тестов |
| T09 | PASS | 2026-09-25 | PHPUnit `OrderReceiptsAndAccessTest`, `StaffTransferUseCaseTest`, `TransferChildUseCaseTest` | в полном прогоне |
| T10 | PASS | 2026-09-25 | grep из плана | exit 1, совпадений нет |
| T11–T12 | PASS | 2026-09-25 | PHPUnit `CalendarCommandValidatorTest` | 22 теста |
| T13 | PASS | 2026-09-25 | PHPUnit `StaffRequestDtoArchitectureTest` | 1 тест, >25 DTO |
| T14 | PASS | 2026-09-25 | PHPStan, phplint, php-cs-fixer dry-run | No errors; 972 files OK; 0 из 26 |
| T15 | PASS | 2026-09-25 | `vendor/bin/phpunit` | 684/684 |
| T16 | PASS | 2026-09-25 | `npm run check && npm run test:commerce` | exit 0 |
| T17–T19 | PASS | 2026-09-25 | `make test-e2e` `rabit-e2e-12fc8335ddd7` | exit 0; a 64/64 (включая `staff.spec.ts` 401/403), b 39/39; `verify-links.php` с новым `GroupCalendar` PASS |
| T20 | PASS | 2026-09-25 | `ls docs/plans`, `grep -n OPS CLAUDE.md AGENTS.md` | см. хронологию |
