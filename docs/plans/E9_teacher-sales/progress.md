# E9 — прогресс

## Точка продолжения

- Ветка `codex/e9-teacher-sales` (worktree `/home/user/rabit-api-worktrees/e9-teacher-sales`) от main e728d8c. PR нет.
- Завершено: S0–S4 — план, backend COM-14, PHPUnit, frontend-блок «Продажи», live E2E; быстрые проверки PASS.
- Сейчас: S5/S6 — полный гейт `make test-e2e`.
- Следующий шаг: по результату гейта — визуальная проверка скриншотов `e9-*-teacher-sales.png`, PR, саморевью.
- Блокеры: нет. Открытых решений нет (E9-DEC-01…05 в плане).
- Рабочее дерево: новые файлы в `morefoto.commerce` (Application/Domain/Infrastructure/Presentation Sales, di/sales.php, тесты), `frontend/src/modules/morefoto/overview/{GroupSales.vue,sales-api.ts,sales-rules.ts}`, спека `zzzzzzzzzzz-teacher-sales`.

## Журнал

### 16.10.2026 — старт и реализация

- Владелец взял E9 и принял рекомендации E9-DEC-01…03. При реализации добавлены уточнения DEC-04 (комплект «все фото» считается отдельно: в снимке нет числа кадров) и DEC-05 (поздняя оплата не входит, как в L1/J2).
- Граф: E9 → inProgress, baseline e728d8c; валидатор PASS (61 волна, 123 ID, 17 негативных фикстур).
- Backend: `GET /api/v1/groups/{group_id}/sales-summary`:
  - `GetGroupSalesUseCase` проверяет область через `SalesAccess` (Access `StaffRequestAccessInterface`) и группу через Organization `GroupDirectoryInterface`;
  - `GroupSalesRepository` делает два SQL по `ix_mf_order_group`.
- Frontend: блок «Продажи» в карточке группы «Обзора» воспитателя — сумма, четыре счётчика, комплекты, печать по форматам, пустое состояние и ошибка с повтором.
- E2E `zzzzzzzzzzz-teacher-sales` идёт в группе b после `tables`, до `print-archive` L1. Спека назначает `another-teacher` на печатную группу J4.

### Проверки

| Команда | Результат |
| --- | --- |
| `phpunit --testsuite unit --filter 'GroupSales\|OrderArchitecture\|CommerceBootstrap'` (php-cli d1-local, vendor `rabit-e7-vendor`) | PASS, 20 тестов, 178 проверок |
| `phpstan analyse public/local/modules/morefoto.commerce` | PASS после удаления лишних `array_values` (2 замечания `arrayValues.list`) |
| `php-cs-fixer --dry-run` по новым файлам | 1 файл переупорядочен (data provider), исправлено |
| `npm run check` (docker playwright v1.52.0, том `rabit-e7-node`) | PASS, test:ui 100/100 |
| `npm run test:commerce` | PASS, 248/248 |

## Тест-кейсы

| ID | Статус | Дата | Доказательство |
| --- | --- | --- | --- |
| E9-T01 | PASS | 16.10.2026 | `GroupSalesTest::testOnlyTheOwnScopeSeesTheGroup` (7 областей), `testAMissingGroupLooksLikeAForeignOne` |
| E9-T02 | PASS | 16.10.2026 | `GroupSalesTest::testTheSummaryKeeps…`, `testAGroupWithoutSales…` |
| E9-T03 | PASS | 16.10.2026 | `GroupSalesArchitectureTest` (4 теста) |
| E9-T04 | PASS | 16.10.2026 | `tests/ui/teacher-sales.test.mjs` 2/2 |
| E9-T05…T08 | PENDING | — | live E2E, гейт |
| E9-T09 | PENDING (быстрые PASS) | 16.10.2026 | гейт впереди |
