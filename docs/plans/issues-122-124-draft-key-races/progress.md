# Issues #122, #124 — журнал

## Точка продолжения

- Дата: 2026-09-26.
- Ветка: `codex/issues-122-124-draft-key-races`. Worktree:
  `/home/user/rabit-api-worktrees/issues-122-124-draft-key-races`. Общий checkout `/home/user/rabit-api` не
  трогается.
- Base: `origin/main` `4fc9dce` (на момент проверок `main` не сдвинулся). План: `5f1b5ae`. Код: `76daf79` (#122),
  `d165c06` (#124).
- Issues: [#122](https://github.com/rebit-pro/rabit-api/issues/122), [#124](https://github.com/rebit-pro/rabit-api/issues/124).
  PR: создаётся в `main` после push, без merge. Номер фиксируется в описании PR и в следующем обновлении журнала.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md), [PR #119](../issues-93-94-gallery-question-scope/plan.md).
- Завершено: реализация, unit-тесты, мутационная проверка тестов, быстрые проверки (T01–T09 PASS).
- Сейчас: push и PR.
- Следующий шаг: review PR. После review без блокеров — `make test-e2e` (T10) и ручная проверка диалогов (T11).
- Блокеров нет. Открытых решений нет. Ограничение R1 (ключ другой вкладки не подхватывается в ref) — в плане.
- Рабочее дерево: чистое после коммита журнала. Логи проверок — вне репозитория (scratchpad сессии).
- Следующая проверка после изменения base: команды из раздела «Команды» плана (том `rabit-issues122124-node`).

## Статус тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-26 | `npm run check` (контейнер, `--network none`) | exit 0: eslint, stylelint, vue-tsc, tsc e2e, `test:ui` 60/60 |
| T02 | PASS | 2026-09-26 | `npm run test:commerce` | 209/209 |
| T03 | PASS | 2026-09-26 | `node --experimental-strip-types --test tests/ui/structure-group-shoot.test.mjs` | «an untouched draft is not kept and not restored…» ok |
| T04 | PASS | 2026-09-26 | то же | тот же тест: каждое из четырёх полей |
| T05 | PASS | 2026-09-26 | то же | тот же тест: `pending` при исходных полях |
| T06 | PASS | 2026-09-26 | `node --experimental-strip-types --test tests/support/gallery-question.test.mjs` | «a late 404 for the old key keeps the key…» ok |
| T07 | PASS | 2026-09-26 | то же | «a 404 for the old key keeps a key another tab stored meanwhile» ok |
| T08 | PASS | 2026-09-26 | то же | «a 404 for the stored key forgets it…» ok |
| T09 | PASS | 2026-09-26 | `git diff --stat origin/main...HEAD` | только `structure/model.ts`, `useStructureEditor.ts`, `useGalleryQuestion.ts`, два теста и документы задачи |
| T10 | PENDING | — | `make test-e2e` | после review |
| T11 | PENDING | — | ручная проверка на стенде, desktop/mobile | после review |

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

### 2026-09-26 — реализация #122, `76daf79`

- `model.ts`: `hasDraftChanges(draft)` — `pending !== null` или хотя бы одно поле `fields` отличается от `base`
  (сравнение по ключам, без `JSON.stringify`). `restorableDraft()` дополнительно требует `hasDraftChanges`.
- `useStructureEditor.ts`, `persist()`: грязный черновик записывается, чистый удаляет запись по текущему ключу.
  `restored` и `setParent()` работают через `storedDraft()` → `restorableDraft()`, отдельных правок не потребовалось.
- `tests/ui/structure-group-shoot.test.mjs`: T03–T05 и независимость от порядка полей после `JSON.parse`.

### 2026-09-26 — реализация #124, `d165c06`

- `useGalleryQuestion.ts`: при 404 ключ удаляется, только если `read(questionStorageKey(galleryToken)) === key`.
  Защита ref (`galleryToken === token.value && key === questionKey.value`) не менялась.
- `tests/support/gallery-question.test.mjs`: T06–T08. В T06 новый ключ — `keyC`, потому что `keyB` в файле уже
  принадлежит галерее B.

### 2026-09-26 — проверки

- Том зависимостей создан заново:
  `docker run --rm -v <worktree>/frontend:/app -v rabit-issues122124-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy npm ci`
  — exit 0.
- Форматирование: `npx eslint --fix` по пяти изменённым файлам в контейнере, exit 0, правок не внесено.
- Целевые тесты: `node --experimental-strip-types --test tests/ui/structure-group-shoot.test.mjs tests/support/gallery-question.test.mjs`
  — 10/10 (`--network none`).
- Мутационная проверка: исправления временно откатывались (безусловное удаление ключа при 404; `restorableDraft` без
  `hasDraftChanges`). Результат 7/10: падают ровно новые тесты 4, 5 (#124) и 10 (#122). Исходники восстановлены,
  `git diff` совпал с исправлением.
- `docker run --rm --network none … bash -c 'npm run check && npm run test:commerce'` — exit 0: `test:ui` 60/60,
  `test:commerce` 209/209.
- `git fetch origin main`: новых коммитов после `4fc9dce` нет.
