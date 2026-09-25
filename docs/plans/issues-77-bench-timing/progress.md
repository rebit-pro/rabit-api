# Issue #77 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-77-bench-timing`. Worktree: `/home/user/rabit-api-worktrees/issues-77-bench-timing`.
  Общий checkout `/home/user/rabit-api` не трогается.
- Base: `origin/main` `d92b4c4`. План: `8dec16c`. Код: `29ba68d`.
- Issue: [#77](https://github.com/rebit-pro/rabit-api/issues/77). PR: [#85](https://github.com/rebit-pro/rabit-api/pull/85): самостоятельное ревью без замечаний, gate и bench PASS, сливается.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md), прецедент [issues-72-73](../issues-72-73-orders-scope-retry/progress.md).
- Завершено: реализация, быстрые проверки, стаб-прогоны (лимит 2, лимит 3, малый буфер).
- Сейчас: PR открыт в `main`, ждёт review. Merge не выполняется.
- Base обновлён: `origin/main` `5dcb0e0` влит merge-коммитом `621611a`.
- Следующий шаг: деплой не требуется (изменены только E2E-спеки).
- Блокеров нет. Открытых решений нет.
- Рабочее дерево: чистое после коммита журнала. Скрипты стаба и логи — вне репозитория (scratchpad сессии, `stub77/`).
- Следующая проверка после изменения base: команда из раздела «Команды» плана (том `rabit-issues42-node`).

## Хронология

### 2026-09-25 — анализ

- Worktree создан от `origin/main` `d92b4c4`, рабочее дерево чистое.
- `zz-media-bench.spec.ts`: `parallel` и `transfer` строятся в `requestfinished` из `request.timing()`, замыкание
  `overlap()` повторяет `peakOverlap` из `zz-media.spec.ts` построчно. Перезагрузок в тесте нет.
- `zz-media.spec.ts`, тест #33 (PR #76): `setResourceTimingBufferSize(1000)` перед `goto`, выборка
  `performance.getEntriesByType('resource')` по пути загрузки без query, `initiatorType === 'xmlhttprequest'`,
  `responseEnd > 0`, сверка с `finished` из `requestfinished`/`requestfailed`. Эта выборка нужна и bench-тесту:
  выносится в `helpers.ts` вместе с `peakOverlap` (решение D1).
- Объём буфера bench: `usePhotoQueue` проверяет статус не чаще двух раз за тик в 1 с (`checksPerTick = 2`),
  обновляет список раз в 5 с (`refreshEvery`), превью идут очередью по 6 (`previews.ts`). За 45 минут таймаута —
  меньше 40 000 записей. Выбран буфер 100 000 (решение D2).

### 2026-09-25 — реализация, `29ba68d`

- `helpers.ts`: `peakOverlap(spans)` (перенос из `zz-media.spec.ts`) и `uploadSpans(page, path)` — выборка
  загрузок из Resource Timing с комментарием, почему не `request.timing()`.
- `zz-media.spec.ts`: локальная `peakOverlap` удалена, тест #33 вызывает `uploadSpans`, #54/#55 — общий
  `peakOverlap`. Проверки те же: число записей равно `finished`, пик `≤ 2` (#33) и `≤ 6` (#54/#55).
- `zz-media-bench.spec.ts`: `setResourceTimingBufferSize(100000)` через `addInitScript` перед `goto`; счётчик
  `settled` по `requestfinished`/`requestfailed`; после `Готово: N` — `uploadSpans`, `transfer = end - start`,
  `parallel = peakOverlap(spans)`; отчёт пишется до проверок; `expect(spans).toHaveLength(settled)` и
  `expect(parallel).toBeLessThanOrEqual(2)`. `request.timing()` и замыкание `overlap()` удалены.
- Форматирование: `npx eslint --fix` по трём файлам в контейнере Playwright, exit 0, правок нет. Prettier не
  запускался.

### 2026-09-25 — проверки

- Быстрые проверки (рабочее дерево = `29ba68d`):

  ```bash
  docker run --rm --network none -v /home/user/rabit-api-worktrees/issues-77-bench-timing/frontend:/app \
    -v rabit-issues42-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy \
    bash -c 'npm run check && npm run test:commerce'
  ```

  exit 0: lint, stylelint, vue-tsc, tsc e2e, `test:ui` 27/27; `test:commerce` 198/198.
- `grep -n "timing()" frontend/e2e/live/*.ts` — совпадений нет (exit 1).
- Стаб-прогон `bench-overlap.stub.ts` (основа — `stub72/upload-overlap.stub.mjs` PR #76). Образ
  `mcr.microsoft.com/playwright:v1.52.0-jammy`, `--network none`, Vite `VITE_API_URL=/api VITE_API_MOCKS_ENABLED=false`,
  API через `page.route`. Стаб импортирует `peakOverlap` и `uploadSpans` из `e2e/live/helpers.ts` ветки. Партия из
  30 PNG, ответ загрузки 202 через 150–350 мс, первая проверка статуса — `processing`, вторая — `ready`
  (60 проверок статуса, ~508 записей Resource Timing вместе с модулями Vite).
  - Лимит 2, буфер 100 000, 3 прогона: 3/3 PASS. 30 записей загрузок = 30 завершённых, `parallel` 2,
    `transfer` 157–353 мс. `request.timing()` на стабе тоже давал 2: граничное пересечение не проявилось.
  - Лимит 3 (копия `src` с `parallel: 3`, смонтирована `/app/src:ro`), 3 прогона: 3/3 FAIL
    `Expected: <= 2, Received: 3`.
  - Лимит 2, буфер 150, 1 прогон: FAIL `expect(spans).toHaveLength(settled)`, `Expected length: 30, Received
    length: 0`. Переполнение буфера роняет сверку, а не занижает `parallel`.
  - Временные каталоги `frontend/.stub` и `frontend/test-results` (root) удалены после прогонов.
- Не запускались до review: полный `make test-e2e` (T08) и bench-прогон `E2E_MEDIA_BENCH=1` (T09).

### 2026-09-25 — PR

- `git push -u origin codex/issues-77-bench-timing`, `gh pr create --base main`:
  [#85](https://github.com/rebit-pro/rabit-api/pull/85). Merge не выполнялся.

### 2026-09-25 — ревью, gate и bench

- Самостоятельное ревью (правило пользователя для простых задач): замечаний нет, итог — комментарий в PR #85.
- Base обновлён до `5dcb0e0` (PR #84), конфликтов нет.
- Gate `rabit-e2e-8105711e98f8` (371.4 с) — PASS, exit 0: группа `a` 64/64 (тест #33 на общем хелпере), группа `b` 43/43, верификаторы PASS.
- Bench `E2E_MEDIA_BENCH=1 E2E_MEDIA_BENCH_COUNT=20 make test-e2e … E2E_GROUPS=a` (`rabit-e2e-e2a87bfb3125`) — PASS, exit 0, 65/65:
  - `parallel` 2, партия 20 × 7.4 МБ за 24 с;
  - передача p50 418 мс, p95 785 мс;
  - приём → готовность p50 14.7 с, p95 17.8 с.
  - Партия уменьшена до 20: при старом runner браузерный этап ограничен 900 с (#61, пункт 5, PR #87).

## Результаты тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-25 | `grep -n "timing()" frontend/e2e/live/*.ts` | совпадений нет |
| T02 | PASS | 2026-09-25 | `npm run check` | exit 0, `test:ui` 27/27 |
| T03 | PASS | 2026-09-25 | `npm run test:commerce` | 198/198 |
| T04 | PASS (стаб) | 2026-09-25 | `bench-overlap.stub.ts` `LIMIT=2 RUNS=3` | 30/30 записей, пик 2, `transfer` > 0, 3/3 |
| T05 | PASS (стаб) | 2026-09-25 | `bench-overlap.stub.ts` `LIMIT=3 RUNS=3` на копии с `parallel: 3` | 3/3 падают `Received: 3` |
| T06 | PASS (стаб) | 2026-09-25 | `bench-overlap.stub.ts` `BUFFER=150 RUNS=1` | падает сверка числа записей: 0 из 30 |
| T07 | PASS | 2026-09-25 | `git diff --stat origin/main...HEAD` | 3 файла live E2E, план и журнал; очередь, `src`, backend не затронуты |
| T08 | PASS | 2026-09-25 | `make test-e2e` `8105711e98f8` | exit 0, a 64/64, b 43/43 |
| T09 | PASS | 2026-09-25 | `E2E_MEDIA_BENCH=1 E2E_MEDIA_BENCH_COUNT=20 … E2E_GROUPS=a` `e2a87bfb3125` | 65/65, `parallel` 2, transfer p50/p95 418/785 мс |
