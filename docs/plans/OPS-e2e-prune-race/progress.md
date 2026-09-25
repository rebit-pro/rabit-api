# OPS — `e2e-prune` и исчезновение ресурса (#88): прогресс

## Точка продолжения

- Ветка `codex/ops-e2e-prune-race`, base `main` `94502a1`, head — коммит этого журнала после `d1a957f`. Worktree `/home/user/rabit-api-worktrees/ops-e2e-prune-race`. Issue [#88](https://github.com/rebit-pro/rabit-api/issues/88). PR [#99](https://github.com/rebit-pro/rabit-api/pull/99), `Closes #88`, не слит.
- Завершено: `inspect()`/`inventory()`/`verdict()`, unit-тесты, строка в `docs/waves/a8/README.md`; T01–T06 PASS.
- Следующий шаг: ревью PR #99 пользователем; merge — отдельным действием после ревью.
- Блокеры: нет.
- Ограничения сессии: на машине работают E2E-стенды других сессий; ресурсы Docker не удалялись и не останавливались, `make test-e2e`/`make e2e-up` не запускались, `make e2e-prune` — только dry-run.
- Рабочее дерево: чисто после коммита журнала.
- Команды следующей проверки: `python3 -m unittest discover -s tools/tests -v`, `make e2e-prune`.

## Хронология

### 2026-09-25

- Прочитаны CLAUDE.md, AGENTS.md, #88, план OPS-e2e-gate-findings.
- Сообщения Docker 29.4.1 для отсутствующих ресурсов (только чтение, `docker <kind> inspect rabit-nope-000 rabit-nope-001`): код 1, stdout `[]`,
  stderr по строке на ресурс — `Error response from daemon: No such container: <id>`, `… network <id> not found`, `… get <id>: no such volume`.
  При смешанном запросе stdout содержит JSON найденных ресурсов.
- `--format '{{.ID}}\t{{.Label "rabit.browser_e2e"}}'` работает для `ps`, `network ls`; для `volume ls` — `{{.Name}}`.
- Реализация (`tools/run-browser-e2e.py`):
  - `GONE` — три шаблона сообщений «не найден»;
  - `inspect(kind, ids)` — один пакетный вызов; при ненулевом коде принимается только stderr из строк `GONE` по перечисленным ID и JSON остальных (число записей + исчезнувших = число ID), иначе `RuntimeError`;
  - `inventory()` — листинг с меткой запуска (`--format`), исчезнувший ресурс добавляется в свой запуск как `gone`;
  - `verdict()` — запуск с `gone` остаётся `keep` («disappeared during the check, run prune again»).
- Тесты `Inventory` (подмена `subprocess.run`, фейковый демон с сообщениями Docker 29): dry-run с исчезнувшим `--rm` контейнером живого стенда, apply удаляет только полностью проверенный брошенный запуск, исчезнувшие сеть и том, пять вариантов настоящих ошибок.
- `docs/waves/a8/README.md` — одно предложение о поведении `prune` при гонке.

#### Проверки 2026-09-25

| ID | Результат | Команда | Доказательство |
|----|-----------|---------|----------------|
| T01 | PASS | `python3 -m py_compile tools/run-browser-e2e.py tools/tests/test_run_browser_e2e.py` | exit 0 |
| T02 | PASS | `python3 -m unittest discover -s tools/tests -v` | `test_rm_container_gone_before_inspect_keeps_its_run_and_dry_run_removes_nothing`, `test_apply_removes_only_the_run_checked_completely` — ok |
| T03 | PASS | то же | `test_gone_network_and_volume_are_skipped` — ok |
| T04 | PASS | то же | `test_real_docker_errors_still_fail` (5 subTest) — ok |
| T05 | PASS | то же | Ran 22 tests, OK (18 прежних + 4 новых) |
| T06 | PASS | `make e2e-prune` (без `E2E_PRUNE_APPLY`) | exit 0; `rabit-e2e-053a6380f4ef`, `rabit-e2e-df39f10033d5` — would remove; работающий `rabit-e2e-c7ce157498f4` — keep (running containers); `Dry run: nothing removed` |

- Дополнительно на реальном демоне (только чтение): `inspect(kind, [<существующий ID с меткой>, "rabit-nope-000"])` для container/network/volume — 1 запись и `{'rabit-nope-000'}` в `gone`, без исключения.
- Коммиты `ef79e8d` (план), `d1a957f` (исправление, тесты, a8); push; PR [#99](https://github.com/rebit-pro/rabit-api/pull/99).
