# Issues #26, #27 и #28 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-26-27-28-staff-requests` от `origin/main` `54bd4ab`, upstream `origin/codex/issues-26-27-28-staff-requests`.
- Worktree: `/home/user/rabit-api-worktrees/issues-26-27-28-staff-requests`. Основной checkout `/home/user/rabit-api` занят другой сессией — в нём не работать.
- Issues: #26, #27, #28. PR: см. хронологию (создаётся этим этапом), не сливать.
- Коммиты: #26 `92fbc7f`, #27 `82712b2`, #28 `02d4904` (+ журнал).
- Документация: [план](plan.md), F1: [plan](../F1_staff_requests/plan.md), [README](../../waves/f1/README.md).
- Завершено: код и тесты трёх issue, быстрые проверки backend/frontend, push.
- Сейчас: ожидание review пользователя.
- Следующий шаг: после review без блокеров — полный `make test-e2e` (T08–T15), фактические время/память из `verify-handoff.log` перенести в журнал и PR.
- Блокеров нет. Открыто: Q1 (убрать `history` из элементов HND-06 — изменение контракта).
- Рабочее дерево: закоммичено. Пустые `api/vendor`, `api/var`, `frontend/node_modules` — точки монтирования docker, в git не попадают; `frontend/reports` игнорируется.
- Команды проверок:
  - backend: `docker run --rm --network none --memory 1536m --cpus 2 --env XDEBUG_MODE=off --mount type=bind,source=<worktree>/api,target=/app,readonly --mount type=volume,source=rabit-issues42-vendor,target=/app/vendor --tmpfs /app/var:rw,size=256m --workdir /app --entrypoint php rabit-api-php-cli:d1-local vendor/bin/phpunit --colors=never` (так же `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`, `vendor/bin/phplint`);
  - frontend: том `rabit-issues262728-node`, `docker run --rm --network none -v <worktree>/frontend:/app -v rabit-issues262728-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`.

## Хронология

### 2026-09-25 — разведка и план

- Прочитаны `CLAUDE.md`/`AGENTS.md` (идентичны), issues #26–#28, код `morefoto.handoff` и `frontend/src/modules/morefoto/handoff` на `54bd4ab`.
- Установлено: PK `mf_staff_request_idempotency (ACTOR_ID, RESOURCE_KEY, IDEMPOTENCY_KEY)` уже есть → миграция для #27 не нужна.
- Решения R1–R8 и вопрос Q1 — в плане.
- Создан том `rabit-issues262728-node`, `npm ci` — успешно.

### 2026-09-25 — #26 backend и frontend

- `StaffRequestRepository::page()`: счётчики по статусу (итог страницы берётся из них), карточки страницы одним SQL, строки и итоги переноса одним SQL (`LEFT JOIN` целевой группы), история одним SQL. Было 3 + 4N запросов (N=100 → 403, N=1000 → 4003), стало 4 при любом N (2 при пустой странице). `view()` для HND-08 и `results()` для D3 используют те же пакетные методы.
- Тестовый стаб `Application::getConnection()` + `DB\Connection`/`SqlHelper` в `api/tests/stubs/bitrix.php`; `StaffRequestRepositoryTest` подменяет соединение через reflection и считает SQL.
- Верификатор gate `api/tools/e2e/verify-handoff.php` (зарегистрирован в `tools/run-browser-e2e.py` после `zzz-handoff`): 1000 синтетических заявок в транзакции с откатом, SQL/время/память для pageSize 1/25/100 и глубокой страницы, эталон — чтение 100 карточек по одной (3 SQL на карточку; прежний путь списка — 4).
- Frontend: HND-06 читается одной страницей (pageSize 20) с серверными `status`/`shootId`, `v-pagination`; карточка — HND-08 + HND-06 `pageSize=1` для scope, 404 карточки → «Список не найден». Смена маршрута/фильтра/страницы перечитывает workspace; `reload()` возвращает успех и при ошибке сохраняет прежние данные. Демо отдаёт новые списки первыми (разворот перенесён из экрана в демо-загрузку).
- E2E `zzz-handoff.spec.ts`: первая страница с `pageSize=20`; карточка без чтения других страниц; плитка статуса → серверный фильтр.

### 2026-09-25 — #27 резерв ключа идемпотентности

- `StaffRequestRepository::reserveIdempotency()` (`INSERT IGNORE` с пустым `RESULT_JSON`) и `completeIdempotency()` (`UPDATE` результата); `idempotency()` получил `$lock` — повтор читает зафиксированную строку без `FOR UPDATE`, чтобы ожидающие дубли с S-блокировкой не взаимоблокировались на X.
- `StaffRequestWorkflow::save()/clarify()`: `claim()` — резерв ключа до блокировки заявки; иначе replay или `IDEMPOTENCY_CONFLICT`. Порядок блокировок: ключ → заявка. Ошибка откатывает резерв.
- `ConfirmStaffTransferUseCase` (D3) не менялся: до чтения ключа он блокирует группы заявки, поэтому одинаковые переносы уже сериализуются и второй видит зафиксированный ключ.
- Миграция не нужна: PK `(ACTOR_ID, RESOURCE_KEY, IDEMPOTENCY_KEY)` уже есть.
- PHPUnit: replay update/clarify/create после ожидания ключа без мутации; иной hash → `IDEMPOTENCY_CONFLICT`; резерв раньше блокировки заявки.
- E2E `zzz-handoff.spec.ts`: 4 одновременных PUT, 4 create и 4 clarify с одним ключом → одинаковый результат, одна мутация, история без дублей; иное тело → 409.
- Проверки: PHPUnit OK (741 tests), PHPStan OK, php-cs-fixer (изменённые файлы) — исправлено форматирование теста, `npm run typecheck:e2e` OK.

### 2026-09-25 — #28 восстановление формы

- `api.ts`: нет HTTP-ответа → «Ответ сервера не получен — изменения могли сохраниться…»; тексты `REVISION_CONFLICT`/`IDEMPOTENCY_CONFLICT` ведут к «Загрузить актуальные данные». Предпросмотр переноса при потере ответа пишет о загрузке набора, а не о сохранении.
- `useHandoffEditor`: второй аргумент `refresh()` (серверное чтение, возвращает успех). `reset()` сначала читает сервер, затем пересобирает форму с новым ключом; ошибка чтения оставляет форму. `open()` восстанавливает draft только при совпадении `revision` с серверной, иначе `stale` и форма по серверу. Закрытие после неудачного сохранения перечитывает workspace.
- `StaffRequestsScreen`: `refreshWorkspace()` = `reload()` + ожидание свежего предпросмотра переноса (подпись confirm). `LinksScreen` передаёт `reload` (тот же общий reset). Уведомление «Черновик устарел…» в обоих диалогах.
- `useTransferPreview.settled()` — ожидание последнего запроса HND-10.
- E2E `zzz-handoff.spec.ts`: `loseNextPut()` (`route.fetch()` + `route.abort()`, либо только abort): неопределённый исход и replay тем же ключом; «Загрузить актуальные данные» выполняет GET HND-08; устаревший draft после reload не восстанавливается; недошедшая команда переживает reload с тем же ключом; внешний `REVISION_CONFLICT` → закрытие читает сервер → форма по серверу.
- Проверки: `npm run check` exit 0, `npm run test:commerce` 195/195.

### 2026-09-25 — финальные проверки и PR

- Backend: PHPUnit OK (741 tests, 44531 assertions), PHPStan OK, phplint OK (989 files), `php -l tools/e2e/verify-handoff.php` OK, php-cs-fixer по изменённым файлам OK.
- Frontend: `npm run check` exit 0, `npm run test:commerce` 195/195.
- Дополнительно: демо-Cucumber `e2e/features/handoff.feature` + `dashboard.feature` (`start-server-and-test e2e:server … cucumber-js`). Сценарии R10 (handoff, включая «черновик и повтор после ошибки» и reset подтверждения переноса) — без падений. Падения только в `dashboard.feature` на входе демо-кабинета («Не удалось загрузить данные» на странице логина) — вне diff ветки (auth не менялся), демо-Cucumber открыт и на `main`. Прогон остановлен по таймауту 60 мин после прохождения handoff.
- Push `codex/issues-26-27-28-staff-requests`.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|----|--------|------|---------|----------------|
| T01 | PASS | 2026-09-25 | PHPUnit (backend-команда) | `testPageQueryCountDoesNotDependOnItems` 1/100/1000 → 4 SQL |
| T02 | PASS | 2026-09-25 | PHPUnit | `testRelatedRowsAndHistoryStayWithTheirRequestsInPageOrder`, `testDetailCardUsesTheSameBatchReads` |
| T03 | PASS | 2026-09-25 | PHPUnit | `testFiltersScopeAndPaginationReachTheCardQuery`, `testEmptyPageSkipsRelatedQueries` |
| T04 | PASS | 2026-09-25 | PHPUnit | `testConcurrentUpdateWithTheSameKeyReplaysAfterTheKeyWait` |
| T05 | PASS | 2026-09-25 | PHPUnit | `testConcurrentClarificationWithTheSameKeyReplays`, `testConcurrentCreateWithTheSameKeyDoesNotCreateASecondRequest` |
| T06 | PASS | 2026-09-25 | PHPUnit | `testSameKeyWithAnotherBodyIsAConflict` |
| T07 | PASS | 2026-09-25 | PHPUnit | `testKeyIsReservedBeforeTheRequestIsLocked`, `testTeacherCreatesVerifiedRequestOnlyInsideAssignedGroup` |
| T08 | PENDING | 2026-09-25 | `zzz-handoff.spec.ts` | написан, запуск — gate после review |
| T09–T12 | PENDING | 2026-09-25 | `zzz-handoff.spec.ts` | написан, `typecheck:e2e` OK, запуск — gate после review |
| T13, T14 | PENDING | 2026-09-25 | `zzz-handoff.spec.ts` | написан, запуск — gate после review |
| T15 | PENDING | 2026-09-25 | `verify-handoff.php` | написан, `php -l` OK, запуск — gate после review |
| T16 | PASS | 2026-09-25 | PHPUnit/PHPStan/phplint/php-cs-fixer | 741 tests OK, No errors, 989 files OK |
| T17 | PASS | 2026-09-25 | `npm run check && npm run test:commerce` | exit 0, 195/195 |
