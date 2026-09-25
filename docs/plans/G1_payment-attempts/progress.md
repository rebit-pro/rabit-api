# G1 — журнал

## Точка продолжения

- Ветка `codex/g1-payment-attempts`, base `main` `49f40f9`, head — base (коммитов нет). PR ещё не создан.
- Рабочая копия: `/home/user/rabit-api-worktrees/g1-payment-attempts`.
- Документация: `plan.md`, `docs/plans/sberpay-start-plan/plan.md`, `MoreFoto/docs/05-rest-api/README.md` (PAY/COM).
- Завершено: S1 план и решения; S2 графы, PAY-10/11, канонический patch `docs/waves/g1/morefoto-contract.patch`.
- Сейчас: S3 — каркас модуля `morefoto.payment`.
- Следующий шаг: S4 — миграция foundation и репозитории.
- Блокеры: нет. Открытых решений нет. Ограничение: СБП в тестовом магазине не проверяется (`BLOCKED` до боевого).
- Рабочее дерево: чистое после коммита S2.
- Ключи тестового магазина: `~/.config/morefoto/yookassa-test.env` (права 600, вне git), проверено наличие двух переменных без вывода значений.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| G1-T01 | PASS (частично) | 2026-09-25 | `validate_graph` из `tools/verify-wave-graph.py` на `docs/waves/graph.json`: 51 волна, 112 ID, 35 WNN, `readyFromMain` включает G1. Отрицательные фикстуры `main` падают на известной ошибке пакета (исправление в PR #66). Канонический `wave_graph.py`: 51/112/35, готовы E6 и G1; `render-waves.py`, `build.py` (112 запросов), `validate.py`, `validate-postman.cjs` — OK, повторная сборка README воспроизводима; `git apply --check --reverse` patch — OK |
| G1-T02…T18 | PENDING | — | — |

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
