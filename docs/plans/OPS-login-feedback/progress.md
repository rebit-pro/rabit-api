# OPS-login-feedback — прогресс

## Точка продолжения

- Ветка `codex/ops-login-feedback` (worktree `.worktrees/ops-login-feedback`), base `caa37b6` (origin/main).
- PR: https://github.com/rebit-pro/rabit-api/pull/108 (head cdb59f6 + docs). Связано: K3 `morefoto.support` (PR #89).
- Завершено: backend, frontend, тесты, ревью без блокеров (неблокирующее — #111), полный gate PASS.
- Следующий шаг: выкат на stage (backend + миграция `Version20260925210001` + DI-smoke, затем frontend) —
  после того как пользователь разрешит SSH на `rebit-pro`.
- Блокеры: нет. Открыто: после деплоя применить миграцию `Version20260925210001` на stage/prod.
- Рабочее дерево: всё закоммичено.
- Следующая проверка:
  `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`

## Журнал

### 2026-09-25

- Пользователь: форма обратной связи на `/login` пишет в ту же группу кураторов MAX, без Telegram.
- Выбрано: гостевой тип беседы K3 поверх существующего outbox (см. plan.md «Решения»).
- Backend: `AuthorEnum::GUEST`, `QuestionTextPolicy::contact()`, `createGuest`/`countGuestQuestions`,
  `SendGuestFeedbackUseCase` (лимит 30 обращений в час на сайт), `GuestFeedbackController` → `202 {number}`,
  текст MAX «Обращение №N · гость» с контактом; ответ через «Ответить» на него не сохраняется.
- Frontend: `AccessShell` получил необязательные слоты `header`/`aside`; остальные страницы доступа не изменились.
- Проверки (все в docker, worktree смонтирован в контейнер):
  - `php vendor/bin/phpunit public/local/modules/morefoto.support/tests/Unit` → OK (36 tests, 428 assertions).
  - `php-cs-fixer fix --dry-run` по изменённым PHP → чисто после автоисправления одного теста.
  - `phpstan analyse` модуля и миграции → 2 ошибки окружения (`class.notFound` Sprint `Version`, `require` `/runtime`);
    та же ошибка у слитой `Version20260925150001`, модуль без ошибок.
  - `npm run check` (lint, stylelint, typecheck, typecheck:e2e, test:ui) → pass 42, fail 0.
  - `npm run test:commerce` → pass 207, fail 0.
  - Stub-браузер (Vite + Playwright `page.route`), `/login` 1440×900 и 390×844: горизонтальный скролл 0,
    ошибки полей видны, отправка → «Обращение №17 отправлено». Скриншоты в scratchpad сессии, не в репозитории.

### 2026-09-25 — ревью и полный gate

- Пользователь подтвердил лимит 30 обращений в час и попросил довести задачу.
- В ветку слит `origin/main` (`26fe05a`) без конфликтов.
- Самостоятельное ревью: блокеров нет. Исправлено: у поля «Телефон или email» убран `inputmode="email"`.
  Неблокирующее — issue #111 (лимит по IP, гонка подсчёта, индекс).
- Полный `make test-e2e` (прогон `rabit-e2e-544ea69a98dd`) — FAIL: группа a 72/73, гостевое обращение получило 503.
  Причина: стенд применяет миграции явным списком в `api/tools/e2e/prepare.php`, `20260925210001` туда не был добавлен,
  CHECK отклонил `AUTHOR='guest'`. Исправлено добавлением в список. Группа b и её верификаторы — PASS.
- Повторный полный `make test-e2e` (прогон `rabit-e2e-f21b110864a4`) — **full gate PASS** за 331 с: браузер a 73/73,
  b 46/46; все верификаторы, включая `verify-support.php` (гостевое обращение, повтор/409/422, текст MAX, ответ
  в MAX не сохраняется, CHECK) и `verify-payments.php`. Скриншоты `login-{desktop,mobile}.png`,
  `login-feedback-{desktop,mobile}.png` в `api/var/e2e/rabit-e2e-f21b110864a4/a/a/artifacts/` — вёрстка корректна.

### 2026-09-26 — merge

- Пользователь: «можно мержить и выкатывать».
- В ветку влит main `57c00c1` (PR #102, платежи). Полный gate на `b9bd1d5` (`rabit-e2e-3931662471ec`) — PASS за 356 с:
  браузер a 73/73, b 47/47, все верификаторы.
- Затем в main пришли только docs и статика `/guide/` (PR #95), код задачи не затронут — повтор не требуется.
- Выкат: SSH на `rebit-pro` отклоняется автоматическим режимом («Production Reads»); нужен разрешающий
  `Bash(ssh rebit-pro:*)` или ручное подтверждение команд. Обход не выполнялся.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|----|--------|------|---------|----------------|
| T01 | PASS | 2026-09-25 | phpunit `GuestFeedbackTest` | `testGuestFeedbackBecomesPendingReplyWithContact` |
| T02 | PASS | 2026-09-25 | phpunit | `testRepeatReturnsTheSameNumberAndAnotherBodyConflicts` |
| T03 | PASS | 2026-09-25 | phpunit | тот же тест, 409 `IDEMPOTENCY_CONFLICT` |
| T04 | PASS | 2026-09-25 | phpunit | `testGuestFeedbackIsLimitedPerHourForTheWholeSite` |
| T05 | PASS | 2026-09-25 | phpunit | `testContactMustLeadBackToTheGuest` |
| T06 | PASS | 2026-09-25 | phpunit | `testCuratorsSeeTheContactAndRepliesInMaxAreNotStored` |
| T07 | PASS | 2026-09-25 | phpunit `SupportArchitectureTest` | 4 контроллера, 17 UseCase/Service с phpDoc |
| T08 | PASS | 2026-09-25 | `make test-e2e` (`rabit-e2e-f21b110864a4`) | a 73/73, b 46/46, `verify-support.php` PASS |
| T09 | PASS | 2026-09-25 | `make test-e2e` (`rabit-e2e-f21b110864a4`) | a 73/73, b 46/46, `verify-support.php` PASS |
| T10 | PASS | 2026-09-25 | `make test-e2e` (`rabit-e2e-f21b110864a4`) | a 73/73, b 46/46, `verify-support.php` PASS |
| T11 | PASS | 2026-09-25 | `make test-e2e` (`rabit-e2e-f21b110864a4`) | a 73/73, b 46/46, `verify-support.php` PASS |
| T12 | PASS | 2026-09-25 | `npm run check`, `npm run test:commerce` | pass 42 / 207 |
