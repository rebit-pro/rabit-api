# OPS: RabbitMQ и cron после перезагрузки — прогресс

## Точка продолжения

- Ветка `codex/ops-rabbitmq-restart-policy` от main 8e2433c; worktree `/home/user/rabit-api-worktrees/ops-rabbitmq-restart-policy`.
- PR #215; issues #216 (галерея Xiaomi), #217 (сервисы после перезагрузки); падающий тест F2 — #163.
- Завершено: диагностика 19.10.2026, план, compose и тест, быстрые проверки (RRP-T01, RRP-T04 PASS).
- Сейчас: ждём runtime update `site_rabbitmq` от владельца; merge PR — по команде владельца.
- Следующий шаг: владелец запускает runtime update `site_rabbitmq`, затем проверка RRP-T02/T03.
- Блокер: автоматический режим агента запрещает запись на сервере — runtime update `site_rabbitmq` запускает владелец.
- Тест-кейсы: RRP-T01 PASS, RRP-T04 PASS, RRP-T02/T03 PENDING (runtime на stage).
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
