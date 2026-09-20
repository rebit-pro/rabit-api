# H1 — исправления второго круга code review

Статус: исправления выполняются. Ветка `codex/h1-reliable-email`, PR [#22](https://github.com/rebit-pro/rabit-api/pull/22), base `c32b98e7c7407592c54c9ee382e994d7fa611955`.

## Цель

Закрыть блокирующие замечания review H1: подключить уже реализованные команды надёжной email-доставки к локальному и production runtime, документировать четыре UseCase по-русски и сохранить воспроизводимые plan/progress для продолжения задачи из новой сессии.

## В объёме

- class-level phpDoc у `QueueEmailUseCase`, `DeliverEmailUseCase`, `DispatchPendingEmailUseCase`, `ConsumeEmailUseCase`;
- локальный `api-notification-consumer` в Compose и команды управления в Makefile;
- production consumer с отдельным количеством реплик и существующими config/secrets/mounts;
- ежеминутный recovery-dispatch в supercronic;
- сохранённые `plan.md` и `progress.md`, а также обновление wave verification;
- локальные compose/backend/integration/browser проверки;
- второй review с вынесением неблокирующих улучшений в GitHub Issues.

## Вне объёма

- смена выбранного канала EMAIL на MAX;
- новые публичные REST-операции или изменение контрактов H1;
- изменение SMTP exactly-once семантики, схемы БД или миграций;
- merge PR #22 и deployment;
- третий круг review только ради неблокирующих улучшений.

## Зависимости и решения

- A2/A5 уже слиты в base; H1 остаётся независимой веткой от `main`.
- Consumer запускает `app:notification:consume`; cron вызывает `app:notification:dispatch-pending --limit=100`.
- Production использует существующие `rebit_backend_env`, encryption/SMTP/RabbitMQ secrets и bind mounts; новые секреты не создаются.
- `NOTIFICATION_CONSUMER_REPLICAS` имеет безопасный default `1` и попадает в deploy env.
- Production/stage не затрагиваются: все проверки выполняются локально.

## План

- [x] Разобрать три inline-замечания и обновить plan/progress до кода.
- [x] Добавить содержательные русские class-level phpDoc к четырём UseCase.
- [x] Подключить notification consumer в local/production Compose и Makefile.
- [x] Добавить recovery-dispatch в cron и отразить runtime в документации волны.
- [x] Проверить оба Compose-конфига, cron и команды Makefile.
- [x] Выполнить PHP lint/style/PHPStan/PHPUnit, интеграционный round-trip и полный disposable browser E2E.
- [x] Выполнить второй review полного diff; блокирующие находки исправить, неблокирующие оформить отдельными Issues.
- [ ] Закоммитить, отправить ветку, ответить на inline-комментарии и актуализировать progress/verification.

## Риски и ограничения

- Без постоянно запущенного consumer очередь принимает сообщения, но email не доставляется; без cron pending/retryWait не восстанавливаются после publish failure.
- Consumer должен использовать тот же runtime config и SMTP/RabbitMQ secrets, что web/cron, без записи значений секретов в репозиторий.
- `docker compose config` проверяет структуру, но не заменяет реальный RabbitMQ round-trip.
- Неблокирующие находки второго review не задерживают движение волны и получают отдельную трассируемую Issue.

## Приёмка и тест-кейсы

| ID | Проверка | Ожидаемый результат |
| --- | --- | --- |
| H1-R01 | Просмотреть четыре Delivery UseCase | Перед каждым классом есть содержательный русский class-level phpDoc |
| H1-R02 | Проверить `docs/plans/H1_reliable-email/` | Ровно `plan.md` и `progress.md`; точка продолжения актуальна |
| H1-R03 | `docker compose config` | Local config содержит `api-notification-consumer` с `app:notification:consume` |
| H1-R04 | `docker compose -f docker-compose-production.yml config` | Production config содержит consumer, replicas/config/secrets/mounts и не требует новых секретов |
| H1-R05 | Проверить cron/Makefile | Ежеминутный dispatch и команды запуска/остановки/логов/ручного запуска присутствуют |
| H1-R06 | PHP lint/style/PHPStan/PHPUnit | Все backend quality gates проходят |
| H1-R07 | Notification integration | Isolated MySQL + реальный RabbitMQ подтверждают dispatch/consume/dedup/retry recovery |
| H1-R08 | Полный `tools/run-browser-e2e.py run` | Frontend/backend/Chromium проверки проходят локально, временные ресурсы очищены |
| H1-R09 | Второй review и GitHub triage | Блокирующих замечаний нет; каждое неблокирующее замечание оформлено отдельной Issue с ссылкой на PR/ветку |
