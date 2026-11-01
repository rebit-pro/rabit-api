# OPS — ручной релиз 1 ноября 2026 — журнал

## Точка продолжения

- Ветка журнала codex/ops-manual-release-20261101; worktree /tmp/rabit-api-release-plan; исходный base/head a5533da338d45c50b7a27a2cf975c2d8892a0daf.
- Рабочий checkout стенда /home/user/rabit-api: ветка codex/ops-photo-zoom, head fc707ce2b5072bcebd465a10d26f414ef40cbf11, исходно чистый.
- PR #215, #220, #225, #227; документы docs/waves/a8/README.md и docs/deployments/2026-09-11-rabit-api.md.
- Завершено: статусы PR и серверных сервисов прочитаны, origin/main обновлён, решение владельца по #227 получено.
- Сейчас: интеграционный кандидат 1f804b6 после исправления PR #225, локальный Docker Desktop завис на сетевых create/remove. Один следующий шаг: восстановить Docker после согласования с владельцем, затем повторить полный gate.
- Блокеры: локальный Docker Desktop завис на операциях create/remove, обязательный E2E не завершён; merge и release не выполнялись. Владелец разрешил #227 до дальнейшей доработки юридических текстов.
- Рабочее дерево журнала: новые plan.md и progress.md подготовлены к локальному commit; основной checkout чист на интеграционном кандидате.
- Следующие проверки: gh pr view; python3 -m unittest tools/tests/test_production_restart_policy.py -v; make test-e2e.

## Тест-кейсы

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| REL-T01 | PENDING | 2026-11-01 | Четыре PR открыты; #227 пока draft. |
| REL-T02 | PASS | 2026-11-01 | python3 -m unittest tools/tests/test_production_restart_policy.py -v: 2 теста OK на кандидате 8e4ec994. |
| REL-T03 | BLOCKED | 2026-11-01 | make test-e2e на 8e4ec994 завершился exit 2 после 991 с: docker network create завис до старта контейнеров, cleanup timeout; PASS нет. На новом кандидате 1f804b6 не запускался. |
| REL-T04 | PENDING | 2026-11-01 | Не запускалась. |
| REL-T05 | PENDING | 2026-11-01 | Не запускалась. |
| REL-T06 | PENDING | 2026-11-01 | Не запускалась. |
| REL-T07 | PENDING | 2026-11-01 | Не запускалась. |
| REL-T08 | PENDING | 2026-11-01 | Не запускалась. |

## Хронология

- 2026-11-01. Пользователь поручил merge #215, #220, #225, #227, E2E на локальном стенде и ручной деплой.
- 2026-11-01. gh pr view: #215/#220/#225 открыты и mergeable; #227 открыт, mergeable, draft. Actions checks пусты. Review #227 содержит юридический блокер, который владелец явно решил вынести в последующую доработку документов.
- 2026-11-01. ssh rebit-pro docker service ls: morefoto_frontend 2/2, backend-сервисы MoreFoto 1/1, site_rabbitmq/site_api-cron 1/1; деплой ещё не выполнялся.
- 2026-11-01. git fetch по SSH GitHub не сработал (port 22); HTTPS с credential helper gh успешно получил origin/main a5533da.

- 2026-11-01. Локально смоделирован merge #215, #220, #225, #227 поверх a5533da; конфликтов нет, кандидат 8e4ec994, git diff --check PASS, дерево чистое. Тест restart policy: 2/2 PASS.
- 2026-11-01. make test-e2e (изолированный стенд) завис до тестов на docker network create более 9 минут. Чтение Docker API работает; пробный network create с timeout 15 также завис. make e2e-prune E2E_PRUNE_APPLY=1 после dry-run для двух брошенных запусков прерван timeout 90 (exit 124); активные сервисы не затронуты. Запрошено разрешение на перезапуск Docker Desktop из-за работающих контейнеров других проектов.
- 2026-11-01. PR #225: статья guide/reports/index.html была скрыта правилом reports в frontend/.gitignore; node --test tests/ui/guide-links.test.mjs на составе старого PR дал FAIL 1/2. Статья добавлена force-add, commit 642813d опубликован в #225. На новом кандидате 1f804b6 тест PASS 2/2, git ls-tree подтверждает файл; git diff --check PASS. PR #227: документационный commit 632656b опубликован, решение владельца записано комментарием в PR.
