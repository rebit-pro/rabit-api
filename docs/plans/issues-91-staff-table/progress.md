# Issue #91 — журнал

## Точка продолжения

- Ветка `codex/issues-91-staff-table`, worktree `/home/user/rabit-api-worktrees/issues-staff-table`,
  base `origin/main` `4ca7e9c`, head — base (коммитов нет). PR ещё нет. Issues: #91, #92.
- Завершено: исследование, согласование архива и объёма, issues, план.
- Сейчас: backend архивирования.
- Следующий шаг: контракт `StaffIdentityGatewayInterface::archive()` и реализация в `rebit.auth`.
- Блокеров нет. Открытых решений нет.
- Рабочее дерево: новые `docs/plans/issues-91-staff-table/{plan,progress}.md`.
- Следующая проверка: PHPUnit `morefoto.access` и `rebit.auth` (команда фиксируется после первого прогона).

## Журнал

### 2026-09-25

- Замечание пользователя: таблицы кабинета без сортировки/мультивыбора/удаления; минимум — нельзя удалить сотрудников.
- Найдено: `StaffManagementScreen.vue` на самописных строках; в API нет `DELETE /api/v1/users/{id}`; список
  всегда `ORDER BY p.UF_USER_ID`; `UiDataTable` уже умеет сортировку, выбор и удаление.
- Решения пользователя: удаление = архив; объём — сотрудники сейчас, остальные таблицы отдельным issue (#92).
- Созданы issues #91 и #92, worktree и ветка, план.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01–T12 | PENDING | — | — | — |
