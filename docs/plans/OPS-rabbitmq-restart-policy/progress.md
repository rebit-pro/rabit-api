# OPS: RabbitMQ и cron после перезагрузки — прогресс

## Точка продолжения

- Ветка `codex/ops-rabbitmq-restart-policy` от main 8e2433c; worktree `/home/user/rabit-api-worktrees/ops-rabbitmq-restart-policy`.
- PR #215; issues #216 (галерея Xiaomi), #217 (сервисы после перезагрузки); падающий тест F2 — #163.
- Завершено: диагностика, compose (`any` + обязательный `RABBITMQ_HOSTNAME`), Makefile, тест, быстрые проверки;
  runtime на stage: `any` и hostname `01a8af19c94b`, MoreFoto consumers работают.
- Сейчас: PR #215 на ревью; merge — по команде владельца.
- Следующий шаг: ревью PR #215. При деплое стека `site` передавать `RABBITMQ_HOSTNAME=01a8af19c94b`.
- Блокеров нет. Запись на сервере агенту запрещена автоматическим режимом — runtime-команды запускает владелец.
- Тест-кейсы: RRP-T01…T06 PASS (19.10.2026).
- Swarm UpdateStatus `site_rabbitmq` = paused: первая задача с новым hostname получила `Canceled: context canceled`
  на подготовке (нагрузка), задача-замена работает с новой спецификацией. Следующий `docker service update` сбросит статус.
- Бэкап спецификации `site_rabbitmq` до изменения: root-only `/root/morefoto-ops/rabbitmq-restart-20261019T1013*Z/service-before.json`.

## Журнал

### 19.10.2026 — диагностика

- Повод: владелец оплатил электронный кадр (live-магазин), письмо с кадром не пришло, чек ЮKassa пришёл.
- `last -x reboot shutdown`: shutdown 18.10 19:50 UTC, boot 20:14 UTC (ядро 6.8.0-137 → 6.8.0-147).
- `docker service ps site_rabbitmq`: последняя задача `complete`, exit 0, 20:15:02 UTC; RestartPolicy `on-failure`, MaxAttempts 3.
  Так же `site_api-cron`: `Complete 14 hours ago`.
- `morefoto_stage_notification_consumer` и `morefoto_stage_media_consumer` 0/1: задачи падают каждые ~8 с,
  «Could not connect to the AMQP server». `nproc` = 1, load average ~4,2, SSH сначала не проходил banner exchange.
- Письмо заказа: раннер в 09:52:56 UTC «Новых писем: 1, поставлено в очередь: 0» — `MOREFOTO_ORDER_EMAIL_ENABLED`
  не задан на fpm и order_email_runner (J2-DEC-05), письмо `held`. Это отдельная причина, вне scope.
- smtp.yandex.ru:465/587 TCP с хоста — OK.
- Чтение БД stage агентом заблокировано автоматическим режимом; выводы сделаны по логам сервисов и коду.

### 19.10.2026 — compose и тест

- `docker-compose-production.yml`: `api-cron` и `rabbitmq` — `condition: any`, `max_attempts` убран, `delay` прежний.
- RRP-T01 PASS: `python3 -m unittest tools/tests/test_production_restart_policy.py -v` → OK. На compose из main 8e2433c
  тот же разбор находит `api-cron` и `rabbitmq` — тест ловит дефект.
- `python3 -m unittest discover -s tools/tests -v` → 27 тестов, 1 FAIL `test_f2_prepares_first_and_reports_later_in_one_group`
  (`cabinet-tables` в группе `z-links-preparation`). На чистом main 8e2433c тот же FAIL (26 тестов) — не относится к этой ветке, уже #163.
- RRP-T04 PASS: `docker compose -f docker-compose-production.yml config -q` с заглушками обязательных переменных → exit 0;
  в итоговой конфигурации 7 × `condition: any`, `max_attempts` нет. `git diff --check` — OK.
- E2E-гейт не запускался: стенд не использует production compose.

### 19.10.2026 — PR и issues

- Push по HTTPS через `gh auth git-credential`: SSH к github.com:22 в этот день обрывался. PR #215.
- Дубли проверены `gh issue list --search`: #204 закрыт (фикс в PR #206), его продолжение — #216. Перезагрузка — новых дублей нет, #217.
  Xdebug уже в #61, тест F2 — в #163.
- На хосте также 0/1: `admin_api-postgres` (стек admin, on-failure), из-за него `admin_api` с `/health` 503 перезапускается;
  `admin_api-purge-tokens` (none), `cronjob_cronjob` (on-failure). Это другие проекты, записано в #217.

### 19.10.2026 11:47 UTC — проверка после команды владельца

- RRP-T02/T03 FAIL: `docker service inspect site_rabbitmq` — RestartPolicy прежняя (`on-failure`, MaxAttempts 3), Version 11001721,
  UpdateStatus null; `docker events --since 90m --filter type=service` — событий нет. Обновление до сервера не дошло.
- load average 6,0; теперь падают все четыре consumer (`notification`, `media`, `files`, `support`) с «Could not connect to the AMQP server».
- Следующий шаг: владелец повторяет команду с `ConnectTimeout=60` и присылает её вывод.

### 19.10.2026 11:49 UTC — RabbitMQ запущен, но с пустым узлом

- Владелец выполнил `docker service update --restart-condition any --restart-max-attempts 0 --detach=false site_rabbitmq` → converged.
  RRP-T03 PASS: RestartPolicy `{"Condition":"any","MaxAttempts":0}`, UpdateStatus completed 11:49:10 UTC, `site_rabbitmq` 1/1.
- RRP-T02 FAIL: notification/support consumers падают дальше; лог RabbitMQ — `vhost morefoto_stage not found`.
- Причина: имя узла `rabbit@<hostname>`, hostname контейнера Swarm = ID контейнера, в спецификации hostname не задан.
  Новый контейнер `74d483c21a28` создал пустой узел (vhost только `rebit`). В томе `site_rabbitmq-data` 20 каталогов узлов с 28.03.2026;
  рабочий — `rabbit@01a8af19c94b` (vhost `morefoto_stage`, 4 очереди; `rebit`, 0 очередей). Приложения объявляют очереди сами (`auto_setup`).
- Решение (предложено владельцу): закрепить hostname `01a8af19c94b` — узел поднимется со старыми vhost, правами и очередями,
  следующие перезапуски сохранят имя. В PR — стабильный hostname в compose (scope расширяется, план обновить до кода).

### 19.10.2026 12:24 UTC — старый узел RabbitMQ возвращён

- Владелец: `docker service update --hostname 01a8af19c94b --detach=false site_rabbitmq` → `update paused due to failure or early
  termination of task wxw6vrxqrpoxxptg5l89hcbht`. Задача отклонена на `preparing` (`Canceled: context canceled`), RabbitMQ не стартовал;
  по политике `any` Swarm запустил задачу-замену `w6kzvo8yaeyel8er2lu0k2il5` с новой спецификацией.
- Контейнер `1ac387f3be22`, hostname `01a8af19c94b`, `rabbitmq-diagnostics check_running` OK; `list_vhosts` → `morefoto_stage`, `rebit`;
  очереди `mediaProcessing`, `filesArchive`, `notificationEmail`, `supportMax` — 0 сообщений.
- RRP-T02 PASS (12:29 UTC): все четыре consumer 1/1, работают 4+ мин без ошибок; `list_connections` — 4 × `rebit`/`morefoto_stage`/running;
  ошибок `[error]` в RabbitMQ с 12:24 — 0. `consumers=0` в очередях — норма: Messenger AMQP забирает сообщения через `basic_get`.
- Диспетчеры notification/media/support/files с 12:20: «Опубликовано: 0» — накопленных операций нет. FPM с 12:24: ошибок публикации 0.
- Load average 6,3 → 2,1 после остановки циклов перезапуска.

### 19.10.2026 — стабильное имя узла в compose

- План обновлён до кода (S5, RRP-T05/T06). `rabbitmq.hostname: ${RABBITMQ_HOSTNAME:?...}`; `Makefile`: `RABBITMQ_HOSTNAME ?=`,
  `guard-RABBITMQ_HOSTNAME`, строка в `.env`.
- RRP-T01/T05 PASS: `python3 -m unittest tools/tests/test_production_restart_policy.py -v` → 2 OK.
- `python3 -m unittest discover -s tools/tests` → 28 тестов, единственный FAIL — `test_f2_...` (#163, есть на main).
- RRP-T04 PASS: `docker compose ... config` с `RABBITMQ_HOSTNAME=01a8af19c94b` → 7 × `condition: any`, `hostname: 01a8af19c94b`.
- RRP-T06 PASS: без переменной — `required variable RABBITMQ_HOSTNAME is missing a value: Select the RabbitMQ node hostname stored in the RabbitMQ volume`;
  `make deploy-check-env` без неё → `Required variable RABBITMQ_HOSTNAME is not set`, с ней — OK. `git diff --check` — OK.
