# E7 — прогресс

## Точка продолжения

- Дата: 11.10.2026. Ветка `codex/e7-sales-windows` от `origin/main` fd7498a, в неё влит `main` 3194db8 (граф MVP, PR #169); рабочая копия `/home/user/rabit-api-worktrees/e7-sales-windows`. PR #162 (https://github.com/rebit-pro/rabit-api/pull/162).
- Связанное: граф ускоренного MVP `docs/waves/graph.json` (в `main`), план `docs/plans/OPS-mvp-payment-print-email-plan/`, отчёт волны `docs/waves/e7/README.md`. Обновлённый состав волн после ручного тестирования — локальная ветка `codex/ops-telemos-feedback-plan` (8ec5c2f, не запушена): после merge E7 — D4.
- Завершено: план, backend, frontend и подвал, оферта `2026-10-11`, E2E-фикстуры, spec и верификаторы, документация; блокеры первого ревью (b9d0b5d), повторного (60e684c) и третьего (ссылка к провайдеру после срока, S9a); E7 отмечена `review` в графе.
- Сейчас: блокер третьего ревью (5483648771) исправлен, ответ в треде. Полный E2E-гейт: прогон 1 упал (дефект счётчика убранных печатных позиций исправлен, G1-T14 — внешний таймаут ЮKassa); идёт прогон 2.
- Следующий шаг: полный `make test-e2e` и visual desktop/mobile (по графу — пользователь с другой моделью).
- Блокеры merge: E2E-гейт и visual не запускались (E7-T12, T14–T16 PENDING). Перед merge перенести дату редакции оферты и `effectiveFrom` на дату публикации, если она позже 11.10.2026. Отметку `merged` в графе вносит следующая волна — по решению владельца D4.
- Открытые решения: нет.
- Рабочее дерево: всё закоммичено и запушено.
- Проверки: PHPUnit/PHPStan/cs-fixer — `php.sh` из scratchpad (docker `rabit-api-php-cli:d1-local`, volume `rabit-e7-vendor`); frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'` (от root); граф — `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Журнал

### 11.10.2026 — старт

- В графе `main` (fd7498a) волны E7 нет. Она определена в незамерженной ветке `codex/ops-mvp-payment-print-email-plan`. План MVP разрешает начинать E7 от актуального `main`: зависимости E3/E4/E5/F2/G1/J1 слиты.
- Других веток и PR по E7 нет (`git branch -a`, `gh pr list`).
- Worktree создан: `git worktree add -b codex/e7-sales-windows /home/user/rabit-api-worktrees/e7-sales-windows origin/main`.
- Инвентаризация (два read-only агента и ручная сверка):
  - единственный решающий срок — `UF_CLOSES_AT`;
  - `latePayment` считается от него же и закрывает файлы J1;
  - открытая попытка оплаты возвращается до проверки срока;
  - у `extend()` нет HTTP-маршрута;
  - подвал — `LegalFooter.vue`;
  - формулировки оферты завязаны на единый «приём заказов».
  Подробности — в плане, раздел «Факты инвентаризации».

### 11.10.2026 — решения и backend

- Пользователь выбрал: `closesAt` + `digitalSalesUntil` (DEC-01), запрет коррекции T0 после +7 (DEC-04), новая редакция оферты в E7 (DEC-10), подпись со ссылкой на https://rebit-pro.ru (DEC-11). План обновлён до кода (de41609).
- Backend (604d435, 70 файлов, +605/−188):
  - Organization: VO `GroupCalendar` (digital = max(T0+14, print), статус `digital`, `PRINT_SALES_CLOSED` для correct/extend), репозиторий, проекция, `GroupStateSql`, справочник, структура, галерейная группа; миграция `Version20261011120001` и её ID в E2E `prepare.php`;
  - Media: `GalleryAvailability` с `digital`, `digitalSalesUntil` в ответе галереи;
  - Commerce: `SalesWindowPolicy` (Domain), quote/order — `PRINT_SALES_CLOSED`, сроки заказа, `PayableOrderOutputDto.salesUntil/containsPrint`;
  - Payment: срок и `latePayment` по составу; проверка срока до повторного использования открытой попытки;
  - Handoff: `digitalSalesUntil` в ссылках, ответах и JSON повторов (старые записи → null), фильтр `digital`, счётчики; перенос в группу сотрудников закрыт с +7.
- E2E: фикстура `digital`, `closed` сдвинута на −15/−8/−1; `verify-links.php` (+14, отказ коррекции, три состояния на границах), `verify-orders.php` (PRINT_SALES_CLOSED, затем GALLERY_CLOSED).
- Проверки 11.10.2026:
  - `php.sh php vendor/bin/phpunit` — OK, 1099 тестов / 47053 проверки;
  - PHPStan `tools/e2e/phpstan.neon` — No errors;
  - php-cs-fixer по 68 изменённым файлам — исправлено 0;
  - `php -l` верификаторов и миграции — OK.

### 11.10.2026 — frontend, оферта, E2E

- Frontend (5f61914): чистый модуль `commerce/sales-windows.ts`; галерея, корзина (печать убирается в окне `digital`), оформление, страница заказа (блок смешанного заказа и перенос электронных фото в корзину по запомненной ссылке галереи), кабинет (ссылки, обзор, структура, учреждение, таймлайн), подвал со ссылкой на rebit-pro.ru.
- Оферта `2026-10-11` (1b33a89): изменены только определение приёма заказов, оплата по составу, передача печати и отказ от печати; registry дополнен, `reconsent=false`.
- E2E: `zzzz-storefront-windows.spec.ts` (группа b), `verify-sales-windows.php` в `VERIFIERS`, `verify-orders.php` (оплата по составу); правки `z-links-preparation` (byState.digital), `zzzzzzz-overview` (плитка), demo-шаг curator (`time` → `.first()`).
- Артефакты `vue-tsc --build` (vite.config.js/.d.ts, tsbuildinfo) и `dist/`, созданные моими проверками, удалены; дальше typecheck — только `npm run check` (`--noEmit`).
- Проверки 11.10.2026:
  - frontend `npm run check` (docker Playwright 1.52, том `rabit-e7-node`) — exit 0, test:ui 88/88; `test:commerce` — 230/230; `build-only` — OK;
  - PHPUnit — OK 1099 / 47058 (после оферты); php -l новых верификаторов — OK;
  - `python3 -m unittest discover -s tools/tests` — 25/26: `test_f2_prepares_first_and_reports_later_in_one_group` падает и на чистом main fd7498a (после #92 `cabinet-tables` сортируется раньше `z-links-preparation`), не регрессия E7.

### 11.10.2026 — PR и правка таблиц

- PR #162 открыт (https://github.com/rebit-pro/rabit-api/pull/162).
- Открытый #156: узкие колонки дат на «Ссылки и сроки» уже ломают заголовки. Отдельная колонка «Электронные до» усугубила бы это, поэтому срок электронных показан подстрокой под «Печать до» (ссылки и учреждение), ширины колонок как в main. `npm run check` — exit 0, UI 88/88.
- Неблокирующая находка вне E7 — issue #163 (падающий на main тест порядка F2 в `tools/tests`).

### 11.10.2026 — ревью PR #162

- `/code-review high 162` и ручная проверка правил CLAUDE.md. Опубликован review с 4 блокерами:
  - нет class-level phpDoc у `CalendarProjection`, устаревший phpDoc `ListGroupLinksUseCase` (правило CLAUDE.md);
  - `OrderPaymentPanel`: заказ на 0 ₽ в закрытой группе показывает «оплатить нельзя»;
  - `storefront.ts`: незащищённый `localStorage.setItem` роняет загрузку галереи;
  - `reorderLines` переносит комплект-подарок по полной цене.
- Неблокирующие замечания вынесены в issues #164–#167 (дублей нет).
- Отклонены: проверка срока до выдачи открытой попытки (решение DEC-06) и лишний индексный запрос `containsPrint`.

### 11.10.2026 — исправление блокеров ревью

- B1: class-level phpDoc у `CalendarProjection`; phpDoc `ListGroupLinksUseCase` описывает `digital` и `closingSoon` по сроку печати.
- B2: `orderSalesBlock` возвращает `null` для оплаченного заказа и для заказа на 0 ₽; недостижимый тернарий `closed` в `OrderPaymentPanel` убран.
- B3: запись в `localStorage` (`store()`) в `storefront.ts` — best effort в try/catch; `addToStoredCart` возвращает успех, при ошибке блок заказа показывает подсказку вместо перехода.
- B4: `digitalLines` блока заказа — только купленные электронные строки, без `coveredByGift`; кнопка переноса скрыта, если таких строк нет.
- Проверки 11.10.2026:
  - frontend `npm run check` — exit 0, UI 88/88; `test:commerce` — 231/231 (новый кейс 0 ₽ и подарка);
  - php-cs-fixer по 2 файлам — 0 исправлений;
  - PHPUnit Organization и Handoff — OK, 215 тестов.

### 11.10.2026 — повторное ревью PR #162 и его блокеры

- Повторное ревью (review 5483325318) нашло 2 блокера:
  - R1: b9d0b5d вставил строку ошибки между кнопкой переноса и подсказкой, и `v-else` подсказки стал относиться к ошибке: при запомненной ссылке галереи покупатель видел и кнопку, и «откройте ссылку на галерею»;
  - R2: после слияния графа MVP в `main` (PR #169) точка продолжения, README, план и описание PR устарели.
- R1: в `OrderPaymentPanel.vue` восстановлена пара `v-btn v-if="galleryToken"` / подсказка `v-else`; ошибка сохранения — отдельная строка `order-reorder-error`. Автотеста шаблона нет (node-тесты не монтируют компоненты); блок увидит visual-проверка заказа.
- R2: влит `main` 3194db8 (merge без конфликтов, только docs и `tools/verify-wave-graph.py`). E7 в графе — `review` (как E6/G1 в своих PR), решения записаны в `baseline.decisionEvidence["E7-DEC-01-11"]`; таблица `docs/waves/README.md` — «На ревью, PR #162». Обновлены план (S9, T17), README волны, verification.json, точка продолжения, описание PR. Канонический `../MoreFoto` не менялся: патч синхронизации MVP туда ещё не применён, E7 в нём нет.
- Проверки 11.10.2026 после смены base:
  - frontend `npm run check` — exit 0, UI 88/88; `test:commerce` — 231/231;
  - `python3 tools/verify-wave-graph.py docs/waves/graph.json` — exit 0, 55 волн, 122 API ID, 35 WNN, readyMvpFromMain=[E7];
  - `python3 -m unittest discover -s tools/tests` — 25/26, известное падение #163 (как на `main`);
  - `git diff --check` — чисто. Backend-код с b9d0b5d не менялся, а `main` принёс только документы и проверку графа, поэтому PHPUnit/PHPStan не перезапускались.

### 11.10.2026 — третье ревью PR #162 и его блокер

- Третье ревью (review 5483648771, на 0e8f404): блокеры повторного ревью закрыты, новый блокер один. Заказ с печатью можно было оплатить после закрытия печати через начатую до срока попытку:
  - кнопка «Проверить начатую оплату» в блоке `order-sales-closed` вела на экран возврата, а `GET` попытки отдавал `redirectUrl` для `pending`, и экран показывал «Продолжить оплату»;
  - точный повтор того же `Idempotency-Key` возвращал старую попытку раньше `assertCanStart`, и `pay()` уводил на страницу провайдера.
- Неблокирующие замечания: #170 (повторный заказ и комплект ребёнка), #171 (скрытая подпись `MfTimeline`), #172 (лишние запросы при старте оплаты). Остальные уже в #164–#167.
- Исправление (S9a, только backend): `PaymentAttemptPolicy::salesOpen` — единое правило «срок продаж состава не наступил» для `assertCanStart`/`canStart`. `PaymentOutputMapper::attempt` получает этот признак и после срока не выдаёт `redirectUrl`; признак передают `GetPaymentAttemptUseCase` и `StartPaymentAttemptUseCase`. Статус попытки по-прежнему сверяется, поэтому оплату, прошедшую до срока, покупатель увидит. Экран возврата без ссылки уходит в «Проверяем оплату», frontend не менялся. Остаточный риск: страница провайдера, открытая до срока, ещё может завершить платёж с `latePayment` (записано в плане).
- Проверки 11.10.2026:
  - `php.sh php vendor/bin/phpunit` — OK, 1101 тест / 47068 проверок;
  - PHPStan `tools/e2e/phpstan.neon` (`XDEBUG_MODE=off`, volume `rabit-e7-phpstan`) — No errors;
  - php-cs-fixer по 7 изменённым файлам — исправлено выравнивание phpDoc в `PaymentOutputMapper`, повторный dry-run чистый;
  - `git diff --check` — чисто.

### 11.10.2026 — полный E2E-гейт, прогон 1

- По просьбе пользователя гейт запущен до повторного ревью: `E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor make test-e2e` на f0c2c1d, прогон `rabit-e2e-030ef2a90e3f`, 343.7 s.
- Результат FAIL. Все статические шаги, PHPStan, PHPUnit, схема, фикстуры и workers прошли. Группа b — 55 passed, 3 failed, 2 did not run; группа a отменена после падения b.
  - `zzzz-storefront-windows` desktop: в корзине нет «Печатные позиции убраны из корзины: 1». Это дефект E7: `loadGallery` вызывает `loadStorefront` на каждой странице галереи. Вторая загрузка (переход в корзину) читает уже очищенную корзину, и `droppedPrint` обнуляется.
  - `zzzz-storefront-windows` mobile: две опции «E7 Электронный кадр». Это следствие первого падения: новый worker повторил `beforeAll` и создал продукты второй раз.
  - G1-T14: попытка СБП осталась `unknown`. Первый `POST` в тестовый магазин ЮKassa упал по таймауту 8 s (`HTTP Request failed`, `durationMs` 8026), повторы сверки получили 400. Это внешний сбой, не E7; после прогона `api.yookassa.ru` отвечает за 0,06 s.
- Исправление: `loadStorefront` прибавляет счётчик предыдущего состояния той же группы, `forgetLiveCart` сбрасывает его после заказа, как `clearLive`. Frontend `npm run check` — exit 0, UI 88/88; `test:commerce` — 231/231.

### 11.10.2026 — обновлённый состав волн

- Владелец обновил состав волн после ручного тестирования (ветка `codex/ops-telemos-feedback-plan`, 8ec5c2f, локальная): после merge E7 делаем D4 (коррекция разметки переданной группы без заказов; зависимости D2, D3, F2, E5, E7). E7 этим планом не расширяется; в его графе E7 тоже `review`.
- Пробное слияние той ветки с E7 (60e684c): `graph.json` сливается автоматически, конфликт только в таблице `docs/waves/README.md` (обе ветки меняли строку E7). Правка таблицы из E7 убрана — статус E7 хранится в графе, план владельца переписывает таблицу целиком. Следующая волна в документах E7 — D4 вместо L1.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| E7-T01, T02, T03 | PASS | 11.10.2026 | PHPUnit `GroupCalendarTest`, `GroupCalendarDeliveryTest`, `GalleryAvailabilityTest` |
| E7-T04 | PASS | 11.10.2026 | PHPUnit `GroupStateSqlTest` |
| E7-T05, T06, T07 | PASS | 11.10.2026 | PHPUnit `StorefrontQuoteTest`, `OrderCheckoutUseCaseTest`, `OrderPaymentsTest` |
| E7-T08, T09 | PASS (unit) | 11.10.2026 | PHPUnit `PaymentAttemptPolicyTest`, `StartPaymentAttemptTest`; поздний платёж по `salesUntil` |
| E7-T10 | PASS | 11.10.2026 | PHPUnit `GroupLinkWorkflowTest`, `GroupLinkContractTest`, `StaffTransferPolicyTest` |
| E7-T11 | PASS | 11.10.2026 | Architecture-тесты модулей входят в PHPUnit 1099 OK |
| E7-T12 | PENDING | — | `verify-sales-windows.php` написан; запуск в `make test-e2e` |
| E7-T13 | PASS | 11.10.2026 | `tests/commerce/sales-windows.test.mjs` 8/8 (после блокеров ревью), `npm run check`, `test:commerce` 231/231 |
| E7-T14, T15 | PENDING | — | spec и `verify-orders.php` написаны; HTTP-оплата PRINT_SALES_CLOSED без тестового магазина проверяется на уровне сервисов |
| E7-T16 | PENDING | — | скриншоты e7-desktop/mobile-gallery/cart появятся в гейте |
| E7-T17 | PASS | 11.10.2026 | `python3 tools/verify-wave-graph.py docs/waves/graph.json` exit 0 после слияния `main` 3194db8; E7 `review` |
| E7-T18 | PASS | 11.10.2026 | PHPUnit 1101 OK: `StartPaymentAttemptTest` (повтор ключа после срока без `redirectUrl`), `PaymentReconcilerTest::testReturnPageDoesNotOfferTheProviderPageAfterTheSalesDeadline`, `PaymentAttemptPolicyTest::testSalesAreOpenStrictlyBeforeTheDeadline` |
