# Issues #62 и #63 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-62-63-photo-group-switch`.
- Worktree: `/home/user/rabit-api-worktrees/issues-62-63-photo-group-switch`. Основной checkout `/home/user/rabit-api` занят другой сессией (`codex/design-ux-plan`), в нём не работать.
- Base: `origin/main` `49f40f9`.
- Issues: [#62](https://github.com/rebit-pro/rabit-api/issues/62), [#63](https://github.com/rebit-pro/rabit-api/issues/63). PR: [#67](https://github.com/rebit-pro/rabit-api/pull/67): review без блокеров (пользователь, 2026-09-25), gate PASS, слит в `main`.
- Параллельно в работе, в отдельных ветках и PR: #42 (`codex/issues-42-access-error-codes`), #39/#41 (`codex/issues-39-41-staff-orders`).
- Завершено:
  - исправление (S2, S3);
  - live E2E-сценарий (S4);
  - быстрые проверки и stub-проверка в Chromium (S5).
- Следующий шаг: деплой — отдельно, после результатов E2E волны E6 (решение пользователя 2026-09-25).
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

### 2026-09-25 — review и полный gate

- Пользователь провёл review, блокирующих замечаний нет. Разрешил полный E2E и merge в `main`. Деплой — позже, после E2E волны E6.
- Перед стартом ждали окончания чужого прогона E6 (стенд `df93f04fc1f3`, worktree `e6-payment-cost-pricing`).
- Gate 1 (`rabit-e2e-5aa4544a344f`, 309.6 с) — FAIL:
  - всё, кроме группы `a`, PASS, в том числе группа `b` с новым сценарием #62/#63;
  - в группе `a` 63 passed, 1 failed: `zz-media.spec.ts:431` «#33: партия отправляется по два файла…», `peakOverlap(spans)` = 3 при пороге 2.
  - Причина не в ветке: тест меряет перекрытие POST через Playwright `request.timing()` с округлением начала до 1 мс, а очередь загрузки ветка не меняет. Соседний тест #54/#55 уже меряет через Resource Timing. Заводится отдельный issue.
- Gate 2 (`rabit-e2e-c687c1b848e1`, 322.0 с) — PASS, exit 0:
  - группа `a` 64/64, группа `b` 39/39;
  - «#62/#63…» ✓ 5.6 с;
  - `verify-storefront/orders/links/transfers/avatar/access` PASS.
  - Во время прогона параллельно работал чужой стенд `0c2d85d9b8d6`.
- Команда: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда / доказательство |
|---|---|---|---|
| T01 | PASS | 2026-09-25 | `npm run check` — exit 0 |
| T02 | PASS | 2026-09-25 | `npm run test:commerce` — 183/183 |
| T03 | PASS | 2026-09-25 | live E2E «#62/#63…» ✓ в обоих gate (`5aa4544a344f`, `c687c1b848e1`) |
| T04 | PASS | 2026-09-25 | live E2E «#62/#63…» ✓ в обоих gate (`5aa4544a344f`, `c687c1b848e1`) |
| T05 | PASS | 2026-09-25 | live E2E «#62/#63…» ✓ в обоих gate (`5aa4544a344f`, `c687c1b848e1`) |
| T06 | PASS | 2026-09-25 | stub Chromium: fix — PASS, main — FAIL (#62 и #63 воспроизводятся) |
| T07 | PASS | 2026-09-25 | `make test-e2e` gate 2 `c687c1b848e1`: exit 0, a 64/64, b 39/39. Gate 1 — flaky #33 вне ветки |
