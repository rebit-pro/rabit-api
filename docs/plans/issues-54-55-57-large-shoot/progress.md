# Issues #54, #55 и #57 — журнал

## Точка продолжения

- Дата: 2026-09-23.
- Ветка: `codex/issues-54-55-57-large-shoot`, upstream не задан (push ещё не выполнялся).
- Worktree: `/home/user/rabit-api-worktrees/issues-54-55-57-large-shoot`. Основной checkout `/home/user/rabit-api` занят другой сессией (`codex/design-ux-plan`), в нём не работать.
- Base: `origin/main` `5f658e5`.
- Issues: [#54](https://github.com/rebit-pro/rabit-api/issues/54), [#55](https://github.com/rebit-pro/rabit-api/issues/55), [#57](https://github.com/rebit-pro/rabit-api/issues/57) — OPEN.
- PR: ещё нет.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md), предыдущий журнал [#47](../issues-31-33-34-photo-upload/progress.md).
- Завершено: разведка, решения D1/D2, план.
- Сейчас: реализация #57.
- Следующий шаг: санитайзер и тесты #57, затем backend #54.
- Блокеров нет. E2E не запускать до команды пользователя: он отдельно оптимизирует E2E (2026-09-23).
- Рабочее дерево: новые `docs/plans/issues-54-55-57-large-shoot/{plan,progress}.md`, не закоммичены.

## Хронология

### 2026-09-23 — разведка и решения

- Пользователь взял в работу #54, #55, #57 и отдельно готовит оптимизацию E2E. E2E запускать только по его команде.
- Worktree: `git worktree add -b codex/issues-54-55-57-large-shoot /home/user/rabit-api-worktrees/issues-54-55-57-large-shoot origin/main`, затем `git branch --unset-upstream`.
- Разведка:
  - MED-02 уже фильтрует `groupId`, `childCode`, `assigned` на сервере, но отдаёт кадры всех статусов, а сводки по группе нет;
  - `MediaController` загрязнён (`MediaRequestFactory` над `HttpRequest`);
  - `PhotoImage` запускает XHR сразу при монтировании, общий таймаут 15 с;
  - санитайзер вырезает все 6 сообщений media и, дополнительно, 3 сообщения `rebit.notification`.
- Решения пользователя (AskUserQuestion):
  - D1 — «Чистый контроллер»: список уходит в новый чистый контроллер, MED-02 получает `status` и `summary`;
  - D2 — «Да, в этой ветке»: записи notification чинятся здесь же.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда / доказательство |
|---|---|---|---|
| T01–T11 | PENDING | — | реализация не начата |
| T12–T15, T17 | PENDING | — | E2E по команде пользователя |
| T16 | PENDING | — | после деплоя |
