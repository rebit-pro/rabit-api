# Issues #83 и #97 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-83-97-payment-followups`, worktree `/home/user/rabit-api-worktrees/issues-83-97-payment-followups`. Общий checkout занят другими сессиями.
- Base: `origin/main` `94502a1`.
- Issues: [#83](https://github.com/rebit-pro/rabit-api/issues/83), [#97](https://github.com/rebit-pro/rabit-api/issues/97), назначены на себя. PR — см. хронологию.
- Параллельно: #88 (`codex/ops-e2e-prune-race`, PR #99), #90 (`codex/issues-90-serialized-name`).
- Завершено: исправление, тесты, быстрые проверки, stub-доказательство #83.
- Следующий шаг: самостоятельное ревью, полный gate, merge.
- Блокеров нет. Открыто вне scope: причина расхождения `createdAt` в прогоне ревью PR #89.
- Команды:
  - быстрые: `docker run --rm --network none -v <worktree>/frontend:/app -v rabit-issues6263-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`;
  - gate: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Хронология

### 2026-09-25 — исправление и проверки

- Пользователь взял A (#83 + #97), B (#88), C (#90). #93/#94 (код в открытом PR #89), #98 (код в открытом PR #96) и #92 (ждёт PR #96) не взяты.
- #83: поколение опроса в `usePaymentReturn` (R1).
- #97: G1-T14 запоминает ID попыток из URL возврата, G1-T09 ищет по ID и проверяет убывание `createdAt` (R2).
- Live-тест #83, первая версия с уходом по «Вернуться к заказу», проходил и на `main`: кнопка перезагружает документ (маркер `window.stay` пропал). Тест переписан на переходы через History API (R3) и теперь проверяет сохранение маркера.
- Stub-проверка теста «#83…» (Vite `VITE_API_MOCKS_ENABLED=false`, live-конфиг, `--project b --grep "#83"`, фиктивный `var/e4-fixture.json` в scratchpad):
  - `src` из `origin/main` — FAIL: после ухода опрос продолжился (`Expected: 4, Received: 5`);
  - ветка — PASS (15.2 с).
- `npx eslint --fix` по изменённым файлам; `npm run check` — exit 0; `npm run test:commerce` — 207/207.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда / доказательство |
|---|---|---|---|
| T01 | PASS | 2026-09-25 | `npm run check` — exit 0 |
| T02 | PASS | 2026-09-25 | `npm run test:commerce` — 207/207 |
| T03 | PASS (stub) / PENDING (gate) | 2026-09-25 | stub: ветка PASS |
| T04 | PASS (stub) / PENDING (gate) | 2026-09-25 | stub: `main` FAIL 5≠4, ветка PASS |
| T05 | PASS | 2026-09-25 | см. хронологию |
| T06 | PENDING | — | полный gate с тестовым магазином |
| T07 | PENDING | — | `make test-e2e` |
