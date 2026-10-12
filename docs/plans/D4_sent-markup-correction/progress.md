# D4 — прогресс

## Точка продолжения

- Дата: 12.10.2026. Ветка `codex/d4-sent-markup-correction` от `origin/main` 50c8ee1 (merge E7, PR #162), влит `main` 480427c (план #173); рабочая копия `/home/user/rabit-api-worktrees/d4-sent-markup-correction`. PR #175.
- Связанное: план владельца `docs/plans/OPS-telemos-feedback-plan/` и граф с D4 — PR #173 (слит 480427c). План волны — `docs/plans/D4_sent-markup-correction/plan.md`.
- PR: [#175](https://github.com/rebit-pro/rabit-api/pull/175), head 63d4b6b; ревью 5486913242, 5487050525 и 5487270700 — блокеры закрыты.
- Завершено: решения D4-DEC-01…06, backend (Organization, Commerce, Access, Media, Handoff), frontend, unit-тесты, E2E-спек и верификатор, документация волны; блокеры ревью 5486913242 (267a560) и 5487050525 (b6df341) исправлены, ответы в тредах.
- Сейчас: полный гейт PASS на 63d4b6b (`rabit-e2e-63b3944c7baf`, тестовый магазин ЮKassa OFF по #174); по поручению пользователя PR #175 сливается в `main`.
- Следующий шаг: после merge — выкладка по runbook `docs/waves/d4/README.md` (отдельное действие); следующая волна вносит D4 `merged` в граф.
- Блокеры merge: нет. Внешнее: тестовый магазин ЮKassa (#174) — часть G1 в гейте BLOCKED. Неблокирующие замечания — #176–#178.
- Рабочее дерево: всё закоммичено и запушено.
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

### 12.10.2026 — ревью PR #175 и его блокеры

- Ревью 5486913242 (на 7ba201c): 4 блокера; неблокирующие — #176 (завершение требует товарных проблем, отмены нет), #177 (дублирование правила и проекции, недостижимый повторный старт), #178 (исключение хранилища Commerce уходит в Handoff как 500).
- R1, подпись завершения: `useMarkupCorrection` отправляет `revision`/`signature` загруженной карточки, а не свежей. Рабочее место перечитывает карточку после каждого обновления кадров, кнопка «Завершить» заблокирована, пока карточка перечитывается. Отказ завершения обновляет кадры на экране. Комментарий `markupCorrectionApi` о ключе исправлен. Спек D4: параллельная привязка «другим сотрудником» → `SIGNATURE_CONFLICT` → кадры обновлены → повторное завершение.
- R2, чтение кадров: `GroupMarkupAccess::assertCanRead` — переданная группа `media.correct`, группа в подготовке и список съёмки без группы `media.manage`; `ListPhotosUseCase`/`GetPhotoUseCase` (и превью сотрудника). Рабочее место не запрашивает кадры подготовки для куратора. DEC-02 в плане уточнён.
- R3, миграция: проверка строк истории `prepared` без подписи и `corrected` без причины до любых изменений; CHECK истории меняется одним `ALTER … DROP CHECK …, ADD CONSTRAINT …`. Проверено на одноразовом mysql:8.0 (`--network none`, tmpfs): прежний CHECK пропускал NULL, единый ALTER проходит, новый CHECK отвергает NULL.
- R4: class-level phpDoc у `PermissionPolicy`.
- Проверки: PHPUnit — OK 1119 тестов / 47235 проверок; PHPStan — No errors; php-cs-fixer по 77 файлам — 0 исправлений; frontend `npm run check` — exit 0 (UI 88/88), `test:commerce` — 233/233; `git diff --check` — чисто.

### 12.10.2026 — повторное ревью PR #175

- Повторное ревью 5487050525 (на 267a560): четыре блокера первого ревью закрыты, ответы в тредах. Новый блокер — гонка в шаге спека: «чужая» привязка через API могла опередить перечитывание карточки после своей правки. Исправлено: перед ней спек ждёт активную кнопку «Завершить исправление» (флаг перечитывания ставится синхронно).
- Гейт на 267a560 `rabit-e2e-25902ea7fb8a` (тестовый магазин OFF): группа b 59 passed и её 11 верификаторов, включая `verify-markup-correction.php`; группа a 80 passed, 1 failed — `zz-questions` (K3, метка ответа куратора), код галереи и вопросов не менялся, нестабильность вынесена в #179; верификаторы группы a не запускались.

### 12.10.2026 — гейт после блокера повторного ревью

- Гейт на b6df341 `rabit-e2e-9f0b20f6ca72` (тестовый магазин OFF), 448.6 s — FAIL только по K3: группа b 59 passed, включая D4 desktop/mobile со сценарием `SIGNATURE_CONFLICT`; 11 верификаторов группы b прошли, включая `verify-markup-correction.php`; группа a 80 passed, 1 failed — `zz-questions` второй раз подряд.
- Причина K3 найдена по trace (комментарий в #179): спек кладёт ключ вопроса в `localStorage` до `page.route`; галерея, смонтировав блок вопроса после записи ключа, получает настоящий 404 на `/api/v1/public/questions/current` и стирает ключ — после `reload` тред не запрашивается. К D4 не относится; исправление — отдельный OPS PR по решению пользователя.
- Третье ревью 5487270700 (на b6df341): блокер второго ревью закрыт, код без замечаний; блокер — устаревшая «Точка продолжения» (две строки «Сейчас», прогон 7 не записан). Исправлено этим обновлением.

### 12.10.2026 — исправление K3 и финальный гейт

- PR #180 (порядок `page.route` в спеке K3, #179) слит пользователем в `main` (7d1e066) и влит в ветку (63d4b6b).
- Гейт на 63d4b6b `rabit-e2e-63b3944c7baf` (тестовый магазин OFF), 398.4 s — PASS: группа a 81 passed, группа b 59 passed, 14 верификаторов, включая `verify-markup-correction.php`.

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
| D4-T08 | PASS | 12.10.2026 | `rabit-e2e-63b3944c7baf`: `verify-markup-correction.php` — старт ждёт транзакцию «заказа», расчёт ждёт старт (второе MySQL-соединение), без частичных изменений |
| D4-T09 | PASS | 12.10.2026 | `npm run check`, `test:commerce` 233/233 (D4-T09 в `sales-windows.test.mjs`, `handoff.test.mjs`) |
| D4-T10 | PASS | 12.10.2026 | `rabit-e2e-63b3944c7baf`: `zzzzz-markup-correction` desktop/mobile, включая параллельную правку и `SIGNATURE_CONFLICT` |
| D4-T11 | PASS | 12.10.2026 | `docs/waves/d4/visual.json`, скриншоты `docs/waves/d4/screenshots/` |
| D4-T12 | PASS | 12.10.2026 | D4 `review` в графе (PR #175), решение `D4-DEC-01-04` в `decisionEvidence`; `python3 tools/verify-wave-graph.py docs/waves/graph.json` exit 0 |
