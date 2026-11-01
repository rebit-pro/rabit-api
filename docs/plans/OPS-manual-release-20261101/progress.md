# OPS — ручной релиз 1 ноября 2026 — журнал

## Точка продолжения

- Ветка журнала codex/ops-manual-release-20261101; worktree /tmp/rabit-api-release-plan; исходный base/head a5533da338d45c50b7a27a2cf975c2d8892a0daf.
- Рабочий checkout стенда /home/user/rabit-api: ветка codex/ops-release-candidate-20261101, head 1e79abec27faee5fde38d43d3d4a0464e26264f9, дерево чистое.
- PR #215 (a2e1d7d), #220 (17e7bf9), #225 (92f0bbb), #227 (cb96522, draft); follow-up issue #228. Документы docs/waves/a8/README.md и docs/deployments/2026-09-11-rabit-api.md.
- Завершено: статусы PR и серверных сервисов прочитаны, origin/main обновлён, решение владельца по #227 получено.
- Сейчас: чистый интеграционный кандидат `1e79abec27faee5fde38d43d3d4a0464e26264f9` с итоговыми head #225/#227; локальный Docker Desktop завис на create/remove. Один следующий шаг: получить разрешение на restart Docker Desktop, восстановить локальный стенд и повторить полный gate.
- Блокеры: локальный Docker Desktop завис на операциях create/remove, обязательный E2E не завершён; merge и release не выполнялись. Владелец разрешил #227 до дальнейшей доработки юридических текстов.
- Рабочее дерево журнала: plan.md/progress.md уже сохранены commit `20cbd93`; текущая запись — единственное изменение. Основной checkout чист на кандидате `1e79abe`.
- Следующие проверки: после восстановления Docker `make e2e-prune`; `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor E2E_PHP_CLI_IMAGE=rabit-api-e2e-php-cli:local E2E_PHP_FPM_IMAGE=rabit-api-e2e-php-fpm:local E2E_NGINX_IMAGE=rabit-api-e2e-nginx:local make test-e2e`.

## Тест-кейсы

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| REL-T01 | PENDING | 2026-11-01 | Четыре PR открыты; #227 пока draft. |
| REL-T02 | PASS | 2026-11-01 | python3 -m unittest tools/tests/test_production_restart_policy.py -v: 2 теста OK на кандидате 8e4ec994. |
| REL-T03 | BLOCKED | 2026-11-01 | make test-e2e на 8e4ec994 завершился exit 2 после 991 с: docker network create завис до старта контейнеров, cleanup timeout; PASS нет. На итоговом кандидате 1e79abe не запускался. |
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
- 2026-11-01. После публикации журналов #225 (`92f0bbb`) и #227 (`cb96522`) локальный кандидат обновлён без конфликтов до `1e79abe`, дерево чистое. Отдельный follow-up по юридическим текстам: issue #228. Ни один из четырёх PR пока не слит, сервер не менялся.
