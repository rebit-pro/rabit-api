# OPS: RabbitMQ и cron поднимаются сами после перезагрузки сервера

## Цель

После перезагрузки хоста `rabbitmq` и `api-cron` стека `site` должны запускаться сами, а RabbitMQ — с теми же vhost,
правами и очередями. Решение владельца 19.10.2026:
«Да, выполняй пункты 1 и 3» — поднять RabbitMQ с политикой `any` и оформить PR и issues.

## Контекст

- 18.10.2026 в 19:50 UTC хост `rebit-pro` выключили, в 20:14 UTC он загрузился с новым ядром.
- `site_rabbitmq` и `site_api-cron` при выключении завершились штатно (exit 0). У обоих `restart_policy.condition: on-failure`
  с `max_attempts: 3`, поэтому Swarm не перезапускает завершённую задачу: оба остались 0/1.
- Без RabbitMQ на stage MoreFoto `notification_consumer` и `media_consumer` перезапускаются каждые ~8 с с ошибкой
  «Could not connect to the AMQP server». Письма H1 и обработка медиа стоят, нагрузка 1-ядерного хоста ~4,2.
- 19.10.2026 после запуска с политикой `any` RabbitMQ поднялся пустым: имя узла `rabbit@<hostname>`, а hostname
  контейнера Swarm — его ID. Каждый новый контейнер создаёт новый узел в томе: с 28.03.2026 их 20.
  Рабочий узел — `rabbit@01a8af19c94b` (vhost `morefoto_stage`, 4 очереди). Его вернули через `--hostname 01a8af19c94b`.
- Остальные долгоживущие сервисы в `docker-compose-production.yml` уже используют `condition: any`.

## Scope

- `docker-compose-production.yml`: у `rabbitmq` и `api-cron` — `condition: any`, `delay` прежний, без `max_attempts`.
- `docker-compose-production.yml`: у `rabbitmq` — `hostname: ${RABBITMQ_HOSTNAME:?...}`, обязательная переменная,
  как `RABBITMQ_VOLUME_NAME`: имя узла привязано к данным тома. `Makefile deploy` требует её и пишет в `.env`.
- Тест `tools/tests/test_production_restart_policy.py`: ни один сервис production compose не использует `on-failure`/`none`
  и `max_attempts`; у `rabbitmq` hostname задан обязательной переменной; `make deploy` проверяет её.
- Runtime на stage (команды запускает владелец, автоматический режим агента блокирует запись на сервере):
  `docker service update --restart-condition any --restart-max-attempts 0 site_rabbitmq`, затем
  `docker service update --hostname 01a8af19c94b site_rabbitmq`.

### Вне scope

- Включение писем заказа `MOREFOTO_ORDER_EMAIL_ENABLED` — владелец не выбрал этот пункт 19.10.2026.
- Runtime-запуск `site_api-cron` (старый образ `20260911`, стек `site`) — отдельное решение, см. issue по сервисам после перезагрузки.
- Сервисы других проектов (`admin_api`, `admin_api-postgres`, `cronjob_cronjob`) — issue.
- Сохранение фото в галерею на Xiaomi — issue; Xdebug в образах — уже #61.

## Решения и риски

- `RABBITMQ_HOSTNAME` без значения по умолчанию: умолчание при деплое на существующий том молча подняло бы пустой узел.
  Для stage значение `01a8af19c94b`; для нового тома — любое стабильное имя, например `rabbitmq`.
- Переименование узла stage в «красивое» имя не делается: нужен перенос данных узла, пользы нет.

- `any` без лимита: падающий RabbitMQ перезапускается бесконечно с паузой 10 с, а не останавливается после трёх попыток.
  Так уже работают все consumers; сбой виден в `docker service ps` и логах.
- Compose применяется только при `docker stack deploy` стека `site`; до него действует runtime-изменение сервиса.
- E2E-стенд production compose не использует: браузерный гейт это изменение не проверяет. Проверки — unit-тест,
  `docker compose config` и runtime на stage.

## Checklist

- [x] S1. План, журнал, ветка `codex/ops-rabbitmq-restart-policy` от main 8e2433c.
- [x] S2. Compose и тест, быстрые проверки.
- [x] S3. PR #215, issues #216 (галерея) и #217 (сервисы после перезагрузки).
- [x] S4. Runtime на stage: политика `any` (11:49 UTC), hostname `01a8af19c94b` (12:24 UTC); RRP-T02/T03 PASS.
- [x] S5. Стабильный hostname в compose и Makefile, тест, быстрые проверки, обновить PR #215.

## Критерии приёмки

- В production compose нет `on-failure` и `max_attempts`; тест это закрепляет.
- На stage `site_rabbitmq` 1/1 с политикой `any`/0 и hostname `01a8af19c94b`; vhost `morefoto_stage` с очередями;
  `notification`, `media`, `files`, `support` consumers 1/1 без ошибок AMQP.
- Деплой стека без `RABBITMQ_HOSTNAME` останавливается с понятной ошибкой.

## Тест-кейсы

| ID | Предусловия / действие | Ожидаемый результат | Проверка |
| --- | --- | --- | --- |
| RRP-T01 | Production compose из ветки | Все `restart_policy.condition` = `any`, `max_attempts` нет | `python3 -m unittest discover -s tools/tests -v` |
| RRP-T02 | Stage: runtime update `site_rabbitmq` | `site_rabbitmq` 1/1; `notification_consumer`, `media_consumer` 1/1; в логах нет «Could not connect to the AMQP server» | `docker service ls`, `docker service logs --since 5m` |
| RRP-T03 | Stage после update | `site_rabbitmq` RestartPolicy `{"Condition":"any","MaxAttempts":0}` | `docker service inspect` |
| RRP-T04 | Синтаксис compose | `docker compose config` без ошибок | команда из progress |
| RRP-T05 | Production compose | у `rabbitmq` hostname — обязательная `RABBITMQ_HOSTNAME`; `make deploy` её требует и пишет в `.env` | `python3 -m unittest tools/tests/test_production_restart_policy.py -v` |
| RRP-T06 | `docker compose config` без `RABBITMQ_HOSTNAME` | ошибка интерполяции с подсказкой | команда из progress |
