# E7 — прогресс

## Точка продолжения

- Дата: 11.10.2026. Ветка `codex/e7-sales-windows` от `origin/main` fd7498a; рабочая копия `/home/user/rabit-api-worktrees/e7-sales-windows`. PR нет.
- Связанное: план ускоренного MVP — ветка `codex/ops-mvp-payment-print-email-plan`, локальная, не опубликована, в общем checkout ведётся другой сессией. Граф E7 пока только там.
- Завершено: план (de41609), backend (604d435), frontend и подвал (5f61914), оферта `2026-10-11` и E2E (1b33a89), верификатор миграции и правки E2E (c00776d), документация волны.
- Сейчас: блокеры ревью PR #162 исправлены, ждём повторного ревью.
- Следующий шаг: после ревью без блокеров — полный `make test-e2e` и visual (по графу — пользователь с другой моделью); перед merge — граф MVP в main и дата редакции оферты.
- Следующий шаг: ревью PR; после ревью без блокеров — полный `make test-e2e` и visual (по графу — пользователь с другой моделью).
- Блокеры merge: граф MVP с волной E7 не в `main`; полный E2E-гейт не запускался. Перед merge перенести дату редакции оферты на дату публикации, если она позже 11.10.2026.
- Рабочее дерево: всё закоммичено, кроме документации последнего шага до её коммита.
- Проверки: PHPUnit/PHPStan/cs-fixer — `php.sh` из scratchpad (docker `rabit-api-php-cli:d1-local`, volume `rabit-e7-vendor`, копия `rabit-j1-vendor`: composer.lock не менялся с J1); frontend — docker Playwright-образ с node_modules volume.

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
| E7-T17 | BLOCKED | 11.10.2026 | граф MVP с E7 не слит в main |
