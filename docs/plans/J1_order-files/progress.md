# J1 — журнал

## Точка продолжения

- Ветка `codex/j1-order-files`, base `main` `e847e87` (слит в ветку), head `3b1246c` и последующий docs-коммит. PR https://github.com/rebit-pro/rabit-api/pull/143, issue #142.
- Рабочая копия `/home/user/rabit-api-worktrees/j1-order-files`.
- Документы: `plan.md` этой папки, граф `docs/waves/graph.json` (J1), канонический `MoreFoto/docs/05-rest-api/endpoints.json` (FIL-01…04), решения `docs/waves/w05/decisions.md` (D07, D10, D12).
- Завершено: S0–S6, S8 — графы, контракты, модуль `morefoto.files`, docker/nginx/cron, frontend-блок, unit/architecture-тесты, E2E-спецификация `zzzzzzzzzz-files` и `verify-files.php` (написаны, не запускались), документы волны, issue #142.
- Сейчас: gate PASS, PR #143 сливается в main.
- Следующий шаг: после ревью без блокеров — полный `make test-e2e` (с ключами тестового магазина), визуальная проверка скриншотов, `visual.json`, затем выкладка на stage по `docs/waves/j1/README.md`.
- Блокеры: нет. Открытых решений нет.
- Рабочее дерево: всё закоммичено в `codex/j1-order-files`.
- Следующая проверка: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=<vendor>` — nginx-образ стенд соберёт сам из `api/docker/development/nginx/Dockerfile`.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
| --- | --- | --- | --- | --- |
| J1-T01 | PASS (граф) | 2026-09-26 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`; канонический `wave_graph.py backend-waves.json`, `render-waves.py` | 52 волны, 118 ID, 35 WNN, 13 отрицательных фикстур; `readyFromMain` = [J1]. Patch канонического плана — на S8 |
| J1-T02 | PASS | 2026-09-26 | PHPUnit `OrderEntitlementsTest` | 31.01→28.02, високосный год, граница суток по Москве, декабрь→январь; продление ключа до `2027-02-28 07:00 UTC` |
| J1-T03, T04 | PASS (unit) | 2026-09-26 | PHPUnit `FileAccessTest` | unpaid/pending/review/available/граница/expired/empty; комплект без дублей, код digital сохраняется. HTTP — в E2E |
| J1-T05…T08, T11 | PASS (unit) | 2026-09-26 | PHPUnit `DownloadFlowTest`, `FilesAdaptersTest` | идемпотентность, одна сборка на заказ, переиспользование, настоящий ZIP (CM_STORE, байты совпадают), дубль сообщения, 3 попытки → failed без остатков, сбой брокера → dispatch, токен/ключ/срок/состав, purge |
| J1-T10 | PASS | 2026-09-26 | PHPUnit `FilesArchitectureTest` | контроллер, границы Commerce/Media, phpDoc |
| J1-T09 | PASS (unit) | 2026-09-26 | PHPUnit `OrderPaymentsTest` | paid продлевает ключ, pending — нет; миграция — в E2E verifier |
| J1-T12 | PASS | 2026-09-26 | docker Playwright-образ, volume `rabit-j1-node`: `npm run check`, `npm run test:commerce`, `npm run build-only` | check exit 0 (UI unit 56), commerce 214/214 (5 новых J1-T12), сборка OK |
| J1-T13, T14, T15 | PASS | 2026-09-26 | `make test-e2e` (стенд `rabit-e2e-3eb7b0053335`, 509,9 с) | 130 сценариев (a 79, b 51), full gate, ЮKassa test shop on; 3 сценария J1; `verify-files.php` и остальные 11 verifier PASS; скриншоты просмотрены — `docs/waves/j1/visual.json` |

## Хронология

- 2026-09-26. Пользователь проверил тестовую оплату G1 на stage и попросил следующую волну для выдачи электронных товаров. По графу J1 заблокирована цепочкой G2 → I1 → I2 → I3. Пользователь выбрал ранний срез J1 с зависимостями D1/E5/G1 (J1-GRAPH), затем — «Взять в реализацию». Проверено: веток и PR по J1 нет. Создан worktree от `origin/main` `4fc9dce`.
- 2026-09-26. Разведка:
  - Контракты FIL-01…04 есть только в каноническом `endpoints.json`, кодов ошибок FIL там нет.
  - Числа (лимит ZIP, срок архива, TTL ссылки) не заданы.
  - Модулей `morefoto.files` и `morefoto.settlement` нет. Контракта выдачи оригинала в Share нет.
  - Оригиналы лежат на локальной ФС (`MOREFOTO_PRIVATE_MEDIA_PATH`). В nginx и `api-cron` каталог не смонтирован.
  - Файлы отдаются только буферизованным PHP-ответом.
  - Событий об оплате нет, признак оплаты — `mf_order.PAYMENT_STATUS/PAID_AT/LATE_PAYMENT`.
  - `GIFTS` заказа — карта «ребёнок → bundle», подарок даётся без bundle-строки.
  - Ключ заказа действует 30 дней и может истечь раньше срока файлов.
- 2026-09-26. Пользователь принял J1-DEC-01…05 в рекомендованных вариантах: динамический состав комплекта, короткая ссылка + nginx X-Accel-Redirect, лимиты 2 ГиБ/500 файлов/24 ч/10 мин/1 сборка, фоновый consumer и продление ключа.
- 2026-09-26. S1: J1 зависит от D1/E5/G1, гейты D07/D10, `inProgress`; I2 зависит от J1 и получает обязательство отзыва `files=selected/all`, I3 — применение SET-07 к Files. K3 отмечена слитой (PR #89, `caa37b6`) в обоих графах. Канонический план правится на месте, снимок «до» лежит в scratchpad сессии (`mf-before`), patch соберётся на S8.
- 2026-09-26. Реализация backend. Ошибка, найденная тестом: при сбое сборки деструктор `ZipArchive` дописывал частичный архив — теперь `unchangeAll()` и `close()` в `finally`. PHPStan выявил конфликт свойства `$request` контроллера с Bitrix — переименовано.
- 2026-09-26. X-Accel-Redirect проверен на живом nginx 1.25 (python upstream): uid 1000 читает файл 0600, `Content-Disposition` и `Cache-Control` проходят от upstream, Range → 206, прямой `/_protected/` → 404. Решение по правам: в образах nginx пользователь `nginx` переназначен на 1000:1000 (как `www-data` в php-образах); архивы, собранные consumer от root, наследуют владельца корня хранилища.
- 2026-09-26. Проверки: `php.sh` (docker `rabit-api-php-cli:d1-local`, volume `rabit-j1-vendor`) — phplint OK 1347; PHPStan `tools/e2e/phpstan.neon` — No errors; PHPUnit — OK 1019 тестов / 46734 проверки; php-cs-fixer по изменённым файлам — исправлено 5, повтор 0. `tools/tests/test_run_browser_e2e.py` — 22 OK. `docker compose -f docker-compose-production.yml config` с подставными переменными — OK, 4 монтирования private-files.
- 2026-09-26. S5–S8: блок «Электронные фотографии» на странице заказа; E2E-спецификация `zzzzzzzzzz-files` (группа b): неоплаченный заказ, оплата картой на странице ЮKassa, файл и ZIP со сверкой sha256 оригинала, Range, подпись, идемпотентность, desktop/mobile; `verify-files.php`. Фикстура E4 передаёт оригиналы `www-data` (docker exec работает от root) и отдаёт sha256. `payOnProvider` перенесён в `helpers.ts`. Контракт FIL-01…04 обновлён в каноническом `build.py`, patch `docs/waves/j1/morefoto-contract.patch` (8 файлов, reverse-check OK). Неблокирующее — issue #142 (удаление купленных кадров).
- 2026-09-26. Слит `origin/main` `09597fd`: конфликт `prepare.php` (#117 перенёс миграцию legal после загрузки init.php) — оставлен цикл main, добавлены `20260926180001` и DoInstall files. Повтор: граф 52/118, ready [J1]; runner tests 22 OK; PHPStan — No errors; PHPUnit — OK 1029 / 46768; frontend check — exit 0, commerce 214/214, build OK.
- 2026-09-26. Открыт PR #143. Объём: 112 файлов, ~4,9 тыс. строк без patch и графа (в пределах одной волны).
- 2026-09-26. Ревью PR #143, первый круг: 3 блокера (R1 лог frontend nginx, R2 ключ переиспользованной загрузки, R3 архив без пути после сбоя). Исправлено: исключение из лога frontend nginx; таблица `mf_file_download_request` и `OrderDownloadGuardInterface` (GET_LOCK + транзакция); путь ZIP в строке при создании, purge убирает `.tmp`. Новые тесты: `testReusedArchiveBindsTheNewKeyToItsBody`, `testKeyKeepsItsDownloadWhenTheSetChanges`, `testArchiveIsPurgedAfterItsStatusWasNeverSaved`, проверки конфигов nginx (PHP и `tests/ui/private-links-log.test.mjs`). `verify-files.php` проверяет привязку ключей и пути живых архивов. Миграция `20260926180001` изменена до merge (не выкладывалась).
- 2026-09-26. Слит `origin/main` `e847e87` (#135): конфликт `VERIFIERS` — оставлены `verify-photo-deletion.php` и `verify-files.php`. Повтор: граф 52/118 ready [J1]; runner tests 22 OK; php-cs-fixer 0 из 87; phplint OK 1358; PHPStan — No errors; PHPUnit — OK 1050 / 46841; frontend check exit 0 (UI 67), commerce 220/220, build OK. Браузерный E2E по-прежнему после ревью без блокеров.
- 2026-09-26. Второй круг ревью — без новых замечаний. Слит `origin/main` `41b1146` (#141, медиа-воркер от www-data): конфликт в `tools/run-browser-e2e.py` — files-воркер тоже запускается `--user www-data`, проверка «не root» распространена на оба воркера; `api-files-consumer` в обоих compose получил `APP_RUN_AS_USER: www-data`; фикстура E4 теперь сама работает от www-data, поэтому J1-блок chown в `prepare-storefront.php` удалён; `test_media_worker_user.py` покрывает files-воркер. Проверки на `2cf5f8d`: tools tests 26 OK; граф 52/118 ready [J1]; cs-fixer 0/87; PHPStan — No errors; PHPUnit — OK 1050 / 46841; frontend check exit 0, commerce 220/220. Запущен полный `make test-e2e`.
- 2026-09-26. Gate 1 (`rabit-e2e-5434bc206fae`, 304,6 с) — FAIL. Все быстрые стадии PASS. Группа b: 48 PASS, `J1-T04/T13` — FAIL: FIL-02 `{kind:"zip"}` получил 422 `VALIDATION_FAILED`. Причина: `DtoMetadataService` понимает только `@var тип[]`, а у `CreateDownloadRequestDto::$photoIds` стояло `@var null|mixed[]`, поэтому метаданные DTO не строились (unit-тесты создавали DTO напрямую и этого не видели). Исправлено на `@var mixed[]`, nullable берётся из `?array`. Добавлен `FilesRequestContractTest`: тела FIL-02 и query FIL-04 проходят через `StrictRequestValues` + `ArrayToDtoMapper`, как в HTTP. Группа a отменена раннером после падения b.
- 2026-09-26. Gate 2 на `3249760` (`rabit-e2e-3eb7b0053335`, 509,9 с) — PASS: 130 браузерных сценариев (a 79, b 51), full gate, тестовый магазин ЮKassa включён; J1: неоплаченный заказ, оплата картой → файл (sha256 = фикстура) и ZIP с теми же байтами, идемпотентность/подпись/Range; 12 verifier PASS, включая `verify-files.php`. Скриншоты desktop/mobile просмотрены, сохранены в `docs/waves/j1/screenshots` с sha256 в `visual.json`. Следующий шаг: merge PR #143, затем выкладка на stage по `docs/waves/j1/README.md` (пользователь сделает позже).
- 2026-09-26. Перед merge `main` продвинулся на #149 (`0ef947b`, карточка кадра в кабинете): общих файлов с J1 нет, #149 менял только frontend (`icons.ts`, `ui/defaults.ts`, `_base.scss`, `PhotoCollection.vue`, `zz-media.spec.ts`) и прошёл собственный полный gate. Слит в ветку без конфликтов; повтор на объединённом состоянии: `npm run check` — exit 0 (UI 67), `test:commerce` — 220/220, `build-only` — OK, `mdi-download` в реестре иконок; граф 52/118. Backend #149 не менял — PHP-проверки и gate 2 остаются в силе.

### 2026-09-26, выкатка J1 (main 591d698) на app.morefoto36.ru

- Перед этим в тот же день выкачен main `0ef947b` (#149, код #144 без его серверных шагов): релиз
  `main-20260926161546-0ef947b`, миграций нет, DI-smoke 11/11, живая проверка PASS.
- **Релиз** `/srv/morefoto/releases/main-20260926163307-591d698` из main `591d698` (J1 #143 поверх `0ef947b`), по команде пользователя.
  `backup.sh` (85 050 байт), `restore-check.sh` — 173 таблицы: PASS.
- **Подготовка J1** (`j1-prepare.sh`): `runtime/var/private/files` 1000:1000 0700; симлинк
  `runtime/public/local/modules/morefoto.files`; образ `rabit-api-nginx:20260911-074507-uid1000` — прежний stage-образ,
  где пользователь `nginx` переназначен на 1000:1000 (как `api/docker/production/nginx/Dockerfile` J1).
- **Миграции**: только `migrate.sh up Version20260926180001` — success (Installed 31 → 32); `install-module.sh` —
  `morefoto.files installed`.
- **Backend nginx**: `backend.conf` поставляется с релизом — `default.conf` main с поправками stage; внутренние
  `/_protected/media/` и `/_protected/files/` указывают на `/runtime/var/private/…` (backend видит хранилище через
  `/runtime`); URL скачивания исключён из access-лога. Образ backend — `…-uid1000`; `nginx -t` OK, воркеры uid 1000,
  временные каталоги 1000, хранилища читаются.
- FPM первым; DI-smoke 16/16 (в т. ч. `PublicFileController`, Request/Open/Consume/Purge use case), блокировка
  оригинала OK. Затем backend и семь воркеров/диспетчеров.
- **Новые сервисы** (`files-services.sh`, клоны `morefoto_stage_media_consumer` + секрет `rebit_encryption_key`):
  `morefoto_stage_files_consumer` (`app:files:consume --limit=100 --time-limit=300`, от root, как медиа-воркер —
  архивы наследуют владельца корня хранилища) и `morefoto_stage_files_dispatcher` (`app:files:dispatch-pending` раз в
  минуту, `app:files:purge` раз в час). Очередь `filesArchive` создана в vhost `morefoto_stage`. Диспетчер: 0/0 без ошибок.
- Frontend 2/2 `morefoto-frontend:main-20260926163307-591d698` (прежний `morefoto-frontend:main-20260926161546-0ef947b`).
- Живая проверка: страницы — 200; `GET /api/v1/public/orders/current/files` без ключа — 404 `ORDER_NOT_FOUND`;
  `/_protected/files/…` на backend — 404 (internal), на домене frontend — SPA; удаление кадров без токена — 401;
  Playwright desktop/mobile — без ошибок. Скачивание по оплаченному заказу (файл и ZIP) — проверка пользователя.
- **Для следующих релизов**: `switch-backend.sh` должен включать `morefoto_stage_files_consumer` и
  `morefoto_stage_files_dispatcher` (всего 11 сервисов), backend — образ `…-uid1000` и `backend.conf` с J1.
- **Поправка**: ранее записано, что `rebit.leadhunter` «не слинкован в runtime» — неверно: симлинк есть (проверка
  `test -e` на хосте смотрела на путь внутри контейнера). Модуль по-прежнему не подключается в `init.php` и не имеет
  маршрутов.
- Серверные шаги #144 (смена владельца превью, медиа-воркер от www-data) **не выполнялись** — ждут отдельного согласия.
- Откат: `docker service rm morefoto_stage_files_consumer morefoto_stage_files_dispatcher`; backend/воркеры —
  `docker service rollback` (прежний `/app` — `main-20260926161546-0ef947b`, образ backend `rabit-api-nginx:20260911-074507`);
  FPM — по `services-before.json`. Модуль и таблицы J1 можно оставить.
