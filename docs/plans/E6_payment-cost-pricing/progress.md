# E6 — прогресс

## Точка продолжения

- Ветка `codex/e6-payment-cost-pricing` от main `49f40f97fb5ee8b16425fa3a6b2e2b3da0c2b771` (merge плана PR #35). Checkout: `/home/user/rabit-api-worktrees/e6-payment-cost-pricing`. Upstream снят, push только явным `git push -u origin HEAD:codex/e6-payment-cost-pricing`.
- Завершено: S1–S9 — план, backend, frontend, E2E-спецификация и verifier, канон MoreFoto (patch `docs/waves/e6/morefoto-contract.patch`), отчёт `docs/waves/e6/`. E6 в обоих графах — `review`.
- Сейчас: S10 — PR в main с быстрыми проверками.
- Следующий шаг: ревью PR. После ревью без блокеров — S11: `make test-e2e` (группа b содержит `zzzzzzzz-payment-costs`, verifier `verify-payment-costs.php`), `visual.json`, запись результатов.
- Блокеров нет. Решения E6-DEC-01…03 приняты 25.09.2026.
- Канонический MoreFoto изменён на месте; снимок до E6 — в scratchpad сессии (`morefoto-before-e6`), воспроизводимость patch проверена.
- Проверки backend (том vendor `rabit-e6-vendor` — копия `rabit-u-vendor`; в worktree пустой `api/vendor` как точка монтирования):
  `docker run --rm --network none --entrypoint php --mount type=bind,source=$PWD/api,target=/app,readonly --mount type=volume,source=rabit-e6-vendor,target=/app/vendor,readonly --tmpfs /app/var:rw,size=512m --workdir /app rabit-api-php-cli:d3-webp -d xdebug.mode=off vendor/bin/phpunit --colors=never` (PHPStan — `vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` с `-d memory_limit=2G`).
- Проверки frontend (том `rabit-e6-node` — копия `rabit-u-node`, lockfile не менялся): `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e6-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce && npm run build-only'`.

## Хронология

### 2026-09-25 — старт

- Пользователь выбрал E6 как единственную волну, свободную от ИП. PR #35 (план ЮKassa и E6) обновлён на актуальный main и слит по решению пользователя: `49f40f9`.
- Ветка создана от `49f40f9`. `python3 tools/verify-wave-graph.py docs/waves/graph.json` на main: 51 волна, 110 API ID, 35 WNN, readyFromMain E6, U1, U5 (U1/U5 — формально: пакет design-ux слит PR #53, но в графе ещё `inProgress`).
- Учёт merge пакета design-ux (U1–U8, B3, B4): `deliveryState=merged`, `mergedWaves`, `mergedPullRequests` → PR #53, `mergeCommits` → `b20423f08c5794f66346a607c376bc89ffe3bb82`, baseline → `49f40f9`, дата 2026-09-25. E6 → `inProgress`. Те же изменения внесены в канонический `backend-waves.json`; `render-waves.py` перерисовал Markdown (перед этим проверено на копии, что генерация идемпотентна).
- Валидатор (`tools/verify-wave-graph.py` и канонический `wave_graph.py`, файлы идентичны) падал на полностью слитом пакете: негативная фикстура «bundle depends on unmerged wave outside the bundle» принималась. Добавлено правило «слитая волна зависит только от слитых». Фикстура «bundle partially merged» теперь переключает состояние первого участника в обе стороны.
- Разведка кода: цена продажи должна считаться в одном месте (use case чтения условий), потому что одно поле `price` читают витрина, `SalesProduct`, строка расчёта (→ `PRODUCT_PRICE` заказа) и F2. Контроллер условий старого образца; валидация во входных DTO; `lockOrganizer` требует токен.
- Решения пользователя 25.09.2026: предел цены 1 000 000 ₽ (E6-DEC-02), чистка контроллера в E6 (E6-DEC-03). Ставку сначала ограничили 99,99%, но в крайнем случае (товар 1 000 000 ₽ при 99,99%) цена для покупателя достигала бы 10 млрд ₽ (множитель ×10 000). Пользователь спросил, откуда миллиарды; после объяснения таблицей множителей выбрал предел 10% (E6-DEC-01). Итог: цена продажи ≤ 1 111 150 ₽, прежние INT-пределы достаточны, отдельные лимиты сумм не нужны.

## Проверки

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| E6-T16 (граф, старт) | PASS | 2026-09-25 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 51/110/35, 13 негативных фикстур, readyFromMain [E6]; канон `python3 docs/04-bitrix-modules/wave_graph.py docs/04-bitrix-modules/backend-waves.json` — тот же результат; старый граф main с новым валидатором — PASS (readyFromMain E6, U1, U5) |
| E6-T01…T15, T17, T18 | PENDING | 2026-09-25 | Реализация не начата |

### 2026-09-25 — backend S2–S6

- Домен: VO `PaymentCostPolicy` (0…1000 bps, шаг 5000, формула на целых), `ProductDetails::MAX_PRICE` = 100 000 000, VO `ConditionProduct` (ID и цена товара условий). Репозиторий условий больше не импортирует Application DTO.
- Миграция `Version20260925120001`: политика в `mf_sales_conditions` (по умолчанию выключена, 380), CHECK ставки и предела цены на `b_hlbd_mf_product`/`mf_group_product_condition`; до изменения проверяет данные; `down()` отказывает при включённой политике. Добавлена в список `api/tools/e2e/prepare.php`. DDL проверен на одноразовом `mysql:8.0` (tmpfs, без сети): значения по умолчанию, 4 нарушения CHECK отклонены, допустимое обновление прошло, откат удалил столбцы и ограничения.
- Условия: входные DTO без проверок (`ProductConditionInputDto`, `SaveConditionsInputDto`, новый `PaymentCostsInputDto`); проверки — `ConditionsInputValidator` и `ConditionsProducts`; `PublishedPrices` считает цену для покупателя один раз для ответов, витрины и расчёта; хеш идемпотентности групп сохранил прежнюю форму.
- `AuthorizedConditions` → `ManageConditionsUseCase` с переводом предметных исключений в коды API. Контроллер на `AuthenticatedApiJsonController` и четырёх RequestDto; удалены `ConditionsRequestFactory`, `ConditionsRequestDto`, `ConditionsResponseMapper`. Токен для `lockOrganizer` — из заголовка `Authorization` через RequestDto (A5).
- Отличия ошибок общей обвязки (A7 плана): неверный Content-Type или query у PUT — 400 `JSON_REQUIRED` (было 422), тело больше 262 144 байт — 413, неизвестные/отсутствующие поля — 422 `UNKNOWN_FIELD`.

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| E6-T01, E6-T02 | PASS | 2026-09-25 | PHPUnit `PaymentCostPolicyTest`: примеры, 1 000 000 ₽ при 10% → 1 111 150 ₽, >10 000 граничных случаев (кратность, компенсация, минимальность), отказ ставки −1/1001 и цены выше предела |
| E6-T03 (unit) | PASS | 2026-09-25 | `CatalogTest` (предел `ProductDetails`), `ConditionsContractTest` (предел в условиях); live — PENDING |
| E6-T05, E6-T07 (unit) | PASS | 2026-09-25 | `ConditionsContractTest`: строгий JSON (типы, лишние/отсутствующие поля, `paymentCosts` в теле группы — `UNKNOWN_FIELD`), mapper, хеш; `ManageConditionsUseCaseTest`: повтор с другой ставкой — 409 `IDEMPOTENCY_CONFLICT`; live — PENDING |
| E6-T09 (unit) | PASS | 2026-09-25 | `StorefrontQuoteTest`: 550 ₽ со скидкой → 275 ₽, порог подарка по ценам продажи, отпечаток меняется при смене ставки без смены цен |
| E6-T12 (unit) | PASS | 2026-09-25 | `ConditionsControllerArchitectureTest` (`CleanControllerSource` — без нарушений); `ManageConditionsUseCaseTest`: 10 предметных отказов → коды API, отказ 403 не читает повтор и не проверяет тело |
| E6-T15 (DDL) | PASS | 2026-09-25 | Одноразовый `mysql:8.0`: `ALTER` миграции, 4 нарушения CHECK, откат; полный verifier на стенде — PENDING |
| E6-T17 (backend) | PASS | 2026-09-25 | PHPUnit — OK, 693 теста / 44 131 проверка; PHPStan — No errors; `php -l` по 41 изменённому файлу — без ошибок; php-cs-fixer — 1 файл исправлен (порядок типов в catch), повторный dry-run чист |

### 2026-09-25 — frontend, E2E и канон

- Frontend: `conditions/payment-costs.ts` (формула как на сервере, ввод ставки в процентах), `conditions/conditions-command.ts` (создание команды, проверки, тело запроса — вынесены из composable для unit-тестов), блок «Расходы на оплату» и «Для покупателя» в `ConditionsFields.vue`, строка в сводке, подсказка в редакторе группы, предел 1 000 000 ₽ в редакторах каталога и условий, ключ черновика `v2`. Демо-режим политику не показывает (поле команды необязательно).
- E2E: `zzzzzzzz-payment-costs.spec.ts` в конце группы b — политика по умолчанию, идемпотентный повтор и конфликт, отказы контракта (ставка 1001, без политики, политика в теле группы), витрина, `PRICE_CHANGED` старого расчёта, заказ по новому, возврат цен после выключения, неизменный снимок заказа, подпись ссылки F2, 403 для куратора/руководителя/воспитателя, 401 без токена, UI desktop/mobile. `afterAll` выключает политику. В `conditions.spec.ts` тело общих условий и отказ воспитателю дополнены политикой. Verifier `verify-payment-costs.php` добавлен в `VERIFIERS`.
- Канон MoreFoto: COM-02/03 (предел цены), COM-04/05/06/07/08 в `build.py`, пересборка README/реестра/Postman; решения E6-DEC-01…03 в `decisions.md` и `decisionEvidence` обоих графов; E6 → `review`.

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| E6-T13 | PASS | 2026-09-25 | `npm run test:commerce` — 188/188 (`payment-costs.test.mjs`: примеры сервера, диапазон, ввод ставки, тело общих условий и группы, пределы) |
| E6-T16 | PASS | 2026-09-25 | `tools/verify-wave-graph.py` и канон: 51/110/35, 13 негативных фикстур, E6 `review`; `render-waves.py` дважды — одинаковый SHA; `build.py` + `validate.py` — passed, 110 запросов, 51 волна; `validate-postman.cjs` — 110/220 фикстур; patch на снимке до E6 воспроизводит канон |
| E6-T17 (frontend) | PASS | 2026-09-25 | `npm run check` (lint, stylelint, vue-tsc, tsc e2e, UI 27) — PASS; `npm run build-only` — PASS; eslint и tsc e2e для новой спецификации — PASS |
| E6-T04…T11, T14, T15, T18 | PENDING | 2026-09-25 | Живые сценарии, verifier и снимки — в `make test-e2e` после ревью |
