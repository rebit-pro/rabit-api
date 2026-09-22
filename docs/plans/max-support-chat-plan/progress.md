# Прогресс: чат клиента MoreFoto36.ru с куратором через MAX

## Точка продолжения

- Ветка: `codex/max-support-chat-plan`.
- Worktree: `/home/user/rabit-api-worktrees/max-support-chat-plan`.
- Base и HEAD при старте: `5b750c07e964e279e3517292dae6517643f3e7be` (`origin/main`, проверен fetch 22.09.2026).
- PR/issue: ещё не создан; существующих открытых задач по MAX не найдено.
- Связанные материалы: `plan.md`, `docs/waves/graph.json` (K1/H1), `docs/architecture.md`, соседний MoreFoto `docs/05-rest-api/README.md`.
- Завершено: чтение инструкций, просмотр main, PR/issues, источников MAX; создан изолированный worktree.
- Сейчас: итоговая проверка документов перед commit/push.
- Следующий шаг: создать draft PR только с plan.md и progress.md.
- Блокеры реализации: выбор UX MAX, доступ к подтверждённому боту, зависимости K1, отсутствие live-проверки reply/webhook.
- Рабочее дерево: только новые `docs/plans/max-support-chat-plan/plan.md` и `progress.md`; код не менялся.
- Следующая проверка: `git diff --cached --check`; после публикации `gh pr view --json number,url,isDraft,baseRefName,headRefName,files`.

## Журнал

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
| DOC-05 | PENDING | 22.09.2026 | PR ещё не создан |

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

- python3 /tmp/rabit-max-plan-check.py — PASS: 2 files, 18 testCases, 25 markdownLinks, localLinks/scope PASS. Временный скрипт не входит в PR.
- Перед commit/push: подготовлен только документационный diff; следующий шаг — публикация draft PR без merge.
