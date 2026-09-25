# Issues #68 и #69 — прогресс

## Точка продолжения

- PR https://github.com/rebit-pro/rabit-api/pull/74 слит: merge `cfd5718452171e0ffc3a450ba5981409f6881986` (2026-09-25T10:46:56Z); issue #68 и #69 закрыты.
- Выкачен только frontend на https://app.morefoto36.ru: релиз `/srv/morefoto/releases/issues68-69-20260925104722-cfd5718`, образ `morefoto-frontend:issues68-69-20260925104722-cfd5718`. Backend остаётся на `e6-20260925101815-54bd4ab` (api не менялся).
- Откат: `docker service rollback morefoto_frontend` (прежний образ в `frontend-before.txt` — `morefoto-frontend:e6-20260925101815-54bd4ab`).
- Задача завершена; открыта только пользовательская проверка в кабинете.

## Хронология

### 2026-09-25 — старт

- Пользователь: пока ждём решения по PR #66, issue #68 и #69 должны пройти, с тестами. Ветка создана от головы #66, план записан до кода.
- #68: в `conditions-command.ts` ставка проверяется только при включённой политике; тело выключенной политики несёт введённую корректную ставку или `savedRateBps` из COM-04. Черновики без `savedRateBps` не восстанавливаются. Поле ставки при выключенной политике по-прежнему отключено.
- #69: E6-T04 в плане E6 — `maxRateBps` 1000; других упоминаний 9999 в плане и отчёте E6 нет.
- Live: сценарий UI E6 — ввести 10,5 %, выключить, сохранить → 200, `{enabled: false, rateBps: 380}`, сводка «Не учитываются», затем включение 3,8 % как раньше.

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| I-T01…I-T03 | PASS | 2026-09-25 | `npm run test:commerce` — 189/189, тест «#68: switching the policy off…» |
| I-T05 | PASS | 2026-09-25 | `grep -n 9999` по плану и отчёту E6 — пусто |
| I-T06 | PASS | 2026-09-25 | `npm run check` (lint, stylelint, vue-tsc, tsc e2e, UI 27), `npm run build-only` — в образе Playwright с томом `rabit-e6-node` |
| I-T04, I-T07 | PASS | 2026-09-25 | `make test-e2e …` на `2ca64a3` (E6 + исправления): 104 браузерных сценария (a 64, b 40), в том числе новый шаг #68 в `zzzzzzzz-payment-costs`; verifier storefront, orders, links, transfers, avatar, payment-costs, access — PASS; 297,2 с |

### 2026-09-25 — база обновлена, PR

- PR #66 слит (`54bd4ab`). `git merge origin/main` — без конфликтов, `d656d66`. Diff с main — 8 файлов: план и журнал задачи, план E6 (T04), `conditions-command.ts`, `useConditionsEditor.ts`, `management/types.ts`, unit-тест и шаг live-сценария E6.
- Быстрые проверки на обновлённой базе: `npm run check` — PASS (UI 27); `npm run test:commerce` — 196/196 (включая тесты #71); `npm run build-only` — PASS.
- PR https://github.com/rebit-pro/rabit-api/pull/74 открыт («Closes #68, #69»).
- Полный gate на `1a9d62f` (база — main после merge #66) (`rabit-e2e-3e085548d3fd`, 294,8 с) — PASS: 106 браузерных сценариев (a 64, b 42), все verifier, в том числе шаг #68 в `zzzzzzzz-payment-costs`.

### 2026-09-25 — merge и выкатка frontend

- Пользователь: «Сливай PR #74 и делай деплой фронта». Main отличался от базы PR только записью о выкатке E6 (`a6e9b0a`, журнал E6), проверки не повторялись. `gh pr merge 74 --merge --match-head-commit 2720bfe…` → `cfd5718`; #68 и #69 закрыты через «Closes».
- С выкаченного релиза E6 изменились только frontend и документы — выкатка frontend-only. Образ собран из `git archive cfd5718 frontend` с `VITE_API_MOCKS_ENABLED=false`, в чанке `ConditionsFields` есть `savedRateBps`. SHA256 `frontend-image.tar.gz` 01c51d5a…e5e9. Загрузка через ssh, `sha256sum --check` и `bash -n` — PASS.
- `switch-frontend.sh`: 2/2 на `morefoto-frontend:issues68-69-20260925104722-cfd5718`, прежний образ E6 — в `frontend-before.txt`.
- Smoke: `/health`, `/login`, `/cabinet/{overview,catalog,orders,links}`, `/access/recover` — 200; SHA-256 отдаваемого `index.html` равен файлу образа; отдаваемый `ConditionsFields-cDE3jIsg.js` содержит `savedRateBps`; `/api/v1/catalog/conditions` без токена — 401 JSON `UNAUTHORIZED`.
