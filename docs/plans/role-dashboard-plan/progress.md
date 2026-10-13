# Прогресс: дашборды организатора и куратора

## Точка продолжения

- Ветка `codex/role-dashboard-plan`; base/main/origin/main `8cba22c7655b5886d5fe663523214bcd059e674b`; план опубликован коммитом d3be2f2, текущий HEAD — коммит с записью PR (`git rev-parse HEAD`).
- PR: https://github.com/rebit-pro/rabit-api/pull/36 — открыт в main. #35 — отдельный план СберПэй, не base и не runtime-зависимость.
- Документы: `plan.md`, `docs/waves/graph.json` (N1), соседний MoreFoto `docs/04-bitrix-modules/backend-waves.json` и `docs/05-rest-api/README.md`.
- Завершено: исследование существующего кабинета, областей Access, N1 и UI токенов; создана ветка, описаны роли/метрики/инфографика/ссылки/тест-кейсы.
- Сейчас: плановый срез завершён, PR #36 опубликован и прикреплён к задаче Codex; реализация отложена.
- Следующий шаг: review планового PR #36. Реализацию сейчас не начинать; вернуться отдельной задачей при готовности N1 и её зависимостей.
- Реализация отложена по просьбе пользователя; N1 planned, нужны её слитые зависимости и решения. Для текущего планового PR блокеров нет.
- Ветка содержит только plan/progress, требования N1 в graph.json, `docs/waves/role-dashboard/morefoto-plan-sync.patch` и verification.json. Перед финальным push изменяются только P6 и журнал; после push проверить чистое дерево. Локальные изменения СберПэй в соседнем MoreFoto сохранены и не входят в PR #36.
- Проверки: `python3 tools/verify-wave-graph.py docs/waves/graph.json`; канонический `wave_graph.py`; scoped comparison N1; `git diff --cached --check`.

## 2026-09-21 — Исследование и рамки

Пользователь попросил ещё один PR только с планом и отложил дальнейшую работу. Проверены AGENTS/CLAUDE, PR #29/#35, чистое дерево предыдущей задачи; выполнен HTTPS fetch с gh credential helper. Создана `codex/role-dashboard-plan` от актуального main 8cba22c, без коммитов #35.

Прочитаны MainRoutes, OverviewPage, DashboardBoard/DashboardGroups, dashboard snapshot, useCabinetScope, cabinet service, useGroupLink, MoreFotoTheme и UI токены; API ORG-01/11, HND-01/02/04/05, COM-12. Текущая загрузка полного кабинета без mocks явно отказывает; backend-дашборд не объявляем существующим. N1 уже владеет полными ролевыми сводками, поэтому уточняем её требования, а не создаём волну-дубликат.

План различает paid/ожидание/pending/unknown, нет двойного счёта заказов и возвратов. Срок ссылки — closesAt приёма заказов, не срок скачивания файлов; copy не изменяет sentAt и не отмечает отправку. Организатор видит общую область, куратор — свои назначения. Обязательны три содержательные инфографики и реальные HTTP/visual проверки будущей реализации.

Смежное наблюдение: legacy `frontend/src/views/dashboard/DashboardPage.vue` содержит P2P/Bybit; текущий MoreFoto overview идёт через modules/morefoto. Legacy не менялся.

## Проверки

| ID | Статус | Дата | Команда / результат |
| --- | --- | --- | --- |
| DOC-01 | PASS | 2026-09-21 | HTTPS fetch; `git switch -c codex/role-dashboard-plan origin/main`; base=8cba22c, чистый main |
| DOC-02 | PASS | 2026-09-21 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 40 волн, 99 ID, 35 WNN, 10 negative fixtures |
| DOC-03 | PASS | 2026-09-21 | `python3 /home/user/MoreFoto/docs/04-bitrix-modules/wave_graph.py /home/user/MoreFoto/docs/04-bitrix-modules/backend-waves.json` + scoped assertions: N1 одинаков, кроме N1 обе копии неизменны, ID/edges/statuses сохранены |
| DOC-04 | PASS | 2026-09-21 | `python3 /home/user/MoreFoto/docs/04-bitrix-modules/render-waves.py` дважды — одинаковые байты; `git -C /home/user/MoreFoto apply --check --reverse /home/user/rabit-api/docs/waves/role-dashboard/morefoto-plan-sync.patch` — exit 0 |
| DOC-05 | PASS | 2026-09-21 | Staged `git diff --cached --check` PASS, только 5 документов; push успешен; `gh pr view 36 --repo rebit-pro/rabit-api --json url,state,baseRefName,headRefName,headRefOid`: OPEN, main ← codex/role-dashboard-plan |
| UX-01 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |
| UX-02 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |
| UX-03 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |
| UX-04 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |
| UX-05 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |
| UX-06 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |
| UX-07 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |
| UX-08 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |
| UX-09 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |
| UX-10 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |
| UX-11 | PENDING | 2026-09-21 | Плановый PR: реализация/тесты этого сценария не выполнялись |

## 2026-09-21 — N1 и проверки

В обоих графах добавлены только scope/review/acceptance N1: организатор/куратор, корректные KPI, три инфографики, время закрытия и защищённое получение ссылки, UX/HTTP/visual приёмка. Существующий генератор автоматически отразил уточнение, его правка не требовалась; Markdown пересобран дважды с одинаковым результатом. Канонические изменения только JSON/Markdown N1 сохранены в отдельном review-patch, reverse-check прошёл.

Оба штатных валидатора PASS (40 волн, 99 ID, 35 WNN, по 10 negative fixtures). Scoped assertions: N1 побайтно эквивалентен как JSON-объект; ID, владельцы, dependsOn/unlocks, решения и статусы не менялись; все изменения вне N1 исключены. В данной ветке отсутствует paymentIntegration и коммиты PR #35. В соседнем MoreFoto уже есть локальная актуализация E4 и платёжный план из #35, поэтому общий readyFromMain там E5, а в этой ветке по базовому snapshot — E4. Это известное расхождение baseline, не повод включать чужую незамерженную правку; здесь проверяются только собственные изменения N1. Полный backend/runtime не объявляется готовым.

Результаты и контрольные SHA в `docs/waves/role-dashboard/verification.json`. Перед commit/push проверить staged whitespace и список файлов; UI/build/E2E не запускались, поскольку пользователь запросил только план. Публикация PR разрешена исходным запросом; merge/deployment и реализация не выполняются.

### Staged-проверка артефакта

Первый `git diff --cached --check` вернул FAIL: пустая строка контекста в конце unified patch распознана как новая пустая строка EOF. Patch пересобран с двумя строками контекста вместо трёх; целевые изменения JSON/Markdown не менялись. Повторный reverse-check на канонических файлах PASS. Staged whitespace повторяется после обновления артефакта.

Финальная staged-проверка `git diff --cached --check` — PASS. `git diff --cached --stat`: 5 документов, продуктовых файлов нет. `git merge-base HEAD origin/main` = 8cba22c, до первого собственного коммита `git log origin/main..HEAD` пуст — ветка независима. DOC-05: локальная часть PASS, удалённая проверка выполняется после создания PR. Следующий шаг — commit/push/create через настроенный gh; P6 пока ожидает URL PR.

## 2026-09-21 — Публикация и завершение

Создан коммит d3be2f2 с планом/графом/артефактами. HTTPS push через gh credential helper успешен; `gh pr create --repo rebit-pro/rabit-api --base main --head codex/role-dashboard-plan --title 'План дашбордов организатора и куратора MoreFoto' --body-file /tmp/rabit-role-dashboard-pr-body.md` создал https://github.com/rebit-pro/rabit-api/pull/36. PR прикреплён к текущей задаче.

DOC-01–DOC-05 PASS, P1–P6 закрыты. UX-01–UX-11 остаются ожидаемыми проверками будущей реализации и не запускались. Финальная запись журнала отправляется отдельным коммитом; после push проверить headRefOid и `git status --short`. Merge, UI/API-реализация и deployment не выполняются. На этом работа останавливается согласно просьбе пользователя.
