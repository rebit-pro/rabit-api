# L1 — архив оплаченной печати — прогресс

## Точка продолжения

- Дата: 15.10.2026. Ветка `codex/l1-print-archive`, base main cc92006; рабочая копия `/home/user/rabit-api-worktrees/l1-print-archive`. PR #196 (https://github.com/rebit-pro/rabit-api/pull/196); граф: L1 `review`. План: `docs/plans/L1_print-archive/plan.md`; README волны `docs/waves/l1/README.md`; патч MoreFoto `docs/waves/l1/morefoto-contract.patch`.
- Завершено: S0–S7 — контракты Commerce/Organization, модуль `morefoto.production` (миграция 20261015120001, cron `app:production:run`, PRD-01/02/03/04/07/10 и перевыпуск ссылки), блок «Печать» в «Ссылках и сроках», unit, live E2E `zzzzzzzzzzzz-print-archive` и верификатор `verify-production.php` (написаны, не запускались), README, патч MoreFoto.
- Сейчас: S8 — PR и ревью пользователя (большая волна: backend, миграция, новый модуль).
- Следующий шаг: после ревью без блокеров — полный гейт `make test-e2e` (первый прогон нового E2E и верификатора).
- Блокеры: нет. Риск: новый E2E и верификатор ещё не запускались на стенде.
- Рабочее дерево: всё закоммичено.
- Команды проверки:
  - PHP: `docker run --rm --network none -u 1000:1000 -e XDEBUG_MODE=off -v $PWD/api:/app -v rabit-e7-vendor:/app/vendor:ro -w /app rabit-api-php-cli:d1-local php vendor/bin/phpunit`; PHPStan — то же с `-v rabit-l1-phpstan:/app/var/phpstan ... vendor/bin/phpstan analyse`;
  - frontend: `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`;
  - гейт: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Журнал

### 15.10.2026 — старт

- Выбор волны: готовы из main только L1 и J2 (G2 ждёт D09 и реквизиты; J2 поставлен на паузу владельцем 12.10, SMTP не работает — #161). Рекомендована L1; пользователь подтвердил D06: «D06 подтверждаю».
- Инвентаризация (Explore по main 60bd97f): модуля `morefoto.production` и таблиц нет; оплаченный снимок `mf_order_line` содержит всё для печати (PRODUCT_PUBLIC_ID/NAME/FORMAT, PRINT_COUNT, QUANTITY, PHOTO_PUBLIC_ID, CHILD_CODE); `UF_CLOSES_AT` = printSalesUntil, после закрытия неизменен; ничего не срабатывает по окончании продаж (crontab); `PaymentReconciler` даёт `latePayment`, но нет выборки открытых попыток по группе; `OriginalFilesInterface` без исходного имени; ZIP/хранилище/токен J1 привязаны к заказу (токен без отзыва); live-карточки группы нет — блок «Печать» в `LinksScreen.vue`; нет `LogChannelEnum` для production; api-cron не видит private-media — сборка в consumer.
- Граф: D06 → `acceptedImplementationDecisions`, `decisionEvidence.D06-2026-10-15`, MVP-D06-SYNC убран из `openLaunchDecisions`; F3 merged (PR #186, 68355a0); baseline 60bd97f; L1 `inProgress`.
- Проверка 15.10.2026: `python3 tools/verify-wave-graph.py docs/waves/graph.json` — exit 0: 59 волн, 122 API, 35 legacy; готовы из main J2, L1; негативные фикстуры отклонены.

### 15.10.2026 — решения приняты

- Пользователь: «рекомендации L1 принимаю все». Граф: `decisionEvidence.L1-DEC-01-08`; из `openLaunchDecisions` убран MVP-LATE-01 (закрыт DEC-02), MVP-SIVMA-01 оставлен только операционной частью (рабочий каталог, ручной порядок передачи).
- Rebase на main cc92006 (PR #194 и запись выкладки — только frontend/docs, пересечений нет); baseline графа → cc92006. `python3 tools/verify-wave-graph.py docs/waves/graph.json` — exit 0.

### 15.10.2026 — реализация

- Технические уточнения плана до кода (коммит 9517946): без очереди RabbitMQ — одна cron-команда `app:production:run` (план, сборка под арендой, удаление по сроку); имя файла `<код кадра>_x<отпечатков>` — код кадра Media содержит код ребёнка; контракт Media не меняется; ожидающие оплаты — `mf_order.PAYMENT_STATUS='pending'` через Commerce, без контракта Payment; PRD-01 по `groupIds` для значка строк; «Скачать архив» в кабинете открывает ту же ссылку PRD-10.
- Backend (90a0639, 58bdf5a): `GroupPrintOrdersInterface` (Commerce, по индексу `GROUP_PUBLIC_ID`), `PrintClosedGroupsInterface` (Organization, курсор срок+ID), `AtomicZipWriter` в rebit.share (J1 `ZipArchiveBuilder` переведён на неё), `TextFileResponse`/`textFile()`, канал логов `production`; модуль `morefoto.production` — Domain (политика сроков, раскладка, состояние), Application (снимок, сборка, представление версии, 8 UseCase), Infrastructure (репозиторий с транзакционным снимком и `INSERT IGNORE`, хранилище `production/<группа>/<версия>.zip`, ZIP, CSV, HMAC-ссылка с поколением, доступ), чистые контроллеры, миграция, cron, map access-лога nginx, `private-media` только для чтения в api-cron production.
- Frontend (f8904d2): `production/live/*` — API, правила, `useGroupArchive` (опрос сборки), `PrintArchiveDialog`; «Ссылки и сроки» — значок в строке (PRD-01 для страницы) и «Архив печати» в меню строки.
- E2E: спецификация `zzzzzzzzzzzz-print-archive` последней в группе b; хук раннера `production_fixture` (запросы `l1-request-N.json`: закрыть печать группы J4 сразу после оплаченного заказа, выполнить `app:production:run`); `production-fixture.php`; верификатор `verify-production.php` (группа b, после J4).
- MoreFoto: реестр PRD-01/02/03/04/07/10 под реализацию L1 поверх `morefoto-plan-sync.patch`, `validate.py` — passed, 122 запроса, 59 волн.

### 15.10.2026 — быстрые проверки

- `php -l` всех изменённых файлов — без ошибок.
- PHPStan (основной конфиг и `tools/e2e/phpstan.neon`) — без ошибок.
- PHPUnit — OK, 1165 тестов, 50281 проверка (до L1 — 1145; новые 20 в `morefoto.production`).
- php-cs-fixer по изменённым файлам — 0 исправлений после автоправки.
- `npm run check` (lint, stylelint, typecheck, typecheck:e2e, `test:ui` 98/98) — exit 0; `npm run test:commerce` 243/243.
- `python3 tools/verify-wave-graph.py docs/waves/graph.json` — exit 0.

### 15.10.2026 — гейт 1 и самопроверка

- `make test-e2e` (команда из «Точки продолжения»), прогон `rabit-e2e-c87bf170a344` на e62c4cc — FAIL: группа b 69 passed, 1 failed, 2 did not run; группа a отменена после падения b; верификаторы не запускались. Упал `zzzzzzzzz-payments` G1-T14 — тестовый магазин ЮKassa не перевёл СБП-попытку в «Оплата не прошла» за 10 с (известный внешний #174, к L1 не относится). `zzzzzzzzzzzz-print-archive` — PASS с первого прогона (12,9 с), скриншоты desktop/mobile сняты.
- Самопроверка diff: блокер — `mf_production_line.PRODUCT_FORMAT VARCHAR(64)` уже `mf_order_line.PRODUCT_FORMAT VARCHAR(100)`: `INSERT IGNORE` молча обрезал бы длинный формат в снимке; исправлено до `VARCHAR(100)` (миграция ещё нигде не выполнялась). По скриншоту: после готовности в окне оставалась плашка «Архив собирается» — `useGroupArchive` снимает её, когда перезагрузка блока не видит сборки; E2E проверяет, что плашки нет.
- После правок: `npm run check` — exit 0 (`test:ui` 98/98). Повторный полный гейт — следующий шаг.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| L1-T01 | PASS (unit), live — PENDING | 15.10.2026 | `ProductionFlowTest::testRunBuildsOnlyPaidPrintAnHourAfterTheDeadlineAndOnlyOnce`; гейт: `zzzzzzzzzzzz-print-archive`, `verify-production.php` |
| L1-T02 | PASS (unit) | 15.10.2026 | `testGroupWithoutPaidPrintGetsAnEmptyVersionWithoutZip` |
| L1-T03 | PASS (unit), MySQL — PENDING | 15.10.2026 | повторный прогон в unit; уникальность на MySQL — `verify-production.php` |
| L1-T04 | PASS (unit) | 15.10.2026 | `testMissingOriginalFailsAtOnceAndBrokenWritesRetryThreeTimes` |
| L1-T05 | PASS (unit) | 15.10.2026 | `ProductionRulesTest::testLayoutSeparatesProductsByIdAndSanitizesFolders` |
| L1-T06 | PASS (unit), live — PENDING | 15.10.2026 | ZIP и `состав.csv` в unit; диск и байты — гейт |
| L1-T07 | PASS (unit) | 15.10.2026 | `testManifestIsExcelFriendlyAndDefusesFormulas` |
| L1-T08 | PASS (unit), live — PENDING | 15.10.2026 | `testRolesSeeOnlyTheirScopeAndOnlyTheOrganizerManages`; E2E: воспитатель 403 |
| L1-T09 | PASS (unit), live — PENDING | 15.10.2026 | то же; E2E: куратор на сборку 403 |
| L1-T10 | PASS (unit), live — PENDING | 15.10.2026 | `testLinkOpensOnlyItsCurrentGenerationWithinRetention`; E2E: ZIP без входа, `no-store`; map access-лога — `ProductionArchitectureTest` |
| L1-T11 | PASS (unit), live — PENDING | 15.10.2026 | то же; E2E: прежняя и неверная ссылка — 404 |
| L1-T12 | PASS (unit) | 15.10.2026 | `testLateConfirmedPaymentGoesIntoASupplementWithOnlyNewLines` |
| L1-T13 | PASS (unit), MySQL — PENDING | 15.10.2026 | фильтр контракта; SQL Commerce — `verify-production.php` |
| L1-T14 | PASS (unit), live — PENDING | 15.10.2026 | `testGroupClosedBeforeTheReleaseWaitsForTheOrganizer`; E2E: кнопка «Собрать архив» |
| L1-T15 | PASS (unit), MySQL — PENDING | 15.10.2026 | unit; `verify-production.php`: удаление по сроку и сборка заново |
| L1-T16 | PENDING | — | E2E desktop/mobile + visual |
| L1-T17 | PASS | 15.10.2026 | быстрые проверки выше |
| L1-T18 | PENDING | — | полный гейт после ревью |
