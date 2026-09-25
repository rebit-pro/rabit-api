# Issues #68 и #69 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-68-69-payment-cost-toggle`, worktree `/home/user/rabit-api-worktrees/issues-68-69-payment-cost-toggle`. Основной checkout `/home/user/rabit-api` занят другой сессией.
- Base: `origin/main` `54bd4ab` (merge PR #66, E6).
- Issues: [#68](https://github.com/rebit-pro/rabit-api/issues/68), [#69](https://github.com/rebit-pro/rabit-api/issues/69). PR — см. хронологию.
- Параллельно в работе: #72/#73 (`codex/issues-72-73-orders-scope-retry`), #26/#27/#28 (`codex/issues-26-27-28-staff-requests`).
- Завершено: исправление, unit и live-тест, #69, быстрые проверки.
- Следующий шаг: review PR. После review без блокеров — полный `make test-e2e` (T04, T05), затем merge.
- Блокеров нет.
- Команды:
  - быстрые: `docker run --rm --network none -v <worktree>/frontend:/app -v rabit-issues6263-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`;
  - gate: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`. Перед запуском убедиться, что нет чужих стендов: `docker ps --filter label=rabit.browser_e2e`.

## Хронология

### 2026-09-25 — разведка и исправление

- Пользователь взял в работу A (#68 + #69), C (#72 + #73), D (#26–#28); #51 не берём.
- Факты и решения R1–R3 — в плане. Ключевое: сервер проверяет `rateBps` и при выключенной политике, поэтому для выключенной политики с испорченным черновиком отправляется сохранённая ставка.
- Live-проверка #68 добавлена в конец UI-сценария E6 (390 px). `afterAll` спеки и `verify-payment-costs.php` ожидают выключенную политику 3,80 % — сценарий оставляет именно её.

### 2026-09-25 — проверки

- `npx eslint --fix` по изменённым файлам, затем `npm run check` — exit 0.
- `npm run test:commerce` — 197/197, в том числе два новых теста #68.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда / доказательство |
|---|---|---|---|
| T01 | PASS | 2026-09-25 | `npm run check` — exit 0 |
| T02 | PASS | 2026-09-25 | `npm run test:commerce` — 197/197 |
| T03 | PASS | 2026-09-25 | `npm run test:commerce` — 197/197 |
| T04 | PENDING | — | live E2E, полный gate после review |
| T05 | PENDING | — | `make test-e2e` после review |
