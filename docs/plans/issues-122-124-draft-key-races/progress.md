# Issues #122, #124 — журнал

## Точка продолжения

- Дата: 2026-09-26.
- Ветка: `codex/issues-122-124-draft-key-races`. Worktree:
  `/home/user/rabit-api-worktrees/issues-122-124-draft-key-races`. Общий checkout `/home/user/rabit-api` не
  трогается.
- Base: `origin/main` `4fc9dce`.
- Issues: [#122](https://github.com/rebit-pro/rabit-api/issues/122), [#124](https://github.com/rebit-pro/rabit-api/issues/124).
  PR: ещё нет.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md), [PR #119](../issues-93-94-gallery-question-scope/plan.md).
- Завершено: анализ, план.
- Сейчас: реализация #122.
- Следующий шаг: `hasDraftChanges` в `model.ts` и `persist()`.
- Блокеров нет. Открытых решений нет.
- Рабочее дерево: новые файлы плана и журнала.
- Следующая проверка: команды из раздела «Команды» плана (том `rabit-issues122124-node`).

## Статус тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PENDING | — | `npm run check` | — |
| T02 | PENDING | — | `npm run test:commerce` | — |
| T03 | PENDING | — | `tests/ui/structure-group-shoot.test.mjs` | — |
| T04 | PENDING | — | то же | — |
| T05 | PENDING | — | то же | — |
| T06 | PENDING | — | `tests/support/gallery-question.test.mjs` | — |
| T07 | PENDING | — | то же | — |
| T08 | PENDING | — | то же | — |
| T09 | PENDING | — | `git diff --stat origin/main...HEAD` | — |
| T10 | PENDING | — | `make test-e2e` | после review |
| T11 | PENDING | — | ручная проверка на стенде | после review |

## Хронология

### 2026-09-26 — анализ

- Worktree от `origin/main` `4fc9dce`, дерево чистое.
- #122: `open()` присваивает `draft.value`. Синхронный deep-watcher `watch(draft, persist)` сразу записывает черновик,
  затем `open()` вызывает `persist()` ещё раз. Поэтому одного удаления явного `persist()` недостаточно: правило
  должно стоять в самом `persist()`. `restored.value = !!stored` — любой валидный черновик.
- #122: `useStructureEditor` в Node не загрузить (`@/stores/auth`, `./api` → `@/api/http`). Правило выносится в
  `model.ts` и тестируется там же, где `restorableDraft` (`tests/ui/structure-group-shoot.test.mjs`). Существующие
  тесты `restorableDraft` используют грязные черновики и остаются валидными.
- #124: ветка 404 в `load()` вызывает `write(questionStorageKey(galleryToken), null)` без сравнения с `key`.
  Инфраструктура теста (custom renderer, управляемые Promise, заглушка `localStorage`) уже есть в
  `tests/support/gallery-question.test.mjs`. 404 — объект с `isAxiosError: true` и `response.status = 404`:
  `questionProblem` использует `axios.isAxiosError`.
