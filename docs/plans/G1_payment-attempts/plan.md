# G1 — платёжные попытки и подтверждённые факты (ЮKassa, sandbox)

## Цель и контекст

Покупатель оплачивает уже созданный заказ E5 на готовой странице ЮKassa, а сервер сам подтверждает результат. Попытка, webhook, возврат браузера и неизвестный исход не создают ложного оплаченного заказа и двойного списания. Сотрудник видит реестр платежей в своей области доступа.

- Ветка `codex/g1-payment-attempts` от `main` `49f40f9`; рабочая копия `/home/user/rabit-api-worktrees/g1-payment-attempts`.
- Граф: `docs/waves/graph.json`, волна G1 (legacy W19). Зависимости E5 (PR #37) и F2 (PR #40) слиты.
- Основание провайдера: `docs/plans/sberpay-start-plan/plan.md` (PR #35, merge `49f40f9`), `paymentIntegration` графа.
- API: соседний `MoreFoto/docs/05-rest-api/README.md` — PAY-01/02/03/07, проверяемые COM-06/07/11/12/13.
- Активация: только тестовый магазин. Боевые списания закрыты до возвратов (I2), чеков (G2), исполнения/выдачи (I3/J) и N2.

## Решения

Приняты пользователем 25.09.2026 («Давай возьмем G1» на предложение узких решений):

- **G1-D12-SCOPE.** Поздний платёж записывается как денежный факт с `latePayment=true` и не открывает файлы и печать. Решения исполнения, возвраты и перенос с оплатой — I2/I3. D12 снимается с `decisionGates` G1 и остаётся у I1–I3 и далее.
- **G1-D09-SANDBOX.** D09 закрыт для G1 в части тестового режима: тестовый магазин ЮKassa и служебные API ID реестра. Параметры чеков, налогообложения, форма продавца (ИП/ООО или НПД) и боевой магазин остаются D09 для G2 и далее. D09 снимается с `decisionGates` G1.
- **G1-MODULE.** Модуль `morefoto.payment` (единственное число), namespace `Morefoto\Payment` (как у `Morefoto\Commerce`). Владелец `morefoto.payments` в обоих графах и `paymentIntegration` переименовывается.

Согласованы пользователем 25.09.2026: G1-DEC-03 — вариант (б), G1-DEC-06 — клиент по образцу `orteka.payment`, остальные — рекомендованный вариант (а). Итоговые формулировки:

- **G1-DEC-03 (б).** Способ оплаты выбирает покупатель на нашей странице, в запросе к ЮKassa передаётся `payment_method_data.type`. Список доступных способов задаёт конфигурация магазина `MOREFOTO_PAYMENT_METHODS`, порядок — порядок кнопок: боевой магазин `sbp,bank_card`. Тестовый магазин ЮKassa принимает только карту и кошелёк ЮMoney, поэтому для sandbox задаётся `bank_card`. Путь СБП (`sbp` + `confirmation.type=redirect`) покрывается unit-тестом маппинга, а реальная проверка СБП — `BLOCKED` до боевого магазина.
- **G1-DEC-06.** Клиент повторяет схему `orteka.payment` (`Infrastructure/Http/PaymentClient`, `PaymentClientFactory`, `Exception/PaymentHttpException`, `Provider/SberPay/Provider/SberPayClientProvider` с request/response mapper и gateway DTO). `Morefoto\Payment\Infrastructure\Http\PaymentClient` зависит от общего `Rebit\Share\Infrastructure\HttpClient\RebitHttpClient`: базовый URL и Basic-авторизация `shopId:secretKey` из `PaymentConnectionConfig`, заголовки `Idempotence-Key` и `Content-Type`. `YooKassaClientProvider` реализует исходящий порт Application и маппит DTO. Нового Composer-пакета нет. `RebitHttpClient` хранит авторизацию в объекте, поэтому у `PaymentClient` собственный экземпляр. В `RebitHttpClientFactory::create()` добавляются необязательные таймауты с прежними значениями по умолчанию; для ЮKassa — короткие, чтобы PAY-01 не держал покупателя 60 с. Таймаут даёт `unknown`.

Исходные варианты, предложенные до согласования (рекомендация — первый вариант):

- **G1-DEC-01. Сумма оплаты.** (а) Оплачивается неизменяемый снимок заказа E5 (`mf_order.TOTAL`); PAY-03 возвращает этот же итог, `quoteToken` — подпись `orderId+version+total`. Пересчёта неоплаченного заказа в G1 нет: цена доведена до покупателя при оформлении и не повышается после. (б) PAY-03 пересчитывает по текущему каталогу и требует подтверждения новой суммы — нужен сценарий редактирования неоплаченного заказа, которого нет в E5.
- **G1-DEC-02. Когда можно платить.** (а) Новая попытка — пока период группы заказа открыт (`closesAt` не наступил) и нет pending/unknown попытки. Платёж, подтверждённый после `closesAt` по попытке, начатой до закрытия, — `latePayment=true`. (б) Разрешать оплату и после закрытия.
- **G1-DEC-03. Способ оплаты.** (а) `confirmation.type=redirect`, `capture=true`, без `payment_method_data`: способ выбирает покупатель на странице ЮKassa из включённых в магазине (карта, СБП, SberPay, ЮMoney). (б) Фиксировать СБП на нашей стороне.
- **G1-DEC-04. Ключ заказа не уходит провайдеру.** (а) `return_url` = `/orders/payment/{attemptId}` без `X-Order-Key`; перед переходом frontend сохраняет ключ в `sessionStorage`. Если ключа нет (другое устройство), страница просит открыть заказ по личной ссылке. (б) Передавать ключ в `return_url` — он окажется в журналах ЮKassa.
- **G1-DEC-05. Подлинность webhook.** (а) Тело уведомления — только сигнал: берём `object.id`, запрашиваем платёж `GET /v3/payments/{id}` и сверяем магазин, сумму, валюту, `metadata.attemptId` и заказ. Фильтр по IP ЮKassa не нужен (за прокси он ненадёжен); неизвестный платёж отвечает 200 без изменений. (б) Дополнительно фильтровать по IP.
- **G1-DEC-06. Клиент ЮKassa.** (а) Свой тонкий клиент на cURL (`POST /v3/payments`, `GET /v3/payments/{id}`) с таймаутами и `Idempotence-Key`: два вызова, строгие DTO, без нового Composer-пакета. (б) Официальный `yoomoney/yookassa-sdk-php`.
- **G1-DEC-07. Реестр и права.** (а) Новые ID `PAY-10 GET /api/v1/payments` и `PAY-11 GET /api/v1/payments/{payment_attempt_id}`; область как у COM-12: organizer — все, curator — свои учреждения, head/teacher — 403. Старые 110 ID не меняются. (б) Только organizer.
- **G1-DEC-08. Фоновая сверка.** (а) Команда `app:payment:reconcile --limit=100` в `api-cron` раз в минуту: pending/unknown попытки с наступившим `NEXT_CHECK_AT`, растущий интервал (1, 2, 5, 15 минут, далее раз в час) до конечного статуса. (б) Только по webhook и PAY-02.

### Уточнения по ходу реализации (25.09.2026)

- G1-DEC-04: ключ заказа для страницы возврата хранится в `localStorage` по ID попытки, а не в `sessionStorage`. Банковское приложение при СБП может открыть возврат в новой вкладке. После конечного статуса ключ удаляется. Провайдеру ключ по-прежнему не передаётся.
- Оплата встроена в страницу заказа `/orders/access/:orderKey` (панель в итоге заказа). Демо-маршрут `/orders/access/:orderKey/payment` остаётся только для демо-режима.
- PAY-07: тело без `object.id` получает 422 `INVALID_NOTIFICATION` (общий контракт ошибок), а не 400.
- PAY-11: вместо журнала проверок отдаются `checkCount`, `lastCheckAt`, `nextCheckAt` и `cancelReason` попытки. Отдельной таблицы журнала сверки нет.
- Секрет ЮKassa в production: `runtime-env.php` читает `/run/secrets/morefoto_yookassa_secret_key`. Подключение Swarm-секрета в `docker-compose-production.yml`, Makefile и `deploy/swarm-publish-runtime.sh` делается на шаге выкладки (S13). Для тестового магазина на stage допустим `backend.env`.
- Проверка на тестовом магазине 25.09.2026: тело `bank_card` принято (`pending`, страница на `yoomoney.ru`, `recipient.account_id` = магазин, `metadata` возвращается); повтор с тем же `Idempotence-Key` возвращает тот же платёж. `sbp` отвечает 400 `invalid_request` «Payment method is not available» и классифицируется как отказ. `GET` неизвестного платежа — 404.

## Scope

1. **Модуль `morefoto.payment`**: `install/index.php`, `.settings.php`, `di/`, `include.php`, `routes.php`, `LogChannelEnum` (осмысленный канал `payment`), регистрация в bootstrap/E2E `prepare.php` и проверке `b_module`.
2. **Хранение (миграция foundation)**:
   - `mf_payment_attempt` — PUBLIC_ID, ORDER_ID, ORDER_VERSION, AMOUNT, CURRENCY, PROVIDER, SHOP_ID, STATUS (`created/pending/unknown/succeeded/canceled`), PRECEDING_ID, IDEMPOTENCY_KEY (unique), PROVIDER_PAYMENT_ID (unique), CONFIRMATION_URL, CANCEL_REASON, PAID_AT, INCOME_AMOUNT, LATE_PAYMENT, CHECK_COUNT, NEXT_CHECK_AT, LAST_CHECK_AT, CREATED_AT, UPDATED_AT; частичная уникальность «одна активная попытка на заказ» через колонку-замок.
   - `mf_payment_fact` — подтверждённый денежный факт: ATTEMPT_ID, ORDER_ID, PROVIDER_PAYMENT_ID (unique), AMOUNT, INCOME_AMOUNT, PAID_AT, LATE_PAYMENT, CONFIRMED_BY (`webhook/return/reconcile`), CREATED_AT. Не удаляется и не переписывается.
   - `mf_payment_notification` — входящие уведомления: PROVIDER, EVENT_KEY (unique `event:object.id`, у ЮKassa нет ID события), EVENT, PROVIDER_OBJECT_ID, RECEIVED_AT, PROCESSED_AT, RESULT. Без raw body и секретов.
   - `mf_order`: добавить `PAID_AT`, `LATE_PAYMENT` (владелец — Commerce, миграция в этой волне).
3. **Межмодульный контракт** `Rebit\Share\Contracts\Commerce\`: `OrderPaymentInterface` + DTO — чтение платёжной проекции заказа (по ключу покупателя и по ID), применение статуса `pending/declined/paid` с `paidAt/latePayment` и проверкой версии. Реализация и DI — в Commerce; Payment не читает таблицы Commerce напрямую.
4. **Application Payment**: UseCase `GetPaymentQuote` (PAY-03, плюс `paymentMethods` из конфигурации магазина), `StartPaymentAttempt` (PAY-01, плюс поле `paymentMethod` из списка PAY-03; иначе 422), `GetPaymentAttempt` (PAY-02, запускает сверку pending не чаще раза в 5 с), `AcceptProviderNotification` (PAY-07), `ReconcilePendingAttempts` (cron), `ListPayments`/`GetPayment` (PAY-10/11). Сервис сверки применяет статус провайдера к попытке, факту и заказу в одной SQL-транзакции; HTTP к ЮKassa всегда вне транзакции.
5. **Domain Payment**: переходы статусов попытки, правило «одна активная попытка», late payment, сверка суммы/валюты/магазина/заказа.
6. **Infrastructure**: `Http/PaymentClient` + `PaymentClientFactory` поверх `RebitHttpClient`, `Provider/YooKassa/` (client provider, request/response mapper, gateway DTO) по G1-DEC-06; конфигурация из env (`MOREFOTO_PAYMENT_YOOKASSA_SHOP_ID`, `MOREFOTO_PAYMENT_YOOKASSA_SECRET_KEY`, `MOREFOTO_PAYMENT_METHODS`, `MOREFOTO_PAYMENT_RETURN_BASE_URL`), в production — Docker Swarm secret; SQL-репозитории; маппинг ошибок ЮKassa в предметные исключения (4xx кроме 429 — отказ, 429/5xx/таймаут — `unknown`).
7. **Presentation**: чистые контроллеры (`PublicPaymentController`, `PaymentWebhookController`, `StaffPaymentController`), `*RequestDto`, маппер ответов.
8. **Commerce**: COM-11 отдаёт `paidAt`/`latePayment`; COM-12 фильтр `late` становится доступным (`settlement` остаётся 422 до I-волн); COM-13 — `paidAt`/`latePayment`.
9. **Frontend**:
   - покупатель: `/orders/access/:orderKey/payment` из демо становится реальным экраном (PAY-03 → подтверждение итога и выбор способа: СБП/карта → PAY-01 → переход на ЮKassa); `/orders/payment/:attemptId` — возврат, опрос PAY-02 с честными `pending/unknown`; блок оплаты на странице заказа;
   - сотрудник: `/cabinet/payments` — реестр с фильтрами (период, статус, номер заказа), серверной пагинацией; блок «Оплата» в карточке заказа COM-13.
10. **Документация и графы**: G1 в `inProgress`, решения в `decisionEvidence`, владелец `morefoto.payment`, PAY-10/11 в генераторе API и Postman канонического плана (`docs/waves/g1/morefoto-contract.patch`), `docs/waves/g1/README.md`, `verification.json`, `visual.json`, `deploy/secrets/README.md`.

## Исключено

Чеки и фискализация (G2), возвраты и PAY-08 (I2), решения исполнения и поздний платёж для файлов/печати (I3), выдача файлов (J), боевой магазин и договор, автоматический переход на другого провайдера, E6 (PR #66 — G1 оплачивает снимок заказа, какой бы ни была цена), настройка HTTP-уведомлений в кабинете ЮKassa до выкладки на stage.

## Зависимости, риски и ограничения

- E6 (PR #66) и G1 оба меняют `docs/waves/graph.json`; E6 уже исправляет `tools/verify-wave-graph.py` и статус U1–U8/B3/B4. G1 эти правки не дублирует: кто сливается вторым — обновляется на `main` и разрешает конфликт графа как «main + своя дельта». До merge E6 проверка YK-01 на `main` падает на известной ошибке фикстуры пакета.
- Webhook не доходит до локального стенда и изолированной E2E-БД: там подтверждение идёт через сверку PAY-02/cron; PAY-07 проверяется повтором уведомления с реальным `object.id` из sandbox.
- Срок гарантии `Idempotence-Key` ЮKassa — 24 часа. Попытка `unknown` старше суток без `PROVIDER_PAYMENT_ID` не повторяется вслепую: остаётся `unknown`, блокирует новую попытку и видна в реестре для ручного решения (действие — I-волны).
- Срок жизни pending-платежа с redirect у ЮKassa уточнить на sandbox; от него зависит, когда покупатель сможет начать новую попытку после брошенной.
- Тестовая E2E требует сети до `api.yookassa.ru` и страницы оплаты ЮKassa, ключи — в `~/.config/morefoto/yookassa-test.env` (вне git). Без ключей спецификация явно `BLOCKED`, а не `PASS`.
- Объём: оценка ~5–6 тыс. рукописных строк (backend ~3,5, frontend ~1,2, тесты ~1,5) — в пределах одной волны.

## Checklist

- [x] S1. План и журнал; согласование G1-DEC-01…08 с пользователем.
- [x] S2. Графы: G1 `inProgress`, решения, владелец, PAY-10/11 в каноническом генераторе; проверки DAG/ID.
- [x] S3. Модуль `morefoto.payment`: установка, DI, лог-канал, регистрация.
- [x] S4. Миграция foundation и репозитории.
- [x] S5. Контракт `OrderPaymentInterface` и реализация в Commerce; COM-11/12/13.
- [x] S6. Domain и Application: попытка, сверка, webhook, реестр.
- [x] S7. Клиент ЮKassa и конфигурация; ручная проверка sandbox.
- [x] S8. HTTP: контроллеры, DTO, маршруты, архитектурные тесты границы.
- [x] S9. Cron-сверка.
- [x] S10. Frontend покупателя и сотрудника.
- [x] S11. Быстрые проверки: php-cs-fixer, unit, arch, frontend lint/type/build.
- [x] S12. E2E: регистрация модуля в стенде, sandbox-спецификация, verifier.
- [ ] S13. PR, ревью пользователя, затем `make test-e2e` и визуальная проверка desktop/mobile; подключение Swarm-секрета при выкладке на stage.

## Критерии приёмки

1. На тестовом магазине заказ проходит `pending → succeeded` и `pending → canceled`; неизвестный исход разрешается сверкой без второго платежа.
2. Повтор PAY-01, двойной клик и две вкладки дают одну попытку; повторный webhook не меняет факт; перезапуск сверки идемпотентен.
3. Чужие сумма, валюта, магазин или заказ в ответе провайдера не делают заказ оплаченным и пишутся в лог без секретов.
4. Оплаченный снимок заказа неизменяем; `paidAt/latePayment` видны в COM-11/13; фильтр `late` работает в COM-12.
5. Реестр PAY-10/11: область доступа, фильтры, стабильная пагинация, без секретов, raw payload и ключа заказа.
6. Контроллеры чистые по правилам `CLAUDE.md`; у UseCase/Service содержательный phpDoc.
7. Реальный браузерный E2E на изолированной БД и sandbox; desktop/mobile скриншоты экранов оплаты и реестра.

## Тест-кейсы

| ID | Предусловие → действие | Ожидаемый результат | Команда |
| --- | --- | --- | --- |
| G1-T01 | Граф после правок | DAG без циклов, 112 ID (110 + PAY-10/11), 35 WNN, G1 `inProgress` | `python3 tools/verify-wave-graph.py docs/waves/graph.json`; канонический `wave_graph.py` |
| G1-T02 | Попытка по неоплаченному заказу | `created → pending`, сохранены `IDEMPOTENCY_KEY`, `PROVIDER_PAYMENT_ID`, `CONFIRMATION_URL`; HTTP вне транзакции | unit `StartPaymentAttemptUseCase` |
| G1-T03 | Повтор PAY-01 при pending, конкурентные запросы | Та же попытка; второй платёж не создаётся | unit + интеграционный тест репозитория |
| G1-T04 | Таймаут создания у провайдера | `unknown`; повтор с тем же ключом в пределах 24 ч; новая попытка запрещена | unit |
| G1-T05 | Сверка `succeeded` | Факт записан один раз, заказ `paid`, `paidAt`; повтор без изменений | unit сервиса сверки |
| G1-T06 | Ответ провайдера с чужой суммой/валютой/магазином/`attemptId` | Заказ не меняется, попытка не `succeeded`, предупреждение в логе | unit |
| G1-T07 | Оплата подтверждена после `closesAt` | Факт с `latePayment=true`, заказ `paid` + `latePayment`; новая попытка после закрытия — 409 | unit Domain |
| G1-T08 | PAY-07: повтор, неизвестный объект, некорректное тело | 200 и одна запись инбокса; неизвестный — без изменений; некорректное — 400 без деталей | unit + HTTP |
| G1-T09 | PAY-01/02/03 без ключа, с чужим/истёкшим ключом | 404 ORDER_NOT_FOUND, как COM-11 | HTTP E2E |
| G1-T10 | PAY-10/11 для organizer/curator/head | Область как COM-12; head — 403; нет секретов и ключа | unit + HTTP E2E |
| G1-T11 | Cron-сверка pending | Интервал растёт; конечный статус прекращает опрос; повторный запуск идемпотентен | unit команды/UseCase |
| G1-T12 | Архитектура | Контроллеры без Bitrix/HttpRequest/ServiceLocator; Payment не читает `mf_order` напрямую | arch-тесты |
| G1-T13 | Sandbox вручную | Реальный платёж картой `5555…4444` → succeeded; `5555…4592` → canceled | ручной сценарий, запись в progress без ключей |
| G1-T14 | Браузер: покупатель | Страница заказа → подтверждение итога → ЮKassa → возврат → «Оплачено» | `make test-e2e`, спецификация payments |
| G1-T15 | Браузер: сотрудник | Реестр с фильтрами и карточка оплаты; desktop/mobile | `make test-e2e`, `visual.json` |
| G1-T17 | Выбор способа: `sbp`, `bank_card`, не входящий в конфигурацию | Тело запроса к ЮKassa содержит выбранный `payment_method_data.type`; чужой способ — 422 без обращения к провайдеру | unit mapper + UseCase |
| G1-T18 | `PaymentClient` поверх `RebitHttpClient` | URL, Basic-авторизация, `Idempotence-Key`, таймауты; 4xx → отказ, 429/5xx/таймаут → `unknown`; секреты не в логе | unit клиента |
| G1-T16 | Быстрые проверки | php-cs-fixer, PHPUnit, frontend lint/type-check/build зелёные | команды из журнала |
