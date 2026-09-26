# Issues #93, #94 — область переписки галереи и черновик вопроса куратору

## Цель и контекст

Закрыть два неблокирующих замечания независимого ревью PR #89 (K3, вопросы из галереи):

- [#93](https://github.com/rebit-pro/rabit-api/issues/93). При SPA-переходе между галереями Vue переиспользует
  `GalleryScreen`. `useGalleryQuestion` читает `questionKey`, `seen` и `pending` только при создании. После смены
  `token` история и отправка продолжают беседу прежней галереи. Поздний ответ `ask()` сохраняет ключ беседы A
  под `token` B.
- [#94](https://github.com/rebit-pro/rabit-api/issues/94). `QuestionThread.vue` после успешной отправки безусловно
  выполняет `draft.value = ''` и стирает текст, набранный во время ожидания ответа.

Это не волна графа: `docs/waves/graph.json` не меняется. Прецедент — [issues-77](../issues-77-bench-timing/plan.md).

- Ветка: `codex/issues-93-94-gallery-question-scope`.
- Worktree: `/home/user/rabit-api-worktrees/issues-93-94-gallery-question-scope`.
- Base: `origin/main` `23642d4`.
- Инструкции: `AGENTS.md`, `CLAUDE.md`. Порядок приёмки: [A8](../../waves/a8/README.md).

## Scope

- `support/composables/useQuestionThread.ts`: метод `reset()`. Он отбрасывает историю, ошибки, незавершённую
  попытку и флаги загрузки/отправки. Поздние результаты `load()` и `send()` прежней области не применяются:
  к счётчику `generation` добавляется счётчик области `scope`.
- `support/composables/useGalleryQuestion.ts`:
  - синхронный `watch(token)`: `thread.reset()`, повторное чтение `questionKey`, `seen`, `pending` из
    `localStorage` нового `token`, сброс имени и тот же старт, что при монтировании (восстановление первого вопроса,
    тихая проверка ответа; при открытом диалоге — загрузка истории);
  - порты первого вопроса создаются на каждую отправку и замыкают `token` момента запроса. Поздний `ask()`
    сохраняет ключ под своей галереей, а refs текущей галереи меняет только если `token` не сменился. То же для
    404 в `load()`;
  - серверные вызовы передаются параметром (`GalleryQuestionApi`), как порты `firstQuestion.ts`. Без этого
    composable не загрузить в Node-тесте: `api.ts` импортирует `@/api/http`.
- `support/problem.ts`: перенос `questionProblem` из `api.ts` (зависит только от `axios`), чтобы composables
  загружались в Node без alias `@/`. Импорты в `useQuestionThread`, `useGalleryQuestion`, `FeedbackDialog.vue`.
- Относительные импорты затронутых composables — с расширением `.ts`, как в `firstQuestion.ts`.
- `gallery/components/GalleryScreen.vue`: передать `questionsApi`; `:key="token"` у `GalleryQuestionDialog`, чтобы
  черновик и подсказка ошибки галереи A не переходили в галерею B.
- `support/rules.ts`: `submitDraft(draft, submit)` — отправляет текст и очищает поле, только если оно всё ещё равно
  отправленному тексту. `QuestionThread.vue` использует её (#94).
- Unit-тесты `tests/support/gallery-question.test.mjs` (реальные composables, Vue custom renderer) и
  `tests/support/rules.test.mjs` (черновик с управляемым Promise). Попадают в `npm run test:ui`.

### Исключено

- Блокировка поля ввода на время отправки: в #94 выбран вариант «очищать только неизменённый черновик».
- `useStaffQuestion` и кабинет сотрудника: у них одна беседа, области не меняются.
- Backend, E2E-спеки, граф волн, `groups.json`.

## Решения, риски и ограничения

- D1 (#93, явный reset вместо remount). Рассмотрены два варианта.
  - Remount по `token` потребовал бы вынести кнопку в шапке и диалог в отдельный компонент с `:key` и передать
    `token` строкой. `RouterView` ключевать нельзя: он общий для layout. Такой вариант даёт больший diff в разметке
    и стилях `GalleryScreen`, а переход A→B проверяется только компонентным тестом с компиляцией SFC.
  - Явный reset локализован в двух composables и проверяется unit-тестом реального `useGalleryQuestion`.
    Защита от поздних ответов стоит в одном месте (`scope`/`generation` в `useQuestionThread`) и в портах,
    которые замыкают `token` на момент запроса.
  - Выбран явный reset. Он минимален по разметке и полностью проверяем в Node.
- D2. `watch(token, …, { flush: 'sync' })`. Между сменой маршрута и pre-flush отправка могла бы взять новый `token`
  со старым `questionKey`. Синхронный сброс закрывает это окно. Сброс дешёвый: чтение трёх ключей `localStorage`.
- D3. Поздний ответ первого вопроса галереи A сохраняет ключ беседы под `token` A и снимает её `pending`. Это
  беседа галереи A, при возврате в A она продолжится. Refs текущей галереи B не меняются, `send()` прежней области
  возвращает `false` и не трогает `sending`/`sendError`/`question`.
- D4 (#94). Логика черновика вынесена в `submitDraft` из `rules.ts`. SFC в `test:ui` не компилируются, а так
  unit-тест проверяет ту же функцию, что вызывает компонент. Ошибка (`submit → false`) текст не трогает.
- D5. `:key="token"` у `GalleryQuestionDialog` пересоздаёт только диалог (черновик, подсказку). Unit-тестом это не
  покрыто: проверяется типами и E2E после review.
- R1. При уходе со страницы галереи `route.params.token` на мгновение становится `'undefined'` до размонтирования.
  Синхронный сброс прочитает пустое хранилище и очистит несуществующий `pending` для `'undefined'`. Безвредно;
  `useGallery` ведёт себя так же.
- Ограничение: полный `make test-e2e` — после review без блокеров. До review — быстрые проверки в Docker.

## Checklist

- [x] Прочитать `AGENTS.md`, `CLAUDE.md`, issues #93, #94.
- [x] План и журнал до изменения кода.
- [x] `problem.ts`, импорты `.ts`, `reset()` в `useQuestionThread`.
- [x] `useGalleryQuestion`: смена `token`, порты с `token` запроса, внедрение API.
- [x] `GalleryScreen.vue`: `questionsApi`, `:key="token"` у диалога.
- [x] `submitDraft` в `rules.ts`, `QuestionThread.vue`.
- [x] Unit-тесты T03–T08.
- [x] Быстрые проверки: `npm run check`, `npm run test:commerce` (в `check` входит `test:ui`).
- [x] Commit, push, PR в `main` без merge (#119).
- [ ] После review без блокеров: `make test-e2e`.

## Критерии приёмки

1. После смены `token` A→B отправка идёт в беседу B (или создаёт первый вопрос B), история A не показывается,
   `seen` и `pending` читаются для B.
2. Ответ `ask()`/`current()`/`add()`, начатый в A и завершённый после перехода в B, не меняет состояние B и не
   сохраняет ключ A под `token` B.
3. Успешная отправка очищает неизменённый черновик. Текст, набранный во время ожидания, сохраняется. Ошибка
   отправки текст не стирает.
4. `npm run check` и `npm run test:commerce` зелёные.
5. После review: `make test-e2e` зелёный, включая `zz-questions.spec.ts`.

## Тест-кейсы

| ID | Предусловия | Действие | Ожидаемый результат | Команда |
|---|---|---|---|---|
| T01 | Ветка | Линт, стили, типы, UI-тесты (включая новые), типы E2E | exit 0 | `npm run check` в контейнере Playwright |
| T02 | Ветка | Юнит-тесты commerce | exit 0 | `npm run test:commerce` |
| T03 | В хранилище ключ беседы A и `seen` A; для B — свой ключ | Смонтировать с `token` A, сменить на B, отправить | `add` с ключом B, не A; `seen` B; история A не видна | `node --experimental-strip-types --test tests/support/gallery-question.test.mjs` |
| T04 | Ключ A, ответ `current()` A задержан | Сменить на B, завершить `current()` A | История B не заменяется историей A | то же |
| T05 | У A нет ключа, `ask()` A задержан | Отправить первый вопрос в A, сменить на B, завершить `ask()` | Ключ A сохранён под A, у B ключа нет; `send()` вернул `false`; `sending` B — `false`; следующая отправка в B — `ask(B)` | то же |
| T06 | Управляемый Promise `submit` | Отправить A, дождаться успеха без изменения поля | Поле пустое | `node --experimental-strip-types --test tests/support/rules.test.mjs` |
| T07 | Как T06 | Отправить A, до успеха заменить поле на B | Поле равно B | то же |
| T08 | Как T06 | Отправить A, `submit → false` | Поле равно A | то же |
| T09 | Ветка | Diff | Только фронт `support/`, `GalleryScreen.vue`, тесты и документы задачи | `git diff --stat origin/main...HEAD` |
| T10 | Review без блокеров | Полный gate, в т. ч. `zz-questions.spec.ts` | PASS | `make test-e2e` (порядок — A8) |
| T11 | Review без блокеров | Браузер: переход между двумя галереями без перезагрузки, набор текста во время отправки | Переписка B, новый текст сохраняется | ручная проверка на стенде |

### Команды

```bash
docker run --rm --network none \
  -v /home/user/rabit-api-worktrees/issues-93-94-gallery-question-scope/frontend:/app \
  -v rabit-issues9394-node:/app/node_modules -w /app \
  mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'
```
