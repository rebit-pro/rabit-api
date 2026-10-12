# Issue #92 (срез 2) — журнал

## Точка продолжения

- Ветка `codex/issues-92-compact-tables-2`, worktree `/home/user/rabit-api-worktrees/issues-92-compact-tables-2`.
  Issue [#92](https://github.com/rebit-pro/rabit-api/issues/92), draft PR [#160](https://github.com/rebit-pro/rabit-api/pull/160).
  В ветку слит `origin/main` b085e8e (merge J4), коммит 700a623.
- Завершено: 2026-09-27 — backend (сортировка трёх списков, `DELETE` списка и заказа, контракт Payment), фронтенд,
  unit/архитектурные тесты, live-спек `zzzzzzzzzzz-tables`. Эти коммиты не были запушены до 2026-10-12.
  2026-10-12 — слияние main (конфликты E7/D4/E8/J4), DEC-07/08 по новым ответам пользователя, быстрые проверки зелёные.
- Сейчас: гейт `rabit-e2e-fc91403fb329` зелёный; PR #160 из draft в review; ревью 5491441893 — 1 блокер (формулы в CSV), исправлен следующим коммитом.
- Следующий шаг: merge PR #160 в main по команде пользователя «влей в main, если всё ок»; выкладка — отдельно (frontend и backend, миграций нет).
- Блокеров нет. Внешнее: тестовый магазин ЮKassa (#174) — гейт без него.
- Backend-проверки: `php-92.sh` в scratchpad сессии (docker `rabit-api-php-cli:d1-local`, vendor volume `rabit-e7-vendor`):
  `vendor/bin/phpunit`, `vendor/bin/phpstan analyse --configuration=tools/e2e/phpstan.neon`.
- Frontend-проверки из `frontend/`: `docker run --rm --network none -v "$PWD":/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`.
- Форматирование фронтенда — только `npx eslint --fix <файлы>`, не `prettier --write`.
- Полный гейт: `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor make test-e2e`.

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

### 2026-10-12

- Пользователь: «взять ветку PR 160, проверить и влить в main, если всё ок». Проверка: на GitHub — draft с планом и прототипом,
  конфликт с main; локально — 9 незапушенных коммитов реализации от 2026-09-27 (`2fa2de6`, `012a85f`, `ed4807c` и слияние).
  Сливать было нельзя; пользователь выбрал «доделать срез».
- Новые ответы пользователя: DEC-05 сервер, DEC-06 организатор (как раньше), DEC-07 — только выбранные строки (без
  «Выгрузить всё»), DEC-08 — только email («Надо учитывать. Остался только e-mail»). DEC-09 — последовательные запросы.
- Локальные коммиты запушены; в ветку слит `origin/main` b085e8e (700a623). Конфликты: `groups.json`, раннер (verify-orders
  + `zzzzzzzzzzz-tables`), `OrderRepository` (оба набора методов), `OrderInputMapperTest` (импорты), `zzzzz-orders.spec`
  (строки таблицы + email E8), `StaffOrdersLiveScreen` (таблица ветки).
- DEC-07/08: удалены «Выгрузить всё» заказов и платежей; колонка покупателя, сортировка (`OrderSortEnum::BUYER_EMAIL`),
  CSV и диалог удаления — email; спек таблиц создаёт заказы только с email и ищет их по нему; T14 снят.
  `staffBuyerLine` (E8) и его тест удалены как неиспользуемые.
- На `mf_order` ссылаются только E5/G1/J1-таблицы (новых FK с 27.09 нет); `mf_legal_consent` без FK — DEC-10.
- Проверки: PHPUnit 1130 OK; PHPStan OK; php-cs-fixer 0 из 38 файлов; `npm run check` OK (UI 88), commerce 237, build OK.
- Полный гейт `rabit-e2e-fc91403fb329` (044b55f, ЮKassa OFF): PASS — a 81, b 63 (в т. ч. `zzzzzzzzzzz-tables`), верификаторы прошли; 388 с.
- PR #160: описание обновлено, переведён из draft в review.
- Ревью 5491441893 (044b55f): блокер — CSV без защиты от формул: email покупателя проходит `BuyerPolicy` и может начинаться
  с `=` (`=HYPERLINK(…)@a.ru`), Excel выполнит его у сотрудника. Исправление: `csvCell()` ставит апостроф перед значением,
  начинающимся с `=`, `+`, `-`, `@`, табуляции или `\r`; unit-тест в `table-query.test.mjs`. Проверки: `npm run check` (UI 88),
  commerce 238. Гейт не перезапускался: изменён только вывод таких ячеек, значения спека (номера, даты, суммы, email `tables-…`)
  под правило не попадают.

