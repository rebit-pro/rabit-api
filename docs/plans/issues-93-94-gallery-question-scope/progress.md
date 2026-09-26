# Issues #93, #94 — журнал

## Точка продолжения

- Дата: 2026-09-26.
- Ветка: `codex/issues-93-94-gallery-question-scope`. Worktree:
  `/home/user/rabit-api-worktrees/issues-93-94-gallery-question-scope`. Общий checkout `/home/user/rabit-api` не
  трогается.
- Base: `origin/main` `23642d4`. После него в `main` пришёл только `00c507f` (документы OPS-legal), пересечений нет,
  merge base не требуется. План: `e4fcbd4`. Код: `98c6f03` (#94), `8b992ad` (#93).
- Issues: [#93](https://github.com/rebit-pro/rabit-api/issues/93), [#94](https://github.com/rebit-pro/rabit-api/issues/94).
  PR: создаётся в `main`, без merge.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md).
- Завершено: реализация, unit-тесты, мутационная проверка тестов, быстрые проверки (T01–T09 PASS).
- Сейчас: PR ждёт review. Merge не выполняется.
- Следующий шаг: после review без блокеров — `make test-e2e` (T10) и ручная проверка перехода между галереями (T11).
- Блокеров нет. Открытых решений нет.
- Рабочее дерево: чистое после коммита журнала. Логи проверок — вне репозитория (scratchpad сессии).
- Следующая проверка после изменения base: команда из раздела «Команды» плана (том `rabit-issues9394-node`).

## Статус тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-26 | `npm run check` (контейнер, `--network none`) | exit 0: eslint, stylelint, vue-tsc, tsc e2e, `test:ui` 53/53 |
| T02 | PASS | 2026-09-26 | `npm run test:commerce` | 207/207 |
| T03 | PASS | 2026-09-26 | `node --experimental-strip-types --test tests/support/gallery-question.test.mjs` | тест 1 ok |
| T04 | PASS | 2026-09-26 | то же | тест 2 ok |
| T05 | PASS | 2026-09-26 | то же | тест 3 ok |
| T06 | PASS | 2026-09-26 | `node --experimental-strip-types --test tests/support/rules.test.mjs` | «a successful send clears…» ok |
| T07 | PASS | 2026-09-26 | то же | «a next question typed while…» ok |
| T08 | PASS | 2026-09-26 | то же | «a failed send keeps the text…» ok |
| T09 | PASS | 2026-09-26 | `git diff --stat origin/main...HEAD` | только `frontend/src/modules/morefoto/{support,gallery}`, `frontend/tests/support`, документы задачи |
| T10 | PENDING | — | `make test-e2e` | gate после review без блокеров |
| T11 | PENDING | — | ручная проверка на стенде | после review |

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

### 2026-09-26 — реализация #94, `98c6f03`

- `rules.ts`: `submitDraft(draft, submit)` — запоминает отправленный текст, после успеха очищает поле, только если
  оно всё ещё равно этому тексту; при `false` поле не трогает.
- `QuestionThread.vue`: `await submitDraft(draft, props.submit)`.
- `tests/support/rules.test.mjs`: три теста с управляемым Promise (T06–T08).

### 2026-09-26 — реализация #93, `8b992ad`

- `support/problem.ts`: `questionProblem` перенесён из `api.ts` без изменений; импорты в `useQuestionThread`,
  `useGalleryQuestion`, `FeedbackDialog.vue`.
- `useQuestionThread`: счётчик `scope` и `reset()`. `send()` запоминает `scope` при старте: после `reset()` его успех,
  ошибка и `finally` не меняют состояние. `reset()` также увеличивает `generation`, поэтому поздний `load()` не
  применяется.
- `useGalleryQuestion(token, open, api)`:
  - `watch(token, …, { flush: 'sync' })`: `thread.reset()`, чтение `questionKey`/`seen`/`pending` для нового
    `token`, `name = ''`, общий `start()` (как `onMounted`), при открытом диалоге — `reload()`;
  - `portsFor(galleryToken)`: `ask`, `readPending`, `writePending`, `keep` работают с хранилищем галереи запроса,
    refs меняются только пока на экране та же галерея. 404 в `load()` снимает ключ своей галереи;
  - API передаётся параметром (`GalleryQuestionApi`), относительные импорты — с `.ts`.
- `GalleryScreen.vue`: `useGalleryQuestion(token, questionOpen, questionsApi)`, `:key="token"` у
  `GalleryQuestionDialog`.
- `tests/support/gallery-question.test.mjs`: реальный `useGalleryQuestion` в компоненте Vue custom renderer
  без DOM; `localStorage` и `document` — заглушки; каждый серверный вызов ждёт ответа теста. Кейсы T03–T05.
- Форматирование: `npx eslint --fix` по `support/`, `GalleryScreen.vue`, `tests/support` в контейнере, exit 0;
  правка форматирования только в новом тесте.

### 2026-09-26 — проверки

- Том зависимостей создан заново:

  ```bash
  docker run --rm -v /home/user/rabit-api-worktrees/issues-93-94-gallery-question-scope/frontend:/app \
    -v rabit-issues9394-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy npm ci
  ```

  exit 0. Node в образе — v22.14.0.
- Адресные тесты:

  ```bash
  docker run --rm --network none -v …/frontend:/app -v rabit-issues9394-node:/app/node_modules -w /app \
    mcr.microsoft.com/playwright:v1.52.0-jammy \
    node --experimental-strip-types --test tests/support/gallery-question.test.mjs tests/support/rules.test.mjs
  ```

  13/13 PASS.
- Мутационная проверка (временные правки, откачены): без `watch(token)` падают 3 теста #93; без проверки `scope` в
  `send()` падает T05; порты с `token.value` на момент ответа — падает T05; безусловная очистка черновика — падает
  T07. T04 без `scope` проходит: поздний `load()` отсекает `generation`.
- Быстрые проверки (рабочее дерево = `8b992ad`):

  ```bash
  docker run --rm --network none \
    -v /home/user/rabit-api-worktrees/issues-93-94-gallery-question-scope/frontend:/app \
    -v rabit-issues9394-node:/app/node_modules -w /app \
    mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'
  ```

  exit 0: lint, stylelint, vue-tsc, tsc e2e, `test:ui` 53/53 (включая 6 новых); `test:commerce` 207/207.
  Предупреждений `[Vue warn]` в выводе нет.
- Дубли: `gh pr list --state all --search "93 in:title"` и `"94 in:title"` — пусто; веток с #93/#94 нет.
- Браузерные кейсы T10, T11 — PENDING: полный gate после review (`make test-e2e`, `make e2e-up` не запускались).
