# Issues #68 и #69 — прогресс

## Точка продолжения

- Ветка `codex/issues-68-69-payment-costs` (локальная, upstream снят) от головы PR #66 `01f024b`. Checkout: `/home/user/rabit-api-worktrees/issues-68-69-payment-costs`.
- Завершено: I1–I7. PR #66 слит (`54bd4ab`), main влит в ветку (`d656d66`), быстрые проверки повторены, PR открыт.
- Полный gate на обновлённой базе — PASS. PR https://github.com/rebit-pro/rabit-api/pull/74.
- Следующий шаг: ревью и merge по решению пользователя; затем выкатка только frontend (backend не менялся).
- Блокеров нет.

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
