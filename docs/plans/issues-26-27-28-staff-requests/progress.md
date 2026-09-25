# Issues #26, #27 и #28 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-26-27-28-staff-requests` от `origin/main` `54bd4ab`.
- Worktree: `/home/user/rabit-api-worktrees/issues-26-27-28-staff-requests`. Основной checkout `/home/user/rabit-api` занят другой сессией — в нём не работать.
- Issues: #26, #27, #28. PR: ещё не создан.
- Документация: [план](plan.md), F1: [plan](../F1_staff_requests/plan.md), [README](../../waves/f1/README.md).
- Завершено: разведка, план.
- Сейчас: #26 backend.
- Следующий шаг: пакетное чтение `StaffRequestRepository::page()` и PHPUnit на число SQL.
- Блокеров нет. Открыто: Q1 (история в элементах HND-06).
- Рабочее дерево: план и журнал не закоммичены.
- Команды проверок:
  - backend: `docker run --rm --network none --memory 1536m --cpus 2 --env XDEBUG_MODE=off --mount type=bind,source=<worktree>/api,target=/app,readonly --mount type=volume,source=rabit-issues42-vendor,target=/app/vendor --tmpfs /app/var:rw,size=256m --workdir /app --entrypoint php rabit-api-php-cli:d1-local vendor/bin/phpunit --colors=never` (так же `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`, `vendor/bin/phplint`);
  - frontend: том `rabit-issues262728-node` (`npm ci` выполнен), `docker run --rm --network none -v <worktree>/frontend:/app -v rabit-issues262728-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`.

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

## Результаты тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|----|--------|------|---------|----------------|
| T01 | PASS | 2026-09-25 | PHPUnit (backend-команда) | `testPageQueryCountDoesNotDependOnItems` 1/100/1000 → 4 SQL |
| T02 | PASS | 2026-09-25 | PHPUnit | `testRelatedRowsAndHistoryStayWithTheirRequestsInPageOrder`, `testDetailCardUsesTheSameBatchReads` |
| T03 | PASS | 2026-09-25 | PHPUnit | `testFiltersScopeAndPaginationReachTheCardQuery`, `testEmptyPageSkipsRelatedQueries` |
| T04–T12 | PENDING | 2026-09-25 | — | #27/#28 не начаты |
| T13, T14 | PENDING | 2026-09-25 | `zzz-handoff.spec.ts` | написан, запуск — gate после review |
| T15 | PENDING | 2026-09-25 | `verify-handoff.php` | написан, запуск — gate после review |
