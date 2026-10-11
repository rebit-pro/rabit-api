# D4 — прогресс

## Точка продолжения

- Дата: 11.10.2026. Ветка `codex/d4-sent-markup-correction` от `origin/main` 50c8ee1 (merge E7, PR #162); рабочая копия `/home/user/rabit-api-worktrees/d4-sent-markup-correction`. PR нет.
- Связанное: план владельца `docs/plans/OPS-telemos-feedback-plan/` и граф с D4 — PR #173 (открыт, не слит). План волны — `docs/plans/D4_sent-markup-correction/plan.md`.
- Завершено: инвентаризация кода (два read-only агента и ручная сверка), план с решениями D4-DEC-01…06.
- Сейчас: решения приняты; реализация S2 (Organization).
- Следующий шаг: S3 (Commerce: контракт заказов группы и отказ quote/заказа).
- Блокеры: PR D4 открывается только после merge #173.
- Рабочее дерево: только `docs/plans/D4_sent-markup-correction/{plan.md,progress.md}`.
- Проверки (план): PHPUnit/PHPStan — docker `rabit-api-php-cli:d1-local` с vendor volume (для D4 — скопировать `rabit-e7-vendor`, если `api/composer.lock` не менялся); frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`; граф — `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Журнал

### 11.10.2026 — старт

- E7 слита в `main` (PR #162, 50c8ee1) по поручению пользователя. Следующая волна по решению владельца — D4.
- D4 определена в графе ветки `codex/ops-telemos-feedback-plan`. Ветка запушена, PR #173 открыт; в неё влит `main` 50c8ee1 без конфликтов, граф валиден (59 волн).
- Других веток и PR по D4 нет (`git branch -a`, `gh pr list`).
- Worktree: `git worktree add -b codex/d4-sent-markup-correction /home/user/rabit-api-worktrees/d4-sent-markup-correction origin/main`.
- Инвентаризация (два read-only агента: media; commerce/handoff/frontend). Факты — в плане, раздел «Факты инвентаризации». Главное:
  - запрет после передачи — `groupEditable = UF_SENT_AT is null`;
  - снятия кадра с ребёнка нет;
  - старые quote уже ломаются при правке разметки;
  - сериализация с заказом — эксклюзивная блокировка строки группы;
  - контракта «у группы есть заказы» нет;
  - куратор не имеет доступа к разметке.
- Номера API: HND-02 — карточка ссылки, HND-03 — подготовка, HND-04 — передача (в графе D4 указаны HND-02…04 как проверяемые); COM-09 — quote, COM-10 — заказ; MED-02 — список кадров, MED-04 — кадр, MED-05 — привязка, MED-06 — обложка.

### 11.10.2026 — решения

- Пользователь принял рекомендуемые варианты: сеанс исправления с паузой продаж (DEC-01), организатор и куратор учреждения с правом `media.correct` (DEC-02), существующие команды без новой операции снятия кадра (DEC-03), начало исправления только пока группа продаёт (DEC-04).

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| D4-T01…T12 | PENDING | — | реализация после согласования решений |
