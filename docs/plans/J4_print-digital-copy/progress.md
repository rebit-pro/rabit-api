# J4 — прогресс

## Точка продолжения

- Дата: 12.10.2026. Ветка `codex/j4-print-digital-copy` от `origin/main` a302774 (merge E8); рабочая копия `/home/user/rabit-api-worktrees/j4-print-digital-copy`. PR нет.
- Связанное: README волны `docs/waves/j4/README.md`, граф `docs/waves/graph.json`, план владельца `docs/plans/OPS-telemos-feedback-plan/` (R03), J1 `docs/waves/j1/README.md`.
- Завершено: S0–S5 — Commerce (df88185), frontend (1e88e79), оферта (коммит редакции 2026-10-12), E2E и фикстура оплаты (cdfd6b9), документация и граф (E8 `merged`).
- Сейчас: S7 — полный гейт, прогон 1 (лог в scratchpad сессии `e2e-j4-run1.log`).
- Следующий шаг: по зелёному гейту — скриншоты в `docs/waves/j4/screenshots/`, `verification.json`, `visual.json`, push и PR, затем J4 `review` в графе.
- Блокеры: нет. Внешнее: тестовый магазин ЮKassa (#174) — гейт без него, оплата печати фикстурой (DEC-04).
- Рабочее дерево: чистое после коммита документации.
- Проверки: PHPUnit/PHPStan — docker `rabit-api-php-cli:d1-local`, vendor volume `rabit-e7-vendor` (скрипт `php-j4.sh` в scratchpad); frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`; гейт — `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor make test-e2e`; граф — `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Журнал

### 12.10.2026 — старт

- E8 слита (PR #181, a302774). Из свободных F3/J4/L1/G2 взята J4: от неё зависят J2 и N3; G2 ждёт D09, L1 — сверки D06.
- Других веток и PR по J4 нет. Worktree: `git worktree add -b codex/j4-print-digital-copy /home/user/rabit-api-worktrees/j4-print-digital-copy origin/main`.
- Инвентаризация — раздел «Факты инвентаризации» плана. Найдено расхождение после E8: п. 27 оферты 2026-10-11 требует имя и телефон.

### 12.10.2026 — решения

- Пользователь выбрал: копия по всем оплаченным заказам (DEC-01), одна редакция оферты в J4 с исправлением п. 27 после E8 (DEC-02), подсказка без блокировки (DEC-03), фикстура оплаты в обход провайдера (DEC-04, вместо рекомендованного тестового магазина).
- Фикстура до браузера невозможна: каталог создают браузерные спеки. Поэтому оплата — во время браузерной группы: спек → `var/j4-pay-request.json` → раннер → `pay-fixture.php` (код G1, подставной провайдер `e2e-fixture`).

### 12.10.2026 — реализация

- Commerce (df88185): `OrderEntitlements` — строки `physical` с кадром дают этот кадр, кадр в проекции один раз; контракт без изменений, phpDoc DTO обновлён.
- Frontend (1e88e79): тексты оформления и заказа, состояние `empty`; подсказка выбора (`commerce/print-copy.ts`); демо-право; Cucumber-сценарий отпечатка — ZIP с одним файлом.
- Оферта: редакция 2026-10-12 (п. 27 — только email; пункт о копии печати в разделе 6), `registry.php`.
- E2E (cdfd6b9): группа `print` в фикстуре E4 (A1/A2, B1/B2); `pay-fixture.php` и наблюдатель `pay_fixture` в раннере; сценарий J4 в `zzzzzzzzzz-files`; `verify-payments`/`verify-files` знают заказ фикстуры.
- По ходу: порог подарка печати (200 ₽ на ребёнка) открывает комплект A целиком — сосед для проверки перенесён к B (B1 один раз дешёвым форматом ниже порога), ожидаемый набор строится по `gifts` расчёта.
- Документация: README волны, раздел J4 в ручном чек-листе; граф — E8 `merged` (PR #181, a302774), `decisionEvidence` J4-DEC-01-04, MVP-PRINT-COPY-HISTORY снят из `openLaunchDecisions`. `verify-wave-graph`: exit 0, 59 волн, readyMvpFromMain [F3, J4].

### 12.10.2026 — быстрые проверки

- PHPUnit (`php-j4.sh`): OK 1110 тестов. PHPStan: без ошибок. php-cs-fixer: 0 исправлений по изменённым PHP-файлам. `php -l` новых E2E-скриптов — без ошибок.
- Frontend: `npm run check` exit 0, `test:commerce` 235/235, `typecheck:e2e` OK, ESLint спека OK.
- Раннер: `python3 -m unittest discover -s tools/tests` — 25/26, падает только `test_f2_prepares_first_and_reports_later_in_one_group` (как на main, #163).

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| J4-T01 | PASS | 12.10.2026 | PHPUnit `OrderEntitlementsTest::testEachPrintedFrameGivesOneCopyWhateverTheFormatsAndCount` |
| J4-T02 | PASS | 12.10.2026 | PHPUnit `OrderEntitlementsTest` (печать + digital + подарок) |
| J4-T03 | PASS | 12.10.2026 | PHPUnit `FileAccessTest` (объединение по `photoId`, в составе 1110 OK) |
| J4-T04 | PASS | 12.10.2026 | PHPUnit `FileAccessTest::testStateFollowsPaymentLatenessAndDeadline` (состояния не зависят от вида строки) |
| J4-T05 | PASS | 12.10.2026 | `npm run test:commerce` → `print-copy.test.mjs` |
| J4-T06 | PENDING | — | гейт, прогон 1 |
| J4-T07 | PENDING | — | гейт, прогон 1 |
| J4-T08 | PENDING | — | гейт, прогон 1 |
| J4-T09 | PENDING | — | PHPUnit legal 21 OK; гейт (legal/orders) |
| J4-T10 | PENDING | — | граф проверен (exit 0); J4 `review` — после открытия PR |
