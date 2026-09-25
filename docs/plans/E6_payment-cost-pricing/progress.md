# E6 — прогресс

## Точка продолжения

- Ветка `codex/e6-payment-cost-pricing` от main `49f40f97fb5ee8b16425fa3a6b2e2b3da0c2b771` (merge плана PR #35). Checkout: `/home/user/rabit-api-worktrees/e6-payment-cost-pricing`. PR ещё не создан. Upstream ветки снят, push только явным `git push -u origin HEAD:codex/e6-payment-cost-pricing`.
- Завершено: S1 — план, журнал, граф; S2–S6 — backend: домен, миграция, условия, витрина и расчёт, чистый контроллер, unit-тесты.
- Сейчас: S7 — frontend (API, редактор, блок «Расходы на оплату», предпросмотр, предел 1 000 000 ₽).
- Следующий шаг: типы `conditions/api.ts` и `useConditionsEditor.ts` с политикой и новой версией ключа черновика.
- Блокеров нет. Решения E6-DEC-01…03 приняты пользователем 25.09.2026.
- Канонический MoreFoto изменён на месте: `backend-waves.json`, `backend-waves.md`, `wave_graph.py`. Снимок до E6 — в scratchpad сессии (`morefoto-before-e6`); итоговый patch — `docs/waves/e6/morefoto-contract.patch` в S9.
- Backend-проверки (том vendor `rabit-e6-vendor` — копия `rabit-u-vendor`, `composer.lock` не менялся; в worktree пустой `api/vendor` как точка монтирования):
  `docker run --rm --network none --entrypoint php --mount type=bind,source=$PWD/api,target=/app,readonly --mount type=volume,source=rabit-e6-vendor,target=/app/vendor,readonly --tmpfs /app/var:rw,size=512m --workdir /app rabit-api-php-cli:d3-webp -d xdebug.mode=off vendor/bin/phpunit --colors=never` (и `vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` с `-d memory_limit=2G`).

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
