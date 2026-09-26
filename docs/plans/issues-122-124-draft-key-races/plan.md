# Issues #122, #124 — пустой черновик структуры и поздний 404 ключа переписки

## Цель и контекст

Закрыть два неблокирующих замечания ревью. Оба касаются гонок и лишних записей в `localStorage` фронтенда.

- [#122](https://github.com/rebit-pro/rabit-api/issues/122). Замечание к PR #107. Если открыть и закрыть пустой диалог
  «Новая съёмка» или «Новая группа», то при следующем открытии появляется «Восстановлен несохранённый черновик.».
  `useStructureEditor.open()` сразу сохраняет черновик: `persist()` вызывается явно, и его же вызывает синхронный
  deep-watcher. При следующем открытии любой валидный сохранённый черновик считается восстановленным.
- [#124](https://github.com/rebit-pro/rabit-api/issues/124). Остаточная находка ревью PR #119
  ([план](../issues-93-94-gallery-question-scope/plan.md)). Поздний 404 от `current(keyA)` в `useGalleryQuestion`
  безусловно удаляет ключ беседы галереи из `localStorage`. К этому моменту там может лежать новый `keyB`, полученный
  после актуального 404. Проверка `key === questionKey.value` защищает только ref.

Это не волна графа: `docs/waves/graph.json` не меняется. Прецедент — [issues-93-94](../issues-93-94-gallery-question-scope/plan.md).

- Ветка: `codex/issues-122-124-draft-key-races`.
- Worktree: `/home/user/rabit-api-worktrees/issues-122-124-draft-key-races`.
- Base: `origin/main` `4fc9dce`.
- Инструкции: `AGENTS.md`, `CLAUDE.md`. Порядок приёмки: [A8](../../waves/a8/README.md).

## Scope

- `structure/model.ts`: чистая функция `hasDraftChanges(draft)`. Черновик «грязный», если поля отличаются от исходных
  (`fields` ≠ `base` хотя бы в одном поле) или есть `pending`. `restorableDraft()` дополнительно требует
  `hasDraftChanges`: нетронутый сохранённый черновик, в том числе записанный до исправления, не восстанавливается.
- `structure/useStructureEditor.ts`, `persist()`: грязный черновик записывается как раньше. Чистый черновик удаляет
  запись по текущему ключу. Так пустой диалог ничего не оставляет, а черновик, в котором пользователь вернул поля к
  исходным значениям, перестаёт восстанавливаться. `restored` берётся из `restorableDraft`, поэтому плашка
  появляется только у грязного черновика. `setParent()` игнорирует нетронутый черновик целевой съёмки через тот же
  `storedDraft()`.
- `support/composables/useGalleryQuestion.ts`, ветка 404 в `load()`: удалять ключ из хранилища галереи запроса,
  только если там всё ещё лежит ключ этого запроса. Отдельная защита ref (`galleryToken === token.value &&
  key === questionKey.value`) сохраняется.
- Unit-тесты:
  - `tests/ui/structure-group-shoot.test.mjs` — правило грязного черновика и его восстановление (попадает в
    `test:ui`);
  - `tests/support/gallery-question.test.mjs` — сценарий #124 на существующей инфраструктуре с управляемыми Promise.

### Исключено

- Unit-тест самого `useStructureEditor`: composable импортирует `@/stores/auth` и `./api` → `@/api/http`, Node их не
  разрешает. Внедрять зависимости ради теста — лишний diff. Правило вынесено в `model.ts` и проверено там. Связка
  «persist/open» проверяется типами и браузерным E2E после review.
- Подхват ключа, записанного другой вкладкой, в ref текущей вкладки при 404. Issue требует только не стирать чужой
  ключ в хранилище. Ref ведёт себя как раньше.
- Backend, E2E-спеки, граф волн, `groups.json`.

## Решения, риски и ограничения

- D1 (#122). Правило «грязного» черновика — в `model.ts`, рядом с `restorableDraft`. Поля сравниваются по ключам
  `fields`, без `JSON.stringify`: порядок ключей после `JSON.parse` не влияет на результат. `persist()` вызывается на
  каждое изменение (deep watcher). Сравнение четырёх строк дешёвое.
- D2 (#122). Чистый черновик не просто не записывается, а удаляет запись по ключу. Альтернатива — удалять при
  `close()` — не покрывает возврат полей к исходным и закрытие вкладки. Ключ `key` чистого черновика ни разу не
  уходил на сервер (`pending === null`), поэтому потерять его безопасно.
- D3 (#122). Выбор съёмки в `setParent()` без ввода полей не считается изменением. При следующем открытии съёмка
  снова выбирается по умолчанию (`defaultShootId`), восстанавливать нечего.
- D4 (#124). Условие удаления — `read(questionStorageKey(galleryToken)) === key`. Покрывает оба случая issue: новый
  ключ после актуального 404 и ключ, заменённый другой вкладкой. `pending` и `seen` ветка 404 не трогает, ключи
  других галерей тоже: запись адресуется `galleryToken` запроса.
- R1. После D4 при замене ключа другой вкладкой ref текущей вкладки обнуляется (`needsName = true`), хотя в хранилище
  лежит живой ключ. Новый первый вопрос из этой вкладки перезапишет ключ. Поведение не ухудшилось по сравнению с
  `main`; оно указано в ограничениях PR.
- Ограничение: полный `make test-e2e` запускается после review без блокеров. До review выполняются быстрые проверки в
  Docker. Браузерные кейсы — PENDING.

## Checklist

- [x] Прочитать `AGENTS.md`, `CLAUDE.md`, issues #122, #124, план PR #119.
- [x] План и журнал до изменения кода.
- [x] #122: `hasDraftChanges`, `restorableDraft`, `persist()`.
- [x] #122: unit-тесты T03–T05.
- [x] #124: условие удаления ключа в `load()`.
- [x] #124: unit-тесты T06–T08.
- [x] Быстрые проверки: `npm run check`, `npm run test:commerce` (в `check` входит `test:ui`).
- [x] Commit, push, PR в `main` без merge (#133).
- [ ] После review без блокеров: `make test-e2e`, ручная проверка диалогов.

## Критерии приёмки

1. Если открыть и закрыть пустой диалог «Новая съёмка» или «Новая группа», при повторном открытии нет плашки
   «Восстановлен» и в `localStorage` нет черновика.
2. Изменённый черновик или черновик, отправленный без ответа (`pending`), по-прежнему восстанавливается с плашкой.
3. Поздний 404 по старому ключу не удаляет из `localStorage` новый ключ той же галереи: ни полученный после
   актуального 404, ни записанный другой вкладкой.
4. Актуальный 404 по сохранённому ключу удаляет его и позволяет начать новую беседу.
5. Поздние ответы не стирают `pending`/`seen` и ключ другой беседы.
6. `npm run check` и `npm run test:commerce` зелёные.

## Тест-кейсы

| ID | Предусловия | Действие | Ожидаемый результат | Команда |
|---|---|---|---|---|
| T01 | Ветка | Линт, стили, типы, UI-тесты (включая новые), типы E2E | exit 0 | `npm run check` в контейнере Playwright |
| T02 | Ветка | Юнит-тесты commerce | exit 0 | `npm run test:commerce` |
| T03 | Черновик с `fields` = `base`, `pending = null` | `hasDraftChanges`, `restorableDraft` | `false`, `null`: нечего восстанавливать | `node --experimental-strip-types --test tests/ui/structure-group-shoot.test.mjs` |
| T04 | Черновик с изменённым полем (любым из четырёх) | То же | `true`, черновик восстанавливается | то же |
| T05 | Черновик с `fields` = `base` и валидным `pending` | То же | `true`, черновик восстанавливается | то же |
| T06 | У A сохранён keyA и `seen`, у B — свой ключ; первый `current(keyA)` висит | A → B → A, второй `current(keyA)` → 404, новый вопрос A → новый ключ (в тесте keyC: keyB занят галереей B), затем первый `current(keyA)` → 404 | В хранилище и ref остаётся новый ключ, `needsName = false`, следующая отправка — `add` с новым ключом; `pending`/`seen` A и ключ B не тронуты | `node --experimental-strip-types --test tests/support/gallery-question.test.mjs` |
| T07 | У A сохранён keyA, `current(keyA)` висит | Другая вкладка пишет keyC, затем `current(keyA)` → 404 | В хранилище keyC | то же |
| T08 | У A сохранён keyA | `current(keyA)` → 404 | Ключ удалён, `needsName = true`, следующий вопрос — `ask(A)` | то же |
| T09 | Ветка | Diff | Только `structure/model.ts`, `useStructureEditor.ts`, `useGalleryQuestion.ts`, два теста и документы задачи | `git diff --stat origin/main...HEAD` |
| T10 | Review без блокеров | Полный gate | PASS | `make test-e2e` (порядок — A8) |
| T11 | Review без блокеров | Браузер: открыть и закрыть пустой «Новая съёмка»/«Новая группа», открыть снова; изменить поле, закрыть, открыть снова | Первое — без плашки; второе — плашка и введённый текст | ручная проверка на стенде, desktop/mobile |

### Команды

```bash
docker run --rm \
  -v /home/user/rabit-api-worktrees/issues-122-124-draft-key-races/frontend:/app \
  -v rabit-issues122124-node:/app/node_modules -w /app \
  mcr.microsoft.com/playwright:v1.52.0-jammy npm ci

docker run --rm --network none \
  -v /home/user/rabit-api-worktrees/issues-122-124-draft-key-races/frontend:/app \
  -v rabit-issues122124-node:/app/node_modules -w /app \
  mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'
```
