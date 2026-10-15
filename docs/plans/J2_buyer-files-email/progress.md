# J2 — прогресс

## Точка продолжения

- Дата: 15.10.2026. Ветка `codex/j2-buyer-files-email`, rebase на main d1741d5 (после L1 #196); рабочая копия `/home/user/rabit-api-worktrees/j2-buyer-files-email`. PR #200 (https://github.com/rebit-pro/rabit-api/pull/200); граф: J2 `review`.
- Завершено: план 12.10.2026; пауза снята пользователем 15.10.2026, DEC-01 — ссылка на страницу оплаченного заказа; граф: L1 `merged` (PR #196, 72aa95b), J2 `inProgress`.
- Сейчас: гейт PASS, самопроверка опубликована; ждём команду владельца на merge и выкладку.
- Следующий шаг: merge и выкладка по команде владельца (миграция 20261015180001 до backend, cron писем); отправка на stage выключена до штатного письма владельцу (#161).
- Блокеры: нет. Реальная доставка (#161) — соединение есть, письмо не проверено; отправка выключена флагом до проверки владельцем.
- Проверка графа: `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Журнал

### 12.10.2026 — старт

- #160 слит (0d02adf). Готовы J2 и F3; взята J2 (критический путь к N3, план владельца: «после merge E8/J4 подключить J2»).
- Других веток и PR по J2 нет. Worktree: `git worktree add -b codex/j2-buyer-files-email /home/user/rabit-api-worktrees/j2-buyer-files-email origin/main`.
- Инвентаризация — раздел «Факты» плана. Главные развилки: ссылку заказа нельзя положить в письмо (только хеш, один ключ), нет outbox для оплаты, email не подтверждён, SMTP на сервере не работает (#161).

### 12.10.2026 — пауза

- Ответы на DEC-01…04: письма пока не отправлять; пользователь завтра выясняет, правомерно ли оформлять оплату с сайта без ФИО и телефона (E8); файлы — на странице оплаченного заказа; автописьмо по старым заказам не слать; оператору — только журнал до J3.
- Реализация не начиналась. План сохранён как точка возобновления; ветка запушена без PR.

### 15.10.2026 — возобновление

- Пользователь выбрал J2 следующей после L1: «Будем отправлять ссылку на оплаченный заказ родителю». Готовы по графу J2 (MVP) и L3 (после MVP); G2 ждёт D09.
- Rebase на main d1741d5: конфликт только в `graph.json` — взята версия main (J4 там уже merged), затем L1 `merged` (PR #196, 72aa95b), baseline d1741d5, J2 `inProgress`. `verify-wave-graph.py` — exit 0, готовы J2, L3.
- SMTP: из контейнера `morefoto_stage_notification_consumer` TCP и TLS до smtp.yandex.ru:465 и TCP до :587 — соединение за 0,01–0,05 с (11.10 — таймаут). Письмо не отправлялось.
- Ключи заказа (текущий код): один действующий ключ на заказ (`ux_mf_order_key_active`); ключ письма — новый `KIND` с отдельной уникальностью.

### 15.10.2026 — решения

- Пользователь: «Принимаю рекомендации» — DEC-03 (email заказа без подтверждения, видно на странице заказа), DEC-05 (флаг `MOREFOTO_ORDER_EMAIL_ENABLED`, по умолчанию выключен), DEC-06 (подсказка у email при включённой отправке), DEC-07 (повтор: 10 минут, 5 в сутки). Граф: `decisionEvidence.J2-DEC-01-07`.

### 15.10.2026 — реализация

- Инвентаризация (Explore): один действующий ключ на заказ (`ux_mf_order_key_active`), `findActive` не различает тип ключа; H1 `queue()` публикует в RabbitMQ сразу (внутри чужой транзакции — до commit), тело хранится открытым текстом; кода ошибки в статусе H1 нет; перевыпуска ключа сотрудником (D07) в коде нет; `verify-orders.php`/`verify-files.php` считали один действующий ключ на заказ.
- Отличие от DEC-01 в реализации (T-05): ключ письма детерминированный (HMAC), повтор не отзывает ссылку — иначе «Отправить ещё раз» на странице, открытой по письму, закрыло бы её. Сообщается владельцу.
- Commerce (da419c0): `OrderEmailLinkInterface` + `OrderEmailLinks`, `PAID_RECORDED_AT`, ключ `KIND=email`, флаг `MOREFOTO_ORDER_EMAIL_ENABLED`, `capabilities.orderEmail`; миграция 20261015180001; H1 очищает текст после `accepted`/`failed`.
- Files (db1556e): журнал и курсор, `OrderEmailSender`, `DeliveryView`, 4 UseCase, `PublicFileDeliveryController`, `app:files:order-emails`, письмо `OrderEmailComposer` (`MOREFOTO_APP_URL`); frontend — блок письма на странице заказа и подсказка у email; E2E — спецификация, хук стенда `stand_requests` (обобщён из L1), `order-email-fixture.php`, `verify-order-email.php`, проверки очистки в `verify-notification.php`, фильтр `KIND='buyer'` в `verify-orders.php`/`verify-files.php`.

### 15.10.2026 — быстрые проверки

- PHPStan (основной и `tools/e2e/phpstan.neon`) — без ошибок; PHPUnit — OK, 1172 теста (+7 J2: `OrderEmailFlowTest` 6, архитектура контроллера 1); php-cs-fixer — 0 исправлений.
- `npm run check` — exit 0 (`test:ui` 98/98); `npm run test:commerce` — 247/247 (+4 `order-email.test.mjs`).
- Тесты раннера `python3 -m unittest discover -s tools/tests` — 25 OK, 1 FAIL `test_f2_prepares_first_and_reports_later_in_one_group` — существующий #163 (тот же сбой на main), не J2.
- MoreFoto: FIL-05/06 в реестре, `validate.py` — passed (122 запроса); патч `docs/waves/j2/morefoto-contract.patch`.

### 15.10.2026 — гейт 1

- `make test-e2e` — прогон `rabit-e2e-33e93688a09d` на d3373e4 — FAIL: группа b 58 passed, 2 failed, 13 did not run; группа a отменена. «durable Notification contract on real MySQL» — PASS (очистка текста письма, сохранение у `unknown`, повтор после очистки).
- Падение 1 (J2): `zzzzz-orders` E5 сравнивает `capabilities` каталога целиком; на стенде отправка включена — пришло `orderEmail: true`. Ожидание обновлено.
- Падение 2 (не J2): G1-T14 — СБП тестового магазина ЮKassa (#174), как в гейте 1 L1.

### 15.10.2026 — гейт 2 PASS и самопроверка

- `make test-e2e` — прогон `rabit-e2e-7f46ab85f418` на fdd104e — **PASS** за 436 с: браузер 154 (a 81, b 73), 17 верификаторов MySQL включая `verify-order-email.php` и `verify-notification.php` (очистка текста); тестовый магазин ЮKassa включён. Скриншоты — `docs/waves/j2/screenshots/`, `visual.json`, `verification.json`.
- Самопроверка diff: блокеров нет (формат Idempotency-Key 32 hex, старая колонка `ACTIVE_ORDER_ID` ключей нигде не читается, ключ письма открывает все публичные API заказа, H1 очищает только accepted/failed). Неблокирующее — #201 (номер выпуска в ключе письма для отзыва в J3).

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| J2-T01 | PASS | 15.10.2026 | unit; `verify-order-email.php` в `rabit-e2e-7f46ab85f418` |
| J2-T02 | PASS (unit) | 15.10.2026 | `testExpiredFilesOrUnpaidOrderGetNoLetter`; заказ до выкладки — нет `PAID_RECORDED_AT` (SQL Commerce) |
| J2-T03 | PASS | 15.10.2026 | unit; гейт: 429 DELIVERY_TOO_SOON |
| J2-T04 | PASS | 15.10.2026 | unit; гейт: чужой ключ и письмо — 404 |
| J2-T05 | PASS (unit) | 15.10.2026 | то же — pending/accepted/failed |
| J2-T06 | PASS | 15.10.2026 | `verify-notification.php` в гейте — текст очищен после accepted/failed, сохранён у unknown |
| J2-T07 | PASS | 15.10.2026 | `npm run check`, `test:commerce` 247 |
| J2-T08 | PASS | 15.10.2026 | `zzzzzzzzzz-order-email`: ссылка из письма открывает оплаченный заказ и файлы, ссылка покупателя работает |
| J2-T09 | PASS | 15.10.2026 | то же: 429, 422, 404 |
| J2-T10 | PASS | 15.10.2026 | `verify-order-email.php` |
| J2-T11 | PASS | 15.10.2026 | `docs/waves/j2/screenshots/` |
| J2-T12 | PENDING | — | письмо владельцу на stage после включения флага (#161) |
