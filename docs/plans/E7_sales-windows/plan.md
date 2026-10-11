# E7 — два срока продаж: печать 7 дней, электронные фото 14 дней

## Цель и контекст

От единого T0 группы (зафиксированный факт передачи ссылки F2) сервер и UI разрешают покупку печатной продукции до T0+7 дней (`printSalesUntil`), а электронных фотографий до T0+14 дней (`digitalSalesUntil`). Оплаченные файлы J1 по-прежнему доступны календарный месяц от оплаты. `deliveryDueAt` по-прежнему означает срок физической передачи печати.

- Ветка `codex/e7-sales-windows` от `origin/main` fd7498a, 11.10.2026 в неё влит `main` 3194db8 (граф MVP); рабочая копия `/home/user/rabit-api-worktrees/e7-sales-windows`.
- Источник волны — граф ускоренного MVP (`docs/waves/graph.json`), слит в `main` 11.10.2026 через PR #168/#169 (3194db8). Зависимости E3, E4, E5, F2, G1 и J1 слиты; решения D07/D10 приняты 11.09.2026.
- Правила MVP 1–4 и 10 из `docs/plans/OPS-mvp-payment-print-email-plan/plan.md`.
- Порядок владельца от 11.10.2026: E7 → L1 → L3 → G2 → N3. Обновлён владельцем в тот же день после ручного тестирования (план `codex/ops-telemos-feedback-plan`, в `main` ещё не слит): после merge E7 берётся D4, затем независимо E8/F3/J4; L1 — после сверки D06. Новые требования E7 не расширяют.

## Факты инвентаризации (11.10.2026)

- Сейчас в `b_hlbd_mf_group` один срок продаж `UF_CLOSES_AT` = T0+7 и `UF_DELIVERY_DUE_AT` = closesAt+7. Только closesAt участвует в решениях. Тип товара в проверках срока не участвует.
- Решения по closesAt:
  - Media `GalleryAvailability`: состояния preparing/open/closed;
  - Commerce `StorefrontQuote`: 409 `GALLERY_CLOSED` для всей корзины;
  - `CreateOrderUseCase`: 409 `GALLERY_CLOSED`;
  - Payment `PaymentAttemptPolicy`: 409 `PAYMENT_CLOSED`, `canPay`, `isLate`;
  - Handoff `StaffTransferPolicy`: 409 `TARGET_GROUP_CLOSED`;
  - SQL-состояния `GroupStateSql`: списки, счётчики, `closingSoon` за 72 ч.
- **Скрытый эффект.** `PaymentReconciler` ставит `latePayment = paidAt >= closesAt`, а J1 при `latePayment` выдаёт состояние `review` без файлов. Без изменения цифровой заказ, оплаченный на 10-й день, остался бы без файлов.
- Открытая попытка оплаты возвращается до проверки срока (`StartPaymentAttemptUseCase::reserve`).
- У `extend()` нет HTTP-маршрута. Его вызывают только `ChangeGroupCalendarUseCase`, unit-тест и E2E verifier.
- Публичный подвал — `LegalFooter.vue` в `BlankLayout`: все публичные страницы (галерея, корзина, заказ, оплата, /legal, /login).
- В оферте `offer/2026-09-25.md` есть строки «Приём заказов — период… Даты указаны на странице группы», «передаётся… в течение семи календарных дней после окончания приёма заказов группы» и «до окончания приёма заказов группы… вправе отказаться». При двух сроках формулировка «окончание приёма заказов» становится неоднозначной.

## Решения

Статус на 11.10.2026: пользователь выбрал DEC-01, DEC-04, DEC-10 (рекомендованные варианты) и DEC-11 со ссылкой на https://rebit-pro.ru. Остальные решения — рекомендованные варианты из этого плана; при возражении план обновляется до кода.

- **E7-DEC-01. Хранение и имена.**
  - Новая колонка `UF_DIGITAL_SALES_UNTIL` (HL-поле, DATETIME UTC). `UF_CLOSES_AT` остаётся сроком печати.
  - В API к существующим проекциям календаря добавляется `digitalSalesUntil`. Поле `closesAt` сохраняет значение и документируется как «окончание приёма печати» (= printSalesUntil).
  - Альтернатива — переименовать `closesAt` → `printSalesUntil` во всех контрактах. Это чище по именам, но даёт большой ломающий diff в API/frontend/E2E и сохранённых JSON повторов.
- **E7-DEC-02. Правило сроков.**
  - `print = T0+7`, `digital = max(T0+14, print)` по календарным дням Europe/Moscow, с точностью до секунды; `deliveryDueAt = print+7` (без изменений).
  - Инвариант хранения: `sentAt < closesAt <= digitalSalesUntil`, `closesAt < deliveryDueAt`.
  - `extend(newPrint)` (без HTTP-маршрута): `digital = max(digital, newPrint)`.
  - `correctLinkSent`: пересчитывает оба срока от нового T0 и сохраняет прежнюю логику продлённой печати.
- **E7-DEC-03. Состояния.**
  - Группа и галерея: `preparing` → `open` (печать и электронные) → `digital` (только электронные, `closesAt <= now < digitalSalesUntil`) → `closed` (`now >= digitalSalesUntil`).
  - `GroupStateSql`, фильтры и счётчики получают `digital`. `closingSoon` по-прежнему считает срок печати открытых групп за 72 ч.
  - Перенос в группу сотрудников (`TARGET_GROUP_CLOSED`) запрещён с момента закрытия печати, как и сейчас.
- **E7-DEC-04. Коррекция после закрытия печати.** `correctLinkSent` и `extend` возвращают 409 `PRINT_SALES_CLOSED`, если текущий срок печати уже наступил. Причина: при +7 L1 формирует снимок печати, и исправление T0 не должно повторно открыть печать. До +7 исправление работает как сейчас. Альтернатива — не ограничивать до L1.
- **E7-DEC-05. Классификация состава.**
  - Печать — строки `physical`. Электронные — `digital` и `bundle`; подарок — это bundle, у него нет отдельного вида. Порог подарка считается по физическим позициям, поэтому после +7 подарок невозможен автоматически.
  - Quote и создание заказа: состояние `closed`/`preparing` → 409 `GALLERY_CLOSED` (как сейчас); состояние `digital` с хотя бы одной строкой physical → 409 `PRINT_SALES_CLOSED`.
  - Каталог по-прежнему отдаётся и в закрытой группе.
- **E7-DEC-06. Оплата по составу заказа.**
  - Commerce отдаёт в `PayableOrderOutputDto` вместо `closesAt` поля `salesUntil` (печать, если в заказе есть physical, иначе digital) и `containsPrint`.
  - Payment: `assertCanStart`/`canStart`/`isLate` сравнивают с `salesUntil`. При закрытии ошибка `PRINT_SALES_CLOSED` для заказа с печатью и `PAYMENT_CLOSED` для остальных.
  - Проверка срока выполняется **до** повторного использования открытой попытки. Точный повтор с тем же ключом идемпотентности остаётся идемпотентным.
  - Оплаченный снимок не меняется.
- **E7-DEC-07. Неоплаченный смешанный заказ после +7.**
  - Страница заказа показывает, что печать закрыта, перечисляет печатные позиции и предлагает оформить электронные позиции новым заказом.
  - Если браузер уже открывал галерею этой группы (токен запоминается на устройстве), кнопка переносит digital/bundle-строки в корзину группы и открывает её. Покупатель явно оформляет новый заказ по новым ценам.
  - На другом устройстве показывается текст: открыть ссылку куратора.
  - Старый заказ не отменяется и не меняется. Нового endpoint нет.
- **E7-DEC-08. Миграция существующих групп.**
  - Foundation-миграция добавляет поле и заполняет `UF_DIGITAL_SALES_UNTIL = GREATEST(UF_SENT_AT + 14 дней, UF_CLOSES_AT)` только для переданных групп с пустым значением. MSK без перехода на летнее время, поэтому +14 суток в UTC равны +14 календарным дням в Москве. Затем добавляются CHECK-ограничения.
  - Печать и доставка не меняются, перезапуска T0 нет.
  - Группы между +7 и +14 дней из `closed` станут `digital`. Список таких групп оператор смотрит заранее read-only SQL-запросом из `docs/waves/e7/README.md`.
- **E7-DEC-09. Журнал передачи ссылки** (`mf_group_link_history`) новых колонок не получает. Событие фиксирует T0, печать и доставку; срок электронных выводится по правилу DEC-02. Ответы transmit/correct и сохранённые JSON повторов получают `digitalSalesUntil`; у старых записей поле читается как null.
- **E7-DEC-10. Оферта.** В E7 добавляется новая редакция оферты; её текст показывается пользователю до публикации: приём печати — 7 календарных дней, электронных фото — 14 от передачи ссылки; передача печати — 7 дней после окончания приёма печати; отказ от печати — до окончания приёма печати. 
- **E7-DEC-11. Подвал.** В `LegalFooter` добавляется подпись «Разработано в ReBit Studio» со ссылкой на https://rebit-pro.ru (новая вкладка, `rel="noopener"`). URL подтвердил пользователь 11.10.2026. Сведения о продавце и legal-ссылки сохраняются.

## Scope

1. **Organization.** VO `GroupCalendar` (digital, статус `digital`, правило DEC-02/04), `GroupCalendarRepository`, `CalendarProjection`, `GroupStateSql`, `GalleryGroup`, `GroupDirectory`, `StructureRepository`/`GroupOutputDto`. Контракты `GroupCalendarOutputDto` и `GalleryGroupOutputDto` получают `digitalSalesUntil`. Миграция foundation и её ID в `api/tools/e2e/prepare.php`.
2. **Media.** `GalleryAvailability` возвращает `digital`; `GalleryResultDto` получает `digitalSalesUntil`.
3. **Commerce.** `StorefrontQuote` (`PRINT_SALES_CLOSED`), `CreateOrderUseCase`, `OrderPeriods` + DTO (`digitalSalesUntil`), `OrderPayments` (`salesUntil`, `containsPrint`).
4. **Payment.** `PaymentAttemptPolicy`, `StartPaymentAttemptUseCase` (порядок проверок), `GetPaymentQuoteUseCase`, `PaymentReconciler`.
5. **Handoff.** Выдача `digitalSalesUntil` в ссылках, ответах transmit/correct и JSON повторов; фильтр состояния `digital`; ошибка `PRINT_SALES_CLOSED` при коррекции.
6. **Frontend (live).**
   - Галерея: шапка с двумя сроками, плашка `digital`, печать в селекторе недоступна.
   - Корзина: печатные строки исключены из quote в `digital`. Оформление: тексты условий и коды ошибок.
   - Страница заказа: сроки, блок смешанного заказа с переносом в корзину.
   - Кабинет: ссылки (колонка и плитка, клиентский расчёт +7/+14), обзор, структура, карточка учреждения, `MfTimeline`, статусы.
   - Demo-данные минимально дополняются для совместимости типов.
7. **Подвал** «Разработано в ReBit Studio».
8. **Оферта** — новая редакция (DEC-10), текст согласуется с пользователем.
9. **Тесты.** PHPUnit (Domain/UseCase/Architecture), frontend `node --test`, E2E-фикстура `digital`, spec/verifier сроков.
10. **Документация.** `docs/waves/e7/README.md` (runbook предпросмотра, выкладка, откат), `verification.json`; в графе E7 — `review` и запись решений `E7-DEC-01-11`. Отметку `merged` вносит следующая волна (D4).

## Исключено

- Снимок печати, экспорт и email в Сивму (L1/L3), чеки (G2), приёмка N3.
- Отдельная post-MVP процедура коррекции и продления после снимка печати.
- Переименование `closesAt` во всех контрактах (если DEC-01 принят по рекомендации).
- Изменение срока скачивания J1 и логики `deliveryDueAt`.
- Новые колонки журнала передачи ссылки (DEC-09).
- Расширение demo-сценариев Cucumber новыми состояниями сверх совместимости.

## Зависимости, риски и ограничения

- Граф с волной E7 должен был попасть в `main` раньше PR E7, чтобы PR не ссылался на несуществующий ID. Условие выполнено 11.10.2026 (PR #169, 3194db8); `main` влит в ветку без stacked PR.
- После выкладки сохранённые расчёты корзины станут устаревшими (`QUOTE_STALE`), потому что отпечаток включает данные группы. Они живут 15 минут, так что риск низкий.
- Миграция откроет продажу электронных фото группам между +7 и +14 днями. Это и есть новое правило, но оператор видит список заранее.
- Граница секунда-в-секунду: везде `now >= deadline` означает «закрыто».
- Оплата, подтверждённая провайдером после срока, по-прежнему получает `latePayment`. Ручное решение остаётся за I3/L1.
- E2E по графу выполняет пользователь с другой моделью; полный прогон делается после ревью без блокеров.

## Checklist

- [x] S0. Инвентаризация, план и progress.
- [x] S1. Согласование DEC-01…11 (11.10.2026).
- [x] S2. Organization: VO, репозиторий, проекции, SQL-состояния, миграция, контракты.
- [x] S3. Media и Commerce: состояние галереи, quote/order, сроки заказа, оплачиваемый заказ.
- [x] S4. Payment: срок по составу, порядок проверок, поздняя оплата.
- [x] S5. Handoff: выдача сроков, фильтр, ограничение коррекции.
- [x] S6. Frontend: галерея, корзина, оформление, заказ, кабинет, подвал.
- [x] S7. Новая редакция оферты `2026-10-11` (DEC-10); текст передан пользователю на проверку в PR.
- [x] S8. PHPUnit, PHPStan, php-cs-fixer, frontend check/test; E2E-фикстуры, spec и верификаторы написаны.
- [x] S9. Документация волны (`docs/waves/e7/`), PR и отметка `review` в графе после слияния графа MVP.
- [ ] S10. Полный `make test-e2e` и visual desktop/mobile — после ревью без блокеров.

## Критерии приёмки

- T0+7−1s: печать и электронные покупаются. На T0+7 печать закрыта (quote, заказ и оплата). На T0+14 закрыто всё.
- Цифровая покупка и оплата на 10-й день проходят, не получают `latePayment`, файлы J1 доступны месяц от оплаты.
- Неоплаченный смешанный заказ после +7 не оплачивается (409 `PRINT_SALES_CLOSED`, в том числе через открытую попытку). Покупателю предлагается новый цифровой заказ. Оплаченные снимки не меняются.
- Повторная передача ссылки не сдвигает T0. Исправление T0 после закрытия печати отклоняется.
- Миграция заполняет только пустые сроки электронных, печать и доставку не трогает; повторный запуск ничего не меняет.
- Публичная галерея, заказ и кабинет показывают оба срока; состояние `digital` видно в списках и фильтрах.
- На desktop/mobile публичный подвал показывает «Разработано в ReBit Studio», сведения продавца сохранены.

## Тест-кейсы

| ID | Предусловия / действие | Ожидаемый результат | Команда |
| --- | --- | --- | --- |
| E7-T01 | Unit VO: record/confirm от T0=11.10.2026 15:00 МСК | print 18.10 15:00, digital 25.10 15:00, delivery 25.10 15:00; повтор не меняет | `make test` (PHPUnit `GroupCalendarTest`) |
| E7-T02 | Unit статусы: −1s/0/+1s у print и digital | open → digital → closed точно на границе | PHPUnit `GroupCalendarTest`, `GalleryAvailabilityTest` |
| E7-T03 | Unit extend/correct: продлённая печать, коррекция до и после +7 | digital = max(...); после +7 — `PRINT_SALES_CLOSED` | PHPUnit `GroupCalendarTest`, `GroupCalendarDeliveryTest` |
| E7-T04 | Unit SQL-состояний | условия open/digital/closed и счётчики согласованы с VO | PHPUnit `GroupStateSqlTest` |
| E7-T05 | Unit quote: состояние digital с physical / только digital+bundle / closed | 409 `PRINT_SALES_CLOSED` / 200 / 409 `GALLERY_CLOSED` | PHPUnit `StorefrontQuoteTest` |
| E7-T06 | Unit создание заказа в состоянии digital | digital-заказ создаётся; со смешанной корзиной — 409 | PHPUnit `OrderCheckoutUseCaseTest` |
| E7-T07 | Unit оплачиваемый заказ | `salesUntil` = print при physical, иначе digital; `containsPrint` | PHPUnit `OrderPaymentsTest` |
| E7-T08 | Unit оплата: смешанный после +7, digital на 10-й день, после +14, открытая попытка после срока | 409 `PRINT_SALES_CLOSED` / старт / 409 `PAYMENT_CLOSED` / 409 без повторного использования; точный повтор ключа идемпотентен | PHPUnit `PaymentAttemptPolicyTest`, `StartPaymentAttemptTest` |
| E7-T09 | Unit поздний платёж | digital, оплаченный на 10-й день, — не late; смешанный, оплаченный после +7, — late | PHPUnit `PaymentReconcilerTest` |
| E7-T10 | Unit handoff: transmit/correct/повтор, фильтр digital | `digitalSalesUntil` в ответе и JSON повтора; старый JSON без поля читается | PHPUnit `GroupLinkWorkflowTest`, `GroupLinkContractTest` |
| E7-T11 | Architecture | UseCase/Service с phpDoc; контроллеры без технологической сборки | PHPUnit Architecture |
| E7-T12 | Миграция на копии БД | заполняет только переданные группы с пустым digital; extended → max; повторный запуск не меняет данные; CHECK действует | `make test-e2e` → `verify-sales-windows.php` |
| E7-T13 | Frontend unit | правила сроков (+7/+14), состояние digital, исключение печати из quote, тексты ошибок `PRINT_SALES_CLOSED`, перенос digital-строк в корзину, `linkStatus`/`groupStateTone` | `npm run check`, `npm run test:commerce`, `npm run test:ui` |
| E7-T14 | HTTP/E2E: галерея в digital-фикстуре | quote с печатью → 409; digital-заказ создан и оплачен тестовой картой; файлы доступны | `make test-e2e` (после ревью) |
| E7-T15 | HTTP/E2E: смешанный заказ после +7 | оплата → 409 `PRINT_SALES_CLOSED`, блок переноса, новый digital-заказ | `make test-e2e` (после ревью) |
| E7-T16 | Visual desktop/mobile | шапка галереи (open/digital/closed), корзина, заказ, ссылки, подвал «Разработано в ReBit Studio» | `make test-e2e` скриншоты → `docs/waves/e7/visual.json` |
| E7-T17 | Граф и документация | после слияния плана MVP: E7 `review`, DAG/ID валидны | `python3 tools/verify-wave-graph.py docs/waves/graph.json` |
