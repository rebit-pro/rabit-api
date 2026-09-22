# D3 — прогресс

## Точка продолжения

- 2026-09-22.
  - Ветка `codex/d3-child-transfers`, worktree `/home/user/rabit-api-worktrees/d3-child-transfers`; основной checkout `/home/user/rabit-api` остаётся на `main`.
  - Base — `57b2a816c7fc9a6e3145bd7fd6fc3b0b96aeb4a2` (`main` после PR #45); проверенный ревьюером коммит — `a7696d5`, `main` влит merge-коммитом `47366ab`.
  - Документы: `plan.md`, `docs/waves/d3/`; PR https://github.com/rebit-pro/rabit-api/pull/46.
- Завершено: волна реализована, ревью без блокеров, PR [#46](https://github.com/rebit-pro/rabit-api/pull/46) влит пользователем 2026-09-22 (merge `533c06b`, head `a7696d5`).
- Сейчас: правки после ревью (текст диалога переноса, спецификация gate, верификатор) вынесены в ветку `codex/ops-d3-gate-fixes` от `main` `533c06b` и идут в `main` отдельным PR по поручению пользователя; затем развёртывание backend и frontend stage.
- Один следующий шаг: после merge follow-up PR собрать релиз из нового `main` и выполнить развёртывание по рецепту F2 с адресной миграцией `Version20260922180001`.
- Блокеры: нет. Незакрыто: верификатор `verify-transfers.php` (инварианты БД, откат при внедрённом сбое) ни разу не проходил целиком — прогон 3 упал на его собственной ошибке SQL, прогон 4 оборвался на сети при `npm ci`. Браузерный сценарий D3 в прогоне 3 — PASS (76/0/0/0).
- Рабочее дерево: чисто; `tools/__pycache__/` — локальный кэш py_compile, не коммитится.

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

### 2026-09-22 — backend

- Миграция `Version20260922180001`: столбцы результата переноса в `mf_staff_request_row` с CHECK «все или ничего» и FK целевой группы; добавлена в `api/tools/e2e/prepare.php`.
  - DDL на одноразовом `mysql:8.0` (`--network none`, tmpfs): применение, отказ частичного результата и неверного кода (CHECK), отказ неизвестной группы (FK), `down()` и повторный `up()` — PASS.
- Контракты `rebit.share`: `Contracts/Media/ChildTransferInterface` (+ DTO набора, кадра и перемещения), `Contracts/Commerce/ChildOrdersInterface`; `ChildPhotosInterface::ready(shootId, childIds)` — кадры в текущей группе ребёнка (DEC-06), вызов E5 и тест обновлены.
- Media:
  - `ChildTransferPolicy` (коды A…ZZZ как `childCodeAt` прототипа, сверка набора, совместные кадры), `ChildTransferRepository` (SQL), `ChildTransfers` — реализация контракта: блокировка ревизии съёмки и строк детей, перенос ребёнка и кадров с сохранением ID, замена обложек (DEC-05), CAS ревизии.
  - MED-07: `TransferChildUseCase` (права → блокировка → повтор → состояние групп → ревизия → набор → совместные кадры → код → заказы), чистый `ChildTransferController`, RequestDto, мапперы, DI `di/transfer.php`, маршрут. Отказы Access переводятся в коды (обход #42).
- Commerce: `OrderRepository::childrenWithOrders` и адаптер `Infrastructure/Transfer/ChildOrders`.
- Handoff:
  - `StaffTransferPolicy` (план, целевые коды, подпись SHA-256, SET_CHANGED/SHARED_PHOTO/TARGET_GROUP_CLOSED), `StaffTransferPlanner`, `StaffTransferGuard`;
  - HND-10 `GetStaffTransferPreviewUseCase`, HND-11 `ConfirmStaffTransferUseCase` (порядок блокировок F2, повтор по ключу и по состоянию transferred, журнал повтора без результатов — они в строках);
  - `results` в HND-08, `rows[].childCode` на момент переноса; `appendHistory` принимает ID и имя актора;
  - `BitrixHandoffTransaction(attempts)`: ограниченный повтор при deadlock только для HND-11 (F1/F2 — одна попытка, как раньше).
- Проверки:
  - `php -l` для изменённых файлов — PASS;
  - PHPUnit (`PHPUNIT` из плана) — OK, 561 тест / 2636 проверок, без уведомлений; до тестов D3 — 511/2463;
  - `vendor/bin/phpstan analyse --configuration=phpstan.neon` — No errors (сначала 10 ошибок типов моков в новых тестах, исправлено);
  - php-cs-fixer по 62 изменённым PHP-файлам — исправлено 6, повторный PHPUnit — OK.

### 2026-09-22 — frontend, E2E и документы

- HND-10 отдаёт `targetGroupName`: в live-области заявок есть только обычные группы, имя папки сотрудников берётся из ответа (backend, тесты, `build.py`; `validate.py` — passed, Postman — PASS).
- Frontend:
  - `ReviewBundle`/`RequestPreview` обобщены по типу кадра: демо работает с полными кадрами, live — с ID, кодом и служебным превью;
  - `useTransferPreview` — превью переноса (демо — локальные правила, live — HND-10), предупреждение о заказах, коды кадров без миниатюр для куратора (служебные превью только у организатора);
  - live HND-11 в `saveStaffRequest`, тексты отказов переноса в `rules.ts` (чистые функции с тестами);
  - live MED-07 в рабочем месте фото (`photosApi.transferChild`), тексты отказов, обновление списка при `SET_CHANGED`.
- E2E:
  - `frontend/e2e/live/zzzzzz-transfers.spec.ts`: перенос ребёнка (отказы, занятый код в UI, обложки, повтор ключа, совместный кадр на mobile), льготный перенос после заказа (превью, устаревшая подпись, UI-подтверждение, повтор, заказ и `correctionPhotos`, старая корзина, блокировка разметки, сброс подготовки, льгота и право сотрудника), 4 параллельных подтверждения и неоднозначная цель; различающиеся PNG генерируются в тесте;
  - `zzz-handoff.spec.ts`: кнопка переноса есть, но заблокирована без папки сотрудников;
  - `api/tools/e2e/verify-transfers.php` и вызов в `tools/run-browser-e2e.py` (`D3 integration passed`).
- Документы: `docs/waves/d3/README.md`, `verification.json` (review-pending), `morefoto-contract.patch` (9 файлов MoreFoto), разделы в `docs/architecture.md` и `docs/testing/manual-wave-checklist.md`.
- Проверки:
  - frontend в `playwright:v1.52.0-jammy` с томом `rabit-e5-node` (lockfile не менялся с E5): `npm run check` — PASS (сначала prettier и 6 ошибок типов спецификации — исправлено), `npm run test:commerce` — 172/172, `npm run build` — PASS;
  - PHPUnit — OK, 561/2639 без уведомлений; PHPStan — No errors; php-cs-fixer — исправлен 1 файл (`verify-transfers.php`); `php -l` верификатора — PASS; `python3 -m py_compile tools/run-browser-e2e.py` — PASS;
  - ссылки после DEC-08: старые пути остались только в пометке об истории OPS.

### 2026-09-22 — перенос на актуальный main

- `git fetch --prune origin`: `origin/main` = `a43e4ea` (docs(f2): развёртывание и откат F2, только файлы F2). `git rebase origin/main` — 3 коммита D3 без конфликтов; `git merge-base --is-ancestor origin/main HEAD` — PASS.
- `baseline.commit` обоих графов → `a43e4ea`; `render-waves.py` — «Rendered 41 independent waves»; `validate.py` — passed; `verify-wave-graph.py` — 40/99 `["D3"]` и канон 41/99 `["E6","D3"]`; patch MoreFoto пересобран (`diff -u --suppress-blank-empty`, dry-run применения к исходной копии — PASS).
- Код base не затронут (только документы F2), поэтому PHPUnit, PHPStan и frontend не повторялись.

### 2026-09-22 — публикация на ревью

- `git push -u origin codex/d3-child-transfers` — PASS; `gh pr create --base main` — https://github.com/rebit-pro/rabit-api/pull/46.
- В описании: ответственность модулей, решения D3-DEC-01…08, зависимости, быстрые проверки, ограничения и условие merge — финальный `make test-e2e` и визуальная проверка после ревью без блокирующих замечаний.

### 2026-09-22 — ревью и поручение финального gate

- Ревью PR #46 (комментарий 2026-09-22T15:33:25Z): статическая проверка `a7696d5` относительно `a43e4ea`, подтверждённых блокирующих замечаний нет; тесты и E2E ревьюер не запускал.
- Пользователь: «ревью ветки прошло. Замечаний нету блокирующих… можно прогонять итоговые E2E тесты и деплой на сервер».
- `git fetch --prune origin`: `origin/main` = `57b2a81` (PR #45: форма сотрудника, фильтры заказов — файлы D3 не затронуты). `git merge --no-edit origin/main` → `47366ab` без конфликтов, force-push не нужен.
- `baseline.commit` обоих графов → `57b2a81`; `render-waves.py` — PASS; `validate.py` — passed; `verify-wave-graph.py` — 40/99 `["D3"]`, канон 41/99 `["E6","D3"]`; patch MoreFoto пересобран, dry-run применения — PASS.

### 2026-09-22 — первый финальный gate: FAIL, исправления спецификации

- `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor` на `1503200` (стенд `rabit-e2e-c8b5305c7a23`): frontend check/unit/build, phplint, PHPStan, PHPUnit, схема и Notification — PASS; браузер — 74 passed, 2 failed, 0 skipped. Стенд остановлен, `cleanupErrors=[]`.
  - Прошли: обновлённый F1 (кнопка переноса заблокирована без папки сотрудников) и D3 «4 параллельных подтверждения, неоднозначная цель».
  - D3 MED-07: таймаут на `getByRole('combobox', { name: 'Показать кадры' })` — у фильтра кадров нет доступного имени (aria-снимок: безымянный `combobox`). Спецификация открывает фильтр по `data-testid="photo-filter"`. Отсутствие имени у фильтра — дефект доступности вне D3, в отчёт.
  - D3 льготный перенос: 422 `VALIDATION_FAILED` «Disabled gift must have zero threshold…» в подготовке условий папки сотрудников — в тесте порог 0 при выключенных подарках.
- Разбор до повторного прогона:
  - верификатор E5 требует, чтобы число заказов в БД совпадало с записанными браузером: D3 дописывает свои заказы, ключи доступа и ключи повтора в `var/e5-orders.json`;
  - `verify-transfers.php` загружает `sprint.migration` без проверки результата, как E5;
  - верификаторы E4/F2 не считают чужие данные; фикстура E4 размечает кадр в своей группе — инварианты D3 не конфликтуют.

### 2026-09-22 — второй финальный gate: FAIL, зависимость от глобальных условий

- Пользователь: деплой пока не делать (ждём исправлений соседней сессии, `main` к деплою доведёт пользователь); PR #46 можно влить в `main` после успешного E2E.
- Второй `make test-e2e` (стенд `rabit-e2e-490139b4e3a3`): браузер — 75 passed, 1 failed; D3 MED-07 и параллельные подтверждения — PASS.
  - Сбой: COM-09 в обычной группе D3 → 503 `SERVICE_UNAVAILABLE`, в логе FPM `InvalidConditionsException` «A gift policy requires exactly one active bundle product» (`SalesPolicy.php:43`).
  - Причина вне D3: `zzzz-storefront.spec.ts` создаёт в каталоге второй активный комплект, глобальные условия с порогом подарка становятся недопустимыми, и расчёт в любой группе с наследованием условий отвечает 503. F2 выполняется раньше и не затронута. Это дефект E1/E3 (создание или активация комплекта ломает действующую глобальную политику подарка) — в отчёт пользователю.
  - Исправление спецификации: обычная группа D3 тоже получает собственные условия без подарков до проверки ссылки.

### 2026-09-22 — третий финальный gate: браузер PASS, верификатор D3 — ошибка SQL

- Третий `make test-e2e` (стенд `rabit-e2e-70c555e04c0b`): браузер — 76 expected / 0 unexpected / 0 skipped / 0 flaky (все три сценария D3 и обновлённый F1); верификаторы E4, E5 (с заказами D3 в общем списке) и F2 — PASS.
- `verify-transfers.php` — `SqlQueryException` 1064: `x <> NOT EXISTS (...)` без скобок не разбирается MySQL. Исправлено на `<> (NOT EXISTS (...))`; запрос проверен на одноразовом `mysql:8.0` на контрольных данных (находит оба вида нарушения), `php -l` — PASS. Запущен четвёртый прогон.

### 2026-09-22 — четвёртый запуск: сбой сети; текст диалога переноса

- Четвёртый `make test-e2e` (стенд `rabit-e2e-07e0bc5eceb4`) остановился на `npm ci`: `ECONNRESET` при загрузке пакетов — сбой сети, до проверок кода не дошёл. Стенд удалён скриптом (`stopped=true`, `cleanupErrors=[]`).
- Визуальная проверка снимков третьего прогона: макет desktop/mobile без наложений и горизонтальной прокрутки, но в диалоге «Перенести набор ребёнка» текст «будут перенесены все 1 кадра набора» и кнопка «Перенести 1 кадра» (для 1, 5+ кадров форма неверна). Текст из A8 (`33676cc`), пользователям его открывает D3.
  - Исправление по образцу модуля «Выбрано кадров: N»: «будет перенесён весь набор. Кадров в наборе: N.», кнопка «Перенести набор» — без склонения по числу.
  - Селекторы mock-шага R08 (`photos.steps.ts`) и live-спецификации D3 переведены на точное имя кнопки внутри диалога.

### 2026-09-22 — merge PR #46 и follow-up ветка

- Пользователь влил PR #46: merge `533c06b`, head ветки `a7696d5`. Правки после ревью в `main` не попали — остались в рабочем дереве.
- По поручению «свои изменения добавь в main» создана ветка `codex/ops-d3-gate-fixes` от `main` `533c06b`; в неё перенесены docs-коммит `1503200` и правки текста диалога, live-спецификации, mock-шага R08 и `verify-transfers.php`.
- Прямой коммит в `main` не выполняется: изменения идут отдельным PR.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| D3-BASE | PASS | 2026-09-22 | `gh pr view 40`: MERGED, `44f2e36`; `git merge-base --is-ancestor` для `31ebf8a`, `6b81647`, `4b507b3`, `44f2e36` — PASS; HEAD = `origin/main` = `44f2e36` |
| D3-DECISIONS | PASS | 2026-09-22 | «Подтверждаю все восемь решений, начинай»; записано в plan, графах (`decisionEvidence`) и реестре MoreFoto |
| D3-GRAPH | PASS | 2026-09-22 | `verify-wave-graph.py`: 40 волн, 99 API, `readyFromMain=["D3"]`; канон — 41 волна, `["E6","D3"]`; 10 негативных фикстур |
| D3-CONTRACT | PASS | 2026-09-22 | `build.py`, `validate.py` (passed, 99 запросов, 41 волна), `validate-postman.cjs` (99/198); контракт сверен с кодом (`targetGroupName`); `docs/waves/d3/morefoto-contract.patch` |
| D3-OPS-RENAME | PASS | 2026-09-22 | `git mv` в `docs/plans/OPS-stage-media-recovery/`; `grep` старых путей — только пометка об истории; правило в AGENTS.md/CLAUDE.md |
| D3-MIGRATION | PENDING | 2026-09-22 | DDL на одноразовом MySQL 8.0 — PASS (CHECK, FK, `down()`, повторный `up()`); установка на стенде — `make test-e2e` |
| D3-MOVE | PENDING | 2026-09-22 | Unit PASS (`TransferChildUseCaseTest`, `ChildTransfersTest`); HTTP/UI — финальный gate |
| D3-MOVE-REJECT | PENDING | 2026-09-22 | Unit PASS (8 отказов, коды Access, маппер); HTTP — финальный gate |
| D3-MOVE-IDEM | PENDING | 2026-09-22 | Unit PASS (повтор и конфликт ключа); HTTP — финальный gate |
| D3-PREVIEW | PENDING | 2026-09-22 | Unit PASS (`StaffTransferPolicyTest`, контракт превью); HTTP/UI — финальный gate |
| D3-PREVIEW-REJECT | PENDING | 2026-09-22 | Unit PASS (роли, область, состояния, SET_CHANGED/SHARED_PHOTO/TARGET_GROUP_CLOSED); HTTP — финальный gate |
| D3-TRANSFER | PENDING | 2026-09-22 | Unit PASS (порядок блокировок, перенос, результаты, история); HTTP/UI — финальный gate |
| D3-TRANSFER-REPEAT | PENDING | 2026-09-22 | Unit PASS (повтор по состоянию, конфликт ключа); HTTP — финальный gate |
| D3-TRANSFER-STALE | PENDING | 2026-09-22 | Unit PASS (`SIGNATURE_CONFLICT`, `REVISION_CONFLICT`); HTTP — финальный gate |
| D3-MULTI | PENDING | 2026-09-22 | Unit PASS (две строки, общий кадр переносится один раз); верификатор — финальный gate |
| D3-SHARED | PENDING | 2026-09-22 | Unit PASS (детали `photoCodes`); HTTP/UI mobile — финальный gate |
| D3-ATOMIC | PENDING | 2026-09-22 | Верификатор с внедрённым сбоем написан; запуск — финальный gate |
| D3-CONCURRENCY | PENDING | 2026-09-22 | 4 параллельных HND-11 в спецификации; сериализация с COM-10 — по порядку блокировок, отдельного теста нет |
| D3-ORDERS | PENDING | 2026-09-22 | Спецификация и верификатор написаны; запуск — финальный gate |
| D3-ELIGIBILITY | PENDING | 2026-09-22 | Спецификация (льгота 7500 и `STAFF_ELIGIBILITY_REQUIRED`); запуск — финальный gate |
| D3-PREPARATION | PENDING | 2026-09-22 | Спецификация (`prepared=false`, `LINK_NOT_PREPARED`); запуск — финальный gate |
| D3-COVER | PENDING | 2026-09-22 | Unit PASS (`ChildTransfersTest`); HTTP — финальный gate |
| D3-LOCKS | PENDING | 2026-09-22 | Unit F2 (`MediaLockRecheckTest`) PASS; HTTP после заказа — финальный gate |
| D3-ARCH | PASS | 2026-09-22 | PHPUnit 561/2639 (архитектура `ChildTransferController` и `StaffRequestController`, контракты DTO), PHPStan 0, php-cs-fixer по изменённым файлам |
| D3-UI | PENDING | 2026-09-22 | Frontend check/unit/build PASS; браузер и 4 снимка desktop/mobile — финальный gate |
| D3-REGRESSION | PENDING | 2026-09-22 | PHPUnit 561 и frontend unit 172 PASS; полный браузерный набор — финальный gate |
| D3-PUBLISH | PASS | 2026-09-22 | `git diff --check a43e4ea..HEAD` PASS; `origin/main` = base `a43e4ea`; `git push -u origin codex/d3-child-transfers`; `gh pr create` → PR #46. Merge — после ревью и финального gate |
