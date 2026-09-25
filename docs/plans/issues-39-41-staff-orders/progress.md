# Issues #39 и #41 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-39-41-staff-orders`. Worktree: `/home/user/rabit-api-worktrees/issues-39-41-staff-orders`.
  Общий checkout `/home/user/rabit-api` не трогается: там чужая ветка с незакоммиченными правками.
- Base: `origin/main` `49f40f97fb5ee8b16425fa3a6b2e2b3da0c2b771`. Код: `3396b05` (#41), `1f7d1da` (#39).
- Issues: [#39](https://github.com/rebit-pro/rabit-api/issues/39), [#41](https://github.com/rebit-pro/rabit-api/issues/41). PR: [#71](https://github.com/rebit-pro/rabit-api/pull/71): review без блокеров (пользователь, 2026-09-25), gate PASS, сливается в `main`.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md).
- Завершено:
  - реализация #41 и #39, unit-тесты правил;
  - live E2E-сценарии в `zzzzz-orders.spec.ts`: написаны, не запускались;
  - быстрые проверки и стаб-прогон в Chromium зелёные.
- Сейчас: gate PASS, PR #71 сливается. Base обновлён до `6b9449d` (после merge PR #67 и #70) merge-коммитом `064567f`.
- Следующий шаг: деплой — отдельно, после результатов E2E волны E6 (решение пользователя 2026-09-25).
- Блокеров нет. Открытых решений нет.
- Рабочее дерево: чистое после коммита журнала. Скриншоты стаб-прогона вне репозитория (scratchpad сессии).
- Следующая проверка после изменения base: команда из раздела «Команды» плана (том `rabit-issues6263-node`).

## Хронология

### 2026-09-25 — анализ

- `git fetch origin main`: `49f40f9`, ветка создана от него.
- COM-12: `OrderInputMapper::search()` принимает `institutionId`/`shootId`/`groupId` (UUID, иначе `INVALID_FILTER`).
- Organization API по ролям:
  - `GET /api/v1/institutions` — область сотрудника, для куратора только назначенные учреждения;
  - `GET /api/v1/institutions/{id}` — organizer/curator/head, съёмки и группы в области, `pageSize` ≤ 100;
  - `GET /api/v1/shoots/{id}` — только organizer, поэтому для каскада не используется (решение D2).
- Для каскада API хватает, блокера нет.
- #41: причина подтверждена чтением `useLiveCheckout.ts`. `draft.pending` заполняется до запроса и в первой
  отправке, поэтому нужен вид отправки (решение D1).

### 2026-09-25 — реализация

- #41, `3396b05`:
  - `rules.ts`: `CheckoutSubmission` и `showsCheckoutRecovery`;
  - `useLiveCheckout.ts`: `submission: 'idle' | 'first' | 'recovery'` вместо `submitting`;
  - live E2E: `recover()` принимает `whilePending`, сценарий «потерянный ответ» держит ответ на повтор и проверяет экран.
- #39, `1f7d1da`:
  - `types.ts`, `rules.ts`: новые поля, `staffFilterKeys`, `staffFilterQuery`, `hasStaffFilters`, `staffScopePatch`,
    `staffScopeOptions`;
  - новый `curator/useStaffOrderScope.ts`: загрузка вариантов из `structureApi` с обходом страниц по 100;
  - `useStaffOrders.ts`: `apply`/`reset` через правила, `selectScope`;
  - `StaffOrdersLiveScreen.vue`: строка «Учреждение/Съёмка/Группа» (1 колонка до 768px, 3 от 768px);
  - live E2E: новый тест «E5: staff narrow orders by institution, shoot and group» в существующем
    `zzzzz-orders.spec.ts`. Файл уже в группе `b` `groups.json`, поэтому `groups.json` не меняется.
- Инцидент: первый прогон `npx prettier --write` без конфигурации проекта переформатировал файлы (двойные кавычки,
  раскрытые объекты). Файлы восстановлены из `HEAD` с повторным применением правок, затем `eslint --fix`.
  Итоговый diff без посторонних изменений.
- Том `rabit-e5-node` устарел: нет `stylelint`, `npm run check` → `sh: 1: stylelint: not found`. Использован
  `rabit-issues6263-node`: `npm ls --depth=0` без missing/invalid.

### 2026-09-25 — проверки

- Быстрые проверки ветки (HEAD `1f7d1da`):

  ```bash
  docker run --rm --network none -v /home/user/rabit-api-worktrees/issues-39-41-staff-orders/frontend:/app \
    -v rabit-issues6263-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy \
    bash -c 'npm run check && npm run test:commerce'
  ```

  `npm run check` exit 0 (lint, stylelint, vue-tsc, tsc e2e, `test:ui` 27/27). `npm run test:commerce` 190/190.
- Промежуточный коммит #41 (`3396b05`) проверен отдельно на выгрузке индекса (`git checkout-index`): `check` exit 0,
  `test:commerce` 184/184.
- Стаб-прогон в Chromium: образ `mcr.microsoft.com/playwright:v1.52.0-jammy`, `--network none`, Vite
  `VITE_API_URL=/api VITE_API_MOCKS_ENABLED=false`, API через `page.route` с предикатом `pathname.startsWith('/api/')`,
  auth-сид в localStorage. Скрипты вне репозитория (`orders-scope.mjs`, `checkout-recovery.mjs`).
  - Заказы, organizer, 1280 и 390 px: T08, T09, T10, T11 PASS, горизонтальной прокрутки нет, `pageerror` нет.
  - Заказы, curator с одним учреждением: учреждение другой области не предлагается; группы доступны без выбора
    учреждения, в запросе только `groupId`.
  - Оформление, ветка: во время повтора `{"recovery":true,"loading":true,"form":0,"emptyCart":0}`; после
    `PURCHASE_DISABLED` экран восстановления с причиной; первая отправка `{"recovery":0,"loading":true}`.
  - Оформление, копия с `useLiveCheckout.ts` из `main`: во время повтора экран восстановления скрыт
    (`recovery:false`, кнопка без загрузки) — дефект #41 воспроизведён. Дальнейшее поведение этой копии на стабе
    (запрос завершается без ответа стаба) не исследовалось: к ветке не относится.
- Полный `make test-e2e` не запускался: gate после review без блокеров.

### 2026-09-25 — PR

- `git push -u origin codex/issues-39-41-staff-orders`, `gh pr create --base main`:
  [#71](https://github.com/rebit-pro/rabit-api/pull/71). Merge не выполнялся.

### 2026-09-25 — review и полный gate

- Пользователь провёл review, блокирующих замечаний нет. Разрешил полный E2E и merge. Деплой — позже, после E2E волны E6.
- Base обновлён: `git merge origin/main` (`6b9449d`) → `064567f`, конфликтов нет.
- Gate 1 (`rabit-e2e-5866714e411d`) — FAIL:
  - группа `a` 64/64;
  - группа `b` 39 passed, 1 failed: «E5: staff narrow orders by institution, shoot and group», таймаут 45 с.
  - Причина — ошибка в тесте. У `c4-curator` нет `order.read` в интерфейсе: экран заказов показывает «Недостаточно прав», запрос `GET /api/v1/institutions` не уходит, `waitForResponse` ждёт до таймаута. Остальные шаги прошли, в том числе куратор `curator`.
- Исправление теста `981e3ef`:
  - случай `c4-curator` убран;
  - для `curator` число вариантов «Учреждение» = 1 + число учреждений из ответа `GET /api/v1/institutions` его сессии, E4 среди них.
  - `eslint`, `typecheck:e2e` — PASS.
- Gate 2 (`rabit-e2e-27b71e0e73d4`, 261.8 с) — PASS, exit 0:
  - группа `a` 64/64, группа `b` 40/40;
  - «staff narrow orders…» ✓ 6.6 с, «lost response…» ✓ 2.8 с;
  - desktop/mobile E5 ✓;
  - `verify-storefront/orders/links/transfers/avatar/access` PASS.
- Команда: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-25 | `npm run test:commerce` | тест «an unconfirmed attempt keeps its recovery screen…», 190/190 |
| T02 | PASS (стаб + live gate `27b71e0e73d4`) | 2026-09-25 | `checkout-recovery.mjs`; live E2E после review | экран восстановления и загрузка во время повтора, формы и пустой корзины нет |
| T03 | PASS (стаб + live gate `27b71e0e73d4`) | 2026-09-25 | `checkout-recovery.mjs` | первая отправка остаётся на форме, «Создать тестовый заказ» в загрузке |
| T04 | PASS (стаб + live gate `27b71e0e73d4`) | 2026-09-25 | `checkout-recovery.mjs`; live «refusals while recovering» | причина `PURCHASE_DISABLED` на экране восстановления |
| T05 | PASS | 2026-09-25 | `npm run test:commerce` | тесты URL/API mapper, ручной `groupId`, пустые значения |
| T06 | PASS | 2026-09-25 | `npm run test:commerce` | тест «picking a parent level drops the levels below it» |
| T07 | PASS | 2026-09-25 | `npm run test:commerce` | тесты вариантов списков и «Выбрано по ссылке» |
| T08 | PASS (стаб + live gate `27b71e0e73d4`) | 2026-09-25 | `orders-scope.mjs`; live E2E после review | `groupId` в запросе, итог 1 из 1 |
| T09 | PASS (стаб + live gate `27b71e0e73d4`) | 2026-09-25 | то же | три уровня в URL и запросе |
| T10 | PASS (стаб + live gate `27b71e0e73d4`) | 2026-09-25 | то же | значения списков после карточки |
| T11 | PASS (стаб + live gate `27b71e0e73d4`) | 2026-09-25 | то же | сброс зависимых, общий сброс, «Сбросить» неактивна |
| T12 | PASS (стаб + live gate `27b71e0e73d4`) | 2026-09-25 | то же | чужое учреждение не предлагается |
| T13 | PASS (стаб + live gate `27b71e0e73d4`) | 2026-09-25 | то же, 1280 и 390 px | `scrollWidth <= innerWidth`, скриншоты |
| T14 | PASS | 2026-09-25 | `npm run check` | exit 0 |
| T15 | PASS | 2026-09-25 | `npm run test:commerce` | 190/190 |
| T16 | PASS | 2026-09-25 | `git diff --stat origin/main...HEAD` | только `frontend/` и план; backend и demo-экраны не затронуты |
| T17 | PASS | 2026-09-25 | `make test-e2e` `rabit-e2e-27b71e0e73d4` | exit 0, a 64/64, b 40/40; gate 1 падал на ошибке теста (`c4-curator`), исправлено `981e3ef` |
