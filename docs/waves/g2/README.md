# G2 — чеки ЮKassa: предоплата печати, полный расчёт и выдача

План и решения: [`docs/plans/G2_payment-receipts/plan.md`](../../plans/G2_payment-receipts/plan.md), журнал —
[`progress.md`](../../plans/G2_payment-receipts/progress.md). Реестр MoreFoto: [`morefoto-contract.patch`](morefoto-contract.patch)
(накладывается после патчей `OPS-telemos-feedback-plan`, L1 и J2).

## Что сделано

- **Чек оплаты.** При старте попытки (PAY-01) состав чека фиксируется в попытке и уходит в каждый запрос создания платежа
  ЮKassa: позиции по виду, товару и цене; подарки и копии кадров J4 (без суммы) не входят; печать — «Предоплата 100%»
  (`full_prepayment`), электронные фото и комплект — «Полный расчёт» (`full_payment`); `measure=piece`, `vat_code` и
  `payment_subject` из настроек; `tax_system_code` не передаётся — система налогообложения берётся из настроек «Чеков от ЮKassa»
  в кабинете магазина.
- **Закрывающий чек.** В блоке «Печать» группы (ссылки и сроки → «Печать») раздел «Выдача печати»: организатор или
  куратор отмечает заказы, печать которых отдана; по каждому создаётся один закрывающий чек «Полный расчёт» по печатным
  позициям с `settlements: prepayment` на их сумму. Отправка ждёт регистрации чека оплаты; отметка необратима.
- **Статусы.** Уведомлений о чеках у ЮKassa нет: `app:payment:reconcile` после попыток сверяет чеки — 1, 2, 5, 15 минут,
  дальше раз в час; не зарегистрированный за 72 часа чек — «Не сформирован» (ручная проверка в кабинете ЮKassa).
  Повтор закрывающего чека с неизвестным исходом — тем же ключом идемпотентности, не позже суток от первого запроса.
- **Покупатель.** На странице оплаченного заказа блок «Чеки»: состояние, позиции с признаком расчёта, ФН/ФД/ФП и дата.
  На оформлении — «Чек об оплате придёт на этот e-mail от ЮKassa»; при тестовом ключе — плашка «Тестовый режим оплаты:
  деньги не списываются» на оформлении и в оплате (#120).
- **Сотрудник.** Реестр платежей — состояние чека в строке и в CSV, чеки заказа в карточке платежа.

## API

| ID | Метод и путь | Доступ |
| --- | --- | --- |
| PAY-04 | `GET /api/v1/public/orders/current/receipt` → `enabled, channel, receipts[]` | `X-Order-Key` |
| PAY-12 | `GET /api/v1/groups/{group_id}/print-handovers` → `orders[]{state waiting/handed/blocked}, summary` | организатор, куратор своих учреждений |
| PAY-13 | `POST /api/v1/groups/{group_id}/print-handovers` `{orderIds, confirmed}` → как PAY-12 | то же |
| PAY-03 | `+ testMode` | `X-Order-Key` |
| PAY-10/11 | `+ receiptStatus`, `+ receipts[]` | организатор, куратор |
| COM-08 | `capabilities.receiptChannels=["email"]`, `capabilities.paymentTestMode` | ссылка галереи |

PAY-05/06 (повтор письма с чеком) перенесены в I1, PAY-09 заменён сверкой (G2-DEC-07/08).

## Настройки

| Переменная | Значение | По умолчанию |
| --- | --- | --- |
| `MOREFOTO_PAYMENT_RECEIPTS` | `off` или `yookassa` | `off` — без чеков, как G1 |
| `MOREFOTO_PAYMENT_RECEIPT_VAT_CODE` | ставка ЮKassa 1–12; `1` — «Без НДС» | `1` |
| `MOREFOTO_PAYMENT_RECEIPT_SUBJECT_PRINT` | `commodity`, `service`, `job`, `intellectual_activity`, `property_right`, `another` | `commodity` |
| `MOREFOTO_PAYMENT_RECEIPT_SUBJECT_DIGITAL` | то же | `commodity` |

Неверное значение при включённых чеках выключает оплату (PAY-01 → 403 `PAYMENT_DISABLED`, ошибка в канале `payment`):
магазин с включёнными чеками не примет платёж без корректного чека, а продавать без чека нельзя.

## Включение на stage

Тестовый магазин 1476254 с 17.10.2026 требует чек: до выкладки G2 оплата на stage отклоняется («Receipt is missing or illegal»).

1. Резервная копия и проверка восстановления, затем только `migrate.sh up Version20261017180001`.
2. Backend-сервисы (13) на релиз; окружение `morefoto_stage_fpm` и `morefoto_stage_payment_reconciler`:
   `MOREFOTO_PAYMENT_RECEIPTS=yookassa`, `MOREFOTO_PAYMENT_RECEIPT_VAT_CODE=1`,
   `MOREFOTO_PAYMENT_RECEIPT_SUBJECT_PRINT=commodity`, `MOREFOTO_PAYMENT_RECEIPT_SUBJECT_DIGITAL=commodity`.
3. DI smoke: `PaymentCapabilitiesInterface::receiptChannels()` = `["email"]`, `testMode()` = `true`; frontend.
4. Ручная проверка: оплата тестовой картой → блок «Чеки» на странице заказа; «Выдача печати» в блоке «Печать».

## Переход на боевой магазин (после N3, по согласию владельца)

- Ключ — Docker secret `morefoto_yookassa_secret_key_live_20261017` (магазин 1491261, «Чеки от ЮKassa» включены). Для
  `morefoto_stage_fpm` и `morefoto_stage_payment_reconciler`: `--secret-rm morefoto_yookassa_secret_key_test_20260925`,
  `--secret-add source=morefoto_yookassa_secret_key_live_20261017,target=morefoto_yookassa_secret_key`,
  `MOREFOTO_PAYMENT_YOOKASSA_SHOP_ID=1491261`, `MOREFOTO_PAYMENT_METHODS=sbp`.
- До перехода владелец задаёт объект УСН / патент в настройках «Чеков от ЮKassa» боевого магазина и HTTP-уведомления
  `https://app.morefoto36.ru/api/v1/webhooks/yookassa/payments` (`payment.succeeded`, `payment.canceled`).
- Откат: вернуть тестовый secret и shop id; данные чеков остаются.

## Ограничения

- Поздний платёж (после срока продаж) получает чек оплаты при создании платежа; решение по заказу — вручную до I3,
  закрывающий чек для него не создаётся (печать в архив не идёт).
- Заказ, оплаченный без чека (до G2 или при выключенных чеках), в «Выдаче печати» — «без закрывающего чека»: оформляется
  вручную в кабинете ЮKassa.
- Тестовый магазин работает через эмуляцию кассы «Кит Инвест», боевой — через «Чеки от ЮKassa»: запросы одинаковые,
  у боевого лимит 80 позиций (в коде заложен он).

## Проверка

- Полный гейт `make test-e2e` `rabit-e2e-4cbd704da394` — PASS: 158 браузерных тестов (a 81, b 77), все `verify-*.php`,
  включая `verify-receipts.php`; тестовый магазин ЮKassa включён.
- Тестовый магазин (настоящие запросы): платёж картой с чеком принят, чек оплаты зарегистрирован (`succeeded`, ФД
  выдан), закрывающий чек `POST /v3/receipts` с `settlements: prepayment` принят (`pending`, без ошибки).
- Скриншоты: [`screenshots/`](screenshots) — блок «Чеки» (desktop/mobile), «Выдача печати» (desktop/mobile), чеки в
  карточке платежа.

