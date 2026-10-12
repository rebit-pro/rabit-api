# OPS-k3-question-spec-race — прогресс

## Точка продолжения

- Дата: 12.10.2026. Ветка `codex/ops-k3-question-spec-race` от `origin/main` 480427c; рабочая копия `/home/user/rabit-api-worktrees/ops-k3-question-spec-race`. Issue #179.
- Сейчас: тест исправлен, проверки зелёные, открывается PR в `main`.
- Следующий шаг: ревью и merge PR; затем влить `main` в D4 (PR #175) и повторить её полный гейт.
- Блокеры: нет.

## Журнал

### 12.10.2026 — причина

- В гейтах D4 `rabit-e2e-25902ea7fb8a` и `rabit-e2e-9f0b20f6ca72` тест «a new curator answer is marked…» упал дважды подряд; в `rabit-e2e-f048a4c2af27` проходил. По trace: 404 настоящего сервера на `questions/current` до `page.route`, ключ стёрт, после `reload` запросов нет. Подробности — в плане и в комментарии #179.

### 12.10.2026 — исправление и проверки

- В тесте `page.route('**/api/v1/public/questions/current')` перенесён до `page.goto` и записи ключа.
- `npx eslint e2e/live/zz-questions.spec.ts` и `npm run typecheck:e2e` (docker playwright:v1.52.0-jammy, volume `rabit-e7-node`) — без ошибок.
- `E2E_GROUPS=a E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor make test-e2e` — прогон `rabit-e2e-e00623a0c485`, 366.5 s: статические шаги, PHPStan, PHPUnit, группа a 81 passed (тест метки — 1.3 s вместо таймаута 11 s), `verify-access`, `verify-support`, `verify-photo-deletion` — passed. Частичный прогон, не полный гейт: группа b этим изменением не затронута.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| K3R-T01 | PASS | 12.10.2026 | ESLint и `typecheck:e2e` без ошибок |
| K3R-T02 | PASS | 12.10.2026 | `rabit-e2e-e00623a0c485`: группа a 81 passed, `zz-questions.spec.ts:125` — 1.3 s |
