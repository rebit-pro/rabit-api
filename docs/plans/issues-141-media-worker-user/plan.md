# Issue #141 — медиа-воркер от www-data

## Цель

Превью кадров создаёт медиа-воркер, удаляет их PHP-FPM (`www-data`). Воркер должен работать от `www-data`, чтобы
FPM мог удалить превью удалённого кадра. Решение пользователя от 2026-09-26 — вариант 1 из issue: воркер от
`www-data` и разовая смена владельца существующих превью при выкатке.

## Контекст

- Production: `api-media-consumer` (`docker-compose-production.yml`) на образе `rabit-api-php-cli`. В образе нет
  `USER`, entrypoint `api/docker/common/php/docker-entrypoint.sh` не понижает права: воркер работает от root.
- Stage (`app.morefoto36.ru`): сервисы `morefoto_stage_media_consumer` и `morefoto_stage_media_dispatcher` на образе
  `rabit-api-php-cli:d3-webp`; `/app` смонтирован из релиза. Entrypoint зашит в образ (старый).
- E2E-стенд: сервис `<stand>-media` в `tools/run-browser-e2e.py` запускается с `--user 0` и `--entrypoint php`.
- Dev: `api-media-consumer` в `docker-compose.yml` (profile `media`), entrypoint монтируется из репозитория.
- `GdPreviewRenderer::writeVariant()` создаёт `<previews>/<xx>/` через `mkdir(0755)` и файлы `chmod 0644`;
  временный файл `*.tmp` лежит в том же каталоге. Каталог от root → `www-data` не может `unlink`.
- В образах php-cli и php-fpm `www-data` = UID/GID 1000 (`usermod`/`groupmod`). На хосте `www-data` обычно UID 33,
  поэтому на серверах нужен числовой `1000:1000`, а не имя.
- Entrypoint делает root-операции: пишет `/etc/msmtprc`, `chown -R` логов. Поэтому `user: www-data` в compose
  ломает старт (`set -e`, `chown` чужих файлов → выход). Права понижаются в конце entrypoint.
- Логи Monolog (`RotatingFileHandler`, `filePermission: 0644`): суточный файл `logs/logstash/media-<дата>.log`
  создаёт первый писатель. `api-cron` от root каждую минуту запускает `app:media:dispatch-pending` и пишет в канал
  `media`. Файл root 0644 → воркер от `www-data` получает `UnexpectedValueException` при первой записи.
  Каталог логов entrypoint держит `2775 www-data` (setgid), значит файл от root получит группу `www-data`;
  при `0664` запись группой разрешена.

## Scope

1. Entrypoint: переменная `APP_RUN_AS_USER`. Если задана и процесс root — после подготовки (env, msmtp, логи)
   `exec setpriv --reuid --regid --init-groups`. Если процесс уже не root — только проверка, что это нужный
   пользователь. Без переменной поведение прежнее (миграции, install-module, cron, другие консьюмеры — root).
2. `docker-compose-production.yml`: `api-media-consumer` → `APP_RUN_AS_USER: www-data`.
3. `docker-compose.yml` (dev): `api-media-consumer` → то же.
4. `settings_extra.php`: `filePermission` логов Monolog `0644` → `0664`.
5. `tools/run-browser-e2e.py`: сервис `-media` от `www-data` (без `--user 0`) и проверка, что PID 1 воркера не root.
6. Разовая операция выкатки: `deploy/media-previews-owner.sh` — проверка (по умолчанию) и `--apply`
   (`chown -h 1000:1000` всех записей превью с чужим владельцем); отчёт по приватным оригиналам без изменений.
7. Проверка конфигурации: `tools/tests/test_media_worker_user.py` (compose prod/dev, entrypoint, E2E-раннер).
8. Порядок выкатки stage/prod — в плане и в PR.
9. PR #135 слит в main (`6d034b3`) во время работы: main влит в ветку, в `api/tools/e2e/verify-photo-deletion.php`
   возвращена проверка удаления превью удалённого кадра (в виде до `94ea82a`).
10. E2E-фикстура E4 (`prepare-storefront.php`) рендерит превью тем же обработчиком: запускать её от `www-data`
    (`docker exec --user www-data`), а `/runtime` в `prepare.php` отдать `www-data` (фикстура пишет туда
    `e4-*.jpg` и `e4-fixture.json`).

## Вне scope

- Остальные CLI-процессы (cron, audit/notification/support-консьюмеры, миграции, install-module) остаются root.
- Выполнение команд на stage/prod — только при деплое с отдельного согласия пользователя.
- Bitrix-кеши `bitrix/{cache,managed_cache,stack_cache}`: воркер от `www-data` работает с ними так же, как FPM.

## Решения

- DEC-1: понижение прав в entrypoint по переменной, а не `user:` в compose и не `USER` в образе: root-подготовка
  (msmtp, права логов, подстановка пароля RabbitMQ из secret) остаётся, остальные задачи образа не затронуты.
- DEC-2: `setpriv` (util-linux, есть в `php:8.4-cli` на Debian trixie) — `exec` без промежуточного процесса,
  сигналы Swarm (`SIGTERM`) приходят прямо воркеру. `--reset-env` не используется: окружение (DSN, пути) нужно.
- DEC-3: umask не меняется (Docker по умолчанию `0022`): каталоги превью `0755`, файлы `0644` от `www-data`.
  FPM (тот же UID 1000) удаляет, nginx читает.
- DEC-4: на E2E воркер запускается `--user www-data` без entrypoint: entrypoint на стенде читал бы `.env` checkout
  и делал `chown` read-only `/app/logs`. Понижение прав entrypoint проверяется локально в контейнере.
- DEC-5: скрипт меняет только превью. Приватные оригиналы пишет FPM (`www-data`, 0600/0700); чужие записи в них
  скрипт показывает, но не меняет — это аномалия для ручного разбора.
- DEC-6: фикстура E4 от root создавала бы каталоги `previews/<xx>/` root 0755 (4 случайных префикса из 256).
  Кадр, загруженный в браузере с тем же префиксом, воркер от `www-data` не смог бы записать: риск 4/256 на каждый
  кадр, на десятках кадров прогона — заметный. Поэтому все записи медиа на стенде — от `www-data`, как на сервере.

## Риски

- Stage: образ `d3-webp` содержит старый entrypoint. Нужен entrypoint из релиза (`--entrypoint` на
  `/app/docker/common/php/docker-entrypoint.sh`) или новый образ; сверить spec сервиса перед изменением.
- Если на сервере существует суточный лог `media-<дата>.log` от root 0644, его исправит `fix_log_permissions`
  при старте воркера (от root, до понижения прав).
- Приватные оригиналы не от UID 1000 (например, восстановленные вручную от root) воркер от `www-data` не прочитает:
  скрипт показывает их число до переключения.

## Checklist

- [x] 1. Прочитаны CLAUDE.md, AGENTS.md, issue #141; найдены все места запуска воркера.
- [x] 2. План и прогресс.
- [x] 3. Entrypoint + compose prod/dev + права логов.
- [x] 4. E2E-раннер: воркер от `www-data`, проверка PID 1; фикстура E4 от `www-data`.
- [x] 5. `deploy/media-previews-owner.sh`.
- [x] 6. `tools/tests/test_media_worker_user.py`.
- [x] 7. Быстрые проверки и локальная проверка прав в контейнере.
- [x] 8. #135 слит — main влит, проверка удаления превью в `verify-photo-deletion.php` возвращена.
- [ ] 9. Коммиты, push, PR (не сливать).
- [ ] 10. После ревью: полный `make test-e2e` (запускает пользователь).

## Критерии приёмки

- Медиа-воркер в production, dev и на E2E работает от `www-data` (UID 1000); остальные CLI-сервисы не изменены.
- Превью, созданные воркером, удаляются процессом `www-data` (FPM).
- Воркер от `www-data` читает приватные оригиналы 0600 от `www-data`, пишет превью, временные файлы и логи.
- Разовая команда смены владельца есть в репозитории и описана в PR с порядком выкатки stage/prod.
- E2E-стенд падает, если воркер запущен от root.

## Тест-кейсы

| ID | Предусловия / действие | Ожидаемый результат | Команда |
|---|---|---|---|
| T01 | Контейнер php-cli с новым entrypoint, `APP_RUN_AS_USER=www-data`, `id` | `uid=1000(www-data) gid=1000(www-data)` | `docker run … docker-entrypoint.sh id` |
| T02 | То же без переменной | `uid=0(root)` — прежнее поведение | то же |
| T03 | Контейнер уже `--user www-data` и `APP_RUN_AS_USER=www-data` | команда выполняется от `www-data` | `docker run --user www-data …` |
| T04 | `--user www-data`, `APP_RUN_AS_USER=root` (несовпадение) | выход ≠ 0 с сообщением | то же |
| T05 | Воркер (`www-data`) в томе: `mkdir 0755` + webp `0644` + `rename`, затем процесс `www-data` (как FPM) `unlink` | удаление успешно; для сравнения от root — `Permission denied` | `docker run` c томом |
| T06 | Оригинал `0600` в каталоге `0700` от `www-data`, чтение воркером `www-data` | чтение успешно | то же |
| T07 | Лог-файл, созданный root с `0664` в каталоге `2775 www-data`, запись от `www-data` | запись успешна; с `0644` — ошибка | то же |
| T08 | PID 1 процесса в `api-media-consumer` после `setpriv` | `/proc/1` принадлежит UID 1000; `SIGTERM` доходит | `docker run -d …; docker stop` |
| T09 | `deploy/media-previews-owner.sh` на временном каталоге: проверка, `--apply`, повтор | проверка ничего не меняет и выходит 1 при чужих записях; `--apply` меняет владельца; повтор — 0 записей, выход 0 | `bash` в контейнере от root |
| T10 | Скрипт с путём не `…/morefoto/previews` или пустым | отказ без изменений | то же |
| T11 | Конфигурация | тест compose/entrypoint/раннера PASS | `python3 -m unittest discover -s tools/tests -v` |
| T12 | Синтаксис | OK | `python3 -m py_compile tools/run-browser-e2e.py`, `sh -n`, `bash -n`, `shellcheck` при наличии |
| T13 | Compose | конфиги валидны, у `api-media-consumer` есть `APP_RUN_AS_USER` | `docker compose -f … config` |
| T14 | PHP | php-cs-fixer/phplint по `settings_extra.php` | docker `rabit-api-php-cli:d1-local` |
| T15 | Полный gate: стенд стартует, воркер не root, превью создаются (все browser/verify) | PASS | `make test-e2e …` (после ревью, запускает пользователь) |
| T16 | Превью удалённого кадра исчезают с диска | `verify-photo-deletion.php`: `…-thumb.webp/…-preview.webp of the deleted frame is removed` | `make test-e2e …` |
| T17 | Реальный `GdPreviewRenderer`: `render()` воркером через entrypoint (`www-data`), `remove()` от `www-data` | превью созданы `www-data` 0755/0644, удалены, осталось 0 | `docker run` c томом |
| T18 | Фикстура E4 от `www-data` на стенде | `E4 real media and capability fixture prepared.` | `make test-e2e …` |

## Порядок выкатки (разовая операция)

Выполняется только при деплое с отдельного согласия пользователя.

### Production (`make deploy`, Swarm stack)

1. Скопировать `deploy/media-previews-owner.sh` на сервер. До деплоя, от root, проверка без изменений:
   `sudo RUNTIME_DATA_DIR=<runtime> bash media-previews-owner.sh`
2. Остановить воркер, чтобы он не создавал новые каталоги от root во время смены владельца:
   `docker service scale <stack>_api-media-consumer=0`
3. Смена владельца: `sudo RUNTIME_DATA_DIR=<runtime> bash media-previews-owner.sh --apply`
4. `make deploy` с новым образом (`api-media-consumer` поднимется от `www-data`, реплики из `MEDIA_CONSUMER_REPLICAS`).
5. Проверка: `docker exec <контейнер воркера> stat -c %u /proc/1` → `1000`; повтор шага 1 → 0 чужих записей;
   загрузка кадра → превью появились; удаление кадра → превью исчезли, нет warning `Deleted photo files remain on disk.`

### Stage (`app.morefoto36.ru`, сервисы `morefoto_stage_*`)

1. `docker service inspect morefoto_stage_media_consumer --format '{{json .Spec.TaskTemplate.ContainerSpec}}'` —
   убедиться, что `Command` пуст (entrypoint образа), и найти источник `/app` и путь превью в монтированиях.
2. Переключить backend на релиз с этим кодом (как обычно, `switch-backend.sh`).
3. `docker service scale morefoto_stage_media_consumer=0`.
4. Скопировать `deploy/media-previews-owner.sh` на сервер (в релиз попадает только `api/`), затем
   `sudo PREVIEWS_DIR=<stage runtime>/public/upload/morefoto/previews PRIVATE_MEDIA_DIR=<источник /app/var/private/media> bash media-previews-owner.sh`
   (проверка), затем то же с `--apply`. Пути — из монтирований шага 1.
5. `docker service update --detach=false --entrypoint /app/docker/common/php/docker-entrypoint.sh --env-add APP_RUN_AS_USER=www-data --replicas 1 morefoto_stage_media_consumer`
   (entrypoint образа `d3-webp` старый; берётся из смонтированного релиза).
6. Проверки как в production, шаг 5. Откат: `docker service rollback morefoto_stage_media_consumer`
   (превью от `www-data` root-воркер по-прежнему может перезаписывать).
