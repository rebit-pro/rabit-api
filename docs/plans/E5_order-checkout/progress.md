# E5 — прогресс

## Точка продолжения

- 2026-09-22. Статическое ревью PR [#37](https://github.com/rebit-pro/rabit-api/pull/37), ветка codex/e5-order-checkout.
- Base: 8cba22c7655b5886d5fe663523214bcd059e674b; проверяемый HEAD: 46c442e6b2817a3e9ca2c2413b1c197190c9e61a.
- Завершено: сверены PR, инструкции, согласованные решения, состояние ветки и открытые issues.
- Сейчас: ревью завершено; note https://github.com/rebit-pro/rabit-api/pull/37#issuecomment-5774904959 опубликован, неблокирующие issues #38/#39 созданы и повторно прочитаны через gh.
- Следующий шаг: разработчику исправить B1–B3 из note; затем повторное ревью и отдельно согласованный финальный gate.
- Блокеры кода: потеря ключа повтора при HTTP 5xx; недоступность восстановления после закрытия группы/сбоя пересчёта; PRICE_CHANGED вместо QUOTE_STALE при изменении количества (противоречит обязательному верификатору); тесты и E2E по прямому указанию пользователя не запускаются. Прежний финальный gate остаётся отложенным.
- Рабочее дерево до ревью чистое; результаты ревью сохраняются отдельным локальным документационным коммитом plan.md и progress.md поверх проверенного HEAD. Push этого коммита не выполняется; удалённый PR остаётся на проверенном 46c442e. Продуктовый код не менялся.
- Следующие команды: /home/user/.local/bin/gh pr view 37 --json headRefOid,baseRefOid,comments; git status --short; git log -1 --oneline. После исправления — статическое чтение затронутых сценариев B1–B3. GitHub — только /home/user/.local/bin/gh.

## Хронология

### 2026-09-22 — готовность и старт

- `gh pr list`: E4 слита PR #30 (2026-09-21T15:16:20Z). `gh pr view 30 --json state,mergeCommit` — MERGED, `7e606e53cc6b347e5c7b70e217ab7f8eb8a45875`; `git merge-base --is-ancestor` к HEAD — PASS.
- Решения E5 (D02, D04, D05, D07, D10) входят в `acceptedImplementationDecisions`. Моделирование графа: сейчас готова только E5; после её merge зависимости закрыты лишь у D3, но D3 блокируют открытые D11/D12.
- Пользователь поручил начать E5. `git fetch origin --prune`; `git switch -c codex/e5-order-checkout origin/main`; `git branch --unset-upstream`, чтобы случайный push не ушёл в `main`.

### 2026-09-22 — исследование

- Прочитаны граф и канон E5, COM-08…13, правила REST (идемпотентность, ошибки, `X-Order-Key`, негативная матрица), W05 D07/D08/D10, модель и схема W05, E4 plan/README, frontend-релизы R04/R11.
- Код commerce E4: `ValidateQuoteUseCase::executeWithinTransaction` предназначен для E5; `StorefrontQuote` требует `open`; quote хранит только SHA-256 токена; `QuoteTransaction` — SERIALIZABLE без повтора; COM-08 жёстко отдаёт `purchaseEnabled=false`, `receiptChannels=[]`, `purchaseTerms=null`.
- Межмодульные контракты: права на заказы в Access нет (нужно `order.read`); контракта кадров ребёнка для `correctionPhotos` нет; служебные превью доступны только организатору; названия и ID учреждения доступны цепочкой `GalleryAccess` → `GroupReference` → `MediaScope`.
- Инфраструктура: общий компонент идемпотентности отсутствует, все журналы привязаны к сотруднику; причина #27 — replay до блокировки без повторной проверки; эталон резервирования — H1. У `PrivateApiJsonController` нет `createdJson()`; единый ответ ошибки не поддерживает `details`; отказы Access приходят с кодом `SERVICE_UNAVAILABLE`, их нужно переводить.
- Frontend: оформление, заказ по ключу и служебные заказы работают только в demo; live-корзина отбрасывает `quoteToken`; в live-навигации нет «Заказов»; `/orders/access/` пишется в access log фронтового nginx.
- Результат: план с пятью решениями на подтверждение.

### 2026-09-22 — синхронизация графа

- `docs/waves/graph.json`: дата 2026-09-22, baseline `8cba22c`, E4 в `mergedWaves`/PR #30/merge `7e606e5`, E4 merged, E5 inProgress.
- Канон `../MoreFoto/docs/04-bitrix-modules/backend-waves.json`: E5 inProgress, дата; `python3 render-waves.py` — «Rendered 40 independent waves». Diff обоих файлов — `docs/waves/e5/morefoto-contract.patch`.
- `python3 tools/verify-wave-graph.py docs/waves/graph.json` и тот же скрипт для канона — exit 0: 40 волн, 99 API, `readyFromMain=["E5"]`, 10 негативных фикстур.
- Сравнение графов: baseline совпадает, кроме D09-evidence; отличаются E5.scope, G1, G2, I2, N1 и `paymentIntegration`. Это правки неслитых PR #35/#36, в E5 не переносятся.

### 2026-09-22 — решения приняты, контракт и backend

- Пользователь подтвердил E5-DEC-01…05 без изменений и разрешил до 6 тыс. рукописных строк одной волной.
- Канон MoreFoto: `build.py` — уточнены COM-08, COM-10…13 (ключ повтора, коды ошибок, `PRICE_CHANGED` с details, `accessKeyExpiresAt`/`createdAt`, `period`, `StaffOrder`, фильтры, `correctionPhotos`, `purchaseEnabled`). `python3 docs/05-rest-api/build.py` — PASS; `python3 docs/05-rest-api/validate.py` — `result: passed`, 99 запросов, 40 волн; `node docs/05-rest-api/validate-postman.cjs` из корня MoreFoto — PASS (99/198 фикстур, 43 проверки повтора). Diff внешних файлов — `docs/waves/e5/morefoto-contract.patch`.
- Базовый прогон до изменений: `docker run --rm --network none --entrypoint php --mount type=bind,source=/home/user/rabit-api/api,target=/app,readonly --tmpfs /app/var:rw,size=256m --workdir /app rabit-api-php-cli:d1-local vendor/bin/phpunit --colors=never` — OK, 420 тестов / 1608 проверок.
- Миграция `Version20260922120001`: `mf_order`, `mf_order_line`, `mf_order_access_key` (генерируемый `ACTIVE_ORDER_ID` с UNIQUE — один действующий ключ), `mf_order_checkout`. DDL дважды применён на одноразовом `mysql:8.0` (tmpfs, без сети) — PASS; проверены отказ второго действующего ключа, неверной суммы, повторного quote, комплекта с кадром, незавершённого чека — PASS.
- Backend: `CreateOrderUseCase` (флаг → capability → резерв ключа повтора → повтор → покупатель → `ValidateQuoteUseCase` → снимок/номер/ключ → чек), `GetBuyerOrderUseCase`, `SearchStaffOrdersUseCase` (3 SQL на страницу), `GetStaffOrderUseCase`; `ValidateQuoteUseCase` различает `PRICE_CHANGED` и `QUOTE_STALE`; копия ключа — AES-256-GCM с ключом HKDF из Idempotency-Key.
- Проверки: PHPUnit — OK, 472 теста / 1874 проверки; PHPStan — No errors; php-cs-fixer применён к 7 из 101 изменённых PHP-файлов, повторный dry-run чист.

### 2026-09-22 — frontend и E2E-обвязка

- Frontend: `quoteToken` в live-корзине, типы `capabilities`/`purchaseTerms`, кнопка оформления по `purchaseEnabled`; `useLiveCheckout` (черновик `morefoto:live:checkout:*`, повтор неизвестного исхода тем же ключом и телом, пересчёт при `PRICE_CHANGED`/`QUOTE_*`); `OrderLiveScreen`; `StaffOrdersLiveScreen` с серверными фильтрами; `Checkout`/`Order` без `demoOnly`, `Payment` остаётся демо; навигация «Заказы» по `order.read`; nginx не пишет `/orders/access/` в access log.
- `CreateOrderUseCase`: новый заказ в закрытой группе отклоняется сразу после проверки повтора (`GALLERY_CLOSED`), unit-тест добавлен — PHPUnit OK.
- Frontend в `mcr.microsoft.com/playwright:v1.52.0-jammy` с томом `rabit-e5-node`: `npm ci` — PASS; `npm run check` (lint, vue-tsc, tsc e2e) — PASS после `eslint --fix` форматирования новых файлов; `npm run test:commerce` — 164/164 PASS.
- E2E: миграция `20260922120001` в `prepare.php`; FPM стенда получает `MOREFOTO_CHECKOUT_ENABLED=1`; после браузера runner копирует `frontend/var/e5-orders.json` и выполняет `verify-orders.php` (секреты, инварианты, жизненный цикл ключа, повтор после закрытия, истёкший quote, `ACCESS_CHANGED`, повтор миграции, 1000+ заказов). Верификатор E4 адаптирован к `ValidatedQuoteOutputDto` и `PRICE_CHANGED`. Ожидания A8/E4 обновлены: оформление включено на стенде, недоступна только оплата.

### 2026-09-22 — первый полный gate

- `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor` (стенд `rabit-e2e-de449cb52b9b`) — FAIL на браузере: 67 passed, 2 failed. Frontend check/unit, PHP lint/PHPStan/PHPUnit, установка схемы и Notification — PASS. Все API-сценарии E5 (повтор, конкуренция 4×, гонка трёх ключей на один quote, отказы, `PRICE_CHANGED`, ключ заказа, область организатора/кураторов/head/teacher), потерянный ответ и изменение цены в UI — PASS.
- Причина двух падений (`buyer checkout and staff orders` desktop/mobile) — локатор теста: `getByLabel` находил и поле поиска, и иконку очистки. Исправлено на `getByRole('textbox', { exact: true })`. Пост-браузерные верификаторы в этом прогоне не выполнялись.
- Просмотр сохранённых снимков оформления и заказа desktop/mobile: вёрстка без переполнения; найдено «поможет куратор .» при пустом имени куратора в live-галерее — `CheckoutTerms` теперь показывает «куратор учреждения», если имени нет. `npm run check` — PASS.

### 2026-09-22 — второй прогон и порядок финального gate

- Второй `make test-e2e` (стенд `rabit-e2e-c14d949a108e`) — FAIL: 67 passed, 2 failed. Тест служебного экрана не смог заполнить поиск: у поля не было явного `aria-label`, точное имя textbox не совпало. Добавлены `aria-label` фильтрам (доступность), повтор не выполнялся.
- Просмотр снимков второго прогона: «куратор учреждения» вместо «куратор .» — PASS; на mobile-снимке оформления кнопка снята во время серверного пересчёта (выключена) — пересчёт теперь показывается индикатором загрузки на кнопке.
- Пользователь: «Давай пока не будем делать финальные прогоны…», затем «Сейчас задачу вернется с ревью. Если не будет блокирующих, то тогда делаем финальный прогон». Поднятый для итераций `make e2e-up` (`rabit-e2e-7d9e899e9205`) остановлен до создания контейнеров; `run-browser-e2e.py down` — `stopped: true`, ошибок нет.
- Верификатор: замер N+1 переделан на сравнение числа SQL для страниц 10 и 100 строк (не более 15 запросов), php-cs-fixer применён.
- Быстрые проверки на итоговом состоянии: php-cs-fixer по 104 изменённым PHP (исправлен 1 — `verify-orders.php`), PHPStan — No errors, PHPUnit — OK 473/1877, phplint — 726 файлов OK; frontend `npm run check` — PASS, `npm run test:commerce` — 164/164, `npm run build` — PASS.
- Граф: E5 `review` в `docs/waves/graph.json` и каноне MoreFoto, `render-waves.py` — PASS, `verify-wave-graph.py` для обоих — 40 волн, 99 API, `readyFromMain=["E5"]`; patch канона обновлён.

### 2026-09-22 — публикация на ревью

- `git fetch origin --prune`: `origin/main` = `8cba22c`, `git merge-base --is-ancestor origin/main HEAD` — PASS. Коммиты `0c4a852` (backend) и `9b52c68` (frontend, E2E, документы).
- `git push -u origin codex/e5-order-checkout` — PASS; `gh pr create --base main` — https://github.com/rebit-pro/rabit-api/pull/37. В описании: ответственность, решения, зависимости, быстрые проверки, результат браузерного прогона и условие merge — финальный gate после ревью.

## Результаты тест-кейсов

`PASS*` — подтверждено unit и HTTP в прогоне `rabit-e2e-c14d949a108e`; повтор в финальном gate после ревью.

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| E5-BASE | PASS | 2026-09-22 | `gh pr view 30`: MERGED `7e606e5`; `git merge-base --is-ancestor` — предок HEAD `8cba22c` |
| E5-GRAPH | PASS | 2026-09-22 | `python3 tools/verify-wave-graph.py` для обоих графов: 40 волн, 99 API, `readyFromMain=["E5"]` |
| E5-CONTRACT | PASS | 2026-09-22 | `build.py`, `validate.py` (passed, 99 API), `validate-postman.cjs` (99/198) |
| E5-CREATE | PASS* | 2026-09-22 | PHPUnit; HTTP в прогоне `rabit-e2e-c14d949a108e` (201, `Location`, номер, ключ, статусы, снимок). *Финальный gate — после ревью |
| E5-REPLAY | PASS* | 2026-09-22 | HTTP повтор тем же ключом — тот же id/номер/ключ; UI потерянного ответа — повтор тем же ключом; повтор после закрытия — в `verify-orders.php` (PENDING) |
| E5-CONCURRENCY | PASS* | 2026-09-22 | HTTP: 4 одновременных запроса с одним ключом → один заказ; 3 ключа на один quote → 201 + 2×409 `QUOTE_ALREADY_USED` |
| E5-CONFLICT | PASS* | 2026-09-22 | PHPUnit и HTTP: другое тело с тем же ключом → 409 `IDEMPOTENCY_CONFLICT` |
| E5-QUOTE | PASS* | 2026-09-22 | PHPUnit; HTTP `PRICE_CHANGED` с новым расчётом и UI подтверждения нового итога; `QUOTE_EXPIRED` — в `verify-orders.php` (PENDING) |
| E5-CLOSED | PASS* | 2026-09-22 | HTTP: closed → 409 `GALLERY_CLOSED`, preparing → 409 `GALLERY_NOT_READY`, revoked → 404 |
| E5-GATE | PASS | 2026-09-22 | PHPUnit (`PURCHASE_DISABLED` до любых обращений); HTTP стенда: `purchaseEnabled=true`, `receiptChannels=[]`, `purchaseTerms=null` |
| E5-VALIDATION | PASS* | 2026-09-22 | PHPUnit `BuyerPolicy`; HTTP 422 по полям, `INVALID_IDEMPOTENCY_KEY`, `UNKNOWN_FIELD` |
| E5-SNAPSHOT | PASS* | 2026-09-22 | HTTP: после смены цены COM-11 показывает прежние цену и итог |
| E5-KEY | PASS* | 2026-09-22 | HTTP: проекция, `no-store`, единый 404 для пустого/чужого ключа, номера MF и токена галереи; отзыв/истечение/перевыпуск — `verify-orders.php` (PENDING) |
| E5-STAFF-LIST | PASS* | 2026-09-22 | HTTP: организатор, свой и чужой куратор, head/teacher 403, фильтры и 422; UI поиска — после исправления локатора (PENDING) |
| E5-STAFF-CARD | PASS* | 2026-09-22 | HTTP: `period`, `correctionPhotos` с A001, 404 для чужого куратора, 403 head/teacher |
| E5-ACCESS-CHANGE | PENDING | — | PHPUnit адаптера PASS; проверка на MySQL в `verify-orders.php` — финальный gate |
| E5-PRIVACY | PENDING | — | HTTP: ответы без ключей/токенов, `no-store` — PASS; сканирование БД — `verify-orders.php` (финальный gate) |
| E5-MIGRATION | PASS* | 2026-09-22 | Установка на пустую БД в обоих прогонах, DDL дважды на MySQL 8.0; повтор `up()`/отказ `down()` — `verify-orders.php` (PENDING) |
| E5-PERF | PENDING | — | `verify-orders.php`: 1000+ заказов, одинаковое число SQL для 10 и 100 строк — финальный gate |
| E5-ARCH | PASS | 2026-09-22 | PHPUnit 472/1874 (включая `OrderArchitectureTest`), PHPStan No errors, php-cs-fixer по изменённым файлам |
| E5-UI | PENDING | — | Оформление и заказ по ключу desktop/mobile — PASS в прогоне; служебные экраны — финальный gate |
| E5-REGRESSION | PENDING | — | 67/69 в последнем прогоне; повтор полного набора — финальный gate |
| E5-PUBLISH | PASS | 2026-09-22 | `git diff --check` PASS; `origin/main` = base `8cba22c`; `git push -u origin codex/e5-order-checkout`; `gh pr create` → PR #37. Merge — после ревью и финального gate |


### 2026-09-22 — начало статического ревью PR #37

- Запрос пользователя: только код, без прогонов/E2E; блокирующие находки — note в PR через gh, неблокирующие — отдельные issues.
- E5-RV-01 PASS: git status --short пустой; gh pr view 37 --json baseRefOid,headRefOid — base 8cba22c, head 46c442e; локальный HEAD совпадает. Diff: 145 файлов, +6514/-114.
- E5-RV-02 PENDING; E5-RV-03 PENDING; E5-RV-04 PENDING.
- Открытые issues #23/#24/#26/#27/#28/#31/#33/#34 прочитаны по заголовкам для исключения дублирования.
- Обычный sandbox не инициализируется (helper_unknown_error); чтение и gh доступны через разрешённый запуск exec вне sandbox.


### 2026-09-22 — статическая проверка и перепроверка находок

- Прочитаны оформление/повтор/ключи, SQL-репозитории и миграция, DI и HTTP DTO/controller/mapper, Access/Media, frontend composables/screens, маршруты, nginx, тесты и runner. Никакие PHPUnit/frontend/E2E/SQL-прогоны не запускались.
- E5-RV-02 FAIL (статическое ревью): B1 — checkoutOutcome считает HTTP 502/504 окончательным отказом, useLiveCheckout удаляет pending и меняет requestId; подтверждённый на сервере заказ становится недоступен для replay. B2 — pending не участвует в canSubmit/render: после закрытия группы и перезагрузки loadStorefront не получает quote, CheckoutForm скрывает форму, хотя сервер разрешает replay. B3 — money() включает quantity/id; смена количества при том же quote бросает QuotePriceChangedException, а verify-storefront.php:79 ожидает QUOTE_STALE; runner запускает его до verify-orders.php.
- Неблокирующие: N1 — clearable VTextField присваивает null через Vuetify validation.reset(), useStaffOrders.apply вызывает filters.q.trim(); обход — «Сбросить». N2 — institutionId/shootId/groupId отсутствуют в StaffOrderFilters, URL mapper и служебной форме, хотя backend COM-12 их поддерживает.
- E5-RV-03 PASS: решения E5-DEC-01…05 учтены; открытые issues #23/#24/#26/#27/#28/#31/#33/#34 не дублируют эти находки (#28 относится к F1). Миниатюры проверены до PhotoImage: используют авторизованный blob-запрос, замечания нет. Nginx задаёт no-referrer; недоказанная утечка не включается.
- Доказательства: чтение файлов с номерами строк; git diff origin/main...HEAD; gh issue list --state open --limit 100 --json number,title,url; установленный frontend/node_modules/vuetify/lib/composables/validation.js:122–125. E5-RV-04 PENDING.


### 2026-09-22 — публикация неблокирующих issues

- gh issue create --repo rebit-pro/rabit-api --title … --body-file /tmp/rabit-pr37-review-20260922/issue-1.md — PASS: #38 https://github.com/rebit-pro/rabit-api/issues/38 (clearable → null → trim).
- Та же команда с issue-2.md — PASS: #39 https://github.com/rebit-pro/rabit-api/issues/39 (фильтры учреждения/съёмки/группы).
- Перед созданием gh issue list --state all --limit 100 — дубликатов нет.
- Note с B1/B2/P1 и B3/P2 (блокирует обязательный верификатор) подготовлен в /tmp/rabit-pr37-review-20260922/review-note.md; следующим действием gh pr comment 37 --body-file …
- git diff --check — PASS (формат документации; это не тестовый прогон).


### 2026-09-22 — ревью завершено

- gh pr comment 37 --repo rebit-pro/rabit-api --body-file /tmp/rabit-pr37-review-20260922/review-note.md — PASS: https://github.com/rebit-pro/rabit-api/pull/37#issuecomment-5774904959.
- E5-RV-04 PASS: gh api repos/rebit-pro/rabit-api/issues/comments/5774904959; gh issue view 38/39 --json title,body,url,state — опубликованные тела совпадают с подготовленными текстами, issues OPEN.
- Повторный gh pr view 37 --json headRefOid,baseRefOid,state — PASS: PR OPEN, HEAD/base остались 46c442e/8cba22c. Ревью относится именно к ним.
- Итог: B1/B2 — P1, B3 — P2, блокирующий финальный gate; PR не рекомендован к merge до исправлений. Неблокирующий долг — #38 и #39.
- Тесты, E2E, контейнеры и приложение в этом ревью не запускались. git diff --check ранее PASS; прежние runtime-статусы выше оставлены как история реализации.
- Перед завершением: локальный документационный коммит только plan.md/progress.md, без push; удалённый продуктовый HEAD сохраняется. Merge/deployment не выполняются.
