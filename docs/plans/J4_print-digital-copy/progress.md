# J4 — прогресс

## Точка продолжения

- Дата: 12.10.2026. Ветка `codex/j4-print-digital-copy` от `origin/main` a302774 (merge E8); рабочая копия `/home/user/rabit-api-worktrees/j4-print-digital-copy`. PR нет.
- Связанное: граф `docs/waves/graph.json`, план владельца `docs/plans/OPS-telemos-feedback-plan/` (R03), J1 `docs/waves/j1/README.md`.
- Завершено: инвентаризация, план с решениями J4-DEC-01…04.
- Сейчас: S2 — Commerce.
- Следующий шаг: S3 (frontend), S4 (оферта), S5 (фикстура оплаты и спек).
- Блокеры: нет. Внешнее: тестовый магазин ЮKassa (#174) — в E2E J4 не нужен (DEC-04).
- Рабочее дерево: `docs/plans/J4_print-digital-copy/{plan.md,progress.md}`.
- Проверки (план): PHPUnit/PHPStan — docker `rabit-api-php-cli:d1-local`, vendor volume `rabit-e7-vendor`; frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`; гейт — `make test-e2e` с тестовым магазином (по DEC-04).

## Журнал

### 12.10.2026 — старт

- E8 слита (PR #181, a302774). Из свободных F3/J4/L1/G2 взята J4: от неё зависят J2 и N3; G2 ждёт D09, L1 — сверки D06.
- Других веток и PR по J4 нет. Worktree: `git worktree add -b codex/j4-print-digital-copy /home/user/rabit-api-worktrees/j4-print-digital-copy origin/main`.
- Инвентаризация — раздел «Факты инвентаризации» плана. Найдено расхождение после E8: п. 27 оферты 2026-10-11 требует имя и телефон.

### 12.10.2026 — решения

- Пользователь выбрал: копия по всем оплаченным заказам (DEC-01), одна редакция оферты в J4 с исправлением п. 27 после E8 (DEC-02), подсказка без блокировки (DEC-03), фикстура оплаты в обход провайдера (DEC-04, вместо рекомендованного тестового магазина).
- Фикстура до браузера невозможна: каталог создают браузерные спеки. Поэтому оплата — во время браузерной группы: спек → `var/j4-pay-request.json` → раннер → `pay-fixture.php` (код G1, подставной провайдер `e2e-fixture`).

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| J4-T01…T10 | PENDING | — | реализация после согласования решений |
