# G1 — журнал

## Точка продолжения

- Ветка `codex/g1-payment-attempts`, base `main` `49f40f9`, head — base (коммитов нет). PR ещё не создан.
- Рабочая копия: `/home/user/rabit-api-worktrees/g1-payment-attempts`.
- Документация: `plan.md`, `docs/plans/sberpay-start-plan/plan.md`, `MoreFoto/docs/05-rest-api/README.md` (PAY/COM).
- Завершено: план с решениями и тест-кейсами.
- Сейчас: S2 — графы и PAY-10/11.
- Следующий шаг: S3 — каркас модуля `morefoto.payment`.
- Блокеры: нет. Открытых решений нет. Ограничение: СБП в тестовом магазине не проверяется (`BLOCKED` до боевого).
- Рабочее дерево: новые `docs/plans/G1_payment-attempts/{plan,progress}.md`, не закоммичены.
- Ключи тестового магазина: `~/.config/morefoto/yookassa-test.env` (права 600, вне git), проверено наличие двух переменных без вывода значений.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| G1-T01…T18 | PENDING | — | — |

## Журнал

### 2026-09-25

- Проверен граф `main` `49f40f9`: из незавершённых волн по зависимостям готова только G1 (E5/F2 слиты), E6 в PR #66. `tools/verify-wave-graph.py` на `main` падает на фикстуре пакета (`bundle depends on unmerged wave outside the bundle`) — исправление уже в ветке E6.
- Пользователь взял G1, принял узкие D12/D09 (G1-D12-SCOPE, G1-D09-SANDBOX), выбрал модуль `morefoto.payment`. Зарегистрирован тестовый магазин ЮKassa, ключи сохранены вне репозитория.
- Изучены Commerce (E5: `mf_order`, `PaymentStatusEnum unpaid/pending/declined/paid`, `X-Order-Key`, COM-12 `late` → 422 FILTER_UNAVAILABLE), демо-экраны оплаты frontend, cron `api-cron` (supercronic).
- Составлен `plan.md` с решениями G1-DEC-01…08 на согласование.
- Пользователь согласовал решения: G1-DEC-03 — выбор способа на нашей странице (СБП), G1-DEC-06 — клиент по образцу `orteka.payment` (`PaymentClient` поверх общего `RebitHttpClient`), остальные — вариант (а).
- Проверено по документации ЮKassa (testing-and-going-live/testing): в тестовом магазине работают только карта и кошелёк ЮMoney, СБП и SberPay — только в боевом. Способы задаёт конфигурация `MOREFOTO_PAYMENT_METHODS`; для sandbox — `bank_card`.
- Изучен `/home/user/orteka/local/modules/orteka.payment` (`c3bcc1b32e`): `PaymentClient(OrtekaHttpClient, PaymentConnectionConfig)`, `PaymentClientFactory`, `SberPayClientProvider` + mapper. `RebitHttpClient` в `rebit.share` — порт `OrtekaHttpClient` с безопасным debug-логом без тела; таймауты зашиты в фабрике (30/60 с) — добавить необязательные параметры.
