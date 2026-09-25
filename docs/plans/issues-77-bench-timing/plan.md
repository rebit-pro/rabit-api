# Issue #77 — замер параллельности загрузок в bench-тесте по Resource Timing

## Цель и контекст

Закрыть неблокирующее замечание review [PR #76](https://github.com/rebit-pro/rabit-api/pull/76):
[#77](https://github.com/rebit-pro/rabit-api/issues/77).

`frontend/e2e/live/zz-media-bench.spec.ts` запускается только с `E2E_MEDIA_BENCH=1`. Перекрытие POST-загрузок и
время передачи он считает по Playwright `request.timing()`. `startTime` имеет разрешение 1 мс, а `responseEnd`
отсчитывается от него. Когда очередь стартует следующую загрузку сразу после предыдущей, интервалы на границе
пересекаются. Поэтому `parallel` в отчёте и проверка `≤ 2` могут показать 3 при лимите 2. В #73 тот же дефект
исправлен в тесте #33 файла `zz-media.spec.ts` (PR #76): интервалы берутся из Resource Timing страницы.

Это не волна графа: `docs/waves/graph.json` не меняется. Прецедент —
[issues-72-73](../issues-72-73-orders-scope-retry/plan.md).

- Ветка: `codex/issues-77-bench-timing`.
- Worktree: `/home/user/rabit-api-worktrees/issues-77-bench-timing`.
- Base: `origin/main` `d92b4c4`.
- Инструкции: `AGENTS.md`, `CLAUDE.md`. Порядок приёмки: [A8](../../waves/a8/README.md).

## Scope

- `e2e/live/helpers.ts`: общие для двух спеков функции:
  - `peakOverlap(spans)`: перенос из `zz-media.spec.ts`, заменяет замыкание `overlap()` bench-теста;
  - `uploadSpans(page, path)`: интервалы завершённых POST-загрузок из `performance.getEntriesByType('resource')`.
    Отбор: путь загрузки без query, `initiatorType === 'xmlhttprequest'`, `responseEnd > 0`.
- `e2e/live/zz-media.spec.ts`: тест #33 и тест #54/#55 используют функции из `helpers.ts`, поведение не меняется.
- `e2e/live/zz-media-bench.spec.ts`:
  - перед `goto` страницы кадров — `setResourceTimingBufferSize` через `addInitScript` с запасом под 45 минут партии;
  - число завершённых загрузок считается по `requestfinished`/`requestfailed`, как в #33;
  - после партии, до любой навигации — `uploadSpans`; число записей сверяется с числом завершённых загрузок;
  - `parallel` = `peakOverlap(spans)`, `transfer` = `responseEnd - startTime` тех же записей;
  - `request.timing()` не используется. Проверка `parallel ≤ 2` не ослабляется.

### Исключено

- Очередь загрузки `usePhotoQueue` и лимит `photoLimits.parallel`.
- Замер готовности (`acceptedToReadyMs`) по событиям `response`: он не зависит от `request.timing()`.
- Выборка превью в тесте #54/#55: у неё свой фильтр, она только переходит на общий `peakOverlap`.
- Backend, `groups.json`, граф волн.

## Решения, риски и ограничения

- D1 (DRY). В `helpers.ts` выносятся только две функции с одинаковым кодом в обоих спеках: `peakOverlap` и выборка
  загрузок. Буфер Resource Timing остаётся в спеках одной строкой `addInitScript`: у тестов разный объём и разный
  момент установки.
- D2. Размер буфера bench — 100 000 записей. За 45 минут таймаута очередь делает не больше двух проверок статуса
  в секунду и обновляет список раз в 5 секунд, превью идут очередью по 6. Даже если каждое обновление заново
  читает страницу превью, записей меньше 40 000. Если буфер всё же переполнится, записи загрузок пропадут и
  сверка числа записей уронит тест. Незаметно занизить `parallel` нельзя.
- D3. Завершённые загрузки считаются по `requestfinished` и `requestfailed`, как в #33. Загрузка, оборванная сетью,
  не даёт записи Resource Timing. Тогда число записей расходится и тест падает, а не занижает перекрытие.
- D4. Отчёт `media-bench.json` пишется до проверок, как раньше: замер сохраняется и при падении.
- D5. В bench-тесте нет перезагрузки. Записи снимаются сразу после `Готово: N`, до любой навигации: она очистит
  буфер.
- Риск R1: live-прогон bench (`E2E_MEDIA_BENCH=1`) длится десятки минут и требует изолированного стенда. До review
  он не запускается — PENDING. Код выборки проверяется стаб-прогоном в Chromium, тест #33 — live-группой `a`
  после review.
- Ограничение: полный `make test-e2e` — после review без блокеров. До review — быстрые проверки и стаб-прогон.

## Checklist

- [x] Прочитать `AGENTS.md`, `CLAUDE.md`, issue #77, PR #76 и прецедент issues-72-73.
- [x] `helpers.ts`: `peakOverlap`, `uploadSpans`; `zz-media.spec.ts` на общих функциях.
- [x] `zz-media-bench.spec.ts`: буфер, счётчик загрузок, Resource Timing для `parallel` и `transfer`.
- [x] `npx eslint --fix` по изменённым файлам.
- [x] Быстрые проверки: `npm run check`, `npm run test:commerce`.
- [x] Стаб-прогон выборки через `helpers.ts`: лимит 2 — PASS, лимит 3 — FAIL.
- [x] Commit, push, PR в `main` без merge.
- [x] После review без блокеров: `make test-e2e`, bench `E2E_MEDIA_BENCH=1` (PASS 2026-09-25).

## Критерии приёмки

1. В `zz-media-bench.spec.ts` нет `request.timing()`. `parallel` и проверка `≤ 2` считаются по Resource Timing.
2. `transfer` в отчёте — `responseEnd - startTime` тех же записей.
3. Число записей загрузок равно числу завершённых загрузок, иначе тест падает.
4. `peakOverlap` и выборка загрузок существуют в одном месте. Тест #33 проверяет то же, что и раньше.
5. При лимите очереди 3 проверка перекрытия падает.
6. `npm run check` и `npm run test:commerce` зелёные.
7. После review: live-группа `a` зелёная (тест #33), bench-прогон даёт `parallel ≤ 2`.

## Тест-кейсы

| ID | Предусловия | Действие | Ожидаемый результат | Команда |
|---|---|---|---|---|
| T01 | Ветка | Поиск `timing()` в live E2E | В `zz-media-bench.spec.ts` и `zz-media.spec.ts` вызовов нет | `grep -n "timing()" frontend/e2e/live/*.ts` |
| T02 | Ветка | Линт, стили, типы, UI-тесты, типы E2E | exit 0 | `npm run check` в контейнере Playwright |
| T03 | Ветка | Юнит-тесты commerce | exit 0 | `npm run test:commerce` |
| T04 | Стабы API, лимит 2, партия из 30 загрузок, статусы сначала `processing` | `uploadSpans` + `peakOverlap` из `helpers.ts`, буфер 100 000 | Записей столько же, сколько завершённых загрузок; пик ≤ 2; `transfer` > 0 | стаб-прогон `bench-overlap.stub.ts`, `LIMIT=2 RUNS=3` |
| T05 | Как T04, копия `src` с `parallel: 3` | Тот же замер | Пик 3, проверка падает | стаб-прогон `LIMIT=3 RUNS=3` |
| T06 | Как T04, буфер 150 записей | Тот же замер | Сверка числа записей падает: переполнение не проходит молча | стаб-прогон `BUFFER=150 RUNS=1` |
| T07 | Ветка | Diff | Меняются только 3 файла live E2E и документы задачи | `git diff --stat origin/main...HEAD` |
| T08 | Review без блокеров | Полный gate | PASS, тест #33 ✓ | `make test-e2e` (порядок — A8) |
| T09 | Review без блокеров, изолированный стенд | Bench-прогон | Отчёт `parallel ≤ 2`, тест PASS | `E2E_MEDIA_BENCH=1 make test-e2e` (переменную передаёт `tools/run-browser-e2e.py`) |

### Команды

```bash
docker run --rm --network none \
  -v /home/user/rabit-api-worktrees/issues-77-bench-timing/frontend:/app \
  -v rabit-issues42-node:/app/node_modules -w /app \
  mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'
```

Стаб-прогон: скрипты вне репозитория, в scratchpad сессии (`stub77/`). Vite с
`VITE_API_URL=/api VITE_API_MOCKS_ENABLED=false`, API — `page.route`.
