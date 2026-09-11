# W04 — подключение модулей и окружения

W04 исправляет подготовку runtime и проверяет подключение следующего модуля. Новых REST-методов и предметных модулей нет. Волна принимается после W03; отдельный PR не разрешает production deployment или миграции.

## Что изменено

- `swarm-publish-runtime.sh`: диагностика идёт в stderr. Результат поиска секрета содержит только путь либо пустую строку; пропущенный optional secret не передаётся Docker как имя файла.
- Nginx: PATCH включён в CORS; разрешены только `Authorization`, `Content-Type`, `Idempotency-Key`, `X-Order-Key`. Существующий список origins сохранён; произвольные заголовки запроса не отражаются.
- Обе Compose-конфигурации требуют явные имена томов `MYSQL_VOLUME_NAME` и `RABBITMQ_VOLUME_NAME` (`external: true`). Имена не зависят от имени проекта. Для Swarm local volumes этого недостаточно: размещение отдельно проверяет строгий guard.
- Production требует `RUNTIME_DATA_DIR` и отдельный `CRON_ENV_CONFIG_NAME`. Настройки LeadHunter не заменяются настройками web FPM. Cron сохраняет период пять минут, один экземпляр и обновление stop-first.
- Audit consumer запускается локально через явный профиль/сервис; в production по умолчанию ноль экземпляров. Существующий обработчик сохранён.
- Make требует явный `STACK_NAME` и до передачи файлов проверяет единственный локальный manager/data node; затем проверяет существование runtime и томов, записывает выбранные имена в release environment. `--prune` удалён из deploy/rollback: частичный манифест RaBit API не удаляет соседние сервисы существующего стека.
- Старый `api-migrate-deploy` останавливается с понятной ошибкой: его `docker run` не переносил Swarm config/secrets и мог запустить миграции с другим runtime. Production-миграция требует отдельного проверенного одноразового Swarm service.

## Подключение следующего модуля

1. Принять предыдущую волну и её миграции на тестовой БД. Добавить реальный вертикальный сценарий; пустые `morefoto.*` заранее не создавать.
2. Проверить Composer PSR-4 и Bitrix autoload. `init.php` загружает Composer, runtime-env, затем Share до Auth/Notification. Share устанавливает alias `Bitrix\Main\Engine\ControllerBuilder`; его нельзя загружать после штатного builder.
3. `.settings.php` подключает предметные DI-файлы. Реализация принадлежит поставщику. Interface-key с ручными аргументами использует `constructor`; `className` допустим при автоматической сборке. Singleton не хранит изменяемое состояние запроса. `ServiceLocator` допускается в DI/bootstrap; текущий FileController не служит образцом новых контроллеров.
4. Установить модуль принятым установщиком/миграцией на тестовой БД, проверить зависимости и include.php. Для нового Composer namespace сгенерировать autoload. Каталог сам по себе не означает установку.
5. Добавить `routes.php` модуля в явный список `api/public/local/routes/rabit-api.php`. Статические маршруты ставить перед параметрическими. Эффективная цепочка: `local/.settings_extra.php → php_interface/settings_extra.php → routing.config = ['rabit-api.php']`. Симлинк routes.php сам по себе не регистрирует маршрут.
6. Проверить route/action/DTO/DI и полный HTTP-сценарий. Для отключения сначала убрать внешний маршрут и остановить зависимый consumer, затем отключать bootstrap/модуль. Откат кода не удаляет бизнес-данные и историю миграций. Действующие Lead/LeadHunter сохраняются.

DI smoke загружает реальный Share include.php и настройки четырёх модулей, регистрирует определения настоящим Bitrix ServiceLocator, собирает выбранные безопасные singleton и четыре Auth UseCase. Проверяется изолированный реестр без Notification routes.php. Установщики, Controller actions, бизнес-методы и БД этим smoke не запускаются; реальная установка/отключение проверяется на интеграционном стенде соответствующей волны.

## Миграции

Активный файл: `api/public/local/php_interface/migrations.cfg.php`; каталог `/local/php_interface/migrations.foundation`, `migration_dir_absolute=false`, таблица `sprint_migration_versions`. Семь исходных foundation-версий сохраняются; W02/W03 и следующие волны добавляют новые версии в этот набор. Исторический P2P-каталог не активируется.

На отдельном тестовом runtime перед применением:

~~~sh
docker compose run --rm api-php-cli php public/local/modules/sprint.migration/tools/migrate.php config
docker compose run --rm api-php-cli php public/local/modules/sprint.migration/tools/migrate.php ls
~~~

Сверить каталог и историю, подготовить восстановимую копию БД/файлов, затем применить конкретные migration ID текущей волны командой `up <Version...>`. Проверить повторный запуск и прикладной сценарий. Не выполнять общий `down` и не переписывать применённые файлы.

Для production подготовить отдельный одноразовый Swarm service с точным digest CLI, теми же network/config/secrets/mounts, что у принятого web runtime, и `restart-condition=none`. Единственная команда — предварительно проверенный конкретный migration ID; никакого supercronic. Сначала проверить `config`/`ls`, затем отдельным авторизованным запуском выполнить `up`. После проверки exit status и истории удалить только одноразовый service. Секреты не передавать в CLI-аргументах и не печатать runtime environment. W04 не создаёт этот service и не выполняет production-команд.

## Существующая установка и имя проекта

Имя репозитория `rabit-api` не переименовывает данные. У действующей установки из предыдущего выпуска стек называется `site`, bind-каталоги находятся под `/srv/rebit-p2p`. Их не переносить и не заменять `/srv/rabit-api` автоматически.

Автоматические `make deploy` и `make rollback` поддерживают только Swarm из одного узла: SSH target должен быть active manager, его LocalNodeID — единственным ID в `docker node ls --quiet`, состояние — ready/active, label — `db=db`. Guard обращается явно к локальному `unix:///var/run/docker.sock`, команды выпуска используют тот же socket. Multi-node, другой DB node, отсутствующий label, drain/down или worker вместо manager останавливают рецепт до передачи файлов и изменений runtime. Топология должна оставаться неизменной во время выпуска.

Это осознанное ограничение текущего рецепта, а не подтверждение фактической топологии production. Для расширения на несколько узлов нужен отдельный план: сверка Source/данных на реальном DB node, однозначное размещение MySQL/RabbitMQ и проверка доступности bind-каталогов каждого приложения. `external: true` само по себе не мешает Swarm создать пустой local volume на другом узле. Проверка тома на manager допустима здесь только после строгого guard единственного локального узла.

До выпуска получить фактические Source существующих Mounts и имена config objects. Проверять отдельные поля `docker service inspect --format`, не печатать environment/содержимое секретов. Заполнить deployment environment:

~~~dotenv
STACK_NAME=site
RUNTIME_DATA_DIR=/srv/rebit-p2p
MYSQL_VOLUME_NAME=<существующий Source MySQL>
RABBITMQ_VOLUME_NAME=<существующий Source RabbitMQ>
BACKEND_ENV_CONFIG_NAME=<существующий web config>
CRON_ENV_CONFIG_NAME=<существующий config LeadHunter>
AUDIT_CONSUMER_REPLICAS=0
~~~

Имена объектов берутся из текущего ServiceSpec. Их не пересоздают из `BUILD_NUMBER`, если выпуск повторно использует проверенный runtime. Согласовать все config/secret references до `make deploy`. Проверить сохранение дополнительных переменных и secret mounts cron: новый манифест не заменяет сверку с ServiceSpec действующего выпуска.

При `docker compose up` параметр `external: true` останавливает запуск при отсутствующем томе. Swarm-рецепт дополнительно требует успешный topology guard и `docker volume inspect` на том же единственном узле. Только для новой пустой установки допустимо заранее создать выбранные тома. Для существующей установки использовать обнаруженные имена либо выполнить проверенное восстановление в новые тома отдельной операцией. `docker compose down -v` и переименование стека не являются миграцией данных.

Без `--prune` сервисы вне манифеста сохраняются. Удаление устаревшего сервиса — отдельное решение после инвентаризации. Действующий frontend и сайт студии этим PR не изменяются.

## Optional secrets и сборка

Publisher проверяется с подставным Docker и синтетическими файлами. При отсутствии optional secret запись в выходном env не создаётся; диагностика не смешивается с путём. Required secret, нечитаемый файл или отсутствие Swarm manager останавливают работу до создания объектов.

Статический production manifest всё ещё ссылается на SMTP/Telegram secret objects. «Optional для publisher» не означает «условный mount в Compose»: перед выпуском нужны существующие согласованные references либо отдельно отревьюенный override, исключающий ненужный mount. Не создавать пустой объект и не подменять путь сообщением об ошибке. Повторное использование текущих immutable objects не требует запуска publisher.

Авторизация registry должна быть заранее настроена у `DEPLOY_USER` на SSH target. Deploy использует существующие Docker credentials и `--with-registry-auth`; сам `docker login` не выполняет и `TOKEN_GIT_HUB` в SSH-команду не подставляет. Синтетический токен проверяется через `make --dry-run`: его нет в stdout/stderr и сформированных командах.

Licensed Bitrix, production environment и credential artifacts не входят в Git. Сборка nginx использует локально предоставленный `.htpasswd`; его значение не помещается в отчёты или исходники. Проверить необходимые сборочные артефакты и digest до запуска production-рецепта.

## Backup, restore и откат

Перед изменением схемы или runtime сохранить в закрытый каталог: исходные ServiceSpec затронутых сервисов, image digests, references config/secrets, имена сетей/томов, историю миграций, дамп БД и согласованный снимок `upload`/приватных файлов. Полный ServiceSpec может содержать environment: не публиковать его в PR. Копии шифруются; ключ хранится отдельно.

Для согласованности БД с файлами остановить новые записи и дождаться текущих загрузок/операций в согласованное окно. Контролируемо остановить cron на это же окно. Сделать логический MySQL dump с транзакционной согласованностью, routines/triggers/events через закрытую конфигурацию клиента, без паролей в командной строке. Зафиксировать время и контрольные суммы. Копирование живого каталога MySQL не заменяет согласованный backup.

Полное прикладное восстановление проверяется в новых пустых томах и отдельном runtime-каталоге: восстановить совместимую версию MySQL, историю миграций и файлы; включить приложение только на тестовой сети. Реальные адресаты и cron до проверки отключены. Проверить Auth, принадлежность/наличие файлов после очистки кеша и нового процесса, миграционный статус, route/DI, отсутствие секретов в логах; тестовую доставку выполнять на подменённых отправителях. Записать результат и время восстановления.

W04 также содержит исполняемую ограниченную репетицию `tools/verify-w04-restore.py`: два временных MySQL 8.0 на отдельных именованных томах, без сети/портов. На синтетической схеме выполняются dump с routines/triggers/events, восстановление в пустую БД, копирование синтетического upload и сверка владельца, SHA-256, истории, процедуры, триггера и отключённого event. Исходный контейнер останавливается до проверок восстановленной копии. Это реальный dump/restore механизм; это не восстановление production или полной Bitrix-БД и не проверка RTO действующей установки.

Хранить копию до успешной приёмки выпуска и подтверждения следующей восстановимой копии, включая отдельную копию вне текущего сервера. Удаление/ротация выполняются по согласованному графику; W04 ничего не удаляет автоматически.

Откат кода использует сохранённые ServiceSpec/digests и точные config/mount references. `make rollback` применим к релизу с проверенным совместимым manifest/environment; старый release без новых обязательных переменных сначала требует проверки. Схему откатывать только проверенным down конкретной миграции; при отсутствии безопасного down остановить запись и использовать отдельный restore-план. Вернуть cron ровно в одном экземпляре и сверить новые/отправленные заявки, чтобы не повторить Telegram-доставку.

## Проверки W04

~~~sh
python3 tools/verify-w04-runtime.py
python3 tools/verify-w04-topology.py
python3 tools/verify-w04-restore.py
php api/tools/verify-w04-bootstrap.php api /path/to/bitrix /path/to/vendor
python3 tools/verify-w04-http.py --api-root /path/to/combined/api \
  --kernel /path/to/bitrix --vendor /path/to/vendor
~~~

PHP smoke требует PHP 8.4. HTTP runner использует локальные образы `rabit-api-nginx:20260911-074507` и `rabit-api-php-fpm:20260911-074507`, запускает только временные контейнеры на внутренней Docker-сети и удаляет их. Тестовый front controller монтируется во временный webroot; он не добавляется в production routes.

HTTP проходит curl → nginx → FPM → Bitrix HttpRequest → production mapper. Для изоляции отключено декодирование cookie, Application создаётся без конструктора БД/сессий, кеш метаданных — настоящий Bitrix CacheEngineNone. Это доказательство proxy/mapper, не авторизации, сохранения CFile, БД или отправки Lead. Последние проверяются интеграционными тестами W02/W03.

Результаты — в [verification](verification/). Полный multipart gate проверен на исходниках W03 через `--api-root`, без копирования чужих PHP-изменений в W04. `--skip-multipart` отмечает gate как `not-run` и не заменяет совместную проверку. После переноса W04 поверх W03 повторить команды на объединённом коде до приёмки.

Topology guard проверен настоящим Docker CLI на синтетическом read-only Engine API через временный Unix socket: single-node PASS; multi-node, чужой DB node, отсутствующий label, drain/down, worker и inactive Swarm — ожидаемый отказ. Живой Swarm этим тестом не используется; он проверяет реальные Go-template поля и поведение CLI.

Последний локальный прогон: topology guard — 8 сценариев PASS; синтетический MySQL 8.0.45 dump/restore в отдельных томах — PASS (40,73 с, не RTO production); shell publisher — 7 сценариев; обе Compose-конфигурации и обязательные значения — PASS; реальный ServiceLocator/Router — PASS на базе W04 и текущей W02; proxy/JSON/multipart — 12 групп PASS на текущей W03. PHP lint/CS и синтаксис Python/shell/сгенерированных SSH-команд — PASS. Точные входные ревизии и наличие незакоммиченных изменений указаны в `verification/checks.json`; это локальная совместная проверка, окончательный gate после переноса волн остаётся обязательным.
