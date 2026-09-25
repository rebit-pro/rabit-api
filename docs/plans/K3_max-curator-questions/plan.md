# K3 — вопросы куратору с сайта через MAX

Ветка `codex/k3-max-curator-questions` от `main` `1dd4a5f` (merge PR #48). Согласованный замысел, решения
MAX-D01…D08, источники MAX и API SUP-07…12 — [план PR #48](../max-support-chat-plan/plan.md). Здесь — реализация.

## 1. Цель

Родитель из галереи (имя, без аккаунта) и сотрудник учреждения (`head`, `teacher`) задают вопрос на сайте;
вопрос приходит в закрытую группу MAX «МореФото — вопросы родителей.» от бота `@se14459249_bot`; Рита или Алёна
отвечают через «Ответить»; ответ появляется у автора на сайте.

## 2. Scope

Входит: модуль `morefoto.support` (миграции, Domain, UseCase, DI, маршруты SUP-07…12), контракт отправки MAX в
`rebit.share` и HTTP-реализация в `rebit.notification`, контракт контекста галереи Media и контекста сотрудника,
доставка с восстановлением, webhook, консольные команды, frontend (панель в галерее, раздел кабинета),
unit/architecture/HTTP/E2E проверки, двойник MAX для E2E, синхронизация графа (E6 merged, K3 inProgress).

Исключено: привязка к заказу (K1), файлы и голос, уведомления родителю вне сайта, рабочее место куратора на
сайте, allow-list MAX-пользователей, production-включение до решения MAX-D06.

## 3. Технические решения реализации

| ID | Решение |
| --- | --- |
| K3-T01 | Реплика сама служит outbox: `delivery_status` pending/delivered/failed/unknown, `attempts`, `lease_until`, `next_attempt_at`, `max_mid` (unique). Отдельная таблица outbox не нужна. |
| K3-T02 | Webhook обрабатывается синхронно в одной транзакции: запись реплики куратора с уникальным входящим `mid` и есть inbox-фиксация; 200 только после commit, повтор — 200 без второй записи. Отдельный inbox и второй worker не нужны: работа — несколько запросов к БД, лимит MAX 30 с. |
| K3-T03 | Исход HTTP-отправки: 2xx → delivered + `mid`; 4xx (кроме 429) → failed; 429/5xx/ошибка соединения до отправки → повтор с backoff; таймаут после отправки → unknown без автоповтора (у POST /messages нет ключа идемпотентности). |
| K3-T04 | Конфигурация: токен `/run/secrets/morefoto_support_max_bot_token` → `MOREFOTO_SUPPORT_MAX_BOT_TOKEN`, секрет webhook `/run/secrets/morefoto_support_max_webhook_secret`, `MOREFOTO_SUPPORT_MAX_CHAT_ID`, `MOREFOTO_SUPPORT_MAX_API_URL` (по умолчанию `https://platform-api2.max.ru`, в E2E — двойник). Без токена или ID группы реплики остаются pending, сайт работает. |
| K3-T05 | Автор реплики: `parent`, `staff` (head/teacher), `curator`. Имя куратора — `first_name last_name` отправителя MAX. |
| K3-T06 | `bot_added`/`bot_removed` пишутся в `morefoto_support_max_chat_event`; команда `support:max:chats` показывает ID групп для настройки. |
| K3-T08 | Сертификат MAX выпущен УЦ Минцифры: Russian Trusted Root CA хранится в `rebit.notification/resources/max/`, клиент MAX использует его через `CURLOPT_CAINFO` (переопределение `REBIT_NOTIFICATION_MAX_CA_FILE`); системное доверие образов не меняется. |
| K3-T07 | Номер вопроса — ID беседы; ключ родителя — 64 hex, в БД SHA-256; беседа родителя привязана к группе галереи. |

Паттерны кода, идемпотентности, лимитов, миграций и E2E уточняются по исследованию существующих модулей
(F2, E5, H1) и фиксируются в progress до написания кода.

## 4. Checklist

- [x] Merge PR #48, worktree и ветка от `1dd4a5f`.
- [x] Граф: E6 merged (PR #66, `54bd4ab`), K3 inProgress; канон MoreFoto пересобран.
- [ ] Исследование паттернов F2/E5/H1/frontend; уточнить K3-T01…T07.
- [ ] Контракты: `Contract/Notification` (отправка MAX), `Contracts/Media` (контекст галереи), контекст сотрудника.
- [ ] Реализации контрактов в `rebit.notification`, `morefoto.media`, `morefoto.access`/`organization`.
- [ ] `morefoto.support`: миграции, Domain, UseCase с phpDoc, DI, маршруты, контроллеры, mapper, LogChannelEnum.
- [ ] Доставка: сообщение RabbitMQ, handler, consumer, dispatcher pending, команды подписки и списка чатов.
- [ ] Webhook SUP-12 с проверкой секрета в инфраструктурной обвязке.
- [ ] Frontend: панель в галерее, раздел кабинета head/teacher, API-сервис, состояния.
- [ ] Двойник MAX для E2E, spec в `groups.json`.
- [ ] Быстрые проверки backend/frontend, затем полный `make test-e2e` после ревью.
- [ ] Stage: секреты, подписка webhook, ID группы, живая проверка MAX-12, desktop/mobile MAX-13.

## 5. Критерии приёмки

Как в [плане PR #48, раздел 12](../max-support-chat-plan/plan.md#12-критерии-приёмки-k3); тест-кейсы MAX-01…13 —
[раздел 13](../max-support-chat-plan/plan.md#13-тест-кейсы). Дополнительно:

| ID | Предусловия → действие | Ожидаемый результат | Команда |
| --- | --- | --- | --- |
| K3-01 | Граф после синхронизации | DAG корректен, E6 merged, K3 inProgress, 116 API ID | `python3 tools/verify-wave-graph.py docs/waves/graph.json` |
| K3-02 | Контроллеры Support | Нет `Bitrix\\*`, `HttpRequest`, `ServiceLocator`; один request DTO на action | Architecture-тест PHPUnit |
| K3-03 | Нет токена/ID группы | Вопрос сохранён, реплика pending, API отвечает 201 | PHPUnit + E2E |
| K3-04 | Живой токен | `GET /me` возвращает бота `@se14459249_bot`, токен не выводится | Ручная команда с `~/.config/morefoto/max-bot.env` |
