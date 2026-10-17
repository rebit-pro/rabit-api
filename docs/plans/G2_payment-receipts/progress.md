# G2 — журнал

## Точка продолжения

- Ветка `codex/g2-payment-receipts`, перемотана на `main` `b3e56a8` (своих коммитов не было); рабочая копия
  `/home/user/rabit-api-worktrees/g2-payment-receipts`. PR ещё нет.
- Завершено: ответы владельца по D09 (17.10.2026), сверка API ЮKassa по OpenAPI-спецификации, инвентаризация кода
  `b3e56a8`, план переписан.
- Решения G2-D09 и G2-DEC-01…12 приняты владельцем («Рекомендации принимаю»). S1–S2 завершены: граф (J5 merged,
  G2 inProgress, D09 принят, PAY-12/13, PAY-05/06 → I1) и `docs/waves/g2/morefoto-contract.patch`.
- Сейчас: S3–S6 — контракты Commerce/Payment, миграция, состав чека, клиент ЮKassa, сверка.
- Следующий шаг: контракты `OrderReceiptSourceInterface` и `PaymentCapabilitiesInterface`.
- Открыто: нет. Тестовый магазин с проверкой чеков включён владельцем.
- Проверка графа: `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| G2-T01…T18 | PENDING | — | — |

## Журнал

### 26.09.2026 — первый черновик

- Граф `6d034b3`: из запланированных волн зависимости слиты только у G2. Черновик решений G2-D09-SANDBOX и
  G2-DEC-01…11 не был согласован; волна отложена до регистрации ИП.

### 17.10.2026 — возобновление

- Граф `b3e56a8`: готовы J5 (на ревью в графе, слита 4fe304b) и L3 (post-MVP); G2 заблокирована только D09.
  Владелец: «Да, продолжай G2» и ответы по D09 (УСН без выбора объекта, патент уточняется, «Без НДС» `vat_code=1`,
  `commodity` для печати и электронных, печать — предоплата и закрывающий чек при выдаче, электронные — полный расчёт).
- Боевой ключ проверен `GET /v3/me` (магазин 1491261, `test=false`, `fiscalization.provider=yoo_receipt`, есть СБП) и
  сохранён Docker secret `morefoto_yookassa_secret_key_live_20261017`; к сервисам не подключён. Тестовый магазин
  1476254: `fiscalization_enabled=false`.
- OpenAPI ЮKassa: `measure` обязателен для «Чеков от ЮKassa», `tax_system_code` игнорируется (налоговая система — в
  кабинете), лимит 80 позиций, закрывающий чек `POST /v3/receipts` с `settlements` prepayment, уведомлений о чеках нет.
- Код `b3e56a8`: комплект — электронный товар; подарок без строки или строка с `TOTAL=0`; копия J4 без строки; Payment
  не видит строк и email; отметки выдачи печати покупателю нет (M1 — передача партии, post-MVP).
- Ветка перемотана `git merge --ff-only origin/main`; план переписан, решения на согласовании.
- Тестовый магазин: владелец включил «Онлайн-касса» в режиме проверки чеков, «Принимать платёж» при недоставленном чеке.
  Пробный платёж 1 ₽ с `receipt` до вступления настроек — без `receipt_registration` (данные чека проигнорированы).
  После сохранения `GET /v3/me`: `fiscalization.enabled=true`, `provider=kit_invest` — эмуляция сторонней кассы, а не
  `yoo_receipt` боевого магазина (лимит позиций 100 против 80; закрывающий чек `POST /v3/receipts` есть у обоих).
  ЮKassa: настройки начинают работать примерно через 10 минут. Поле «Адрес сайта» содержало ссылку галереи —
  владельцу предложено заменить на `https://app.morefoto36.ru` (адрес печатается в чеке).
- Владелец: «Рекомендации принимаю» — G2-D09 и G2-DEC-01…12 приняты (везде вариант (а)).
- Тестовый магазин после применения настроек: платёж без `receipt` — 400 «Receipt is missing or illegal», сумма позиций
  не равна платежу — 400 `receipt.items.amount`. Следствие: до выкладки G2 оплата на stage (G1 без чека) отклоняется;
  гейты других веток — с выключенной ЮKassa (`E2E_YOOKASSA_ENV=/nonexistent`).

### 17.10.2026 — S2: граф и реестр MoreFoto

- `docs/waves/graph.json`: baseline `b3e56a8`, J5 merged (PR #209, 4fe304b), G2 inProgress, D09 в
  `acceptedImplementationDecisions`, `decisionEvidence` G2-D09-2026-10-17 и G2-DEC-01-12, G2 — PAY-04/09/12/13,
  I1 — + PAY-05/06, `endpointCount` 125, `openDecisions`/`openLaunchDecisions` по D09 обновлены.
  `python3 tools/verify-wave-graph.py docs/waves/graph.json` — PASS (61 волна, 125 ID, readyMvp G2).
- Реестр MoreFoto восстановлен локально: снимок `/home/user/MoreFoto` + `OPS-telemos-feedback-plan` + L1 + J2
  (патч `OPS-mvp-payment-print-email-plan` входит в telemos, J1 уже в снимке; E9/J5 реестр не меняли). Изменены
  `build.py` (PAY-04 — список чеков; PAY-09 — заменён сверкой; PAY-05 — перенос в I1; PAY-12/13; testMode PAY-03,
  `paymentTestMode`/`receiptChannels` COM-08; чеки PAY-10/11) и `backend-waves.json` (G2/I1, D09; `deliveryState`
  канонического плана не трогается — он отстаёт с E7). `render-waves.py`, `build.py`, `validate.py` — passed,
  124 запроса. Патч `docs/waves/g2/morefoto-contract.patch` накладывается после J2.

