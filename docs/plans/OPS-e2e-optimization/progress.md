# OPS — ускорение полного E2E-гейта: прогресс

## Точка продолжения

- Ветка `codex/ops-e2e-optimization`, base `main` `aee6808` (merge PR #47). Worktree `/home/user/rabit-api-worktrees/ops-e2e-optimization`. Инструкция и результаты — `frontend/docs/e2e-optimization.md`: перенесена из незакоммиченных файлов основного checkout вместе со ссылкой в `frontend/README.md`.
- Завершено: реализация, быстрые проверки и полные прогоны OPT-01…OPT-12, документация.
  - Прогретый гейт: медиана 243 с (четыре прогона, 237–269 с) против 503 с до изменений.
  - Холодный гейт: 302 с.
  - Состав: 77 сценариев (A 46 + B 31) и 4 верификатора.
- PR [#58](https://github.com/rebit-pro/rabit-api/pull/58), commit `7103ebf`. Merge в `main` выполняется сразу после PR по разрешению пользователя от 2026-09-23 («после PR можно мержить main»), чтобы параллельные ветки получили новый runner. Deployment не нужен: меняется только локальный гейт.
- Следующий шаг после merge: параллельным веткам влить `main`. Новый live spec в ветке нужно добавить ровно в одну группу `frontend/e2e/live/groups.json`, иначе конфиг Playwright и runner откажутся запускаться.
- Блокеров нет.
- Открыто по решению пользователя:
  - третья группа или стенд (−50…70 с браузера, +1,7 ГиБ RAM);
  - перестройка ожидания смены минуты в `zzzz-links`;
  - удаление старых контейнеров и томов `rabit-e2e-053a6380f4ef-*`, `rabit-e2e-df39f10033d5-*` от прерванных прогонов 12–13 сентября;
  - удаление detached worktree `/home/user/rabit-api-worktrees/ops-e2e-baseline` с логами базового прогона. В нём файлы, созданные контейнерами от root.
- Рабочее дерево: все изменения ветки входят в commit. Временные probe-тома удалены. Кеш-тома `rabit-e2e-npm-cache` и `rabit-e2e-phpstan-cache` — часть решения.

Команда полного гейта (образы и пути локальной машины):

```sh
make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor
```

## Хронология

### 2026-09-23

- Основной checkout `/home/user/rabit-api` используется другой сессией: ветку `codex/design-ux-plan` fast-forward'нули до `aee6808`. Работа ведётся в отдельном worktree от `origin/main`.
- Исторические логи `api/var/e2e/`. Последний полный прогон на коде `main` — `rabit-e2e-3ee4cd82d873`, commit `c304270` (worktree issues-31-33-34), 9 мин 39 с, 77 PASS. Длительности этапов по отметкам логов:
  - `npm ci` — 114 с;
  - check — 42 с;
  - unit — 3 с;
  - build — 32 с;
  - MySQL, RabbitMQ и vendor — 22 с;
  - php-lint — 2 с;
  - PHPStan — 25 с;
  - PHPUnit — 8 с;
  - схема, фикстуры и сервисы — 8 с;
  - браузер — 316 с (304,6 с тестов);
  - очистка — 6 с.

  Самые долгие браузерные файлы: links — 57,8 с (ожидание смены минуты), organization — 47,1 с, z-institution-detail — 34,5 с, catalog — 32,9 с, orders — 27,6 с, transfers — 24,7 с.
- Анализ зависимостей spec-файлов (read-only). Все прежние зависимости сохраняются при двух группах, A = файлы до `zzz-handoff`, B = `zzz-handoff`…`zzzzzz-transfers`. Причины, по которым нельзя запускать группы на одном backend:
  - одна сессия на пользователя: второй вход отзывает первый;
  - проверка пустого каталога;
  - ревизия глобальных условий;
  - отзыв сессий и деактивация учёток в staff;
  - `verify-orders` сверяет число заказов.
- Отдельные замеры на этой машине (20 CPU, 16 ГиБ; контейнеры с `--cpus 2`):
  - `npm ci` без кеша — 71,6 с;
  - `npm ci --prefer-offline` с прогретым томом npm-кеша — 12,3 с (кеш 122 МБ, node_modules 410 МБ);
  - `lint` — 19,3 с;
  - `typecheck` — 17,7 с;
  - `typecheck:e2e` — 4,3 с;
  - `test:commerce` — 2,6 с;
  - `vite build` без `vue-tsc` — 15,6 с;
  - lint, typecheck, typecheck:e2e и unit одновременно в отдельных контейнерах — 27,5 с wall, пик ≈ 2,1 ГБ.

  С `node_modules` только для чтения `vue-tsc` и `vite` падают: пишут `.vue-global-types` и `.vite-temp`. Поэтому том node_modules остаётся на запуск, изменяемым.
- Замеры PHP. vendor: копирование и autoload — 8,1 с. PHPStan:
  - текущий `--debug` — 27,6 с;
  - без `--debug`, холодный кеш, число процессов по умолчанию — 22,7 с, 1,31 ГБ при лимите 1,5 ГБ;
  - без `--debug`, 2 процесса, холодный кеш — 15,4 с, 496 МБ;
  - прогретый кеш — 0,7 с, «0 files will be reanalysed».

  Относительный `tmpDir` при `includes` из отдельного конфига указывает на `/app/var/phpstan`. PHPUnit: 566 тестов — 7,8 с с Xdebug и 9,0 с с `XDEBUG_MODE=off`, разница в пределах шума.
- В CLI/FPM development-образах включён Xdebug (`mode=develop,debug`, `start_with_request=yes`). В FPM-логе базового прогона 1102 попытки подключения к `host.docker.internal:9000`. Медиана `durationMs` 1447 API-запросов — 28,3 мс, сумма — 44,8 с.
- Утечки прежних запусков: тома `rabit-e2e-053a6380f4ef-*` (2026-09-12) и `rabit-e2e-df39f10033d5-*` (2026-09-13). Runner не обрабатывает SIGTERM, поэтому убитый процесс не выполняет очистку. Эти тома не удаляются без решения пользователя.
- Составлен `plan.md`: scope, решения, риски, OPT-01…OPT-12.
- OPT-01, базовый прогон старого runner в `ops-e2e-baseline` (`aee6808`): PASS, 77 сценариев, `rabit-e2e-7b3b52b2adf3`. Wall 503 с (8 мин 23 с). Этапы:
  - `npm ci` — 71 с;
  - check — 41 с;
  - unit — 3 с;
  - build — 38 с;
  - MySQL, RabbitMQ и vendor — 21 с;
  - php-lint — 1 с;
  - PHPStan — 26 с;
  - PHPUnit — 7 с;
  - схема и сервисы — 8 с;
  - фикстура — 2 с;
  - браузер — 276 с (Playwright 266 с);
  - очистка — 6 с.

  Пик занятой памяти хоста — 10,8 ГиБ (в покое ≈ 8,1 ГиБ). Контейнеры старого runner без меток, поэтому поконтейнерный пик не собран.
- Реализация:
  - `tools/run-browser-e2e.py`: этапы, параллельность, fail-fast, сигналы, кеши, группы на стендах, проверка состава, режимы `E2E_STANDS=1` и `E2E_GROUPS`;
  - `frontend/e2e/live/groups.json` и проекты в `playwright.live.config.ts` с проверкой полноты;
  - scripts `build`/`build-only`;
  - `api/tools/e2e/phpstan.neon`;
  - A8 README и frontend README.
- Быстрые проверки. Docker `npm run check` на том `rabit-ops-probe-node` — PASS. `py_compile` — PASS: побочный `tools/__pycache__` удалён, дальше синтаксис проверяется через `ast`. Проверка конфига Playwright `--list` в копии каталога:
  - `--project a` — 46 тестов в 7 файлах;
  - все проекты с настоящей фикстурой — 77 тестов в 12 файлах, сначала A, затем B, внутри группы прежний порядок;
  - лишний spec и несуществующий spec в группе — ошибка `Every live spec must belong to exactly one group`;
  - `E2E_MEDIA_BENCH=1` — A = 47 тестов в 8 файлах.

  OPT-08 PASS.
- PHPStan с `tools/e2e/phpstan.neon` в probe-томе: холодный — 16,0 с, 496 МБ; прогретый — 1,2 с. `-v` не печатает состояние result cache, в runner используется `-vv`.
- OPT-02, холодные кеши (томов `rabit-e2e-npm-cache`/`rabit-e2e-phpstan-cache` не было), `rabit-e2e-bef2652b4ae5`: PASS, 77 (A 46 + B 31), full gate, четыре верификатора PASS. Wall 302 с (5 мин 2 с), по сводке 298,8 с.
  - Frontend-ветка: `npm ci` — 91,9 с (сеть). Затем одновременно lint — 24,2 с, typecheck — 23,8 с, typecheck:e2e — 6,2 с, unit — 4,4 с, build-only — 21,7 с.
  - Backend-ветка шла параллельно: autoload — 7,8 с, php-lint — 3,5 с, PHPStan — 27,0 с («cache file does not exist»), PHPUnit — 10,5 с. MySQL и RabbitMQ стендов — 11,5 с.
  - Стенды: схема — 3,4 с, FPM и nginx — 4,5 с, фикстура — 0,6 с.
  - Браузер: A — 160,7 с, B — 140,7 с. Верификаторы — 1,5 с. Очистка — ≈ 11 с.

  После запуска не осталось контейнеров, сетей и томов с меткой запуска, кеш-тома сохранились. Пик памяти контейнеров — 4,1 ГиБ (typecheck 1,1, build 1,0, lint 0,57, MySQL 0,67×2, браузер 0,6/0,78), хоста — 12,3 ГиБ из 15,9.
- OPT-12: медиана `durationMs` API в FPM — 27,1 мс с Xdebug (базовый прогон, 1443 запроса) против 16,1 мс без него (новый прогон, 1449 запросов); p90 — 35,6 → 25,1 мс, сумма — 42,6 → 26,4 с. Попыток подключения Xdebug — 1455 → 0.
- OPT-03, два прогона с прогретыми кешами: PASS, 77/77. PHPStan в обоих: «Result cache restored. 0 files will be reanalysed».
  - `rabit-e2e-a499ef8479e1`: 269 с wall. `npm ci` — 16,3 с, PHPStan — 3,3 с, frontend-скрипты — 34 с (идут вместе с PHPUnit), до браузера — 62 с, A — 195,6 с, B — 139,5 с.
  - `rabit-e2e-41d9a4b71979`: 247 с. До браузера — 67 с, A — 168,0 с, B — 136,3 с.

  Пик контейнеров — 4,0–4,1 ГиБ, хоста — 11,9–12,8 ГиБ. Ресурсов не осталось. В первом прогоне медленнее почти все файлы группы A (catalog +10 с, organization +11 с): это фоновая нагрузка машины. `zzzz-links` по прогонам — 49/27/44 с (ожидание смены минуты).
- OPT-04, `E2E_STANDS=1` (`rabit-e2e-cf9ac1a87ac6`): PASS, 77/77, 330 с wall. До браузера — 53 с, затем A — 163,4 с и B — 103,7 с последовательно, links — 23,7 с. Пик контейнеров — 3,6 ГиБ, хоста — 11,5 ГиБ. Отдельные группы на двух стендах идут на 10–20 % медленнее, чем по одной (конкуренция CPU), но итог всё равно быстрее.
- OPT-10, probe-том PHPStan:
  - код без изменений — «0 files will be reanalysed»;
  - изменённое содержимое `EntityNotFoundException.php` (bind поверх файла) — «1 file will be reanalysed», возврат к исходному — снова 1 файл;
  - конфиг без обёртки E2E — кеш переиспользуется, так как `parallel` не влияет на анализ;
  - `--level=6` — «Result cache not used because the metadata do not match: projectConfig, level».
- OPT-05, `E2E_GROUPS=b` (`rabit-e2e-76c9ab6c94e5`): PASS, 31/31 и 4 верификатора, Notification на стенде b, 212 с wall. Вывод и `state.json` содержат `PARTIAL run, not a full gate`. Ресурсов не осталось.
- OPT-06 (`rabit-e2e-15476f7c4ae7`): временный `frontend/src/opt06-probe.ts` с ошибкой типов. `make` завершился с кодом 2 через 49 с. typecheck `failed` через 27,4 с (TS2322 в `typecheck.log`), lint `cancelled` в тот же момент. Ошибка указывает на `typecheck.log`, ресурсов запуска не осталось. Файл удалён, `git status` чистый.
- OPT-07 (`rabit-e2e-c35939bd9527`): SIGTERM процессу Python через 20 с после старта браузера (`sigterm-test.sh` в scratchpad). Код 143, обе группы `cancelled`, `stopped: true` без `cleanupErrors`, контейнеров, сетей и томов запуска — 0. Видны старые контейнеры `rabit-e2e-053a6380f4ef-*` и `rabit-e2e-df39f10033d5-*` (Exited 9–10 дней назад) — следствие прежнего отсутствия обработки SIGTERM. Их не трогал.
- OPT-09, `make e2e-up` (`rabit-e2e-be85556346ea`, 52 с, один стенд `all`): `/__e2e` отдаёт маркер, `/health` и `/login` — 200. `make e2e-test E2E_STATE=...`: A — 154,3 с, B — 156,2 с, 77/77 и 4 верификатора, `full gate`. `make e2e-down`: ресурсов 0.
- OPT-11: docker `npm run build` — PASS: вызывает `typecheck` (`vue-tsc`), затем `build-only`. `git diff --check` — PASS.
- Очистка стала параллельной внутри вида ресурса (контейнеры → сети → тома): около 7 с вместо 11. Прогон `rabit-e2e-38ee6ba12523` с этой очисткой: PASS, 77/77, 239 с wall, до браузера — 58 с, A — 171,5 с, B — 141,1 с. Логи всех 12 сервисных контейнеров сохранены.
- `check_results` перенесён внутрь этапа браузера: его провал теперь записывается в `stages` и включает fail-fast. Проверка на подготовленных `results.json` отклоняет пропущенный spec, flaky, skipped, ошибку загрузки и чужую группу. `validate` отклоняет неизвестную и пустую группу. Контрольный полный прогон итогового кода `rabit-e2e-67591296b6f3`: PASS, 77/77 (A 46 + B 31), 4 верификатора, `full gate`, 237 с wall; до браузера — 52 с, A — 178,1 с, B — 117,2 с; все этапы `passed`, ресурсов запуска 0, `cleanupErrors` пуст.
- Временные probe-тома удалены (`docker volume rm rabit-ops-probe-*`).
- `git commit` `7103ebf`, `git push -u origin codex/ops-e2e-optimization`, `gh pr create` — PR #58. Перед merge `main` сдвинулся с `aee6808` на `5f658e5`: один docs-коммит в `docs/plans/issues-31-33-34-photo-upload/progress.md`. Файлы гейта (runner, live spec, конфиги, `package.json`, `api/tools/e2e`) не менялись, повторять проверки не требуется. `gh pr view 58`: MERGEABLE, CLEAN.

## Тест-кейсы

- OPT-01: PASS, 2026-09-23, `make test-e2e ...` в `ops-e2e-baseline`, 503 с, 77/77.
- OPT-02: PASS, 2026-09-23, `make test-e2e ...` с удалёнными кеш-томами, 302 с, 77/77, ресурсы удалены.
- OPT-03: PASS, 2026-09-23, `make test-e2e ...` ×2 с прогретыми кешами: 269 и 247 с, 77/77, PHPStan из кеша. Затем 239 с после параллельной очистки и 237 с на итоговом коде.
- OPT-04: PASS, 2026-09-23, `make test-e2e E2E_STANDS=1 ...`, 330 с, 77/77, пик контейнеров 3,6 ГиБ против 4,0–4,1 ГиБ.
- OPT-05: PASS, 2026-09-23, `make test-e2e E2E_GROUPS=b ...`, 212 с, 31/31, помечен PARTIAL.
- OPT-06: PASS, 2026-09-23, временная ошибка типов: красный гейт через 49 с, lint `cancelled`, ресурсов 0.
- OPT-07: PASS, 2026-09-23, SIGTERM runner во время браузера: код 143, ресурсов 0.
- OPT-08: PASS, 2026-09-23, `npx playwright test --config playwright.live.config.ts --list` в копии каталога (лишний и отсутствующий spec — ошибка; 46/77/47 тестов); `check_results` и `validate` на подготовленных данных.
- OPT-09: PASS, 2026-09-23, `make e2e-up` → `make e2e-test E2E_STATE=...` → `make e2e-down E2E_STATE=...`.
- OPT-10: PASS, 2026-09-23, PHPStan probe: 0 файлов без изменений, 1 файл после изменения содержимого, сброс при другом `level`.
- OPT-11: PASS, 2026-09-23, docker `npm run check` и `npm run build`, синтаксис runner, `git diff --check`.
- OPT-12: PASS, 2026-09-23, анализ `*-fpm.log` базового и нового прогона: медиана 27,1 → 16,1 мс, попыток Xdebug 1455 → 0.
