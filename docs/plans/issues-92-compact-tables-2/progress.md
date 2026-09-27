# Issue #92 (срез 2) — журнал

## Точка продолжения

- Ветка `codex/issues-92-compact-tables-2`, worktree `/home/user/rabit-api-worktrees/issues-92-compact-tables-2`,
  base `origin/main` `18d10b1` (слит в `0abb702`). Issue [#92](https://github.com/rebit-pro/rabit-api/issues/92), draft PR
  [#160](https://github.com/rebit-pro/rabit-api/pull/160).
- Завершено: макеты одобрены; backend (сортировка трёх списков, `DELETE` списка и заказа, контракт Payment), фронтенд,
  unit/архитектурные тесты, live-спек `zzzzzzzzzzz-tables` (группа b), поправки селекторов старых спеков, верификатор.
- Сейчас: прогон live E2E группы b (`E2E_GROUPS=b E2E_STANDS=1`).
- Следующий шаг: по итогам группы b — группа a (старые спеки не менялись, но `UiDataTable` общий), затем PR из draft в review.
- Блокеров нет. Открытых решений нет.
- Backend-проверки из `api/`: `docker run --rm --network none -e XDEBUG_MODE=off -v "$PWD":/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app rabit-api-php-cli:d3-webp sh -c 'php vendor/bin/phpunit --testsuite=unit; php -d memory_limit=4G vendor/bin/phpstan analyse --configuration=phpstan.neon'`.
- Frontend-проверки из `frontend/`: `docker run --rm --network none -v "$PWD":/app -v rabit-issues92-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`.
- Форматирование фронтенда — только `npx eslint --fix <файлы>`, не `prettier --write`.
- Полный гейт: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`;
  частичный — с `E2E_GROUPS=b` (не гейт).

## Статус тест-кейсов

| ID | Статус | Дата | Комментарий |
|---|---|---|---|
| T01, T04 | PASS (unit) | 2026-09-27 | `StaffRequestRemovalTest`: автор, организатор; live — в группе b |
| T02 | PASS (unit) | 2026-09-27 | `testTransferredListStaysAsTransferHistory` → `409 REQUEST_TRANSFERRED` |
| T03 | PASS (unit) | 2026-09-27 | куратор/руководитель `FORBIDDEN`, чужой воспитатель `404` |
| T05, T16 | PENDING | 2026-09-27 | live `zzzzzzzzzzz-tables` T01 T05 T16 |
| T06, T09, T12–T15 | PENDING | 2026-09-27 | live `zzzzzzzzzzz-tables`; unit `DeleteOrderUseCaseTest` PASS |
| T07, T08 | PASS (unit) | 2026-09-27 | `testOrderWithMoneyStays`: `ORDER_PAID`, `PAYMENT_IN_PROGRESS`; оплаченного заказа без тестового магазина на стенде нет |
| T10, T11 | PASS (unit) | 2026-09-27 | маппер-тесты сортировки трёх модулей; live `422 INVALID_SORT` — в группе b |
| T17 | PASS | 2026-09-27 | `CleanControllerSource` для двух новых контроллеров |
| T18 | PASS | 2026-09-27 | `screens/impl` против `screens/after` на стенде заглушек |
| T19 | PENDING | 2026-09-27 | старые спеки заказов, платежей, handoff — группа b |

## Журнал

### 2026-09-27

- Запрос пользователя: остальные разделы сайта привести к табличному виду с CRUD, как каталог/учреждение/ссылки в
  срезе 1. Пример: в «Списках сотрудников» нельзя удалить заявку на проверке. Заказы и платежи — тоже таблицами.
- Проверено, что срез никто не взял: открытых PR и веток по #92, кроме слитого среза 1, нет.
- Меню боевого кабинета: табличными остаются «Списки сотрудников», «Заказы», «Платежи»; остальные экраны уже сделаны
  или только демо.
- Разбор API: удаления, отмены, смены статуса и сортировки нет ни в одном из трёх списков; порядок фиксирован
  (новые сверху). FK на `mf_order` — 5 таблиц RESTRICT (см. plan.md «Факты»). Оплата и сверка берут `orders->lock()`.
- Решения пользователя (ответы на вопросы): DEC-01 удалять неперенесённые списки (автор/организатор), DEC-02 удалять
  только неоплаченные заказы, DEC-03 выбор в заказах/платежах → CSV, DEC-04 сначала макеты.
- Прототип фронтенда: `StaffRequestTable`, `StaffOrderTable`, `PaymentTable` на `UiDataTable`; `ui/table-query.ts`
  (sort/page/pageSize ↔ URL и параметры API), `ui/csv.ts` (BOM, `;`), `tableMoment` (дата одной строкой);
  иконки `mdi-download-outline`, `mdi-file-delimited-outline`, `mdi-tune-variant`.
- Сбой по ходу: `npx prettier --write` по модулю без конфигурации проекта (она задана в `eslint.config.js`)
  переформатировал ~190 файлов; все отслеживаемые файлы восстановлены `git checkout`, правки применены заново, новые
  файлы пересозданы. Форматировать только `npx eslint --fix <файлы>`.
- Правки по скриншотам: даты одной строкой, ширины колонок, стиль ссылок; при выборе только оплаченных заказов вместо
  диалога «Удалить 0» — предупреждение.
- Проверки: `npm run check` — EXIT 0, ui-тесты 88/88; `npm run test:commerce` — 223/223 (образ Playwright, том
  `rabit-issues92-node`).
- Стенд: `pageerror Cannot read properties of undefined (reading 'length')` есть и на main (заглушка отдаёт пустой
  ответ на `/api/v1/group-links` и `/legal/consents/pending`) — артефакт стенда, не прототипа.
- Открыт draft PR [#160](https://github.com/rebit-pro/rabit-api/pull/160) (план `b2617ce`, прототип `8da11c3`).
- 2026-09-27, после одобрения макетов («скриншоты посмотрел, можно приступать к реализации»): DEC-05…09 по рекомендации.
- Слит `origin/main` `18d10b1` (`0abb702`), конфликтов нет.
- Backend: `OrderSortEnum`, `PaymentSortEnum`, `StaffRequestSortEnum` (белые списки, статусы — по смыслу через
  `FIELD`, стабильный хвост «сначала новые»); `DeleteOrderUseCase` (роль `organizer`, `lockPublic`, `ORDER_PAID`/
  `PAYMENT_IN_PROGRESS`, строки/ключи/оформление), контракт `Rebit\Share\Contracts\Payment\OrderPaymentRemovalInterface`
  (реализация в `morefoto.payment`), `StaffRequestWorkflow::remove` (код `REQUEST_TRANSFERRED` уже был у PUT),
  `DELETE /api/v1/orders/{id}`, `DELETE /api/v1/staff-requests/{id}`.
- Найдено: при заказе пишется согласие `mf_legal_consent` (без ПДн), верификатор E5 запрещал «осиротевшие» согласия.
  Решение пользователя DEC-10 — согласия оставлять; верификатор допускает ровно 2 записи на заказ из `removedOrderIds`.
- Комментарий `childrenWithOrders` уточнён: строки заказов теперь удаляются, но только сужают множество.
- Проверки: php-cs-fixer по изменённым файлам; `phpunit --testsuite=unit` — OK 888; phpstan — No errors;
  `npm run check` — EXIT 0 (88/88), `npm run test:commerce` — 226/226.
- E2E: новый спек `zzzzzzzzzzz-tables` в группе b (последний: дописывает `removedOrderIds` в `e5-orders.json`, который
  `zzzzz-orders` создаёт заново); старые спеки переведены на `[data-row-id]`/`ui-table-range`, `order-scope-filters`
  теперь на всей форме, шаги mock-Cucumber открывают список по `/^Открыть список /`.
- Скриншоты реализации — `screens/impl/` (12), совпадают с макетами.
