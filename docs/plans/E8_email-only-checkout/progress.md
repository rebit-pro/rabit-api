# E8 — прогресс

## Точка продолжения

- Дата: 12.10.2026. Ветка `codex/e8-email-only-checkout` от `origin/main` 28a8c55 (merge D4); рабочая копия `/home/user/rabit-api-worktrees/e8-email-only-checkout`. PR нет.
- Связанное: граф `docs/waves/graph.json`, план владельца `docs/plans/OPS-telemos-feedback-plan/` (R02).
- Завершено: инвентаризация (два read-only агента), план с решениями E8-DEC-01…05.
- Сейчас: решения на согласовании у пользователя.
- Следующий шаг: после ответа — S2 (Commerce).
- Блокеры: нет. Внешнее: тестовый магазин ЮKassa (#174).
- Рабочее дерево: `docs/plans/E8_email-only-checkout/{plan.md,progress.md}`.
- Проверки (план): PHPUnit/PHPStan — docker `rabit-api-php-cli:d1-local`, vendor volume `rabit-e7-vendor`; frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`; гейт — `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env … make test-e2e`.

## Журнал

### 12.10.2026 — старт

- D4 слита (PR #175, 28a8c55). Из свободных E8/F3/J4 взята E8: от неё зависят J2 и G2 (критический путь к запуску).
- Других веток и PR по E8 нет. Worktree: `git worktree add -b codex/e8-email-only-checkout /home/user/rabit-api-worktrees/e8-email-only-checkout origin/main`.
- Инвентаризация: факты в плане, раздел «Факты инвентаризации».

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| E8-T01…T09 | PENDING | — | реализация после согласования решений |
