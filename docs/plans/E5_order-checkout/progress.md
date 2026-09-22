# E5 — прогресс

## Точка продолжения

- 2026-09-22. Ветка `codex/e5-order-checkout` от `main` `8cba22c7655b5886d5fe663523214bcd059e674b`, upstream не задан, push не выполнялся. PR и issue нет. Документация: `plan.md`, `docs/waves/graph.json`, `docs/waves/e5/morefoto-contract.patch`.
- Завершено: решения E5-DEC-01…05 приняты; канонический контракт COM-08/10–13 уточнён; backend E5 (миграция, домен, Application, Infrastructure, Presentation, DI, маршруты), право `order.read`, контракт Media `ChildPhotosInterface`, `createdJson()` и `error.details` в `rebit.share`; unit/архитектурные тесты, PHPStan, php-cs-fixer.
- Сейчас: E2E-обвязка (миграция в `prepare.php`, фикстура заказов, верификатор MySQL) и frontend.
- Следующий шаг: добавить `Version20260922120001` в `api/tools/e2e/prepare.php`, флаг `MOREFOTO_CHECKOUT_ENABLED=1` в E2E runner и написать `api/tools/e2e/verify-orders.php`.
- Блокеры: нет. Вне E5: коллизия имени D3 ждёт решения пользователя.
- Рабочее дерево: backend и документы E5 закоммичены локально; frontend/E2E ещё не менялись.
- Следующие проверки: `docker run … rabit-api-php-cli:d1-local vendor/bin/phpunit`, PHPStan, затем полный `make test-e2e …` (команда в плане E4/E5).

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

## Результаты тест-кейсов

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| E5-BASE | PASS | 2026-09-22 | `gh pr view 30`: MERGED `7e606e5`; `git merge-base --is-ancestor` — предок HEAD `8cba22c` |
| E5-GRAPH | PASS | 2026-09-22 | `python3 tools/verify-wave-graph.py` для обоих графов: 40 волн, 99 API, `readyFromMain=["E5"]` |
| E5-CONTRACT | PASS | 2026-09-22 | `build.py`, `validate.py` (passed, 99 API), `validate-postman.cjs` (99/198) |
| E5-CREATE | PENDING | — | Код не написан |
| E5-REPLAY | PENDING | — | Код не написан |
| E5-CONCURRENCY | PENDING | — | Код не написан |
| E5-CONFLICT | PENDING | — | Код не написан |
| E5-QUOTE | PENDING | — | Код не написан |
| E5-CLOSED | PENDING | — | Код не написан |
| E5-GATE | PENDING | — | Код не написан |
| E5-VALIDATION | PENDING | — | Код не написан |
| E5-SNAPSHOT | PENDING | — | Код не написан |
| E5-KEY | PENDING | — | Код не написан |
| E5-STAFF-LIST | PENDING | — | Код не написан |
| E5-STAFF-CARD | PENDING | — | Код не написан |
| E5-ACCESS-CHANGE | PENDING | — | Код не написан |
| E5-PRIVACY | PENDING | — | Код не написан |
| E5-MIGRATION | PENDING | — | Код не написан |
| E5-PERF | PENDING | — | Код не написан |
| E5-ARCH | PASS | 2026-09-22 | PHPUnit 472/1874 (включая `OrderArchitectureTest`), PHPStan No errors, php-cs-fixer по изменённым файлам |
| E5-UI | PENDING | — | Код не написан |
| E5-REGRESSION | PENDING | — | Код не написан |
| E5-PUBLISH | PENDING | — | Код не написан |
