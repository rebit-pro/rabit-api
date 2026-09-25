# OPS — находки по E2E-гейту (#61): прогресс

## Точка продолжения

- Ветка `codex/ops-e2e-gate-findings`, base `main` `5dcb0e0` (после rebase; исходный base `d92b4c4`). Worktree `/home/user/rabit-api-worktrees/ops-e2e-gate-findings`. Issue [#61](https://github.com/rebit-pro/rabit-api/issues/61). PR [#87](https://github.com/rebit-pro/rabit-api/pull/87), `Refs #61`: review без блокеров, gate PASS, сливается. Base обновлён до `11df16a` (G1 и #86; merge `11a0886`).
- Завершено: пункты 1, 3, 4, 5, 6; в пункте 2 — двойная загрузка и лог. Быстрые проверки T01–T09 — PASS.
- Следующий шаг: по #61 остаются разовая очистка ресурсов 12–13.09 и томов `rabit-a8-*` (согласие пользователя), T12 и замеры п. 7; после пересборки dev-образов — локальная проверка Xdebug `mode=off`.
- Открытые решения пользователя:
  - п. 7 — резервы ускорения, только после замеров;
  - разовое удаление ресурсов 12–13.09 (`make e2e-prune E2E_PRUNE_APPLY=1`) и томов `rabit-a8-1789211965-node/runtime` (у них нет метки `rabit.browser_e2e`, нужна ручная команда `docker volume rm rabit-a8-1789211965-node rabit-a8-1789211965-runtime`).
- Ограничения сессии: на машине работают E2E-стенды других сессий. Контейнеры, тома, сети и образы не удалялись и не останавливались; `make test-e2e`/`make e2e-up` не запускались; общие образы не пересобирались.
- Рабочее дерево: чисто после коммита журнала.
- Команды следующей проверки:
  - `python3 -m unittest discover -s tools/tests -v`
  - `make e2e-prune`
  - `make test-e2e`, затем `find frontend api/var/e2e/<run> ! -user $(id -u)` и длительность `zzzz-links.spec.ts` в `api/var/e2e/<run>/b/b/results.json`.

## Хронология

### 2026-09-25

- Прочитаны CLAUDE.md, AGENTS.md, #61, план и журнал OPS-e2e-optimization.
- Состояние Docker (только чтение: `docker ps -a/volume ls/network ls --filter label=rabit.browser_e2e`):
  - `rabit-e2e-053a6380f4ef-*` (12.09) и `rabit-e2e-df39f10033d5-*` (13.09): по 4 контейнера `Exited (255)`, 3 тома, 2 сети;
  - тома `rabit-a8-1789211965-node/runtime` имеют только метку `rabit.fixture=a8`, без `rabit.browser_e2e`.
- Xdebug воспроизведён на `rabit-api-php-cli:d1-local`: в `conf.d` два ini (`docker-php-ext-xdebug.ini` с `zend_extension=xdebug` и `xdebug.ini` с `zend_extension=xdebug.so`); `php -r` печатает `Cannot load Xdebug - it was already loaded`, `[Log Files] File '/app/log/xdebug.log' could not be opened`, `[Step Debug] Could not connect … host.docker.internal:9000`.
- Длительность файлов группы B по `results.json` четырёх последних прогонов других веток: `zz-avatar` 8–14 с, `zzz-handoff` 24–39 с, `zzzz-links` 16–69 с, `zzzz-storefront` 7–10 с, `zzzzz-orders` 31–51 с, `zzzzzz-transfers` 29–34 с.
- Разбор F2: подпись готовности включает `salesFingerprint` активной продукции группы. `zzzz-storefront` создаёт глобальную продукцию и комплект, `zzzzz-orders` включает подарок — перенос второй части F2 в конец группы сбросил бы подготовку и упёрся в #51. `zz-avatar` и `zzz-handoff` каталог и условия не трогают — выбрано перенести подготовку в начало группы B.
- Реализация, коммиты по пунктам:
  - `bcddfa1` — план, `__pycache__/` в `.gitignore` (п. 6);
  - `e2bd39a` — `xdebug.ini` без `zend_extension` и лога (п. 2, частично);
  - `17acb50` — F2: `f2-links.ts` (общие хелперы), `z-links-preparation.spec.ts` первым в группе B, `zzzz-links.spec.ts` — передача и коррекция; `verify-links.php` требует оба файла (п. 3);
  - `a5266e1` — метки report/owner, `prune`, `make e2e-prune`, unit-тесты (п. 1);
  - `a0ef66a` — `restore_owner()` в `stop()` (п. 4);
  - `3fe1146` — `browser_timeout()` с bench (п. 5).
- Попутно: рекомендованная команда `docker run … -v rabit-issues262728-node:/app/node_modules …` создаёт на хосте пустой `frontend/node_modules` от root (точка монтирования), а `playwright test --list` без отдельных `var`/`reports` — `frontend/var` и `frontend/reports` от root. В worktree владелец возвращён одноразовым контейнером; `restore_owner()` обрабатывает и сам каталог `frontend/node_modules` (без содержимого).
- `main` ушёл на `5dcb0e0` (PR #84, #81): в `zzzz-links.spec.ts` добавлена проверка потерянного ответа подготовки с повтором по тому же ключу. Ветка перебазирована; конфликт решён переносом этого блока в `z-links-preparation.spec.ts` (там теперь подготовка через экран), `zzzz-links.spec.ts` — версия ветки. Повторены T01–T05, T09: PASS; группа B — 44 теста в 10 файлах.
- Во время dry-run на машине работали стенды других сессий (`rabit-e2e-6ce0b7756853`, затем `rabit-e2e-8105711e98f8`): `prune` пометил их `keep: running containers`.

### 2026-09-25 — решение по Xdebug и ревью

- Ревью пользователя (review на `f1f59a2`): блокирующих нет. Неблокирующее замечание вынесено ревьюером в #88 (`e2e-prune`: исчезновение временного контейнера между list и inspect).
- Решение пользователя по п. 2 — вариант A (коммит `9ae51f7`):
  - `api/docker/development/php/conf.d/xdebug.ini`: `xdebug.mode=off`, комментарий о включении через `XDEBUG_MODE`;
  - `docker-compose.yml`: `XDEBUG_MODE: ${XDEBUG_MODE:-off}` в `x-api-cli-environment` и у `api-php-fpm`; `api-php-cli` остаётся явно `off`;
  - `.env.example`: `XDEBUG_MODE=off` с подсказкой `debug,develop`.
- Проверка без пересборки (подмена ini в существующих образах):
  - `docker run --rm --network none [-e XDEBUG_MODE=debug,develop] --mount type=bind,source=<worktree>/api/docker/development/php/conf.d/xdebug.ini,target=/usr/local/etc/php/conf.d/xdebug.ini,readonly --entrypoint php rabit-api-php-fpm:d1-local -r 'echo implode(",", xdebug_info("mode"));'` — по умолчанию пусто, без попыток подключения; с переменной — `debug,develop`;
  - `docker compose -f docker-compose.yml --env-file .env.example config` — `XDEBUG_MODE: "off"` у всех PHP-сервисов.
- Эффект в локальном окружении — после пересборки dev-образов (`docker compose build api-php-fpm api-php-cli`).

### 2026-09-25 — merge `main` с G1 и полный gate

- `git merge origin/main` (`11df16a`: G1 PR #80 и #86) — два конфликта, разрешены с сохранением обеих сторон (`11a0886`):
  - `frontend/e2e/live/groups.json`: группа `b` — `z-links-preparation` первым и `zzzzzzzzz-payments` последним (11 спеков);
  - `tools/run-browser-e2e.py`: запуск браузера получает и `E2E_YOOKASSA=1` (G1), и `timeout=browser_timeout(group)` (п. 5).
  - `python3 -m py_compile tools/run-browser-e2e.py`, `python3 -m unittest discover -s tools/tests` — 18/18 OK.
- Gate `rabit-e2e-9011c70baab1` (446.1 с) — PASS, exit 0:
  - группа `a` 64/64, группа `b` 46/46;
  - `z-links-preparation` ✓ 5.3 с, `zzzz-links` ✓ 17.4 с (без ожидания полной минуты);
  - все верификаторы PASS, включая `verify-links.php` и `verify-payments.php`.
- `find frontend api/var/e2e/rabit-e2e-9011c70baab1 ! -user $(id -u)` — 0 файлов.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|----|--------|------|---------|----------------|
| T01 | PASS | 2026-09-25 | `python3 -m py_compile tools/run-browser-e2e.py tools/tests/test_run_browser_e2e.py` | без ошибок |
| T02 | PASS | 2026-09-25 | `python3 -m unittest discover -s tools/tests -v` | 18 тестов OK: `Verdict` (7), `Prune` (2) |
| T03 | PASS | 2026-09-25 | то же | `Owner` (3): свой процесс жив, чужой PID/время старта — мёртв, другой host/namespace — `None` |
| T04 | PASS | 2026-09-25 | то же | `BrowserTimeout`: 900 / 3600 / 900 с |
| T05 | PASS | 2026-09-25 | то же; `playwright test --config playwright.live.config.ts --list --project b` с отдельными `var`/`reports` в scratchpad | `validate()` проходит; порядок B: `z-links-preparation`, `zz-avatar`, `zzz-handoff`, `zzzz-links`, … |
| T06 | PASS | 2026-09-25 | `make e2e-prune` | `would remove` для `rabit-e2e-053a6380f4ef` и `rabit-e2e-df39f10033d5` (report path not labelled), `keep` для работающего стенда другой сессии; ничего не удалено |
| T07 | PASS | 2026-09-25 | `git status --porcelain` после `py_compile` | `tools/__pycache__/`, `tools/tests/__pycache__/` существуют и не видны |
| T08 | PASS | 2026-09-25 | `docker run --rm --network none --mount type=bind,source=<worktree>/api/docker/development/php/conf.d/xdebug.ini,target=/usr/local/etc/php/conf.d/xdebug.ini,readonly --entrypoint php rabit-api-php-{cli,fpm}:d1-local -r …` | Xdebug загружен, нет `already loaded` и `[Log Files]`; с `XDEBUG_MODE=off` — без предупреждений. После решения A (`9ae51f7`) по умолчанию режимов нет и попыток подключения нет; `XDEBUG_MODE=debug,develop` включает отладку |
| T09 | PASS | 2026-09-25 | `docker run --rm --network none -v <worktree>/frontend:/app -v rabit-issues262728-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npx eslint --fix <3 файла> && npm run check'` | exit 0: lint, stylelint, typecheck, typecheck:e2e, 27 unit |
| — | PASS | 2026-09-25 | `restore_owner()` на scratch-каталоге с root-файлами, созданными контейнером | `ownerRestored True`; root-файлы в `dist`, `report`, `report/g` возвращены, содержимое `node_modules` не тронуто |
| T10 | PASS | 2026-09-25 | `make test-e2e` `9011c70baab1` | exit 0, a 64/64, b 46/46; `zzzz-links` 17.4 с, `verify-links` PASS |
| T11 | PASS | 2026-09-25 | `find frontend api/var/e2e/rabit-e2e-9011c70baab1 ! -user $(id -u)` | 0 файлов |
| T12 | PENDING | | `make e2e-up` в одном worktree, `make e2e-prune E2E_PRUNE_APPLY=1` из другого | с согласия пользователя; вне merge-gate, `Refs #61` |
