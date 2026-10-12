# J4 — прогресс

## Точка продолжения

- Дата: 12.10.2026. Ветка `codex/j4-print-digital-copy` от `origin/main` a302774 (merge E8); рабочая копия `/home/user/rabit-api-worktrees/j4-print-digital-copy`. PR #184 (https://github.com/rebit-pro/rabit-api/pull/184).
- Связанное: README волны `docs/waves/j4/README.md`, граф `docs/waves/graph.json`, план владельца `docs/plans/OPS-telemos-feedback-plan/` (R03), J1 `docs/waves/j1/README.md`.
- Завершено: S0–S5, S7 — код (df88185, 1e88e79, оферта, cdfd6b9, 2fc24a4, d410e03, 445d379), полный гейт `rabit-e2e-1ef3dd028ca9` и прогоны b для снимков, visual desktop/mobile.
- Сейчас: исправление блокера ревью PR #184 (review 5491026503 на 72518f6): 1 блокер — неатомарная передача `j4-pay-request.json`/`j4-paid.json` между спеком и раннером; неблокирующее — #185 (демо «расчёты» теряют копию печати после правки строки).
- Следующий шаг: прогон группы b после исправления блокера, затем ответ в треде; merge — только по команде пользователя. После merge выкладка без миграций: preview SQL из README, затем backend и frontend.
- Блокеры: нет. Внешнее: тестовый магазин ЮKassa (#174) — гейт без него, оплата печати фикстурой (DEC-04).
- Рабочее дерево: чистое после коммита исправления.
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

### 12.10.2026 — гейт, прогон 1

- `rabit-e2e-352f0346b9e7` (39c96fa, ЮKassa OFF): FAIL — группа b, сценарий J4: `created.gifts` undefined — подарки расчёта лежат в `data.quote.gifts`, а не в `data.gifts`. Остальные 59 сценариев b до него прошли; группа a отменена раннером. Исправлено в спеке, прогон 2.

### 12.10.2026 — гейт, прогоны 2–4 и visual

- Прогон 2 (полный, 2fc24a4, `rabit-e2e-1ef3dd028ca9`): PASS — a 81, b 60, 15 верификаторов; `pay-fixture.log`: «J4 fixture payment succeeded»; 396 с; ЮKassa OFF.
- В этом прогоне подарок по порогу не сработал (`quote.gifts` пуст при условиях стенда): ожидаемый набор файлов — A001 и B001. Ветку «печать + подарок» покрывает unit-тест; README исправлен, без утверждения «выше порога» (d410e03).
- Visual: на mobile подсказка выбора не попадала в полностраничный снимок — прокрутка (d410e03) не помогла, снимок подсказки переведён на видимую область (445d379).
- Прогон 3 (`E2E_GROUPS=b`, d410e03, `rabit-e2e-65fb1516fa3e`): PASS — b 60, 12 верификаторов.
- Прогон 4 (`E2E_GROUPS=b`, 445d379, `rabit-e2e-072725b5135d`): PASS — b 60, 12 верификаторов; скриншоты `docs/waves/j4/screenshots/`, хеши в `visual.json`.

### 12.10.2026 — PR

- Ветка запушена, открыт PR #184 в main. J4 отмечена `review` в графе; `verify-wave-graph` exit 0.

### 12.10.2026 — ревью PR #184

- Review 5491026503 (head 72518f6). Блокер: спек пишет `var/j4-pay-request.json` через `writeFileSync`, а раннер забирает файл по `is_file()`; обратно `docker cp`/`write_text` для `j4-paid.json` и `existsSync` + `JSON.parse` в спеке — неатомарно, возможен случайный красный гейт.
- Неблокирующее: #185 — демо «расчёты» (`eligiblePhotos()`) после правки строки отбрасывают копии печатных кадров.
- Push коммита 72518f6 с первой попытки отклонён GitHub (`fatal error in commit_refs`), повтор прошёл.

### 12.10.2026 — исправление блокера

- Спек пишет `var/j4-pay-request.json.tmp` и делает `renameSync`; раннер копирует ответ в `j4-paid.json.tmp` (ошибку пишет туда же) и делает `os.replace` — обе стороны видят только целый файл.
- Проверки: `py_compile` OK; `tools/tests` 25/26 (только #163); ESLint и `typecheck:e2e` спека OK. Далее прогон `E2E_GROUPS=b`.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| J4-T01 | PASS | 12.10.2026 | PHPUnit `OrderEntitlementsTest::testEachPrintedFrameGivesOneCopyWhateverTheFormatsAndCount` |
| J4-T02 | PASS | 12.10.2026 | PHPUnit `OrderEntitlementsTest` (печать + digital + подарок) |
| J4-T03 | PASS | 12.10.2026 | PHPUnit `FileAccessTest` (объединение по `photoId`, в составе 1110 OK) |
| J4-T04 | PASS | 12.10.2026 | PHPUnit `FileAccessTest::testStateFollowsPaymentLatenessAndDeadline` (состояния не зависят от вида строки) |
| J4-T05 | PASS | 12.10.2026 | `npm run test:commerce` → `print-copy.test.mjs` |
| J4-T06 | PASS | 12.10.2026 | `make test-e2e` → `zzzzzzzzzz-files` J4, `pay-fixture.php`, `verify-files.php` (`rabit-e2e-1ef3dd028ca9`, `rabit-e2e-072725b5135d`) |
| J4-T07 | PASS | 12.10.2026 | тот же сценарий: `unpaid` и 409 `FILES_UNAVAILABLE` до оплаты |
| J4-T08 | PASS | 12.10.2026 | `docs/waves/j4/visual.json`, 4 скриншота (`rabit-e2e-072725b5135d`) |
| J4-T09 | PASS | 12.10.2026 | PHPUnit legal 21 OK; гейт: legal.spec и заказы с текущей редакцией (`rabit-e2e-1ef3dd028ca9`) |
| J4-T10 | PASS | 12.10.2026 | J4 `review` (PR #184), E8 `merged`; `python3 tools/verify-wave-graph.py docs/waves/graph.json` exit 0 |
