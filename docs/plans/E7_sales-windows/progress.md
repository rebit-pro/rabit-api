# E7 — прогресс

## Точка продолжения

- Дата: 11.10.2026. Ветка `codex/e7-sales-windows` от `origin/main` fd7498a; рабочая копия `/home/user/rabit-api-worktrees/e7-sales-windows`. PR нет.
- Связанное: план ускоренного MVP — ветка `codex/ops-mvp-payment-print-email-plan`, локальная, не опубликована, в общем checkout ведётся другой сессией. Граф E7 пока только там.
- Завершено: инвентаризация backend/frontend, `plan.md` с решениями E7-DEC-01…11.
- Сейчас: согласование решений с пользователем.
- Следующий шаг: после ответа обновить решения в плане и начать S2 (Organization).
- Блокеры: merge PR E7 требует слитого в `main` графа MVP. Реализацию это не блокирует.
- Рабочее дерево: новые файлы `docs/plans/E7_sales-windows/{plan,progress}.md`, не закоммичены.
- Проверки следующего шага: `make test` (PHPUnit) в worktree; frontend — docker-команда из памяти (`npm run check && npm run test:commerce`).

## Журнал

### 11.10.2026 — старт

- В графе `main` (fd7498a) волны E7 нет. Она определена в незамерженной ветке `codex/ops-mvp-payment-print-email-plan`. План MVP разрешает начинать E7 от актуального `main`: зависимости E3/E4/E5/F2/G1/J1 слиты.
- Других веток и PR по E7 нет (`git branch -a`, `gh pr list`).
- Worktree создан: `git worktree add -b codex/e7-sales-windows /home/user/rabit-api-worktrees/e7-sales-windows origin/main`.
- Инвентаризация (два read-only агента и ручная сверка):
  - единственный решающий срок — `UF_CLOSES_AT`;
  - `latePayment` считается от него же и закрывает файлы J1;
  - открытая попытка оплаты возвращается до проверки срока;
  - у `extend()` нет HTTP-маршрута;
  - подвал — `LegalFooter.vue`;
  - формулировки оферты завязаны на единый «приём заказов».
  Подробности — в плане, раздел «Факты инвентаризации».

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| E7-T01…T17 | PENDING | 11.10.2026 | Реализация не начата |
