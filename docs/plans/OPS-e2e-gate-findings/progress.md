# OPS — находки по E2E-гейту (#61): прогресс

## Точка продолжения

- Ветка `codex/ops-e2e-gate-findings`, base `main` `d92b4c4`. Worktree `/home/user/rabit-api-worktrees/ops-e2e-gate-findings`. Issue [#61](https://github.com/rebit-pro/rabit-api/issues/61). PR — ещё не создан.
- Завершено: план.
- Выполняется: реализация пунктов 6, 1, 4, 5, 2, 3.
- Следующий шаг: коммит `.gitignore`, затем `prune`.
- Открытые решения пользователя: режим Xdebug по умолчанию (п. 2), резервы ускорения (п. 7), разовое удаление старых ресурсов.
- Ограничения сессии: на машине работают E2E-стенды других сессий. Контейнеры, тома, сети и образы не удаляются и не останавливаются; `make test-e2e`/`make e2e-up` не запускаются; общие образы не пересобираются.

## Хронология

### 2026-09-25

- Прочитаны CLAUDE.md, AGENTS.md, #61, план и журнал OPS-e2e-optimization.
- Состояние Docker (только чтение: `docker ps -a/volume ls/network ls --filter label=rabit.browser_e2e`):
  - `rabit-e2e-053a6380f4ef-*` (12.09) и `rabit-e2e-df39f10033d5-*` (13.09): по 4 контейнера `Exited (255)`, 3 тома, 2 сети;
  - тома `rabit-a8-1789211965-node/runtime` имеют только метку `rabit.fixture=a8`, без `rabit.browser_e2e`;
  - работающих стендов с меткой нет.
- Xdebug воспроизведён на `rabit-api-php-cli:d1-local`: в `conf.d` два ini (`docker-php-ext-xdebug.ini` с `zend_extension=xdebug` и `xdebug.ini` с `zend_extension=xdebug.so`); `php -r` печатает `Cannot load Xdebug - it was already loaded`, `[Log Files] File '/app/log/xdebug.log' could not be opened`, `[Step Debug] Could not connect … host.docker.internal:9000`.
- Длительность файлов группы B по `results.json` четырёх последних прогонов других веток: `zz-avatar` 8–14 с, `zzz-handoff` 24–39 с, `zzzz-links` 16–69 с, `zzzz-storefront` 7–10 с, `zzzzz-orders` 31–51 с, `zzzzzz-transfers` 29–34 с.
- Разбор F2: подпись готовности включает `salesFingerprint` активной продукции группы. `zzzz-storefront` создаёт глобальную продукцию и комплект, `zzzzz-orders` включает подарок — перенос второй части F2 в конец группы сбросил бы подготовку и упёрся в #51. `zz-avatar` и `zzz-handoff` каталог и условия не трогают — выбрано перенести подготовку в начало группы B.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|----|--------|------|---------|----------------|
| T01 | PENDING | | | |
| T02 | PENDING | | | |
| T03 | PENDING | | | |
| T04 | PENDING | | | |
| T05 | PENDING | | | |
| T06 | PENDING | | | |
| T07 | PENDING | | | |
| T08 | PENDING | | | |
| T09 | PENDING | | | |
| T10 | PENDING | | | полный gate после ревью |
| T11 | PENDING | | | после T10 |
| T12 | PENDING | | | после ревью, с согласия пользователя |
