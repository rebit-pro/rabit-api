# Прогресс: вопросы куратору с сайта МореФото через MAX

## Точка продолжения

- Ветка: `codex/max-support-chat-plan`, worktree `/home/user/rabit-api-worktrees/max-support-chat-plan`.
- Base: `4621ad9` (`origin/main` на 25.09.2026, ветка перебазирована). PR: [#48](https://github.com/rebit-pro/rabit-api/pull/48), ready for review (head графа 5f99beb).
- Связанное: `plan.md`, `docs/waves/graph.json` (K1, F2, E4, B2, H1, B4), соседний MoreFoto `docs/05-rest-api/README.md`.
- Завершено: план переписан под постановку 25.09.2026 (общий канал без заказа, родитель по ссылке и имени,
  воспитатель из кабинета, кураторы в группе MAX); сверены требования MAX; логотип бота 500×500 подготовлен вне репозитория.
- Сейчас: план согласован, K3 и SUP-07…12 внесены в граф и канон (патч `docs/waves/k3/morefoto-contract.patch`). Пользователь создаёт бота.
- Следующий шаг: review и merge PR 48; затем ветка `codex/k3-max-curator-questions` от main и реализация K3.
- Блокеры: для плана — нет. Для включения K3 — модерация бота, токен, группа MAX; MAX-D06 до production.
- Рабочее дерево: `plan.md`, `progress.md`, `docs/waves/graph.json`, `docs/waves/k3/morefoto-contract.patch`; runtime-кода нет. Канон MoreFoto изменён на месте (не git). Токен в Git не хранится.
- Расхождения: канон уже содержит PAY-10/11 незамерженной G1 (118 против 116 в main); E6 слита (54bd4ab), но в графе main ещё `review` — синхронизирует следующая волна.
- Команды: `git status --short`; `git diff --check origin/main...HEAD`;
  `python3 tools/verify-wave-graph.py docs/waves/graph.json`; `gh pr view 48 --json isDraft,headRefOid,files`.

## Журнал

### 25.09.2026 — K3 в графе и каноне

- Пользователь принял MAX-D07 (заведующая пишет наравне с воспитателем) и MAX-D08 (отвечает любой участник группы), попросил добавить K3 в граф.
- Канон MoreFoto: `build.py` — SUP-07…12, доступ «Вопрос» (X-Question-Key) и «MAX» (X-Max-Bot-Api-Secret), роль `Р/В`; `validate.py` — проверки новых заголовков и секретных переменных; `backend-waves.json` — K3 (deps B2, B4, E4, F2, H1; без открытых gates), `K1.dependsOn += K3`, endpointCount 112 → 118.
- `python3 docs/04-bitrix-modules/wave_graph.py docs/04-bitrix-modules/backend-waves.json` — PASS, readyFromMain E6, G1, K3, 118 endpoints.
- `python3 docs/04-bitrix-modules/render-waves.py` — PASS, 52 волны; `python3 docs/05-rest-api/build.py` — PASS; `python3 docs/05-rest-api/validate.py` — PASS, 118 requests; `node docs/05-rest-api/validate-postman.cjs` — PASS.
- `docs/waves/graph.json`: K3, `K1.dependsOn += K3`, endpointCount 110 → 116. `python3 tools/verify-wave-graph.py docs/waves/graph.json` — PASS: 52 волны, 116 endpoints, 35 legacy, readyFromMain E6, K3.
- `diff -ruN` снимка канона до/после → `docs/waves/k3/morefoto-contract.patch` (12 файлов); `patch -p1 --dry-run` на исходном снимке — PASS. `git diff --check` — PASS.

### 25.09.2026 — новая постановка и переписанный план

- Пользователь: чат не привязан к заказу; родители без аккаунтов приходят по ссылке галереи и оставляют имя;
  воспитатели пишут из кабинета; Рита и Алёна получают вопросы и отвечают в MAX. Приняты MAX-D01…D05, прежний
  вариант по заказу и K1 заменён отдельной волной K3.
- Пользователь подтвердил профиль самозанятого на платформе MAX для партнёров (снимок экрана).
- Сверены [подключение](https://dev.max.ru/docs/maxbusiness/connection) и [создание бота](https://dev.max.ru/docs/chatbots/bots-create/create):
  самозанятый допускается, до 2 ботов, логотип 500×500 до 5 МБ, название до 59, описание до 200 символов, модерация до 48 ч.
- Проверено в коде: MED-01 отдаёт `curator` группы; роли `teacher`/`head` имеют кабинет; H1 даёт образец outbox,
  dispatcher и consumer; `IssueAccessInvitationUseCase` — образец 429 `RATE_LIMITED`; секреты — `/run/secrets` через `runtime-env.php`.
- Зависимости K3 (F2, E4, B2, H1, B4) — merged по `docs/waves/graph.json`.
- `git rebase origin/main` — PASS, base `4621ad9`.
- Логотип `morefoto-max-logo-500.png` сгенерирован из знака favicon и токенов бренда (sea-600, sea-200, Manrope), передан пользователю; в репозиторий не добавлен.

### 22.09.2026 — исследование и изоляция

- Прочитаны AGENTS.md и CLAUDE.md, профильные skills ReBit и правила Bitrix-контрактов.
- Исходный checkout находится на `codex/design-ux-plan` с незакоммиченными материалами дизайна; они не менялись и не переносились.
- `git fetch origin main` — PASS; актуальный main `5b750c0`.
- `gh pr list --state open --limit 30 --json number,title,headRefName,url` и `gh issue list --state open --limit 70 --json number,title,url` — PASS после повторного запуска; первая попытка завершилась сетевой ошибкой api.github.com.
- `git worktree add -b codex/max-support-chat-plan /home/user/rabit-api-worktrees/max-support-chat-plan origin/main` — PASS.
- Обычный shell недоступен из-за `helper_unknown_error`; команды выполняются с разрешённым повышением доступа. WSL `rg` отсутствует, использованы адресные grep/find.
- По официальной документации MAX подтверждены отправка сообщений и webhook; автоматическое создание группового чата не найдено в опубликованном API. Реальный бот не подключался.
- K1 запланирован после I3/J3; H1 реализует EMAIL. Старое описание основы в docs/architecture.md и профильных справочниках не заменяет проверку текущего кода.

## Результаты проверок

| ID | Статус | Дата | Команда / свидетельство |
| --- | --- | --- | --- |
| DOC-01 | PASS | 22.09.2026 | `git diff --cached --stat`: 2 файла, только plan.md/progress.md |
| DOC-02 | PASS | 22.09.2026 | 11 страниц dev.max.ru открыты; официальная OpenAPI прочитана через gh с фиксированным ref, источники помещены в план |
| DOC-03 | PASS | 22.09.2026 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 40 волн, 99 endpoints, 35 исторических ID, 10 negative fixtures; K1 blocked. Код H1 и отсутствие Support проверены |
| DOC-04 | PASS | 22.09.2026 | `python3 /tmp/rabit-max-plan-check.py`: 2 файла, 18 ID, 25 Markdown-ссылок, локальные цели существуют; `git diff --cached --check` чист |
| DOC-05 | PASS | 22.09.2026 | `gh pr view 48 --json number,url,isDraft,baseRefName,headRefName,headRefOid,files`: draft=true, base=main, нужная ветка и ровно 2 файла |

Runtime-тесты, браузерный E2E и MAX round-trip не запускались: текущая задача документарная, интеграции ещё нет.

### 22.09.2026 — завершён проект плана, подготовка к проверкам

- Подготовлены MVP по заказу, вариант гостевого чата как отдельное решение, личный диалог с ботом и альтернативы.
- Проверена официальная OpenAPI `max-messenger/api-schema`, commit `1a4a502fab096aa3a15d83d7ea95667ffb44d2ac` от 18.09.2026. Команды: `gh repo list max-messenger --limit 50 --json name,url`; `gh api repos/max-messenger/api-schema/contents`; `gh api repos/max-messenger/api-schema/commits?per_page=1`; `gh api repos/max-messenger/api-schema/contents/schema.yaml?ref=1a4a502fab096aa3a15d83d7ea95667ffb44d2ac --jq .content | base64 -d`.
- Подтверждены `message.body.mid`, `message.link.message.mid`, отсутствие операции создания группового чата и документированного idempotency key для POST /messages. Выявлено расхождение описания disable_link_preview между схемой и веб-документацией; оно внесено в план живой проверки.
- Сверены QueueEmailUseCase и технические контракты: H1 EMAIL не выдается за MAX. Подготовлены предложения по отдельному transport-порту, Support outbox/inbox, правам и состоянию deliveryUnknown.
- Уточнены ограничения SUP: повторное сообщение клиента требует нового согласованного API. Активный граф и 99 ID не изменялись.
- План содержит DOC-01…05 и CHAT-01…13. Следующий шаг — проверки документов, затем commit/push и draft PR.

## Приёмочные сценарии будущей реализации

Эти сценарии не входят в проверку документационного PR. Все ещё не выполнены; точные предусловия/действия/ожидания находятся в plan.md.

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| CHAT-01 | PENDING | 22.09.2026 | Планируется `make test-e2e`; чата в коде нет |
| CHAT-02 | PENDING | 22.09.2026 | Планируется `make test-e2e`; чата в коде нет |
| CHAT-03 | PENDING | 22.09.2026 | Планируются `make test-e2e` и live MAX; бот не подключён |
| CHAT-04 | PENDING | 22.09.2026 | Планируются `make test-e2e` и live reply; бот не подключён |
| CHAT-05 | PENDING | 22.09.2026 | Планируется `make test-e2e`; webhook не реализован |
| CHAT-06 | PENDING | 22.09.2026 | Планируется `make test-e2e`; inbox не реализован |
| CHAT-07 | PENDING | 22.09.2026 | Планируется `make test-e2e` с отказами транспорта |
| CHAT-08 | PENDING | 22.09.2026 | Планируется `make test-e2e` с отзывом прав |
| CHAT-09 | PENDING | 22.09.2026 | Планируется `make test-e2e`, Chromium |
| CHAT-10 | PENDING | 22.09.2026 | Планируются `make test-e2e` и ручной MAX |
| CHAT-11 | PENDING | 22.09.2026 | Планируются `make test-e2e` и проверка runbook |
| CHAT-12 | PENDING | 22.09.2026 | Планируются `make e2e-up`, `make e2e-test E2E_STATE=<state>`, MAX, `make e2e-down E2E_STATE=<state>` |
| CHAT-13 | PENDING | 22.09.2026 | Планируются desktop/mobile просмотр и снимки на стенде CHAT-12 |

### 22.09.2026 — результаты проверок перед commit

- `git diff --cached --check` — PASS, замечаний whitespace нет.
- `git diff --cached --stat` — PASS, только два новых документа; реализация и граф не затронуты.
- `python3 tools/verify-wave-graph.py docs/waves/graph.json` — PASS: 40 волн, 99 API ID, 35 legacy ID, все 10 отрицательных сценариев отклонены. K1 явно находится в blocked.
- Backend/frontend/runtime/browser/MAX проверки не выполнялись и не отмечены PASS. Draft PR предназначен для обсуждения плана, не подтверждает готовность продукта к merge.

- `python3 /tmp/rabit-max-plan-check.py` — PASS: 2 files, 18 testCases, 25 markdownLinks, localLinks/scope PASS. Временный скрипт не входит в PR.
- Перед commit/push: подготовлен только документационный diff; следующий шаг — публикация draft PR без merge.

### 22.09.2026 — PR опубликован, финализация

- `git commit -m "docs: plan MoreFoto support chat with MAX"` — PASS, `0da91d32b22acdf5303d0e5e4b9cf82ef5cce621`.
- `git push -u origin codex/max-support-chat-plan` — PASS.
- `gh pr create --draft --base main --head codex/max-support-chat-plan --title "План чата клиента MoreFoto с куратором через MAX" --body-file /tmp/rabit-max-plan-pr-body.md` — PASS, PR #48. PR прикреплён к текущей задаче Codex.
- `gh pr view 48 --json number,url,isDraft,baseRefName,headRefName,headRefOid,files` — PASS: draft, main, нужная ветка, только plan.md/progress.md. `git status --short` после первого push пустой.
- Перед заключительным commit/push обновлены checklist документа и точка продолжения; смысл плана не изменён. Merge и deployment не выполнялись. Ответ на optional-вопрос о предпочтительном UX пока не получен; вариант личного бота остаётся предложением.

## Тест-кейсы редакции 25.09.2026

CHAT-01…13 первой редакции заменены MAX-01…13 (plan.md, раздел 13).

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| DOC-01 | PASS | 25.09.2026 | `git diff --name-only origin/main...HEAD`: plan.md, progress.md, graph.json, k3/morefoto-contract.patch |
| DOC-02 | PASS | 25.09.2026 | Открыты dev.max.ru: подключение к платформе и создание бота; требования внесены в plan.md, раздел 3–4 |
| DOC-03 | PASS | 25.09.2026 | verify-wave-graph: 52/116/35, K3 ready; канон: wave_graph, render, build, validate (118), validate-postman — PASS; patch dry-run — PASS |
| DOC-04 | PASS | 25.09.2026 | `git diff --check origin/main...HEAD` чист; DOC-01…05 и MAX-01…13 есть в progress |
| DOC-05 | PASS | 25.09.2026 | `gh pr view 48 --json isDraft,files,headRefOid`: ready for review, head 5f99beb, 4 файла |
| MAX-01…MAX-13 | PENDING | 25.09.2026 | Реализация K3 не начата |
