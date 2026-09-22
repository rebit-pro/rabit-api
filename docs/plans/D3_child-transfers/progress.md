# D3 — прогресс

## Точка продолжения

- 2026-09-22.
  - Ветка `codex/d3-child-transfers`, worktree `/home/user/rabit-api-worktrees/d3-child-transfers`; основной checkout `/home/user/rabit-api` остаётся на `main`.
  - Base — `44f2e36367351614fab66fdc58fa3dc7ee3899b3` (merge F2, PR #40); PR D3 ещё нет.
  - Документы: `docs/plans/D3_child-transfers/plan.md`.
- Завершено: решения D3-DEC-01…08 приняты; оба графа синхронизированы и проверены; контракт MoreFoto уточнён и провалидирован; материалы PR #32 перенесены в `OPS-stage-media-recovery`, правило ID записано.
- Сейчас: реализация backend — миграция, Media (MED-07 и участник переноса), Commerce (заказы детей), Handoff (HND-10/11, `results`).
- Один следующий шаг: миграция столбцов результата в `mf_staff_request_row` и её проверка на одноразовом MySQL 8.0.
- Блокеры: нет. Неслитые PR #29/#35/#36 в D3 не переносятся.
- Рабочее дерево: документационный коммит; дальше — незакоммиченный код в работе.
- Команды проверки:
  - `git -C /home/user/rabit-api-worktrees/d3-child-transfers status -sb`
  - `python3 tools/verify-wave-graph.py docs/waves/graph.json`
  - PHPUnit — команда `PHPUNIT` из раздела тест-кейсов плана.

## Хронология

### 2026-09-22 — поручение и ветка

- Пользователь поручил, пока идёт деплой F2, начать D3 и составить план в новой ветке; основное окно на `main` не трогать.
- `git fetch --prune origin`: `origin/main` = `4b507b3` (merge E5), PR #40 (F2) — OPEN.
- Создание ветки:
  - `git worktree add -b codex/d3-child-transfers /home/user/rabit-api-worktrees/d3-child-transfers origin/main`;
  - `git branch --unset-upstream` — случайный push не уйдёт в `main`.
- Пользователь сообщил о merge F2:
  - `gh pr view 40` — MERGED 2026-09-22T13:28:01Z, merge `44f2e36`;
  - `git merge --ff-only origin/main` — HEAD `44f2e36`, коммитов D3 ещё не было;
  - `git merge-base --is-ancestor` для D2 `31ebf8a`, F1 `6b81647`, E5 `4b507b3`, F2 `44f2e36` — PASS.

### 2026-09-22 — исследование

- **Граф и канон.**
  - F2 переставила порядок (`decisionEvidence.F2-D3-ORDER`): D3 зависит от D2/F1/E5/F2 и разблокирует только I3.
  - Канон добавил повторную проверку MED-05/06, HND-02/03/04 и COM-10, а также сброс подготовки переносом.
  - D11 и D12 открыты: `docs/waves/w05/decisions.md`, decisions R17.
- **Контракты.**
  - MED-07: одна съёмка, тот же тип групп, свободный код, полный набор, сохранение ID и `originalGroupId`.
  - HND-10: `targetGroupId, bundles, signature, hasOrders, revision`.
  - HND-11: атомарно, без дублей, повтор не переносит заново.
  - Матрица: «перенос с устаревшим набором или купленными фото — 409».
- **Прототип R17.**
  - `moveChild` требует групп в подготовке и без заказов.
  - Льготный перенос разрешён и после оплаченного заказа: сценарий «оплаченный заказ сохраняется» не меняет заказ, а «старая корзина требует проверки» блокирует оформление.
  - Вывод: строка матрицы относится к MED-07. Решение вынесено в D3-DEC-01/02.
- **Media.**
  - Кадр принадлежит одной группе (`UF_GROUP_ID`); чтение связей фильтрует ребёнка по группе кадра.
  - Ребёнок — `mf_media_child` (UNIQUE съёмка+группа+код).
  - Ревизия общая на съёмку.
  - Отдельного журнала переноса и снятия связи нет.
  - Правка разметки запрещена после передачи (`GROUP_LOCKED`, повторная проверка после `lockRevision` добавлена в F2).
  - `MediaController` — legacy (#24).
- **Handoff.**
  - Строки заявки хранят `GROUP_ID`, `CHILD_ID` (FK `mf_media_child`), код и кадры на момент подачи.
  - Одна незавершённая заявка на ребёнка проверяется в коде.
  - Статус `transferred` не выставляется; `results` в выдаче HND-08 нет.
  - Чистый `StaffRequestController` — эталон.
- **F2.**
  - Подготовка проверяется по signature, `prepared()` истинно и после передачи.
  - Незавершённые заявки дают проблему `staffRequestsPending`.
  - Порядок блокировок: Access → Organization → актор → идемпотентность → ссылка → медиа → Commerce share.
  - Commerce зависит от Handoff, поэтому зависимость в обратную сторону — ленивая.
- **Commerce.**
  - `StaffEligibility` ищет строки заявки по `CHILD_ID` без учёта статуса. Перенос с сохранением `CHILD_ID` оставляет право, новый ребёнок его потерял бы.
  - `mf_order_line` копирует ID связи, ребёнка и кадра, есть индекс `ix_mf_order_line_child`.
  - Quote после переноса: `INVALID_CART` или `QUOTE_STALE`.
  - `correctionPhotos` фильтруется по группе заказа, для перенесённого ребёнка список станет пустым (D3-DEC-06).
  - Одна staff-группа на съёмку не гарантируется (D3-DEC-04).
- **Issues.**
  - #42 п. 3 — коллизия ID D3 с оперативным PR #32: `docs/plans/D3_stage-media-recovery/`, `docs/waves/d3/visual/`. Папка плана выбрана `D3_child-transfers`; перенос материалов — D3-DEC-08.
  - #42 п. 1 — коды 401/403/404 общих источников.
  - #27 — порядок идемпотентности F1.

### 2026-09-22 — готовность по графу и план

- `python3 tools/verify-wave-graph.py docs/waves/graph.json` — exit 0:
  - 40 волн, 99 API, `readyFromMain=["F2"]` (в графе F2 ещё `inProgress`), D3 в `blocked`.
- То же для канона MoreFoto — exit 0:
  - 41 волна (E6 из неслитого PR #35), 99 API, `readyFromMain=["E6","F2"]`, D3 в `blocked`.
- Составлен `plan.md`: scope, решения D3-DEC-01…08 с альтернативами, архитектурные правила, проект хранения, риски, чек-лист, критерии и 27 тест-кейсов.

### 2026-09-22 — решения приняты, графы и контракт

- Пользователь: «Подтверждаю все восемь решений, начинай».
- Графы (`docs/waves/graph.json` и канон `../MoreFoto/docs/04-bitrix-modules/backend-waves.json`, только поля F2/D3/baseline/решений):
  - baseline `44f2e36`, F2 в `mergedWaves`/`mergedPullRequests` (PR #40)/`mergeCommits`, F2 merged;
  - D3 inProgress, decisionGates `D03, D05, D11`, уточнён пункт scope о D11/D12;
  - D11 в `acceptedImplementationDecisions`, `decisionEvidence.D11` и `decisionEvidence.D3-D12-SCOPE`.
- `python3 docs/04-bitrix-modules/render-waves.py` — «Rendered 41 independent waves».
- `python3 tools/verify-wave-graph.py docs/waves/graph.json` — exit 0: 40 волн, 99 API, `readyFromMain=["D3"]`, 10 негативных фикстур; для канона — 41 волна, `readyFromMain=["E6","D3"]` (E6 — неслитый #35).
- Контракт MoreFoto `docs/05-rest-api/build.py`: MED-07, HND-02, HND-08, HND-09, HND-10, HND-11, COM-13 — коды отказов, `results`, `hasOrders`, ответ MED-07, `correctionPhotos` после переноса, примеры ответов.
  - `python3 docs/05-rest-api/build.py` — PASS; `validate.py` — `result: passed`, 99 запросов, 41 волна; `node docs/05-rest-api/validate-postman.cjs` — exit 0, 99/198 фикстур, 43 проверки повтора.
  - Реестр решений MoreFoto `docs/frontend/business-review/decisions.md` — дополнение о D11 и границе D12.
  - Копия исходного `docs` MoreFoto сохранена во временном каталоге сессии для итогового patch.
- DEC-08: `git mv` материалов PR #32 в `docs/plans/OPS-stage-media-recovery/` (с `visual/`), ссылки обновлены, заголовки и пометка об истории; `docs/waves/d3/` освобождён. Правило ID волн добавлено в AGENTS.md и CLAUDE.md (файлы идентичны).

## Результаты тест-кейсов

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| D3-BASE | PASS | 2026-09-22 | `gh pr view 40`: MERGED, `44f2e36`; `git merge-base --is-ancestor` для `31ebf8a`, `6b81647`, `4b507b3`, `44f2e36` — PASS; HEAD = `origin/main` = `44f2e36` |
| D3-DECISIONS | PASS | 2026-09-22 | «Подтверждаю все восемь решений, начинай»; записано в plan, графах (`decisionEvidence`) и реестре MoreFoto |
| D3-GRAPH | PASS | 2026-09-22 | `verify-wave-graph.py`: 40 волн, 99 API, `readyFromMain=["D3"]`; канон — 41 волна, `["E6","D3"]`; 10 негативных фикстур |
| D3-CONTRACT | PENDING | 2026-09-22 | `build.py`/`validate.py`/`validate-postman.cjs` — PASS; patch и сверка с кодом — перед PR |
| D3-OPS-RENAME | PENDING | 2026-09-22 | Перенос выполнен (`git mv`), проверка ссылок — перед PR |
| D3-MIGRATION | PENDING | — | — |
| D3-MOVE | PENDING | — | — |
| D3-MOVE-REJECT | PENDING | — | — |
| D3-MOVE-IDEM | PENDING | — | — |
| D3-PREVIEW | PENDING | — | — |
| D3-PREVIEW-REJECT | PENDING | — | — |
| D3-TRANSFER | PENDING | — | — |
| D3-TRANSFER-REPEAT | PENDING | — | — |
| D3-TRANSFER-STALE | PENDING | — | — |
| D3-MULTI | PENDING | — | — |
| D3-SHARED | PENDING | — | — |
| D3-ATOMIC | PENDING | — | — |
| D3-CONCURRENCY | PENDING | — | — |
| D3-ORDERS | PENDING | — | — |
| D3-ELIGIBILITY | PENDING | — | — |
| D3-PREPARATION | PENDING | — | — |
| D3-COVER | PENDING | — | — |
| D3-LOCKS | PENDING | — | — |
| D3-ARCH | PENDING | — | — |
| D3-UI | PENDING | — | — |
| D3-REGRESSION | PENDING | — | — |
| D3-PUBLISH | PENDING | — | — |
