# G2 — журнал

## Точка продолжения

- PR #212 слит в main `65bd194` (17.10.2026) и выложен на stage релизом `main-20261017192004-65bd194` (журнал «Выкладка»).
  Чеки включены на тестовом магазине; боевой ключ не подключён.
- Следующий шаг: владелец проверяет на stage оплату тестовой картой (блок «Чеки» в заказе) и «Выдачу печати»; следующая
  волна записывает G2 `merged` в граф (PR #212, `65bd194`). Переход на боевой магазин — после N3 по согласию владельца
  (runbook в `docs/waves/g2/README.md`).
- Для следующих релизов: на `morefoto_stage_fpm` и `morefoto_stage_payment_reconciler` есть окружение
  `MOREFOTO_PAYMENT_RECEIPTS=yookassa`, `_RECEIPT_VAT_CODE=1`, `_RECEIPT_SUBJECT_PRINT/_DIGITAL=commodity` — переключение
  только `/app` его сохраняет.
- Откат stage: backend/воркеры — `docker service rollback` (прежний `/app` — `main-20261017121241-4fe304b`, спецификации в
  `services-before.json` релиза; откат FPM и сверки снимает и окружение чеков — тогда оплата в тестовом магазине снова
  отклоняется); frontend — `docker service rollback morefoto_frontend`. `down` миграции 20261017180001 при наличии чеков
  отказывает.
- Блокеры: нет.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| G2-T01 | PASS | 17.10.2026 | `verify-wave-graph.py` PASS (61 волна, 125 ID); MoreFoto `validate.py` passed, 124 запроса |
| G2-T02 | PASS | 17.10.2026 | `ReceiptComposerTest` (смешанный заказ, подарок, скидка сотрудника, признаки) |
| G2-T03 | PASS | 17.10.2026 | `ReceiptComposerTest::testMismatchAndTooManyItemsNeverReachTheProvider` |
| G2-T04 | PASS | 17.10.2026 | `ReceiptFlowTest::testAttemptFixesTheReceiptAndRepeatsItWithEveryCreation`, `YooKassaReceiptClientTest` |
| G2-T05 | PASS | 17.10.2026 | `ReceiptSettingsTest`, `ReceiptFlowTest::testReceiptsOffKeepTheRequestOfG1` |
| G2-T06 | PASS | 17.10.2026 | `ReceiptFlowTest` (реквизиты, canceled, 72 ч, сверка по расписанию) |
| G2-T07 | PASS | 17.10.2026 | `PrintHandoverTest`; гейт: повтор PAY-13, HANDOVER_CONFLICT, INVALID_ORDERS, CONFIRMATION_REQUIRED, 401 |
| G2-T08 | PASS | 17.10.2026 | `ReceiptFlowTest` (ожидание чека оплаты, тот же ключ, срок ключа, отказ); гейт: `settlements` = сумма печати |
| G2-T09 | PASS | 17.10.2026 | `PrintHandoverTest::testBuyerSeesTheReceiptsOfTheOrderByItsKey`; гейт: чужой ключ — 404 |
| G2-T10 | PASS | 17.10.2026 | `ReceiptSettingsTest`, `CheckoutAvailabilityTest`; гейт: `paymentTestMode` в COM-08 |
| G2-T11 | PASS | 17.10.2026 | `OrderBuyerPolicyTest`; гейт: `receiptChannels=["email"]`, `receiptChannel='email'` у нового заказа |
| G2-T12 | PASS | 17.10.2026 | гейт: «Чек отправлен» в строке реестра, «Чеки заказа» в карточке |
| G2-T13 | PASS | 17.10.2026 | `PaymentArchitectureTest` (контроллеры, граница с Commerce, phpDoc) |
| G2-T14 | PASS | 17.10.2026 | гейт с тестовым магазином: чек оплаты `succeeded`, закрывающий чек принят `pending` без ошибки |
| G2-T15 | PASS | 17.10.2026 | гейт: блок «Чеки» с реквизитами, после выдачи — закрывающий чек; `g2-*-order-receipts.png` |
| G2-T16 | PASS | 17.10.2026 | гейт: «Выдача печати» desktop/mobile, `g2-*-print-handover.png`, `g2-desktop-payment-receipts.png` |
| G2-T17 | PASS | 17.10.2026 | PHPUnit, PHPStan, php-cs-fixer, `npm run check`, `test:commerce` |
| G2-T18 | PASS | 17.10.2026 | `verify-receipts.php` в гейте `rabit-e2e-4cbd704da394` |

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

### 17.10.2026 — S3–S11: реализация

- Backend `1ccc96d`:
  - контракты `OrderReceiptSourceInterface` (Commerce) и `PaymentCapabilitiesInterface` (Payment);
  - Domain `Receipt`: `ReceiptComposer` (группировка, бесплатное вне чека, ≤80, сумма = платёж, признаки расчёта),
    `ReceiptPolicy` (1/2/5/15 мин, потом час; 72 ч — `timeout`; ключ закрывающего чека — сутки от `SENT_AT`);
  - Application: `AttemptReceipt` (снимок в попытке), `ReceiptRegistrar`, `ReceiptReconciler`, `PrintHandoverView`,
    UseCase PAY-04/12/13; сверка чеков в `app:payment:reconcile`;
  - ЮKassa: `receipt` в `POST /payments` (`measure=piece`, без `tax_system_code`), `POST /receipts` с `settlements`
    prepayment, `GET /receipts?payment_id=`, `GET /receipts/{id}`;
  - настройки `MOREFOTO_PAYMENT_RECEIPTS` (`off|yookassa`), `_RECEIPT_VAT_CODE` (1), `_RECEIPT_SUBJECT_PRINT/_DIGITAL`
    (`commodity`) — `ReceiptSettingsFactory`; неверные значения выключают оплату; `testMode` по префиксу ключа `test_`;
  - миграция `20261017180001` (`RECEIPT` в попытке, `mf_payment_receipt`), добавлена в `prepare.php`.
- Frontend `7d6d537`: блок «Чеки» заказа, строка о чеке и плашка тестового режима на оформлении и в оплате,
  «Выдача печати» в диалоге «Печать», чек в строке/CSV/карточке реестра.

### Проверки

| Команда | Результат |
| --- | --- |
| `phpunit --testsuite unit` (php-cli d1-local, vendor `rabit-e7-vendor`) | PASS, 1027 тестов, 50252 проверки |
| `phpstan analyse` (весь проект) | PASS |
| `php-cs-fixer fix` по 97 изменённым PHP-файлам | исправлено 3 файла (форматирование) |
| `npm run check` (playwright v1.52.0, том `rabit-e7-node`) | PASS (`check=0`), test:ui 100/100 после замены иконки на зарегистрированную |
| `npm run test:commerce` | PASS, 253/253 (новый `receipts.test.mjs`) |
| `python3 tools/verify-wave-graph.py docs/waves/graph.json` | PASS |

### 17.10.2026 — S12–S13: E2E, PR, саморевью

- Стенд E2E: `MOREFOTO_PAYMENT_RECEIPTS=yookassa`, `VAT_CODE=1` (тестовый магазин без чека отклоняет платёж).
  Фикстура `receipt-fixture.php` (действие `reconcile`: подставной провайдер — чеки платежей `e2e-fixture`, настоящий
  тестовый магазин — платёж G1), общий `payment-fixture-provider.php` для `pay-fixture.php`.
- Спецификация `zzzzzzzzzzzzz-receipts` (группа b после L1): чек оплаты заказа J4, «Выдача печати» в блоке «Печать»,
  закрывающий чек, реестр, отказы PAY-13; с ключами тестового магазина — чеки платежа G1 и закрывающий чек в нём.
  `verify-receipts.php`: чек оплаты ↔ снимок, закрывающий только по выданной печати, CHECK и уникальность, нет email,
  `down()` отказывает. `zzzz-storefront`/`zzzzz-orders`: каналы чека и `paymentTestMode` зависят от ключей магазина.
- Scope п. 8 изменён до реализации: production compose сервисов оплаты не содержит — runbook в `docs/waves/g2/README.md`.
- PR #212 открыт; ветка отправлена по HTTPS (`ssh` к github.com:22 — таймаут). Саморевью: блокеров нет. Issues #213
  (устаревшая плашка о печати), #214 (канонический план MoreFoto отстаёт); #120 закрывается PR.
- `python3 -m unittest tools/tests/test_run_browser_e2e.py` — 21/22, падает `test_f2_…` (есть на main, #163).

### 17.10.2026 — S13: гейт и visual

- Прогон 1 `rabit-e2e-f7427f5af1ce` — FAIL: `zzzzz-orders` ждал `receiptChannel: null`, а по G2-DEC-12 сервер ставит
  `email` при включённых чеках. Спецификация поправлена (`email` при ключах тестового магазина).
- Прогон 2 `rabit-e2e-4cbd704da394` — **PASS**: 158 браузерных тестов (a 81, b 77), все `verify-*.php`, включая
  `verify-receipts.php`; тестовый магазин включён. `zzzzzzzzzzzzz-receipts`: T15 3,8 с, T07/T16 7,8 с, T14 4,3 с.
- Тестовый магазин (настоящие запросы): два платежа G1 с чеком — чеки оплаты `succeeded` с ФД; закрывающий чек
  `POST /v3/receipts` принят, `ra-…`, статус `pending`, без ошибки. Подставной провайдер: `settlements` 55000 = сумма
  печати, позиции `full_payment`.
- Visual `docs/waves/g2/screenshots/`: блок «Чеки» (desktop/mobile), «Выдача печати» (desktop/mobile), «Чеки заказа»
  в карточке платежа; на 390 px без горизонтальной прокрутки. Названия товаров в кадрах — тестовый каталог стенда.
- Граф: G2 → review (PR #212), `verify-wave-graph.py` PASS.

### 17.10.2026 — merge и выкладка

- Владелец: «Да, мержи и выкладывай». PR #212 слит merge-коммитом `65bd194` (head `6f7bc34`, MERGEABLE CLEAN, main был `b3e56a8`).
- Релиз `/srv/morefoto/releases/main-20261017192004-65bd194`: `git archive 65bd194 api`, образ
  `morefoto-frontend:main-20261017192004-65bd194` (`VITE_API_MOCKS_ENABLED=false`); vendor и `backend.conf` из
  `main-20261017121241-4fe304b` (`composer.lock` не менялся). `sha256sum --check` и `prepare-release.sh` — PASS.
- `backup.sh` — 284 093 байта; `restore-check.sh` — 183 таблицы PASS.
- `migrate.sh up Version20261017180001` — success (только эта версия).
- `switch-backend.sh morefoto_stage_fpm` (код + окружение чеков) → DI smoke в FPM: 21/21, `OK receipts on, channels
  ["email"], test shop yes`, `OK order email sending is off`. Остальные 12 backend-сервисов переключены;
  `morefoto_stage_payment_reconciler` с окружением чеков печатает «Сверено попыток: 0 …; чеков: 0, с ошибкой: 0».
- Frontend 2/2 `morefoto-frontend:main-20261017192004-65bd194`. Все 14 сервисов 1/1 (frontend 2/2).
- HTTP: `/` и `/legal` 200; PAY-04 без ключа — 404 `ORDER_NOT_FOUND`; PAY-12 без входа — 401; PAY-03 без ключа — 404.

