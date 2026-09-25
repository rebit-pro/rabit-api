# G1 — платёжные попытки и подтверждённые факты (ЮKassa, тестовый магазин)

Ветка `codex/g1-payment-attempts` от main `49f40f97fb5ee8b16425fa3a6b2e2b3da0c2b771`. Зависимости E5 (PR #37) и F2 (PR #40) слиты. Решения G1-D12-SCOPE, G1-D09-SANDBOX и G1-DEC-01…08 приняты пользователем 25.09.2026. План и журнал: `docs/plans/G1_payment-attempts/`.

Покупатель оплачивает созданный заказ на странице ЮKassa выбранным способом. Результат подтверждает только сервер по ответу провайдера. Возврат браузера, уведомление и cron — лишь поводы для сверки. Сотрудник видит реестр платежей своей области. Активация — только тестовый магазин. Боевые списания закрыты до G2 (чеки), I2 (возвраты), I3/J (исполнение и выдача) и N2.

## Контракты

- PAY-03 `GET /api/v1/public/orders/current/payment-quote` (`X-Order-Key`) возвращает итог неизменяемого снимка заказа без пересчёта (G1-DEC-01), `quoteToken` (sha256 версии и суммы), `canPay`, `precedingAttemptId`, `activeAttemptId` и `paymentMethods` из конфигурации магазина.
- PAY-01 `POST …/payment-attempts` (`Idempotency-Key` 32 hex) принимает `orderVersion`, `quoteToken`, `precedingAttemptId` и `paymentMethod`. Ответ 202: та же открытая попытка при повторе, двойном клике или второй вкладке (`created=false`). Отказы: 403 `PAYMENT_DISABLED`, 422 `PAYMENT_METHOD_UNAVAILABLE`, 409 `QUOTE_CHANGED` / `ATTEMPT_CONFLICT` / `ORDER_ALREADY_PAID` / `PAYMENT_CLOSED` / `IDEMPOTENCY_CONFLICT`.
- PAY-02 `GET …/payment-attempts/{id}` отдаёт серверный результат. Для `pending`/`unknown` сервер сверяется с ЮKassa не чаще раза в 5 с. Чужая попытка — 404 `PAYMENT_ATTEMPT_NOT_FOUND`.
- PAY-07 `POST /api/v1/webhooks/yookassa/payments`: из тела берутся только `event` и `object.id`, затем сервер сам запрашивает платёж. Дедупликация по ключу `event:object.id`. Повтор, чужой платёж и неподдерживаемое событие получают 200. Тело без `object.id` — 422 `INVALID_NOTIFICATION`, неизвестный провайдер — 404.
- PAY-10 `GET /api/v1/payments`, PAY-11 `GET /api/v1/payments/{id}`: область как у COM-12 (organizer — всё, curator — свои учреждения, head/teacher — 403). В ответах нет ключей, идемпотентности, страницы провайдера и тела уведомлений.
- COM-11/12/13 отдают `paidAt` и `latePayment`, в COM-12 работает фильтр `late`.

## Хранение и сверка

Миграция `20260925190001` создаёт три таблицы:
- `mf_payment_attempt`: одна открытая попытка на заказ (`ACTIVE_ORDER_ID`); хеш клиентского ключа и тела; `PROVIDER_KEY` для `Idempotence-Key`; расписание проверок.
- `mf_payment_fact`: денежный факт, один на платёж, не переписывается.
- `mf_payment_notification`: только ключ события и итог, без тела запроса.

В `mf_order` добавлены `PAID_AT`/`LATE_PAYMENT` с CHECK-ограничением. `down()` отказывается удалять таблицы с платежами.

Payment меняет заказ только через `Rebit\Share\Contracts\Commerce\OrderPaymentInterface`. HTTP к ЮKassa выполняется вне SQL-транзакции. Блокировки берутся в одном порядке: заказ, затем попытка. Ответ с чужими суммой, валютой, магазином или попыткой не оплачивает заказ: попытка остаётся `unknown` для ручного решения. После 24 часов без ответа создание не повторяется. Поздний платёж — это факт с `latePayment`, исполнение решает I3. Cron `app:payment:reconcile` проверяет с паузами 1, 2, 5 и 15 минут, затем раз в час.

Клиент устроен по образцу `orteka.payment`: `PaymentClient` поверх `RebitHttpClient` (Basic `shopId:secretKey`, таймауты 5/15 с) и `YooKassaClientProvider` с мапперами. Коды 400/401/403/404 — окончательный отказ; 429, 5xx, таймаут и ответ без объекта платежа — неизвестный исход.

## Frontend

- На странице заказа `/orders/access/:orderKey` есть панель оплаты: способы из PAY-03, повтор тем же ключом, переход на ЮKassa.
- Страница возврата `/orders/payment/:attemptId` без личного ключа в адресе. Ключ хранится в `localStorage` по ID попытки и удаляется после конечного статуса. Опрос каждые 3 с, через минуту — каждые 10 с, до 5 минут.
- Реестр `/cabinet/payments` и карточка платежа, пункт меню «Платежи» по `order.read`; в карточке заказа сотрудника — оплата и поздняя оплата.

## Конфигурация

`MOREFOTO_PAYMENT_YOOKASSA_SHOP_ID`, `MOREFOTO_PAYMENT_YOOKASSA_SECRET_KEY`, `MOREFOTO_PAYMENT_METHODS` (тестовый магазин — `bank_card`, боевой — `sbp,bank_card`), `MOREFOTO_PAYMENT_RETURN_BASE_URL`. Без магазина оплата выключена и ничего не имитирует. Секрет в production читается из `/run/secrets/morefoto_yookassa_secret_key` (`deploy/secrets/README.md`).

## Условия подключения на stage

1. Применить миграцию `20260925190001` и установить модуль `morefoto.payment` (`b_module`), затем сверить список модулей stage с `api/tools/e2e/prepare.php`.
2. Добавить ключи тестового магазина и `MOREFOTO_PAYMENT_RETURN_BASE_URL=https://app.morefoto36.ru`. Для тестового магазина допустим `backend.env`. Для боевого — Swarm-секрет, его подключение в compose, Makefile и `swarm-publish-runtime.sh` делается вместе с выкладкой.
3. В кабинете ЮKassa в разделе «Интеграция → HTTP-уведомления» указать `https://app.morefoto36.ru/api/v1/webhooks/yookassa/payments` и события `payment.succeeded`, `payment.canceled`.
4. Cron `app:payment:reconcile` входит в `api/docker/common/cron/crontab`.

## Ограничения

- СБП и SberPay тестовый магазин не принимает (400 `invalid_request`); реальная проверка СБП — `BLOCKED` до боевого магазина.
- Отклонённая карта оставляет платёж ЮKassa `pending` до истечения срока страницы (~10 минут, `expired_on_confirmation`), её переход в `canceled` приходит сверкой.
- Чеки не передаются (G2); возвраты — I2.

## Проверки

Быстрые проверки и тест-кейсы — в `docs/plans/G1_payment-attempts/progress.md`. Полный `make test-e2e` запускается после ревью. Спецификация `zzzzzzzzz-payments` (группа b) использует настоящий тестовый магазин, когда есть `~/.config/morefoto/yookassa-test.env`: fpm подключается к внешней сети, способы `bank_card,sbp`. Сценарий: отказ СБП, затем отклонённая и успешная карта на странице ЮKassa, возврат, повтор уведомления, реестр, desktop/mobile. Без ключей проверяется выключенное состояние. Verifier `verify-payments.php` сверяет попытки, факты и статусы заказов в MySQL.

Канонический план MoreFoto: `morefoto-contract.patch` — только дельта G1 (PAY-01/02/03/07, новые PAY-10/11, владелец `morefoto.payment`, решения). Patch снят относительно текущего состояния плана, где уже есть незамерженные правки E6 (PR #66) и K3.
