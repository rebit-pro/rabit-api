# Issues #93, #94 — журнал

## Точка продолжения

- Дата: 2026-09-26.
- Ветка: `codex/issues-93-94-gallery-question-scope`. Worktree:
  `/home/user/rabit-api-worktrees/issues-93-94-gallery-question-scope`. Общий checkout `/home/user/rabit-api` не
  трогается.
- Base: `origin/main` `23642d4`.
- Issues: [#93](https://github.com/rebit-pro/rabit-api/issues/93), [#94](https://github.com/rebit-pro/rabit-api/issues/94).
  PR: ещё нет.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md).
- Завершено: анализ, план.
- Сейчас: реализация.
- Следующий шаг: `problem.ts` и `reset()` в `useQuestionThread`.
- Блокеров нет. Открытых решений нет.
- Рабочее дерево: план и журнал не закоммичены.
- Следующая проверка: команда из раздела «Команды» плана (том `rabit-issues9394-node`).

## Хронология

### 2026-09-26 — анализ

- Worktree от `origin/main` `23642d4`, дерево чистое.
- `useGalleryQuestion(token, open)`: `questionKey`, `seen`, `pending` читаются один раз; порты `ask`/`keep`/
  `writePending` берут `token.value` в момент ответа — отсюда запись ключа A под B.
- `useQuestionThread`: `generation` защищает только `reload()`; результат `send()` применяется всегда.
- `QuestionThread.vue`: `if (await props.submit(draft.value)) draft.value = ''`.
- Unit-тесты фронта: `node --experimental-strip-types --test` (`test:ui`, `test:commerce`), импорт чистых `.ts` с
  явным расширением. Vue в тестах пока не используется; composables импортируют `../api` → `@/api/http`, что Node не
  разрешает. Отсюда внедрение API и перенос `questionProblem` (план, Scope).
- Решение по #93 — явный reset (план, D1). E2E-покрытие переписки: `e2e/live/zz-questions.spec.ts`.
