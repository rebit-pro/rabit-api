# E8 — прогресс

## Точка продолжения

- Дата: 12.10.2026. Ветка `codex/e8-email-only-checkout` от `origin/main` 28a8c55 (merge D4); рабочая копия `/home/user/rabit-api-worktrees/e8-email-only-checkout`. PR #181 (https://github.com/rebit-pro/rabit-api/pull/181).
- Связанное: README волны `docs/waves/e8/README.md`, `verification.json`, `visual.json`, граф `docs/waves/graph.json`, план владельца `docs/plans/OPS-telemos-feedback-plan/` (R02).
- Завершено: S0–S4, S6 — реализация, быстрые проверки, полный гейт (rabit-e2e-64ca39ba3e76) и прогон b после правки ширины (rabit-e2e-14bb523c86a3), visual desktop/mobile.
- Сейчас: блокеры ревью 5489141780 (8cab453) и повторного ревью 5489255619 исправлены, ответы в тредах. Неблокирующие — #182, #183.
- Следующий шаг: повторное ревью или merge — только по команде пользователя. После merge выкладка: backend + `migrate up 20261012100001`, затем frontend (`docs/waves/e8/README.md`).
- Блокеры: нет. Внешнее: тестовый магазин ЮKassa (#174) — в гейте выключен.
- Рабочее дерево: чистое после коммита исправления.
- Проверки: PHPUnit/PHPStan — docker `rabit-api-php-cli:d1-local`, vendor volume `rabit-e7-vendor`; frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`; гейт — `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor make test-e2e`; граф — `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Журнал

### 12.10.2026 — старт

- D4 слита (PR #175, 28a8c55). Из свободных E8/F3/J4 взята E8: от неё зависят J2 и G2 (критический путь к запуску).
- Других веток и PR по E8 нет. Worktree: `git worktree add -b codex/e8-email-only-checkout /home/user/rabit-api-worktrees/e8-email-only-checkout origin/main`.
- Инвентаризация: факты в плане, раздел «Факты инвентаризации».

### 12.10.2026 — решения

- Пользователь выбрал: имя и телефон сервер игнорирует (DEC-01, вместо рекомендованного «принимать как необязательные»); в списке сотрудника — email (DEC-04); демо-режим тоже без имени и телефона (DEC-05). DEC-02/03 — технические следствия.

### 12.10.2026 — реализация

- Commerce (3753999): миграция `20261012100001` — `BUYER_NAME`/`BUYER_PHONE` NULL-допустимы, CHECK `ck_mf_order_values` пересоздан одним `ALTER` с NULL-safe условием покупателя; комбинированный ALTER проверен на одноразовом mysql:8.0. `OrderBuyerRequestDto` принимает `name`/`phone` со значением по умолчанию `''`, `BuyerPolicy::accept` их не получает, запись — `NULL`. Хеш повтора — прежний кортеж из исходных значений тела (тест с литералом хеша старого тела).
- Frontend (0564193): форма — только email; черновик, тело, проверка и демо без имени и телефона; строки «Покупатель»/«Телефон» по наличию; список сотрудника — `staffBuyerLine` (имя · телефон, иначе email); исправление контактов в «расчётах» — имя и телефон необязательны.
- E2E (79bda08): `zzzzz-orders` — заказ с одним email, старое тело с невалидными именем и телефоном → 201 без них, форма без полей; тела API payments/transfers/payment-costs/files только с email; Cucumber-шаги демо; верификатор `verify-email-only-orders.php` (после `verify-markup-correction.php`).
- Ошибка по ходу: runtime-импорт `'.js'` в `orders/live/rules.ts` ломал `node --test` — исправлен на `'.ts'`. Prettier-замечания — `npx eslint --fix` по изменённым файлам.

### 12.10.2026 — быстрые проверки

- PHPUnit (`php-e8.sh`): OK 1109 тестов. PHPStan: без ошибок. php-cs-fixer: 0 исправлений.
- Frontend: `npm run check` exit 0, `test:ui` 88/88, `test:commerce` 233/233, `typecheck:e2e` OK.

### 12.10.2026 — документация и граф

- df8783e: `docs/waves/e8/README.md` (контракт COM-10, миграция, выкладка и откат), раздел E8 в `docs/testing/manual-wave-checklist.md`; граф — D4 `merged` (PR #175, 28a8c55), `baseline.commit` 28a8c55, `decisionEvidence` E8-DEC-01-05. `verify-wave-graph`: exit 0, 59 волн, readyMvpFromMain [E8, F3, J4].
- Запущен полный гейт, прогон 1 (ЮKassa выключена).

### 12.10.2026 — гейт и visual

- Прогон 1 (полный, df8783e, rabit-e2e-64ca39ba3e76): PASS — a 81, b 59, 15 верификаторов, включая `verify-email-only-orders.php`; 416 с; ЮKassa OFF.
- Visual: на desktop поле email (560 px) было уже комментария (640 px). Исправлено в 25fa0bc: ширина набора полей — 560 px, отдельное правило email удалено. Cucumber-шаг ширины (`.contact-email .v-field` ≤ 560) не меняется.
- Прогон 2 (`E2E_GROUPS=b`, 25fa0bc, rabit-e2e-14bb523c86a3): PASS — b 59, 12 верификаторов; 273 с. Скриншоты оформления, заказа, списка и карточки сотрудника на desktop/mobile — `docs/waves/e8/screenshots/`, хеши в `visual.json`.
- Группа a последний раз шла на df8783e; следующий коммит меняет только стили `CheckoutContacts.vue`.

### 12.10.2026 — PR

- Ветка запушена, открыт PR #181 в main. E8 отмечена `review` в графе; `verify-wave-graph` exit 0.

### 12.10.2026 — ревью PR #181

- Review 5489141780 (head 081afa7). Блокер: `plan.md:79` обещает, что миграция перед `ALTER` проверяет строки и отказывает, — в `up()` проверки нет (и не нужна: правило E8 только ослабляет E5).
- Демо-набор Cucumber (`npm run test:e2e:run`, mock) запущен при ревью: полный прогон не уложился в 25 мин; `--fail-fast` падает на демо-входе (`login.feature:20`) одинаково на main 28a8c55 и на E8 → #182. Шаги, изменённые в E8, проверены только `typecheck:e2e`.
- Неблокирующие: #182, #183 (устаревший черновик оформления с именем и телефоном остаётся в localStorage).

### 12.10.2026 — исправление блокера

- `plan.md`: пункт риска о пересоздании CHECK переписан — предварительной проверки нет и она не нужна (E8 только ослабляет E5), сбой `ALTER` оставляет схему E5; добавлено ограничение #182. `verification.json`: `demoCucumber` = failed-baseline (#182) и ограничение в `limits`. Код не менялся, гейт не перезапускался.

### 12.10.2026 — повторное ревью

- Review 5489255619 (head 8cab453): прошлый блокер закрыт; новый — дубль фразы о выкладке в «Следующий шаг» и заголовки журнала без пустой строки. Исправлено следующим коммитом; код не менялся, гейт не перезапускался.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| E8-T01 | PASS | 12.10.2026 | PHPUnit `OrderBuyerPolicyTest` (в составе 1109 OK; OK в гейте) |
| E8-T02 | PASS | 12.10.2026 | PHPUnit `OrderReceiptsAndAccessTest::testReplayHashOfAnAttemptBegunBeforeE8IsUnchanged` |
| E8-T03 | PASS | 12.10.2026 | PHPUnit `OrderMapperTest::testEmailOnlyOrderReadsWithoutMadeUpContacts` |
| E8-T04 | PASS | 12.10.2026 | PHPUnit `OrderInputMapperTest::testOrderBuyerNeedsOnlyTheEmailAndStillAcceptsAnOlderBody` |
| E8-T05 | PASS | 12.10.2026 | `npm run check`, `test:commerce` 233/233; в гейте lint/typecheck/UI/commerce/build |
| E8-T06 | PASS | 12.10.2026 | `make test-e2e` → `verify-email-only-orders.php` (rabit-e2e-64ca39ba3e76, rabit-e2e-14bb523c86a3) |
| E8-T07 | PASS | 12.10.2026 | `make test-e2e` → `zzzzz-orders`, payments, transfers, payment-costs, files (a 81, b 59) |
| E8-T08 | PASS | 12.10.2026 | `docs/waves/e8/visual.json`, 8 скриншотов desktop/mobile (rabit-e2e-14bb523c86a3) |
| E8-T09 | PASS | 12.10.2026 | E8 `review` (PR #181), D4 `merged`; `python3 tools/verify-wave-graph.py docs/waves/graph.json` exit 0 |
