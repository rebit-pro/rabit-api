# D4 — прогресс

## Точка продолжения

- Дата: 12.10.2026. Ветка `codex/d4-sent-markup-correction` от `origin/main` 50c8ee1 (merge E7, PR #162), влит `main` 480427c (план #173); рабочая копия `/home/user/rabit-api-worktrees/d4-sent-markup-correction`. PR нет.
- Связанное: план владельца `docs/plans/OPS-telemos-feedback-plan/` и граф с D4 — PR #173 (слит 480427c). План волны — `docs/plans/D4_sent-markup-correction/plan.md`.
- Завершено: инвентаризация, решения D4-DEC-01…06, backend (Organization, Commerce, Access, Media, Handoff), frontend, unit-тесты, E2E-спек и верификатор, документация волны.
- Сейчас: S2–S9 сделаны, полный гейт PASS (прогон 5 `rabit-e2e-f048a4c2af27`, тестовый магазин ЮKassa OFF из-за #174), visual PASS.
- Следующий шаг: при зелёном гейте — visual/verification в docs/waves/d4, D4 `review` в графе, push и PR (пользователь поручил открыть PR после гейта).
- Блокеры: нет (#173 слит). Внешнее: G1-T14 с тестовым магазином ЮKassa (#174).
- Рабочее дерево: изменения закоммичены по этапам.
- Проверки (план): PHPUnit/PHPStan — docker `rabit-api-php-cli:d1-local` с vendor volume (для D4 — скопировать `rabit-e7-vendor`, если `api/composer.lock` не менялся); frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`; граф — `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Журнал

### 11.10.2026 — старт

- E7 слита в `main` (PR #162, 50c8ee1) по поручению пользователя. Следующая волна по решению владельца — D4.
- D4 определена в графе ветки `codex/ops-telemos-feedback-plan`. Ветка запушена, PR #173 открыт; в неё влит `main` 50c8ee1 без конфликтов, граф валиден (59 волн).
- Других веток и PR по D4 нет (`git branch -a`, `gh pr list`).
- Worktree: `git worktree add -b codex/d4-sent-markup-correction /home/user/rabit-api-worktrees/d4-sent-markup-correction origin/main`.
- Инвентаризация (два read-only агента: media; commerce/handoff/frontend). Факты — в плане, раздел «Факты инвентаризации». Главное:
  - запрет после передачи — `groupEditable = UF_SENT_AT is null`;
  - снятия кадра с ребёнка нет;
  - старые quote уже ломаются при правке разметки;
  - сериализация с заказом — эксклюзивная блокировка строки группы;
  - контракта «у группы есть заказы» нет;
  - куратор не имеет доступа к разметке.
- Номера API: HND-02 — карточка ссылки, HND-03 — подготовка, HND-04 — передача (в графе D4 указаны HND-02…04 как проверяемые); COM-09 — quote, COM-10 — заказ; MED-02 — список кадров, MED-04 — кадр, MED-05 — привязка, MED-06 — обложка.

### 11.10.2026 — решения

- Пользователь принял рекомендуемые варианты: сеанс исправления с паузой продаж (DEC-01), организатор и куратор учреждения с правом `media.correct` (DEC-02), существующие команды без новой операции снятия кадра (DEC-03), начало исправления только пока группа продаёт (DEC-04).

### 11.10.2026 — Organization, Commerce, Access, Media

- Organization: миграция `Version20261011180001` (`UF_MARKUP_CORRECTION_AT`, CHECK `ck_mf_group_markup_correction` — только у переданной группы; `down()` отказывает при открытом исправлении), ID в `api/tools/e2e/prepare.php`. Контракт `GroupMarkupCorrectionInterface` (start/finish), правило `MarkupCorrectionPolicy` (LINK_NOT_SENT / GALLERY_CLOSED), сервис `GroupMarkupCorrection` с CAS ревизии группы и журналом Organization. Признак в `GalleryGroupOutputDto::markupCorrectionAt`, `GroupDirectoryItemOutputDto::markupCorrectionAt`, `MediaScopeOutputDto::groupCorrecting`.
- Commerce: `GroupOrdersInterface::exist` по `ix_mf_order_group`; `SalesWindowPolicy::assertNotPaused` → 409 `GALLERY_UPDATING` в quote и заказе.
- Access: право `media.correct` (куратор в своём учреждении; организатор — все права).
- Media: `GroupMarkupAccess` — до передачи `media.manage`, в режиме исправления `media.correct`; команды привязки, обложки, удаления, загрузки и привязки при загрузке проверяют редактируемость до и после блокировки ревизии съёмки. MED-07 не тронут (D11). Чтение кадров и превью сотрудника — `media.correct`. Галерея отдаёт `updating`.
- Проверки: PHPUnit organization (270 OK), commerce (228 OK), media + access (240 OK) — `php-d4.sh php vendor/bin/phpunit <dir>`.

### 11.10–12.10.2026 — Handoff, frontend, E2E

- Handoff: `StartMarkupCorrectionUseCase` и `FinishMarkupCorrectionUseCase` в общем порядке блокировок F2, транзакция с повтором ×3 (`BitrixHandoffTransaction(3)`), маршруты `/markup-corrections` и `/markup-correction-completions`, события `markup_started`/`markup_finished`, CHECK истории в миграции D4. Карточка ссылки — `markupCorrectionAt`, `markupCorrectable` (заказы читаются, только если остальное разрешает).
- Frontend: `useMarkupCorrection` в рабочем месте кадров (кнопки «Исправить разметку»/«Завершить исправление», перенос набора только при подготовке, куратор входит по `media.correct`), подписи событий D4 в истории ссылки, тексты ошибок; галерея, корзина и оформление — `galleryBuys`, плашка обновления, `GALLERY_UPDATING`.
- E2E: фикстура E4 — группы `markup` и `markup-db`; спек `zzzzz-markup-correction` (группа b, desktop/mobile); `verify-markup-correction.php` (второе MySQL-соединение держит строку группы: старт ждёт «заказ», расчёт ждёт старт).
- PR #173 (план владельца с D4) слит пользователем 11.10.2026 (480427c), `main` влит в ветку (312cf11), E7 отмечена `merged` в графе; `verify-wave-graph`: readyMvpFromMain = D4, E8, F3, J4.
- Полный гейт (`make test-e2e` с образами d1-local):
  - прогон 1 `rabit-e2e-a9b8fa951c36` — фикстура: цвет 260 у седьмой группы (ValueError) → модуль 256 (c357559);
  - прогоны 2 `rabit-e2e-684b683411d0` и 3 `rabit-e2e-de3a5a1ee193` — спеки D4 desktop/mobile PASS; падал только G1-T14: тестовый магазин ЮKassa не отвечает на создание СБП за 9 с, повтор — 400 (issue #174, не D4); группа a отменялась, верификаторы не запускались;
  - прогон 4 `rabit-e2e-47250c37c9da` с `E2E_YOOKASSA_ENV=/nonexistent/…` (тестовый магазин OFF, часть G1 — BLOCKED): группа b PASS, `verify-markup-correction.php` нашёл дефект — CHECK истории пропускал `markup_finished` без подписи (CHECK принимает UNKNOWN). Условия сделаны устойчивыми к NULL, включая старые `prepared`/`corrected` из F2; верификатор проверяет все три; в README — read-only запрос перед миграцией.
- Прогон 5 `rabit-e2e-f048a4c2af27` (a452c4b, тестовый магазин OFF) — PASS: группа a 81, группа b 59, 14 верификаторов, `verify-markup-correction.php` — «schema checks, start and quote serialize on the group row, pause refuses quote and order, correction published, stale quote, order, GROUP_HAS_ORDERS, migration replay and guarded rollback». Скриншоты и проверки — `docs/waves/d4/visual.json`.
- Visual (прогон 2): рабочее место в режиме исправления и галерея с плашкой на 1440×1000 и 390×844 — без горизонтального скролла, тексты и кнопки на месте.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| D4-T01 | PASS | 12.10.2026 | PHPUnit `MarkupCorrectionPolicyTest`, `GroupLinkWorkflowTest` (LINK_NOT_SENT, GROUP_HAS_ORDERS); `verify-markup-correction.php` (GROUP_HAS_ORDERS на MySQL) |
| D4-T02 | PASS | 12.10.2026 | PHPUnit `GroupLinkWorkflowTest` (завершение, LINK_NOT_READY, CORRECTION_NOT_STARTED, SIGNATURE_CONFLICT) |
| D4-T03 | PASS | 12.10.2026 | PHPUnit `MediaLockRecheckTest` (режим и перепроверка под блокировкой) |
| D4-T04 | PASS | 12.10.2026 | PHPUnit `PermissionPolicyTest` (`media.correct`), `LinkPolicyTest` (матрица D08) |
| D4-T05 | PASS | 12.10.2026 | PHPUnit `StorefrontQuoteTest`, `OrderCheckoutUseCaseTest` (GALLERY_UPDATING); верификатор — QUOTE_STALE после исправления |
| D4-T06 | PASS | 12.10.2026 | `verify-markup-correction.php`: заказ в любом статусе закрывает исправление |
| D4-T07 | PASS | 12.10.2026 | Architecture: `GroupLinkControllerArchitectureTest` (7 action), phpDoc UseCase/Service |
| D4-T08 | PASS | 12.10.2026 | `rabit-e2e-f048a4c2af27`: `verify-markup-correction.php` — старт ждёт транзакцию «заказа», расчёт ждёт старт (второе MySQL-соединение), без частичных изменений |
| D4-T09 | PASS | 12.10.2026 | `npm run check`, `test:commerce` 233/233 (D4-T09 в `sales-windows.test.mjs`, `handoff.test.mjs`) |
| D4-T10 | PASS | 12.10.2026 | `rabit-e2e-f048a4c2af27`: `zzzzz-markup-correction` desktop/mobile |
| D4-T11 | PASS | 12.10.2026 | `docs/waves/d4/visual.json`, скриншоты `docs/waves/d4/screenshots/` |
| D4-T12 | PENDING | — | после PR: D4 `review` в графе, `verify-wave-graph` |
