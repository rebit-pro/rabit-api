# Issue #42 — коды отказа доступа и пассивный `CalendarCommandInputDto`

## Цель и контекст

[#42](https://github.com/rebit-pro/rabit-api/issues/42) описывает три дефекта, найденных при разработке F2 (PR #40).

1. Источники отказа доступа бросают `HttpException` с человекочитаемым текстом (`'Unauthorized'`, `'Action is forbidden.'`, `'Resource not found.'`, `'Staff access is unavailable.'`). `ApiJsonExceptionResponse` берёт сообщение как код, только если оно похоже на код (`^[A-Z_]+$`). Поэтому все контроллеры на `AuthenticatedApiJsonController` отвечают 401/403/404 с `error.code = SERVICE_UNAVAILABLE`. Это нарушает контракт ошибок `docs/waves/e2/README.md`: 401/403 — `UNAUTHORIZED`/`FORBIDDEN`, 404 — `NOT_FOUND`.
2. `rebit.share/lib/Contracts/Organization/Dto/CalendarCommandInputDto.php` проверяет данные в конструкторе. Это нарушает правило CLAUDE.md: DTO содержит только public readonly свойства и пустой конструктор.
3. Коллизия ID волны D3 с оперативным PR #32.

Это issue-ветка, не продуктовая волна: `docs/waves/graph.json` не меняется.

- Ветка: `codex/issues-42-access-error-codes`.
- Worktree: `/home/user/rabit-api-worktrees/issues-42-access-error-codes`.
- Base: `origin/main` `49f40f9` (merge PR #35).

## Установленные факты (main 49f40f9)

**Источники текстовых отказов:**
- `rebit.share`: `BearerTokenFilter:54`, `AuthenticatedControllerTrait:42` — `'Unauthorized'`, 401.
- `rebit.auth`: `TokenResolver:31` (пустой токен), `AuthController::logoutAction:86` — `'Unauthorized'`, 401. Остальные отказы `TokenResolver` уже кодовые: `SESSION_REVOKED`, `TOKEN_EXPIRED`.
- `morefoto.access`:
  - `StaffAuthorization::context` — `'Unauthorized'` 401 и `'Staff access is unavailable.'` 403;
  - `StaffAuthorization::assertCan` — `'Resource not found.'` 404 или `'Action is forbidden.'` 403;
  - `InstitutionAccess::scope` и `::lockParticipants` — `'Action is forbidden.'` 403 и `'Unauthorized'` 401;
  - `AccessGuard::assertCan` (неизвестное действие) — `'Action is forbidden.'` 403;
  - `CatalogAccessGuard` — `CatalogAccessException` с текстами. Её потребители (`CatalogController`, `ConditionsController`) текст не читают, код выбирают по статусу.

**Локальные обходы, переводящие текст в код по статусу:**
- `morefoto.commerce/lib/Infrastructure/Order/OrderStaffAccess.php` — 401/403 → `UNAUTHORIZED`/`FORBIDDEN`;
- `morefoto.handoff/lib/Application/Request/Service/StaffTransferGuard.php` — 401/403 → коды, phpDoc прямо ссылается на #42;
- `morefoto.media/lib/Application/Transfer/UseCase/TransferChildUseCase.php::authorize` — 401/403 → коды, 404 → `GROUP_NOT_FOUND` (комментарий «until #42»);
- legacy `CatalogController`/`ConditionsController` — полный собственный exception mapping по статусу с человекочитаемым `message`;
- «загрязнённые» `StaffController`, `InstitutionController`, `StructureController`, `MediaController` — берут кодовое сообщение, иначе код по статусу.

**Frontend** (`frontend/src`) не сравнивает текст этих сообщений. Решения принимаются по HTTP-статусу или по машинному коду (`apiErrorCode` берёт `error.code`, затем `error.message`, только если значение похоже на код). `sessionEndReason` для `UNAUTHORIZED` и для отсутствующего кода ведёт себя одинаково.

**`CalendarCommandInputDto`:** используется в `ChangeGroupCalendarUseCase`, `GroupCalendar::confirmLinkSent/extend`, `GroupCalendarTest`, `api/tools/e2e/verify-links.php`, фикстурах C3 (`api/tools/fixtures/c3/*.php`) и в заглушке `GroupLinkWorkflowTest`. Никто, кроме unit-теста, не полагается на исключение конструктора. `GroupCalendar` напрямую создают `verify-links.php` и `api/tools/fixtures/c3/calendar-checks.php`.

**Архитектурная проверка DTO:** `morefoto.handoff/tests/Unit/StaffRequestDtoArchitectureTest.php` проверяет DTO handoff и три DTO из `rebit.share/lib/Contracts`. Из 25 DTO в `rebit.share/lib/Contracts/*/Dto` форму нарушает только `CalendarCommandInputDto`.

**Пункт 3** уже закрыт в main коммитом `90584de`:
- материалы PR #32 лежат в `docs/plans/OPS-stage-media-recovery/`;
- `docs/waves/d3/` принадлежит волне графа D3 (PR #46);
- правило «оперативные исправления вне графа — `codex/ops-<slug>`, `docs/plans/OPS-<slug>/`» записано в `CLAUDE.md` и `AGENTS.md`.

## Решения

- **R1.** Коды бросаются в источнике строковыми литералами, как в остальном коде: `UNAUTHORIZED` (401), `FORBIDDEN` (403), `NOT_FOUND` (404). Отключённый профиль сотрудника (`'Staff access is unavailable.'`) — `FORBIDDEN`: отдельного кода контракт не описывает. HTTP-статусы не меняются.
- **R2.** `StaffAuthorization` отдаёт общий `NOT_FOUND`, а не предметный: он же сейчас получается у `InstitutionController`/`StructureController` по статусу. `TransferChildUseCase` сохраняет свой контракт и переводит только 404 → `GROUP_NOT_FOUND`. Переводы 401/403 удаляются.
- **R3.** Удаляются обходы `OrderStaffAccess` (весь `try/catch`) и `StaffTransferGuard` (перевод 401/403). Их поведение не меняется: источник уже отдаёт те же коды.
- **R4.** `CatalogController`/`ConditionsController` не меняются. Их mapping по статусу — это собственный контракт legacy-контроллера:
  - `message` — человекочитаемый текст;
  - любой 401 (включая `TOKEN_EXPIRED`/`SESSION_REVOKED`) сводится к `UNAUTHORIZED`;
  - непредвиденная ошибка — 500 `INTERNAL_ERROR`.
  Удаление mapping изменило бы контракт. Перевод на чистую границу — отдельный follow-up по правилу CLAUDE.md о загрязнённых контроллерах. Тексты `CatalogAccessException` в `CatalogAccessGuard` заменяются кодами (`UNAUTHORIZED`, `FORBIDDEN`, `ACCESS_UNAVAILABLE`); ответы контроллеров от этого не меняются.
- **R5.** Контроллеры на `BaseJsonController` с общим `JsonExceptionResponse` (`AuthController`, `ProfileController`, `FileController`) отдают текст исключения в `error.message`. Там `Unauthorized` станет `UNAUTHORIZED`, а у `/api/v1/me` 403 — `FORBIDDEN`. Статусы прежние, frontend на текст не опирается.
- **R6.** Проверки команды календаря переносятся в `Morefoto\Organization\Application\Calendar\Service\CalendarCommandValidator`: stateless, singleton в DI, исключение и тексты прежние (`\InvalidArgumentException`). Вызывается:
  - в начале `ChangeGroupCalendarUseCase::execute` — до транзакции и любых запросов Access/Organization;
  - в начале `GroupCalendar::confirmLinkSent/extend` — до `findOperation`, для прямых межмодульных вызовов через `GroupCalendarInterface` (`verify-links.php`, фикстуры C3).
  Двойная проверка на пути UseCase дешёвая и сохраняет инвариант для обоих входов.
- **R7.** Существующий `morefoto.handoff/tests/Unit/StaffRequestDtoArchitectureTest.php` расширяется: вместо явного списка трёх DTO из share он обходит все `rebit.share/lib/Contracts/**/Dto/*Dto.php` тем же правилом формы. Отдельный тест и support-класс не создаются (минимальный diff). Перенос проверки в модуль-владелец `rebit.share` — возможный follow-up.
- **R8.** Unit-тест `ApiJsonExceptionResponse` требует классов ответа Bitrix. В `api/tests/stubs/bitrix.php` добавляются минимальные runtime-стабы `Bitrix\Main\Response`, `HttpResponse`, `Engine\Response\Json`, а для теста `BearerTokenFilter` — `Event`, `EventResult`, `HttpRequest`, `Engine\ActionFilter\Base`. Все стабы под `class_exists`-guard, как существующие.
- **R9.** HTTP/E2E-контракт добавляется в существующий `frontend/e2e/live/staff.spec.ts` (группа `a` в `groups.json`, новый spec не нужен). Полный `make test-e2e` — после review (правило пользователя), до этого кейсы E2E в статусе PENDING.

## Scope

### Пункт 1 — коды отказа

1. `BearerTokenFilter`, `AuthenticatedControllerTrait`, `TokenResolver` (пустой токен), `AuthController::logoutAction` → `UNAUTHORIZED`.
2. `StaffAuthorization` → `UNAUTHORIZED` / `FORBIDDEN` / `NOT_FOUND`; `InstitutionAccess` → `FORBIDDEN` / `UNAUTHORIZED`; `AccessGuard` → `FORBIDDEN`; `CatalogAccessGuard` → коды (R4).
3. Удалить обходы: `OrderStaffAccess`, `StaffTransferGuard`, перевод 401/403 в `TransferChildUseCase` (R2, R3). Обновить phpDoc, где он ссылался на #42.
4. Тесты:
   - `ApiJsonExceptionResponse`: код из сообщения, запасные коды, статусы;
   - источники: `BearerTokenFilter`, `AuthenticatedControllerTrait`, `TokenResolver`, `StaffAuthorization`, `InstitutionAccess`, `AccessGuard`, `CatalogAccessGuard`;
   - обновить тесты удалённых обходов.
5. E2E: запрос без Bearer → 401 `UNAUTHORIZED`, запрещённое действие → 403 `FORBIDDEN`.

### Пункт 2 — `CalendarCommandInputDto`

1. DTO — только свойства и пустой конструктор.
2. `CalendarCommandValidator` с phpDoc, DI, вызовы по R6.
3. Обновить прямые конструкторы `GroupCalendar`/`ChangeGroupCalendarUseCase`: DI, `verify-links.php`, `fixtures/c3/calendar-checks.php`.
4. Перенести тесты `GroupCalendarTest` (ключи, UUID) на валидатор, добавить actor/revision/reason и тест «отказ до обращения к БД» для UseCase и `GroupCalendar`.
5. Архитектурный тест DTO на `rebit.share/lib/Contracts` (R7).

### Пункт 3 — только проверка

Подтвердить состояние main, код и документацию не менять.

### Исключено

- Перевод `CatalogController`/`ConditionsController` и других «загрязнённых» контроллеров на `ApiJsonExceptionResponse` (R4) — follow-up.
- Человекочитаемые сообщения `rebit.auth` о регистрации (409/404/410/429) и капче: это не отказ доступа, их показывает frontend.
- Изменение графа волн, документации D3 и `docs/waves/e2/README.md` (контракт не меняется, код приводится к нему).

## Риски и ограничения

- Внешние клиенты `AuthController`/`ProfileController`/`FileController` увидят `UNAUTHORIZED` вместо `Unauthorized` в `error.message` (R5). Frontend проверен; других клиентов у API нет.
- Runtime-стабы Bitrix в `api/tests/stubs` могут разойтись с реальным ядром. Стабы содержат только используемые методы, а реальный путь проверяет E2E.
- E2E-gate проводится после review: до него контракт подтверждён unit-тестами и кодом E2E-сценария, но не прогоном.

## Checklist

- [x] S1. План и прогресс.
- [x] S2. Источники отказа → коды, удаление обходов.
- [x] S3. Unit-тесты источников и `ApiJsonExceptionResponse`, runtime-стабы.
- [x] S4. `CalendarCommandValidator`, пассивный DTO, DI, tools, тесты.
- [x] S5. Архитектурный тест DTO `rebit.share/lib/Contracts`.
- [x] S6. E2E-сценарий контракта в `staff.spec.ts`.
- [x] S7. Быстрые проверки: phplint, PHPStan, PHPUnit, php-cs-fixer по изменённым файлам, frontend `npm run check && npm run test:commerce`.
- [ ] S8. Коммиты, push, PR в `main` (не сливать).
- [ ] S9. После review — полный `make test-e2e` (PENDING).

## Критерии приёмки

- Для контроллеров на `AuthenticatedApiJsonController` отказ доступа отдаёт `UNAUTHORIZED`/`FORBIDDEN`/`NOT_FOUND` (или предметный `*_NOT_FOUND`) с прежними статусами. Это подтверждают unit-тесты источников и `ApiJsonExceptionResponse` и E2E (без Bearer, запрещённое действие).
- В `lib` нет текстовых `'Unauthorized'`, `'Action is forbidden.'`, `'Resource not found.'`, `'Staff access is unavailable.'`.
- Обходы `OrderStaffAccess`, `StaffTransferGuard` и перевод 401/403 в `TransferChildUseCase` удалены, их тесты зелёные.
- `CalendarCommandInputDto` содержит только свойства и пустой конструктор. Невалидные команды отклоняются до обращения к БД. Архитектурный тест DTO покрывает `rebit.share/lib/Contracts`.
- Пункт 3 подтверждён без изменений.
- Быстрые backend/frontend проверки зелёные; полный E2E — после review.

## Тест-кейсы

| ID | Предусловия / действие | Ожидаемый результат | Команда |
|----|------------------------|---------------------|---------|
| T01 | `ApiJsonExceptionResponse` с `HttpException('UNAUTHORIZED'/'FORBIDDEN'/'NOT_FOUND', 401/403/404)` | статус прежний, `error.code` = сообщение, `meta.requestId` есть | PHPUnit `ApiJsonExceptionResponseTest` |
| T02 | `ApiJsonExceptionResponse` с текстовым сообщением, `ValidationHttpException`, чужим исключением, недопустимым статусом | `SERVICE_UNAVAILABLE` (503/прежний статус), `VALIDATION_FAILED` (422); `details` только у кодового сообщения | PHPUnit `ApiJsonExceptionResponseTest` |
| T03 | `BearerTokenFilter` required без заголовка / без `Bearer ` / с пустым токеном; optional без заголовка | `UNAUTHORIZED` 401; optional пропускает, resolver не вызывается | PHPUnit `BearerTokenFilterTest` |
| T04 | `AuthenticatedControllerTrait::getAuthUserId()` без пользователя | `UNAUTHORIZED` 401 | PHPUnit `BearerTokenFilterTest::testControllerWithoutUserRefusesWithCode` |
| T05 | `TokenResolver::resolveUserId('')` | `UNAUTHORIZED` 401, репозиторий не вызывается | PHPUnit `TokenResolverTest` |
| T06 | `StaffAuthorization`: нет активной identity; отключённый профиль; запрет действия; скрытая чужая группа | `UNAUTHORIZED` 401; `FORBIDDEN` 403; `FORBIDDEN` 403; `NOT_FOUND` 404 | PHPUnit `AccessRefusalCodesTest`, `TeacherAuthorizationTest` |
| T07 | `InstitutionAccess::scope` учителя; `lockParticipants` с чужим bearer; `lockParticipants` не организатора | `FORBIDDEN`; `UNAUTHORIZED`; `FORBIDDEN` | PHPUnit `AccessRefusalCodesTest` |
| T08 | `AccessGuard::assertCan` с неизвестным действием; `CatalogAccessGuard` без токена / без права / при сбое провайдера | `FORBIDDEN` 403; `UNAUTHORIZED` 401 / `FORBIDDEN` 403 / `ACCESS_UNAVAILABLE` 503 | PHPUnit `AccessRefusalCodesTest` |
| T09 | `OrderStaffAccess`, `StaffTransferGuard`, `TransferChildUseCase` получают кодовый отказ Access | код проходит без изменений; `TransferChildUseCase` переводит `NOT_FOUND` 404 в `GROUP_NOT_FOUND` | PHPUnit `OrderReceiptsAndAccessTest`, `StaffTransferUseCaseTest`, `TransferChildUseCaseTest` |
| T10 | `grep` текстовых отказов в исключениях `api/public/local/modules/*/lib` | совпадений нет; строки `'Unauthorized'` остаются только в человекочитаемом `message` ответа `CatalogController`/`ConditionsController` (R4) | `grep -rnE "Exception\('(Unauthorized\|Action is forbidden\.\|Resource not found\.\|Staff access is unavailable\.)'" api/public/local/modules/*/lib` |
| T11 | `CalendarCommandValidator` с неверным ключом (8 вариантов), неканоническим UUID (4), actor < 1, revision < 1 и > 2147483646, пустой / не UTF-8 / с `\0` / длиннее 1000 причиной | `\InvalidArgumentException`; валидные данные проходят | PHPUnit `CalendarCommandValidatorTest` |
| T12 | `ChangeGroupCalendarUseCase` и `GroupCalendar` получают невалидную команду | исключение до транзакции, Access и репозиториев (моки `never()`) | PHPUnit `CalendarCommandValidatorTest` |
| T13 | Все `rebit.share/lib/Contracts/**/Dto/*Dto.php` и DTO handoff | `final readonly`, только public типизированные свойства, единственный метод — пустой `__construct` | PHPUnit `StaffRequestDtoArchitectureTest` |
| T14 | Статический анализ, синтаксис, стиль | PHPStan `[OK] No errors`, phplint OK, php-cs-fixer dry-run 0 файлов | `vendor/bin/phpstan analyse`, `vendor/bin/phplint`, `php-cs-fixer fix --dry-run` по изменённым файлам |
| T15 | Полный PHPUnit | все тесты зелёные | `vendor/bin/phpunit` |
| T16 | Frontend | `npm run check && npm run test:commerce` зелёные | docker `playwright:v1.52.0-jammy` |
| T17 | E2E: `GET /api/v1/users` и `GET /api/v1/staff-requests` без `Authorization` | 401, `error.code = UNAUTHORIZED` | `make test-e2e` (после review) |
| T18 | E2E: учитель запрашивает `GET /api/v1/users` | 403, `error.code = FORBIDDEN` | `make test-e2e` (после review) |
| T19 | E2E-верификатор `verify-links.php` и фикстуры C3 с новым конструктором `GroupCalendar` | проходят в полном gate | `make test-e2e` (после review) |
| T20 | Пункт 3: `docs/plans/OPS-stage-media-recovery/` существует, `docs/plans/D3_stage-media-recovery/` нет, правило в `CLAUDE.md`/`AGENTS.md` | подтверждено | `ls docs/plans`, `grep -n OPS CLAUDE.md AGENTS.md` |
