# OPS — ручной релиз 1 ноября 2026 — журнал

## Точка продолжения

- Ветка журнала codex/ops-manual-release-20261101; worktree /home/user/rabit-api/.worktrees/ops-manual-release-20261101; base a5533da338d45c50b7a27a2cf975c2d8892a0daf, сохранённый head 2b89329552b03ccd49b513b86d883c5633ec9b5b.
- Рабочий checkout стенда /home/user/rabit-api: ветка codex/ops-release-candidate-20261101, head 1e79abec27faee5fde38d43d3d4a0464e26264f9, дерево чистое.
- PR #215 (a2e1d7d), #220 (17e7bf9), #225 (92f0bbb), #227 (cb96522, draft); follow-up issue #228. Документы docs/waves/a8/README.md и docs/deployments/2026-09-11-rabit-api.md.
- Завершено: статусы PR и серверных сервисов прочитаны, origin/main обновлён, решение владельца по #227 получено.
- Сейчас: полный gate на кандидате `1e79abec27faee5fde38d43d3d4a0464e26264f9` PASS: 156 браузерных сценариев; desktop/mobile отчёта и checkout просмотрены. Один следующий шаг: сверить неизменность PR и последовательно влить их в main.
- Блокеры: нет для merge; платёжный sandbox YooKassa не подключён, платёжные сценарии прошли в disabled mode. Merge и release ещё не выполнялись. SSH к rebit-pro восстановлен после включения владельцем. Владелец разрешил #227 до дальнейшей доработки юридических текстов.
- Рабочее дерево журнала: изменяется только progress.md; исходный head журнала `2b89329`, основной checkout чист на кандидате `1e79abe`.
- Следующие проверки: дождаться текущего `make test-e2e`; при PASS визуально проверить desktop/mobile и сверить PR; `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor E2E_PHP_CLI_IMAGE=rabit-api-e2e-php-cli:local E2E_PHP_FPM_IMAGE=rabit-api-e2e-php-fpm:local E2E_NGINX_IMAGE=rabit-api-e2e-nginx:local make test-e2e`.

## Тест-кейсы

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| REL-T01 | PENDING | 2026-11-01 | Четыре PR открыты; #227 пока draft. |
| REL-T02 | PASS | 2026-11-01 | python3 -m unittest tools/tests/test_production_restart_policy.py -v: 2 теста OK на кандидате 8e4ec994. |
| REL-T03 | PASS | 2026-11-01 | `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor E2E_PHP_CLI_IMAGE=rabit-api-e2e-php-cli:local E2E_PHP_FPM_IMAGE=rabit-api-e2e-php-fpm:local E2E_NGINX_IMAGE=rabit-api-e2e-nginx:local make test-e2e` на 1e79abe: exit 0, 156 browser (81+75), frontend/backend/SQL verifier PASS, 879.1 с; YooKassa sandbox OFF. Артефакт `api/var/e2e/rabit-e2e-cd8588bc9a8e/state.json`. |
| REL-T04 | PASS | 2026-11-01 | Просмотрены PNG из реального Chromium прогона: E10 desktop/mobile teacher group и E5 desktop/mobile checkout; блоки, поля имени ребёнка, текст согласия и нижние разделы видны, горизонтального переполнения нет. Guide links `node --test tests/ui/guide-links.test.mjs` PASS 2/2, статья отслеживается Git. |
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
- 2026-11-01. По разрешению владельца выполнен restart Docker Desktop. WSL CLI не нашёл backend, Windows docker.exe desktop restart/stop завершились timeout; Docker процессы принудительно остановлены, docker-desktop distro завершён, Docker data VHD штатно отсоединён `wsl --unmount` (exit 0). Ошибка VHD_ALREADY_ATTACHED исчезла, но чистый start снова timeout: status starting, /var/run/docker.sock отсутствует, backend не работает. Ubuntu процессы `ip` и `npm` в D-state указывают на зависший WSL netlink. Запрошено отдельное разрешение на полный `wsl --shutdown`. Сервер и PR merge не менялись.
- 2026-11-01. После перезагрузки компьютера владельцем `docker info` PASS (29.4.1), Docker API доступен. `timeout 20 docker network create --driver bridge rabit-e2e-network-probe-20261101` и `docker network rm rabit-e2e-network-probe-20261101` PASS. `make e2e-prune E2E_PRUNE_MIN_AGE_HOURS=0` отказал с кодом 2 (минимум 1 час, без удаления); dry-run с 1 часом показал только два брошенных запуска, `make e2e-prune E2E_PRUNE_MIN_AGE_HOURS=1 E2E_PRUNE_APPLY=1` PASS.
- 2026-11-01. Полный `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor E2E_PHP_CLI_IMAGE=rabit-api-e2e-php-cli:local E2E_PHP_FPM_IMAGE=rabit-api-e2e-php-fpm:local E2E_NGINX_IMAGE=rabit-api-e2e-nginx:local make test-e2e` запущен на `1e79abe`; две isolated MySQL/RabbitMQ ветви и npm ci уже PASS, PHP/frontend проверки выполняются.
- 2026-11-01. `ssh -o BatchMode=yes -o ConnectTimeout=10 rebit-pro ...` дважды дал timeout SSH banner; `ssh -o BatchMode=yes -o ConnectTimeout=45 rebit-pro printf` дал тот же timeout (exit 255). Пользователю отправлено уточнение о временном доступе. Серверных изменений нет.
- 2026-11-01. Локальный gate `rabit-e2e-cd8588bc9a8e`: npm ci, frontend UI/commerce, lint/style/typecheck/build, PHP lint/PHPStan/PHPUnit, две Bitrix/MySQL схемы, Notification и запуск FPM/nginx/worker — PASS; обе группы реального Chromium выполняются.
- 2026-11-01. `make test-e2e` на кандидате `1e79abe` завершился exit 0: Chromium 81+75=156, все SQL verifier PASS, total 879.1 с; skipped/flaky в итоговом выводе нет. Тестовый магазин YooKassa отключён (`E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env`), sandbox часть не проверялась.
- 2026-11-01. Визуально открыты E10 desktop/mobile teacher group и E5 desktop/mobile checkout из `api/var/e2e/rabit-e2e-cd8588bc9a8e`: имя ребёнка, отчёт, суммы и согласие отображаются, desktop/mobile layout не переполняется. SSH к rebit-pro после повторного включения владельцем отвечает; предварительно сверены 13 backend service 1/1, frontend 2/2, RabbitMQ и cron 1/1, production SHA 40b44df.
