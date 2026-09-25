# Issues #62 и #63 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-62-63-photo-group-switch`.
- Worktree: `/home/user/rabit-api-worktrees/issues-62-63-photo-group-switch`. Основной checkout `/home/user/rabit-api` занят другой сессией (`codex/design-ux-plan`), в нём не работать.
- Base: `origin/main` `49f40f9`.
- Issues: [#62](https://github.com/rebit-pro/rabit-api/issues/62), [#63](https://github.com/rebit-pro/rabit-api/issues/63). PR: [#67](https://github.com/rebit-pro/rabit-api/pull/67), на review.
- Параллельно в работе, в отдельных ветках и PR: #42 (`codex/issues-42-access-error-codes`), #39/#41 (`codex/issues-39-41-staff-orders`).
- Завершено:
  - исправление (S2, S3);
  - live E2E-сценарий (S4);
  - быстрые проверки и stub-проверка в Chromium (S5).
- Следующий шаг: review PR. После review без блокеров — полный `make test-e2e` (T03–T05, T07).
- Блокеров нет.
- Рабочее дерево: закоммичено. Пустые `frontend/.stub` и `frontend/test-results` — следы stub-прогона, в git не попадают.
- Команды проверок:
  - быстрые: `docker run --rm --network none -v <worktree>/frontend:/app -v rabit-issues6263-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'` (том `rabit-issues6263-node`: `npm ci` текущего lockfile);
  - полный gate: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`. Новый сценарий в группе `b` (`zzzzzz-transfers`).

## Хронология

### 2026-09-25 — разведка и исправление

- Пользователь взял в работу группы A (#62 + #63), B (#42) и D (#39 + #41), каждую отдельной веткой и PR.
- Worktree: `git worktree add -b codex/issues-62-63-photo-group-switch /home/user/rabit-api-worktrees/issues-62-63-photo-group-switch origin/main --no-track`.
- Причины подтверждены чтением кода (см. «Установленные факты» в плане). Решения R1–R4.
- Изменения:
  - `usePhotoWorkspace.ts`:
    - `pageGroupId` и `pageReady`;
    - `changeGroup()` очищает данные страницы;
    - `refreshPhotos()` применяет ответ и ошибку только для той же группы, обложка берётся по группе запроса;
    - `transfer()` получает исходную группу явно.
  - `PhotoWorkspace.vue`:
    - селектор заблокирован и при `moveLoading`;
    - `move` хранит `groupId`, а результат и ошибка подготовки для сменившейся группы отбрасываются;
    - в «Состоянии подборки» вместо чисел — «Загружаем кадры группы…» / «Кадры группы не загружены.».
  - `PhotoCollection.vue`: «Кадров пока нет» не показывается во время загрузки.
  - `e2e/live/zzzzzz-transfers.spec.ts`: сценарий «#62/#63…» с задержкой и обрывом запросов через `page.route`.
- Ошибка по ходу: `npx prettier --write` без конфигурации проекта переформатировал весь каталог photos. Изменения откатили `git checkout` и применили правки заново. Форматировать только через `npx eslint --fix <файлы>`.

### 2026-09-25 — проверки

- `npm run check` — exit 0: lint, stylelint, typecheck, typecheck e2e, `test:ui`.
- `npm run test:commerce` — 183/183.
- Stub-проверка в Chromium:
  - Vite + Playwright `page.route`, скрипт в scratchpad сессии;
  - шаги повторяют live-сценарий на подменённых ответах API, плюс проверка 390 px без горизонтальной прокрутки;
  - исправленный код — PASS;
  - код `main` (`src` из `origin/main`, смонтированный поверх) — FAIL на двух местах:
    - «Загружаем кадры группы…»: получено «Кадров: 2 · Детей: 1 · Без ребёнка: 0» от прежней группы (#62);
    - отдельный прогон только #63: селектор группы не заблокирован во время подготовки переноса.
- Скриншоты: `visual/before-main-g2-shows-g1.png` (main), `visual/after-failed-group-load.png`, `visual/after-move-dialog.png`, `visual/after-mobile-390.png`.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда / доказательство |
|---|---|---|---|
| T01 | PASS | 2026-09-25 | `npm run check` — exit 0 |
| T02 | PASS | 2026-09-25 | `npm run test:commerce` — 183/183 |
| T03 | PENDING | — | live E2E «#62/#63…», полный gate после review |
| T04 | PENDING | — | live E2E «#62/#63…», полный gate после review |
| T05 | PENDING | — | live E2E «#62/#63…», полный gate после review |
| T06 | PASS | 2026-09-25 | stub Chromium: fix — PASS, main — FAIL (#62 и #63 воспроизводятся) |
| T07 | PENDING | — | `make test-e2e` после review |
