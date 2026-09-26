# Issue #141 — прогресс

## Точка продолжения

- Ветка `codex/issues-141-media-worker-user` (worktree `/home/user/rabit-api-worktrees/issues-141-media-worker-user`),
  создана от `bf3dfd8`, затем fast-forward до origin/main `6d034b3` (merge PR #135). Issue #141. PR — см. журнал.
- Завершено: реализация, проверка удаления превью в `verify-photo-deletion.php` возвращена, быстрые проверки,
  локальная проверка прав в контейнерах (T01–T14, T17).
- Сейчас: коммит, push, PR.
- Следующий шаг: ревью PR; затем полный `make test-e2e` (запускает пользователь) — T15, T16, T18.
- Блокеры: нет. Открыто: выкатка stage/prod с разовой сменой владельца превью — только с отдельного согласия.
- Рабочее дерево: изменения ветки (см. `git status`), игнорируемые `api/var/`.
- Следующая проверка:
  `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`

## Журнал

### 2026-09-26

- Прочитаны `CLAUDE.md`, `AGENTS.md`, issue #141 (решение пользователя — вариант 1).
- Места запуска медиа-воркера:
  - production: `docker-compose-production.yml` → `api-media-consumer` (`rabit-api-php-cli`, `app:media:consume`);
  - dev: `docker-compose.yml` → `api-media-consumer` (profile `media`);
  - E2E: `tools/run-browser-e2e.py` → `<stand>-media` (`--user 0`, `--entrypoint php`, `tools/e2e/consume-media.php`);
  - stage: `morefoto_stage_media_consumer` (вне репозитория, образ `rabit-api-php-cli:d3-webp`, `/app` из релиза);
  - `Makefile`: `consume-media-once` через `api-php-cli` (ручной dev-запуск, не сервис) — не менялся.
  - Кроме воркера превью рендерит E2E-фикстура E4 `prepare-storefront.php` (`docker exec` в FPM-контейнер от root)
    через тот же `ProcessPhotoMessageHandler`.
- Образ `rabit-api-php-cli:d1-local`: Debian trixie, `setpriv` из util-linux 2.41.5, `www-data` = 1000:1000, umask 0022.
- Найден сопутствующий риск: Monolog `RotatingFileHandler` с `filePermission: 0644`; `api-cron` от root пишет в канал
  `media` (`dispatch-pending`) → суточный файл root 0644 → запись от `www-data` бросает `UnexpectedValueException`.
  Исправлено на `0664` (каталог логов `2775 www-data` уже задаёт группу).
- Реализовано:
  - `api/docker/common/php/docker-entrypoint.sh`: `run_command` — при `APP_RUN_AS_USER` и root после
    `load_runtime_env`/`configure_msmtp`/`fix_log_permissions` → `HOME` из passwd, `exec setpriv --reuid --regid
    --init-groups`; не root — проверка имени пользователя, иначе выход 1; без переменной — прежний `exec "$@"`.
  - `docker-compose-production.yml`, `docker-compose.yml`: `api-media-consumer` → `APP_RUN_AS_USER: www-data`.
  - `settings_extra.php`: `filePermission: 0664`.
  - `tools/run-browser-e2e.py`: `-media` от `--user www-data`; после старта `stat -c %U /proc/1` = `www-data`, иначе
    стенд падает; фикстура E4 — `docker exec --user www-data`.
  - `api/tools/e2e/prepare.php`: `/runtime` → `www-data` 0755 (фикстура E4 пишет туда `e4-*.jpg`, `e4-fixture.json`).
  - `deploy/media-previews-owner.sh` — разовая операция выкатки (проверка / `--apply`).
  - `tools/tests/test_media_worker_user.py` — compose prod/dev, прочие CLI-сервисы без переменной, поведение
    entrypoint на заглушках `id`/`setpriv`, E2E-раннер.
- Координатор: PR #135 слит (`6d034b3`). `git merge origin/main` — fast-forward (коммитов в ветке ещё не было).
  В `verify-photo-deletion.php` вместо комментария про #141 возвращена проверка из версии до `94ea82a`:
  `foreach ($previews($scenario['canonical']) as $path) { $check(!is_file($path), … ' of the deleted frame is removed'); }`.

### Проверки 2026-09-26

- T01–T04 (entrypoint в `rabit-api-php-cli:d1-local`, новый entrypoint смонтирован поверх):
  `docker run --rm --network none -v $EP:/usr/local/bin/docker-entrypoint.sh:ro -e APP_RUN_AS_USER=www-data rabit-api-php-cli:d1-local id`
  → `uid=1000(www-data) gid=1000(www-data)`; без переменной → `uid=0(root)`; `--user www-data` + переменная →
  `www-data`; `--user www-data` + `APP_RUN_AS_USER=root` → `Cannot switch from www-data to APP_RUN_AS_USER=root`, exit 1.
  Окружение (`MESSENGER_TRANSPORT_DSN`) сохраняется, `HOME=/var/www`, umask 0022.
- T05/T06 (том, воркер через entrypoint, как `writeVariant`): оригинал 0600 в 0700 от `www-data` прочитан (104 байта),
  `previews/ab` `www-data 755`, файл `www-data 644`; `unlink` от `www-data` → `true`. Контроль: воркер от root →
  `previews/cd` `root 755`, `unlink` от `www-data` → `Permission denied` (воспроизведение issue).
- T17 (реальный `GdPreviewRenderer` из ветки): `render()` через entrypoint с `APP_RUN_AS_USER=www-data` → thumb и preview
  `www-data:www-data 644` в `ab/` 755; `remove()` от `www-data` → OK, осталось 0 файлов.
- T07 (реальный Monolog `RotatingFileHandler` из vendor, каталог `2775 www-data`): root создаёт файл, затем запись от
  `www-data`: `0644` → `UnexpectedValueException`; `0664` → `write OK` (файл `root:www-data 664`).
  Дополнительно: root-файл лога 0644 в `/app/logs` при старте воркера через entrypoint становится `www-data 664`,
  дозапись от `www-data` — OK (подготовка root выполняется до понижения прав).
- T08: `docker run -d … -e APP_RUN_AS_USER=www-data … php -r 'while (true) sleep(1);'` → `ps`: PID 1 `www-data php`
  (`setpriv` заменён командой через `exec`, сигналы идут прямо PHP).
- T09/T10 (`deploy/media-previews-owner.sh` в образе от root, том смонтирован в `/rt/upload/morefoto`):
  проверка → 2 чужие записи превью + 1 чужая запись приватных (предупреждение), exit 1; `--apply` → 0, exit 0;
  повтор проверки → 0/0, exit 0; `unlink` от `www-data` после смены → `true`. Отказы: без путей; `PREVIEWS_DIR=/rt/upload`;
  `PREVIEWS_DIR=/`; запуск не от root; `MEDIA_UID=0`; неизвестный аргумент → usage, exit 2.
- T11: `python3 -m unittest discover -s tools/tests -v` → OK (26 tests). Мутация (вернуть `exec "$@"` в entrypoint) →
  FAILED (1), тест ловит регресс.
- T12: `python3 -m py_compile tools/run-browser-e2e.py tools/tests/test_media_worker_user.py` → OK;
  `sh -n docker-entrypoint.sh`, `bash -n deploy/media-previews-owner.sh` → OK;
  `docker run --rm -v $PWD:/mnt:ro koalaman/shellcheck:stable …` → у нового скрипта замечаний нет, у entrypoint
  только прежние SC2016 (info) в строках с `php -r`.
- T13: `docker compose -f docker-compose-production.yml config` (с обязательными переменными) → exit 0,
  `APP_RUN_AS_USER=www-data` только у `api-media-consumer`; `docker compose -f docker-compose.yml --profile media config`
  → exit 0, у dev `api-media-consumer` переменная есть и `MESSENGER_TRANSPORT_DSN` сохранён, у `api-php-cli`/`api-cron` — нет.
  (Без `MYSQL_VOLUME_NAME` dev-конфиг и на main падает с `required variable MYSQL_VOLUME_NAME` — не относится к задаче.)
- T14: `docker run --rm --network none -e XDEBUG_MODE=off -v $PWD/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app rabit-api-php-cli:d1-local sh -c "php -l …; php vendor/bin/php-cs-fixer fix --config=public/local/php-cs-fixer.php --dry-run --diff --allow-risky=yes --path-mode=intersection …"`
  по `settings_extra.php`, `tools/e2e/prepare.php`, `tools/e2e/verify-photo-deletion.php` → `No syntax errors`,
  php-cs-fixer `Found 0 of 1` (tools/e2e вне finder конфига).
- PHPUnit/PHPStan не запускались: PHP-код модулей не менялся (только конфиг логов и E2E-скрипты).
- `make test-e2e` / `make e2e-up` не запускались по указанию: gate — после ревью.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-26 | `docker run … -e APP_RUN_AS_USER=www-data … id` | `uid=1000(www-data)` |
| T02 | PASS | 2026-09-26 | то же без переменной | `uid=0(root)` |
| T03 | PASS | 2026-09-26 | `--user www-data` + переменная | `uid=1000(www-data)` |
| T04 | PASS | 2026-09-26 | `--user www-data` + `APP_RUN_AS_USER=root` | exit 1, `Cannot switch …` |
| T05 | PASS | 2026-09-26 | том: воркер через entrypoint, `unlink` от `www-data` | `true`; от root-воркера — `Permission denied` |
| T06 | PASS | 2026-09-26 | чтение оригинала 0600/0700 | `read original: 104 bytes` |
| T07 | PASS | 2026-09-26 | Monolog root → `www-data` | 0644 исключение, 0664 OK |
| T08 | PASS | 2026-09-26 | `ps` в контейнере | PID 1 `www-data php` |
| T09 | PASS | 2026-09-26 | скрипт: проверка / `--apply` / повтор | 2 → 0, exit 1 → 0 → 0 |
| T10 | PASS | 2026-09-26 | скрипт: защитные отказы | 5 отказов exit 1, usage exit 2 |
| T11 | PASS | 2026-09-26 | `python3 -m unittest discover -s tools/tests -v` | 26 OK; мутация ловится |
| T12 | PASS | 2026-09-26 | `py_compile`, `sh -n`, `bash -n`, shellcheck | без новых замечаний |
| T13 | PASS | 2026-09-26 | `docker compose … config` prod/dev | exit 0, переменная только у медиа-воркера |
| T14 | PASS | 2026-09-26 | `php -l`, php-cs-fixer | без ошибок |
| T15 | PENDING | — | `make test-e2e …` | после ревью |
| T16 | PENDING | — | `make test-e2e …` (`verify-photo-deletion.php`) | после ревью |
| T17 | PASS | 2026-09-26 | реальный `GdPreviewRenderer` в томе | render `www-data`, remove OK, 0 файлов |
| T18 | PENDING | — | `make test-e2e …` (фикстура E4) | после ревью |
