# Issues #72 и #73 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-72-73-orders-scope-retry`. Worktree: `/home/user/rabit-api-worktrees/issues-72-73-orders-scope-retry`.
  Общий checkout `/home/user/rabit-api` не трогается: там чужая ветка с незакоммиченными правками.
- Base: `origin/main` `54bd4ab`. Код: `6042865` (#72), `b1e0495` (#73). План: `11a465d`.
- Issues: [#72](https://github.com/rebit-pro/rabit-api/issues/72), [#73](https://github.com/rebit-pro/rabit-api/issues/73). PR: [#76](https://github.com/rebit-pro/rabit-api/pull/76): ревью выполнено по поручению пользователя, блокеров нет; gate PASS; сливается в `main`.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md), прецедент [issues-39-41](../issues-39-41-staff-orders/progress.md).
- Завершено:
  - реализация #72, unit-тест правила, live E2E-сценарий в `zzzzz-orders.spec.ts` (написан, не запускался);
  - #73: замер в тесте #33 переведён на Resource Timing;
  - быстрые проверки и стаб-прогоны в Chromium зелёные.
- Сейчас: PR #76 открыт в `main`, ждёт review пользователя.
- Base обновлён: `origin/main` `4621ad9` влит merge-коммитом `0b011c1`.
- Следующий шаг: деплой — отдельным решением пользователя.
- Блокеров нет. Открытых решений нет.
- Рабочее дерево: чистое после коммита журнала. Скрипты стабов и скриншоты вне репозитория (scratchpad сессии,
  `stub72/`).
- Следующая проверка после изменения base: команда из раздела «Команды» плана (том `rabit-issues42-node`).

## Хронология

### 2026-09-25 — анализ

- Worktree создан от `origin/main` `54bd4ab`, рабочее дерево чистое.
- #72 подтверждён чтением `useStaffOrderScope.ts`: после ошибки списка `listed = false`, но `watch(active)`
  срабатывает только при смене `active`; после ошибки карточки `opened === id`, и наблюдатель `institutionId`
  запрос не повторяет. «Повторить» в `StaffOrdersLiveScreen.vue` вызывает `useStaffOrders.reload()`.
- #73: в тесте #33 интервалы строятся `span(request)` из `request.timing()`. Список кадров
  (`photosApi.list`) всегда передаёт query (`groupId`, `status`, `page`, `pageSize` в `usePhotoWorkspace`), загрузка
  (`photosApi.upload`) — без query: POST отбираются по точному пути без query (решение D6). Оба запроса идут через
  axios XHR.
- В `zzzzz-orders.spec.ts` нет проверки 5xx в `beforeEach`, но обрыв сети через `route.abort('failed')` выбран как
  более точная имитация временного сбоя (R1).

### 2026-09-25 — реализация

- #72, `6042865`:
  - `rules.ts`: `StaffScopeLoad` и `staffScopeRetry(list, card)`;
  - `useStaffOrderScope.ts`: состояния `list`/`card` вместо `listed`/`listFailed`/`openFailed`/`loading`, `retry()`,
    `listing`. Наблюдатель `active` запускает список в `idle` и `failed`, как и раньше при возврате с карточки;
  - `StaffOrdersLiveScreen.vue`: блок ошибки фильтров с кнопкой «Повторить» (`retryScope`), индикатор загрузки
    у «Учреждение»;
  - unit-тест «a scope retry repeats only failed sources…»;
  - live E2E «#72: staff retry scope options after a failed institution list or institution card»: организатор с
    обрывом первого списка и `?groupId=`; куратор `curator`, список сужен до E4 (`route.fetch` + `fulfill`),
    обрыв первой карточки E4. Точное число запросов списка не проверяется: при >100 учреждениях список
    читается несколькими страницами.
- #73, `b1e0495`: `span()` удалена; перед `goto` — `setResourceTimingBufferSize(1000)`; после первой партии
  интервалы берутся из `performance.getEntriesByType('resource')` по пути загрузки без query и
  `initiatorType === 'xmlhttprequest'`. Число записей сверяется с числом POST (`finished`), лимит `<= 2` сохранён.
- Форматирование: только `npx eslint --fix` по изменённым файлам (exit 0), prettier не запускался.

### 2026-09-25 — проверки

- Быстрые проверки (HEAD `b1e0495`):

  ```bash
  docker run --rm --network none -v /home/user/rabit-api-worktrees/issues-72-73-orders-scope-retry/frontend:/app \
    -v rabit-issues42-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy \
    bash -c 'npm run check && npm run test:commerce'
  ```

  exit 0: lint, stylelint, vue-tsc, tsc e2e, `test:ui` 27/27; `test:commerce` 196/196.
- Стаб-прогон #72 (`scope-retry.stub.mjs`): образ `mcr.microsoft.com/playwright:v1.52.0-jammy`, `--network none`,
  Vite `VITE_API_URL=/api VITE_API_MOCKS_ENABLED=false`, API через `page.route` с предикатом
  `pathname.startsWith('/api/')`, auth-сид в localStorage, `/api/v1/me` с `active: true` и `order.read`.
  - Ветка: 3/3 PASS. Обрыв первого списка (1280 и 390 px): сообщение и «Повторить» у фильтров, `dblclick` при
    задержанном повторе даёт ровно один новый запрос списка, после ответа «Школа №7» в вариантах, «Группа» и URL
    сохраняют `groupId`, заказы повтором не перезапрашиваются. Куратор с одним учреждением: обрыв карточки,
    повтор загружает группы, выбор группы работает. Горизонтальной прокрутки нет, `pageerror` нет.
  - `src` из `origin/main` (`git archive`, смонтирован поверх `/app/src:ro`, `MODE=main`): 3/3 PASS ожидания
    дефекта. «Повторить» у фильтров нет; «Найти» не повторяет список (1 запрос); выбор того же единственного
    учреждения и «Найти» не повторяют карточку; сообщение об ошибке остаётся.
- Стаб-прогон #73 (`upload-overlap.stub.mjs`, 10 загрузок, одна отклоняется 422, ответ через 150–350 мс):
  - лимит 2 (ветка), 5 прогонов: 5/5 PASS; на пути 12 записей Resource Timing, из них отобрано 10 POST;
    пик 2 по обоим способам (граничное пересечение `request.timing()` на стабе не проявилось);
  - лимит 3 (копия `src` с `parallel: 3`), 3 прогона: 3/3 FAIL `Expected: <= 2, Received: 3` — регрессия ловится.
- Полный `make test-e2e` и 5 прогонов группы `a` не запускались: gate после review без блокеров.

### 2026-09-25 — PR

- `git push -u origin codex/issues-72-73-orders-scope-retry`, `gh pr create --base main`:
  [#76](https://github.com/rebit-pro/rabit-api/pull/76). Merge не выполнялся.

### 2026-09-25 — ревью и gate

- Пользователь поручил ревью простых PR выполнять самостоятельно: блокирующие замечания — комментарии в тредах PR, неблокирующие — отдельные issues.
- Ревью: блокирующих нет, итог — комментарий в PR #76. Неблокирующее: тот же `request.timing()` в `zz-media-bench.spec.ts` → [#77](https://github.com/rebit-pro/rabit-api/issues/77).
- Base обновлён до `4621ad9` (PR #74 и его выкатка), конфликтов нет.
- Полный gate `rabit-e2e-f4841cd93bde` (329.6 с) — PASS, exit 0:
  - группа `a` 64/64, группа `b` 43/43, «#72…» ✓;
  - все верификаторы PASS, включая `verify-payment-costs.php`.
- Пять прогонов `E2E_GROUPS=a` (`5848ca604886`, `cbf5b3e27eb1`, `1b5d03555fa5`, `d5b8ff04bfb0`, `22262204d3ce`) — 5/5 exit 0, по 64/64, «#33» ✓ в каждом.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-25 | `npm run test:commerce` | «a scope retry repeats only failed sources…», 196/196 |
| T02 | PASS (стаб + live `f4841cd93bde`) | 2026-09-25 | `scope-retry.stub.mjs`; live E2E после review | повтор восстанавливает учреждения, сообщение скрыто |
| T03 | PASS (стаб + live `f4841cd93bde`) | 2026-09-25 | то же | группы единственного учреждения после повтора |
| T04 | PASS (стаб + live `f4841cd93bde`) | 2026-09-25 | то же | `groupId` в URL и в «Группа» сохранён |
| T05 | PASS (стаб) | 2026-09-25 | то же | `dblclick` при задержке — один новый запрос |
| T06 | PASS (дефект воспроизведён) | 2026-09-25 | `MODE=main`, `src` из `origin/main` | нет «Повторить», «Найти» и повторный выбор не повторяют запросы |
| T07 | PASS (стаб) | 2026-09-25 | `scope-retry.stub.mjs` | 1280/390 px без прокрутки, скриншоты `stub72/out/` |
| T08 | PASS (стаб + live группа `a` ×5) | 2026-09-25 | `upload-overlap.stub.mjs` `LIMIT=2 RUNS=5`; live группа `a` ×5 после review | 10 из 12 записей, пик 2, 5/5 |
| T09 | PASS (стаб) | 2026-09-25 | `upload-overlap.stub.mjs` `LIMIT=3 RUNS=3` на копии с `parallel: 3` | 3/3 падают `Received: 3` |
| T10 | PASS | 2026-09-25 | `npm run check` | exit 0 |
| T11 | PASS | 2026-09-25 | `npm run test:commerce` | 196/196 |
| T12 | PASS | 2026-09-25 | `git diff --stat origin/main...HEAD` | 8 файлов: план, журнал, 2 live E2E, экран, composable, правила, unit-тест; backend и очередь не затронуты |
| T13 | PASS | 2026-09-25 | `make test-e2e` `f4841cd93bde`; `E2E_GROUPS=a` ×5 | exit 0; a 64/64, b 43/43; ×5 по 64/64 |
