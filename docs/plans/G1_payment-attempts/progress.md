# G1 — журнал

## Точка продолжения

- Ветка `codex/g1-payment-attempts`, base `main` `49f40f9`, head `675928a`. PR ещё не создан.
- Рабочая копия: `/home/user/rabit-api-worktrees/g1-payment-attempts`.
- Документация: `plan.md`, `docs/plans/sberpay-start-plan/plan.md`, `MoreFoto/docs/05-rest-api/README.md` (PAY/COM).
- Завершено: S1–S11 — план, графы, модуль `morefoto.payment` (миграция, контракт с Commerce, сверка, webhook, реестр, cron), frontend покупателя и сотрудника, быстрые проверки.
- Сейчас: S12 — E2E-стенд (регистрация модуля, ключи тестового магазина, спецификация и verifier).
- Следующий шаг: подключить модуль и переменные оплаты в `api/tools/e2e/prepare.php` и runner.
- Блокеры: нет. Открытых решений нет. Ограничение: СБП в тестовом магазине не проверяется (`BLOCKED` до боевого).
- Рабочее дерево: чистое после коммита `675928a`. Корневые каталоги `api/vendor` и `api/var/*` созданы контейнером от root (игнорируются git).
- Ключи тестового магазина: `~/.config/morefoto/yookassa-test.env` (права 600, вне git), проверено наличие двух переменных без вывода значений.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| G1-T01 | PASS (частично) | 2026-09-25 | `validate_graph` из `tools/verify-wave-graph.py` на `docs/waves/graph.json`: 51 волна, 112 ID, 35 WNN, `readyFromMain` включает G1. Отрицательные фикстуры `main` падают на известной ошибке пакета (исправление в PR #66). Канонический `wave_graph.py`: 51/112/35, готовы E6 и G1; `render-waves.py`, `build.py` (112 запросов), `validate.py`, `validate-postman.cjs` — OK, повторная сборка README воспроизводима; `git apply --check --reverse` patch — OK |
| G1-T02, T03, T17 | PASS (unit) | 2026-09-25 | `StartPaymentAttemptTest`: попытка выбранным способом, return_url без ключа, повтор/двойной клик/вторая вкладка — одна попытка, конфликт ключа, 7 отказов без вызова провайдера |
| G1-T04 | PASS (unit) | 2026-09-25 | Таймаут → `unknown`, повтор через 60 с с тем же ключом провайдера; после 24 ч — `provider_key_expired` без повтора |
| G1-T05, T06, T07 | PASS (unit) | 2026-09-25 | `PaymentReconcilerTest`: один факт, `paid`, повтор без изменений; 4 варианта чужого ответа не оплачивают заказ; поздний платёж `latePayment` |
| G1-T08 | PASS (unit) | 2026-09-25 | Повтор уведомления, чужой платёж `unknown_payment`, неподдерживаемое событие, чужой провайдер 404 |
| G1-T11 | PASS (unit) | 2026-09-25 | Cron берёт только попытки с наступившим сроком; PAY-02 сверяет не чаще раза в 5 с |
| G1-T12 | PASS (unit) | 2026-09-25 | `PaymentArchitectureTest`: 3 чистых контроллера, канал `payment`, нет `mf_order*`/`Morefoto\Commerce` в Payment, нет Bitrix/HTTP в Application/Domain, phpDoc UseCase/Service |
| G1-T13 | PASS (частично) | 2026-09-25 | Прямой вызов API тестового магазина скриптом (ключи из файла, не выводились): `bank_card` → `pending` + `yoomoney.ru`, повтор ключа → тот же платёж, `sbp` → 400, неизвестный `GET` → 404. Оплата картой через страницу — в E2E |
| G1-T18 | PASS (unit) | 2026-09-25 | `YooKassaClientTest`: Basic-авторизация, `Idempotence-Key`, классификация 400/401/404 → отказ, 429/500/таймаут/JSON → `unknown`, секрет не в сообщении |
| G1-T16 | PASS | 2026-09-25 | Backend: phplint 1047 файлов OK; PHPStan (tools/e2e/phpstan.neon) — No errors; PHPUnit — OK 718 тестов / 3644 проверки; php-cs-fixer — исправлено 8 из 112, повтор чист. Frontend: `npm run check` (lint, stylelint, typecheck, typecheck:e2e, test:ui) — exit 0; `test:commerce` — 188/188; `build-only` — OK |
| G1-T01…T18 прочие | PENDING | — | T09, T10, T14, T15 — HTTP/браузер в `make test-e2e` |

## Журнал

### 2026-09-25

- Проверен граф `main` `49f40f9`: из незавершённых волн по зависимостям готова только G1 (E5/F2 слиты), E6 в PR #66. `tools/verify-wave-graph.py` на `main` падает на фикстуре пакета (`bundle depends on unmerged wave outside the bundle`) — исправление уже в ветке E6.
- Пользователь взял G1, принял узкие D12/D09 (G1-D12-SCOPE, G1-D09-SANDBOX), выбрал модуль `morefoto.payment`. Зарегистрирован тестовый магазин ЮKassa, ключи сохранены вне репозитория.
- Изучены Commerce (E5: `mf_order`, `PaymentStatusEnum unpaid/pending/declined/paid`, `X-Order-Key`, COM-12 `late` → 422 FILTER_UNAVAILABLE), демо-экраны оплаты frontend, cron `api-cron` (supercronic).
- Составлен `plan.md` с решениями G1-DEC-01…08 на согласование.
- Пользователь согласовал решения: G1-DEC-03 — выбор способа на нашей странице (СБП), G1-DEC-06 — клиент по образцу `orteka.payment` (`PaymentClient` поверх общего `RebitHttpClient`), остальные — вариант (а).
- Проверено по документации ЮKassa (testing-and-going-live/testing): в тестовом магазине работают только карта и кошелёк ЮMoney, СБП и SberPay — только в боевом. Способы задаёт конфигурация `MOREFOTO_PAYMENT_METHODS`; для sandbox — `bank_card`.
- Изучен `/home/user/orteka/local/modules/orteka.payment` (`c3bcc1b32e`): `PaymentClient(OrtekaHttpClient, PaymentConnectionConfig)`, `PaymentClientFactory`, `SberPayClientProvider` + mapper. `RebitHttpClient` в `rebit.share` — порт `OrtekaHttpClient` с безопасным debug-логом без тела; таймауты зашиты в фабрике (30/60 с) — добавить необязательные параметры.
- S2: G1 `inProgress`, gates `[D10]`, владелец `morefoto.payment` во всех волнах и `paymentIntegration`, `decisionEvidence` G1-D12-SCOPE/G1-D09-SANDBOX/G1-DEC, `endpointCount` 112. В каноническом `build.py`: PAY-01 (`paymentMethod`, коды ошибок), PAY-02 (сверка не чаще 5 с), PAY-03 (`paymentMethods`, без пересчёта), PAY-07 (тело ЮKassa, дедупликация `event:object.id`), новые PAY-10/11. Канонический план уже содержал незамерженные правки E6 (PR #66), поэтому patch снят относительно его текущего состояния (снимок до правок в scratchpad сессии).
- S3–S9 (коммит `5a05d55`): модуль `morefoto.payment` — миграция `Version20260925120001` (3 таблицы, `PAID_AT`/`LATE_PAYMENT` в `mf_order`), контракт `Rebit\Share\Contracts\Commerce\OrderPaymentInterface` и реализация `OrderPayments` в Commerce, `PaymentReconciler`, 7 UseCase, `PaymentClient` поверх `RebitHttpClient` + `YooKassaClientProvider`, SQL-хранилища, 3 контроллера, cron `app:payment:reconcile`. COM-11/12/13 отдают `paidAt`/`latePayment`, фильтр `late` в COM-12 работает. В `RebitHttpClientFactory::create()` добавлены необязательные таймауты (ЮKassa: 5/15 с).
- Команды быстрых проверок backend (том `rabit-g1-vendor` скопирован из `rabit-e6-vendor`):
  `docker run --rm --network none --env XDEBUG_MODE=off --mount type=bind,source=$PWD/api,target=/app --mount type=volume,source=rabit-g1-vendor,target=/app/vendor --mount type=bind,source=/home/user/rebit-p2p/api/public/bitrix/modules,target=/kernel/modules,readonly --workdir /app --entrypoint sh rabit-api-php-cli:d1-local -c 'composer dump-autoload --no-scripts --no-plugins -q; vendor/bin/phplint; vendor/bin/phpstan analyse --configuration=tools/e2e/phpstan.neon --no-progress --memory-limit=1G; vendor/bin/phpunit --colors=never'`
- S10 (коммит `675928a`): frontend — панель оплаты на странице заказа (способы из PAY-03, повтор тем же `Idempotency-Key`, переход на ЮKassa), страница возврата `/orders/payment/:attemptId` с опросом PAY-02, реестр `/cabinet/payments` и карточка платежа, пункт меню «Платежи» по `order.read`, оплата в карточке заказа сотрудника. Иконки `mdi-close-circle`, `mdi-credit-card-outline` добавлены в реестр.
- Команда быстрых проверок frontend: `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e6-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce && npm run build-only'`.
- Замечание: `zzzzz-orders.spec.ts:251` ждёт `FILTER_UNAVAILABLE` для `late=true` — обновить в S12.
