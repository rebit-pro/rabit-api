# L1 — архив оплаченной печати — прогресс

## Точка продолжения

- Дата: 15.10.2026. PR #196 слит в main 72aa95b и выложен на stage релизом `main-20261015141010-72aa95b` (журнал «выкладка»).
- Сейчас: волна закрыта; на stage архив собирается сервисом `morefoto_stage_production_runner`.
- Следующий шаг: живая проверка владельцем на ближайшей группе, у которой закроется печать (через час после срока архив соберётся сам); группы, закрытые до выкладки, — кнопкой «Собрать архив». Следующая волна записывает L1 `merged` в граф (PR #196, 72aa95b).
- Блокеры: нет. Открыто: #197, #198, #199.
- Для следующих релизов: `switch-backend.sh` включает **12** backend-сервисов — прежние 11 и `morefoto_stage_production_runner`; `backend.conf` берётся из этого релиза (строка map `production/archives`).
- Откат stage: `docker service rm morefoto_stage_production_runner`; backend/воркеры — `docker service rollback` (прежний `/app` — `main-20261013161806-96b74b6`, спецификации в `services-before.json` релиза); frontend — `docker service rollback morefoto_frontend` (прежний `morefoto-frontend:main-20261015105755-6356e12`). Таблицы и модуль L1 можно оставить; `down` миграции не выполнять.

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

### 15.10.2026 — гейт 2

- Прогон `rabit-e2e-3597a0d6a331` на 013433a — FAIL только на новом `verify-production.php`: `SqlQueryException (1525) Incorrect DATETIME value '15.10.2026 10:18:54'`. Bitrix `fetch()` превращает DATETIME из `SELECT *` в объект в формате сайта, верификатор подставлял его обратно в SQL. Ошибка верификатора, не продукта (репозиторий L1 читает даты через `DATE_FORMAT`). Группа b браузера — PASS целиком (включая G1-T14 и `zzzzzzzzzzzz-print-archive`), 13 прежних верификаторов — PASS; группа a отменена после падения.
- Исправлено: `fetchRaw()` и `DATE_FORMAT(DEADLINE_AT)` в верификаторе. Следующий шаг — гейт 3.

### 15.10.2026 — гейт 3 PASS и самопроверка

- `make test-e2e` — прогон `rabit-e2e-961182e05ae4` на 357c64b — **PASS** за 384 с: браузер 153 (a 81, b 72), 16 верификаторов MySQL включая `verify-production.php`; быстрые проверки frontend/backend и сборка; тестовый магазин ЮKassa включён. Скриншоты desktop/mobile — `docs/waves/l1/screenshots/`, `visual.json`, `verification.json`.
- Самопроверка по поручению владельца («потом сам сделай ревью»): review 5531966364 в PR #196 — три блокера в тредах к строкам (PRODUCT_FORMAT, плашка «Собирается», верификатор), все исправлены; неблокирующие — #197 (PRD-01 по запросу на группу), #198 (INSERT IGNORE и CHECK), #199 (адресация PRD-05/06/11 в L2/L3, куратор в E2E).

### 15.10.2026 — выкладка

- По команде владельца («Если гейт зелёный — мержи и выкладывай») после гейта PASS PR #196 слит: 72aa95b.
- Релиз `/srv/morefoto/releases/main-20261015141010-72aa95b`: `git archive 72aa95b api`, образ `morefoto-frontend:main-20261015141010-72aa95b` (`VITE_API_MOCKS_ENABLED=false`, включает и гайд #192); vendor из `main-20261013161806-96b74b6` (composer.lock не менялся); `backend.conf` — прежний + строка map `~^/api/v1/public/production/archives/ 0;`.
- `sha256sum --check` PASS; `prepare-release.sh` PASS; `l1-prepare.sh` — симлинк `runtime/public/local/modules/morefoto.production`; `backup.sh` — 274 240 байт; `restore-check.sh` — 176 таблиц PASS.
- Preview (только чтение): переданных групп 5, из них печать закрыта у 2 (архив только кнопкой организатора, L1-DEC-08), открыта у 3; оплаченных заказов с печатью 1.
- `migrate.sh up Version20261015120001` — success (только эта версия); `install-module.sh` — `morefoto.production installed`.
- FPM первым: DI-smoke 15/15 (контроллеры и UseCase'ы L1, контракты Commerce/Organization, курсор планировщика). Затем backend (`nginx -t` OK) и 9 воркеров/диспетчеров.
- Новый сервис `morefoto_stage_production_runner` (`production-service.sh`, клон `morefoto_stage_files_dispatcher` с его монтированиями, env и секретами, в том числе ключом подписи): `app:production:run --limit=20` раз в минуту; первый прогон — «Новых версий: 0, собрано архивов: 0, не собрано: 0, удалено по сроку: 0».
- Frontend 2/2 `morefoto-frontend:main-20261015141010-72aa95b` (прежний `main-20261015105755-6356e12`). Все 13 stage-сервисов 1/1.
- Живая проверка: `/`, `/login`, `/health`, `/kak-kupit/`, `/guide/`, `/cabinet/links` — 200; PRD-01 и PRD-02 без входа — 401 UNAUTHORIZED; PRD-10 с неверным и коротким токеном — 404 ARCHIVE_NOT_FOUND, `Cache-Control: no-store`; бандл `index-DSfoVWoI.js` совпадает со сборкой; адресов архива в логе backend нет; Playwright 390/1280 — без ошибок и горизонтальной прокрутки, кабинет без сессии ведёт на вход; ошибок в логах FPM, воркеров и сервиса архива за 20 минут нет (кроме собственных проверочных 401/404 в канале `production`).
- Не проверено вживую под учётной записью: окно «Архив печати» и сборка на настоящей группе — покрыто гейтом, за владельцем на stage.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| L1-T01 | PASS | 15.10.2026 | unit; гейт `rabit-e2e-961182e05ae4`: `zzzzzzzzzzzz-print-archive`, `verify-production.php` |
| L1-T02 | PASS (unit) | 15.10.2026 | `testGroupWithoutPaidPrintGetsAnEmptyVersionWithoutZip` |
| L1-T03 | PASS | 15.10.2026 | unit; MySQL — `verify-production.php` (строка в одной версии, повторный прогон без новой версии) |
| L1-T04 | PASS (unit) | 15.10.2026 | `testMissingOriginalFailsAtOnceAndBrokenWritesRetryThreeTimes` |
| L1-T05 | PASS (unit) | 15.10.2026 | `ProductionRulesTest::testLayoutSeparatesProductsByIdAndSanitizesFolders` |
| L1-T06 | PASS | 15.10.2026 | unit; гейт: ZIP по ссылке, папки продукции, `состав.csv`; размер файла на диске — верификатор |
| L1-T07 | PASS (unit) | 15.10.2026 | `testManifestIsExcelFriendlyAndDefusesFormulas` |
| L1-T08 | PASS | 15.10.2026 | unit; гейт: воспитатель 403 |
| L1-T09 | PASS | 15.10.2026 | unit; гейт: куратор на сборку 403 |
| L1-T10 | PASS | 15.10.2026 | unit; гейт: ZIP без входа, `application/zip`, `no-store`, копирование ссылки |
| L1-T11 | PASS | 15.10.2026 | unit; гейт: прежняя, неверная и короткая ссылка — 404 |
| L1-T12 | PASS (unit) | 15.10.2026 | `testLateConfirmedPaymentGoesIntoASupplementWithOnlyNewLines` |
| L1-T13 | PASS | 15.10.2026 | unit; MySQL: состав = оплаченные до срока печатные строки без поздних |
| L1-T14 | PASS | 15.10.2026 | unit; гейт: «Собрать архив» в окне |
| L1-T15 | PASS | 15.10.2026 | unit; MySQL: удаление по сроку, 404 ссылки, сборка заново с новым поколением |
| L1-T16 | PASS | 15.10.2026 | гейт: desktop 1440 / mobile 390, `docs/waves/l1/screenshots/` |
| L1-T17 | PASS | 15.10.2026 | быстрые проверки выше |
| L1-T18 | PASS | 15.10.2026 | `rabit-e2e-961182e05ae4` |
