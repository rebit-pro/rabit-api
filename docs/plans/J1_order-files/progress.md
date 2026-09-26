# J1 — журнал

## Точка продолжения

- Ветка `codex/j1-order-files`, base `main` `4fc9dce`, head — без коммитов. PR ещё не открыт.
- Рабочая копия `/home/user/rabit-api-worktrees/j1-order-files`.
- Документы: `plan.md` этой папки, граф `docs/waves/graph.json` (J1), канонический `MoreFoto/docs/05-rest-api/endpoints.json` (FIL-01…04), решения `docs/waves/w05/decisions.md` (D07, D10, D12).
- Завершено: S0–S4 и unit-часть S6 — графы, контракты Share, реализации Commerce/Media, модуль `morefoto.files` (FIL-01…04, сборка ZIP, dispatch/purge), docker/nginx/cron, E2E-стенд.
- Сейчас: S5 — frontend, блок файлов на странице заказа.
- Следующий шаг: E2E-спецификация и verifier (S7), документы волны (S8), PR.
- Блокеры: нет. Открытых решений нет.
- Рабочее дерево: всё закоммичено в `codex/j1-order-files`.
- Следующая проверка: быстрые frontend-проверки (`npm run check`, `test:commerce`) в Playwright-образе, см. память local-check-commands.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
| --- | --- | --- | --- | --- |
| J1-T01 | PASS (граф) | 2026-09-26 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`; канонический `wave_graph.py backend-waves.json`, `render-waves.py` | 52 волны, 118 ID, 35 WNN, 13 отрицательных фикстур; `readyFromMain` = [J1]. Patch канонического плана — на S8 |
| J1-T02 | PASS | 2026-09-26 | PHPUnit `OrderEntitlementsTest` | 31.01→28.02, високосный год, граница суток по Москве, декабрь→январь; продление ключа до `2027-02-28 07:00 UTC` |
| J1-T03, T04 | PASS (unit) | 2026-09-26 | PHPUnit `FileAccessTest` | unpaid/pending/review/available/граница/expired/empty; комплект без дублей, код digital сохраняется. HTTP — в E2E |
| J1-T05…T08, T11 | PASS (unit) | 2026-09-26 | PHPUnit `DownloadFlowTest`, `FilesAdaptersTest` | идемпотентность, одна сборка на заказ, переиспользование, настоящий ZIP (CM_STORE, байты совпадают), дубль сообщения, 3 попытки → failed без остатков, сбой брокера → dispatch, токен/ключ/срок/состав, purge |
| J1-T10 | PASS | 2026-09-26 | PHPUnit `FilesArchitectureTest` | контроллер, границы Commerce/Media, phpDoc |
| J1-T09 | PASS (unit) | 2026-09-26 | PHPUnit `OrderPaymentsTest` | paid продлевает ключ, pending — нет; миграция — в E2E verifier |
| J1-T12…T15 | PENDING | — | — | — |

## Хронология

- 2026-09-26. Пользователь проверил тестовую оплату G1 на stage и попросил следующую волну для выдачи электронных товаров. По графу J1 заблокирована цепочкой G2 → I1 → I2 → I3. Пользователь выбрал ранний срез J1 с зависимостями D1/E5/G1 (J1-GRAPH), затем — «Взять в реализацию». Проверено: веток и PR по J1 нет. Создан worktree от `origin/main` `4fc9dce`.
- 2026-09-26. Разведка:
  - Контракты FIL-01…04 есть только в каноническом `endpoints.json`, кодов ошибок FIL там нет.
  - Числа (лимит ZIP, срок архива, TTL ссылки) не заданы.
  - Модулей `morefoto.files` и `morefoto.settlement` нет. Контракта выдачи оригинала в Share нет.
  - Оригиналы лежат на локальной ФС (`MOREFOTO_PRIVATE_MEDIA_PATH`). В nginx и `api-cron` каталог не смонтирован.
  - Файлы отдаются только буферизованным PHP-ответом.
  - Событий об оплате нет, признак оплаты — `mf_order.PAYMENT_STATUS/PAID_AT/LATE_PAYMENT`.
  - `GIFTS` заказа — карта «ребёнок → bundle», подарок даётся без bundle-строки.
  - Ключ заказа действует 30 дней и может истечь раньше срока файлов.
- 2026-09-26. Пользователь принял J1-DEC-01…05 в рекомендованных вариантах: динамический состав комплекта, короткая ссылка + nginx X-Accel-Redirect, лимиты 2 ГиБ/500 файлов/24 ч/10 мин/1 сборка, фоновый consumer и продление ключа.
- 2026-09-26. S1: J1 зависит от D1/E5/G1, гейты D07/D10, `inProgress`; I2 зависит от J1 и получает обязательство отзыва `files=selected/all`, I3 — применение SET-07 к Files. K3 отмечена слитой (PR #89, `caa37b6`) в обоих графах. Канонический план правится на месте, снимок «до» лежит в scratchpad сессии (`mf-before`), patch соберётся на S8.
- 2026-09-26. Реализация backend. Ошибка, найденная тестом: при сбое сборки деструктор `ZipArchive` дописывал частичный архив — теперь `unchangeAll()` и `close()` в `finally`. PHPStan выявил конфликт свойства `$request` контроллера с Bitrix — переименовано.
- 2026-09-26. X-Accel-Redirect проверен на живом nginx 1.25 (python upstream): uid 1000 читает файл 0600, `Content-Disposition` и `Cache-Control` проходят от upstream, Range → 206, прямой `/_protected/` → 404. Решение по правам: в образах nginx пользователь `nginx` переназначен на 1000:1000 (как `www-data` в php-образах); архивы, собранные consumer от root, наследуют владельца корня хранилища.
- 2026-09-26. Проверки: `php.sh` (docker `rabit-api-php-cli:d1-local`, volume `rabit-j1-vendor`) — phplint OK 1347; PHPStan `tools/e2e/phpstan.neon` — No errors; PHPUnit — OK 1019 тестов / 46734 проверки; php-cs-fixer по изменённым файлам — исправлено 5, повтор 0. `tools/tests/test_run_browser_e2e.py` — 22 OK. `docker compose -f docker-compose-production.yml config` с подставными переменными — OK, 4 монтирования private-files.
