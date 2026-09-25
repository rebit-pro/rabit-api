# Issue #103 — журнал

## Точка продолжения

- Ветка `codex/issues-103-table-toolbar`, worktree `/home/user/rabit-api-worktrees/issues-103-table-toolbar`,
  base `origin/main` `03f4e3b`. PR — нет.
- PR #104 влит как `e69ee57`, issue #103 закрыт. Выкачено на https://app.morefoto36.ru 2026-09-25 (только frontend):
  `morefoto-frontend:issues103-20260925200321-e69ee57`, релиз `/srv/morefoto/releases/issues103-20260925200321-e69ee57`.
  Backend не менялся (K3 `caa37b6`). Откат: `docker service rollback morefoto_frontend` (прежний образ
  `morefoto-frontend:k3-20260925204828-caa37b6` в `frontend-before.txt`).
- Открыто: пользовательская проверка на своём кабинете.
- Блокеров нет.

## Журнал

### 2026-09-25

- Замечание пользователя со скриншотами: плашка выбора сдвигает таблицу; шапка бледная, стрелки текстовые.
- Создан issue #103, план.
- Панель в `UiDataTable` фиксированной высоты, слот `selection`; шапка (фон `surface-2`, 14px semibold, граница 2px,
  иконки сортировки); склонение «N записей». Экран сотрудников и пример UI-кита на слоте.
- Замер на заглушках (Vite + Playwright), позиция таблицы до / при выборе / после снятия:
  - первый вариант: desktop 734/734/734, mobile 1477/1533/1477 — на mobile «Снять выбор» переносилась;
  - короткие кнопки на mobile: mobile 1477/1481/1477 — тач-кнопки 44px поднимали панель 52→56px;
  - `min-height: 56px`: desktop 738/738/738, mobile 1481/1481/1481 — сдвига нет.
- Скриншоты desktop (шапка, выбор, диалог) и mobile (панель в одну строку) просмотрены.
- WSL перезапускался: scratchpad `/tmp` очищен, скрипт заглушек восстановлен.
- `npm run check` (lint, stylelint, typecheck, typecheck:e2e, test:ui 27/27) — PASS. В live E2E #91 добавлена
  проверка, что таблица не сдвигается при выборе.
- PR #104, самопроверка без замечаний. Gate на `164685b` (`rabit-e2e-…`, база 03f4e3b) — PASS, 111 сценариев.
- `main` ушёл на `caa37b6` (K3, в т.ч. `icons.ts`): merge `af82939`, конфликт реестра иконок — оставлены обе
  (`mdi-send-outline`, `mdi-swap-vertical`). `npm run check` 38/38. Повторный gate на `af82939`
  (`rabit-e2e-953158a9bdbc`, ~283 с) — PASS: 117 сценариев (a 71, b 46), все verifier, включая `verify-support`.

### 2026-09-25 — merge и выкатка

- Пользователь: «если тесты ок — мерж и деплой». Merge `gh pr merge 104 --merge --match-head-commit` → `e69ee57`.
- Разница с выкаченным K3 `caa37b6` — только `frontend/` и `docs/`, поэтому выкатка только frontend.
- Образ из `git archive e69ee57 frontend`, `VITE_API_MOCKS_ENABLED=false`; в чанке `UiDataTable-CJ5oYBWd.js` —
  `ui-table-toolbar`. `switch-frontend.sh`: 2/2 на новом образе.
- Smoke: `/health`, `/login`, `/cabinet/{users,institutions,overview}` — 200; SHA-256 отдаваемого `index.html` равен
  файлу образа; отдаваемый `UiDataTable-CJ5oYBWd.js` содержит новую панель.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-25 | стаб-замер; `make test-e2e` | 738/738/738, 1481/1481/1481; live-проверка в сценарии #91 |
| T02 | PASS | 2026-09-25 | `make test-e2e` | сценарий #91 |
| T03 | PASS | 2026-09-25 | `make test-e2e` | 117 сценариев |
| T04 | PASS | 2026-09-25 | `npm run check` | 27/27, lint/typecheck OK |
