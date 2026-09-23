# Issue #64 — журнал

## Точка продолжения

- Дата: 2026-09-23.
- Ветка: `codex/issues-64-photo-page-size`, base `origin/main` `3987390`, upstream снят.
- Worktree: `/home/user/rabit-api-worktrees/issues-64-photo-page-size`.
- Issue: [#64](https://github.com/rebit-pro/rabit-api/issues/64) — OPEN.
- PR: [#65](https://github.com/rebit-pro/rabit-api/pull/65), gate PASS на `e307c4b`.
- Сейчас: merge и выкатка frontend по поручению пользователя.
- Следующий шаг: merge #65, сборка образа из merge-коммита, `switch-frontend.sh`, smoke.
- Блокеров нет.

## Хронология

### 2026-09-23 — постановка

- Пользователь прислал скриншот: на большом экране последний ряд из 3 карточек при 5 колонках. Оформлен #64 с разбором.
- Пользователь: «Да, бери в работу и выкатывай».
- Worktree: `git worktree add -b codex/issues-64-photo-page-size /home/user/rabit-api-worktrees/issues-64-photo-page-size origin/main`, `git branch --unset-upstream`.

### 2026-09-23 — реализация и проверки

- `photoPageSize` 48 → 60 (`e307c4b`).
- Unit-тест: 130 кадров, страницы 2 и 3, фильтр «без ребёнка» на странице 2, отдельная проверка кратности 1–5 колонкам.
- Live-сценарий «#54/#55»: константы `perPage = 60` и `frames = perPage + 2`, загрузка партиями по 5 с остатком.
- `npm run check` exit 0; `npm run test:commerce` 183/183.
- Полный gate `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor` (`rabit-e2e-e65d9f876acb`, 285,6 с): **PASS**.
  - Группа `a`: 48; группа `b`: 31. unexpected, flaky и skipped — 0.
  - Группа `a` дольше прежнего (205 с против 175 с): сценарий загружает 62 кадра вместо 50.
- Скриншот стенда `q54-desktop-photos.png` (1440 px с боковым меню): 4 колонки, последний ряд полный, пагинация 1/2.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда / доказательство |
|---|---|---|---|
| T01 | PASS | 2026-09-23 | `npm run test:commerce` 183/183 |
| T02 | PASS | 2026-09-23 | `npm run check` exit 0 |
| T03 | PASS | 2026-09-23 | gate `rabit-e2e-e65d9f876acb`: 79/79, «Показано 60 из 62», `pageSize=60`, 390 px без прокрутки |
| T04 | PENDING | — | после выкатки |
