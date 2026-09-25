# OPS-login-feedback — прогресс

## Точка продолжения

- Ветка `codex/ops-login-feedback` (worktree `.worktrees/ops-login-feedback`), base `caa37b6` (origin/main).
- PR: https://github.com/rebit-pro/rabit-api/pull/108 (head cdb59f6 + docs). Связано: K3 `morefoto.support` (PR #89).
- Завершено: backend (миграция, гостевой тип беседы, `POST /api/v1/public/feedback`), frontend (шапка, блок «Кабинет
  «Море фото»», диалог «Написать нам»), unit-тесты, live E2E-спека и DB-верификатор, быстрые проверки.
- Следующий шаг: ревью PR; если нет блокеров, запустить полный `make test-e2e` (T08–T11).
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
| T08 | PENDING | — | `make test-e2e` | после ревью |
| T09 | PENDING | — | `make test-e2e` (zz-questions + verify-support.php) | после ревью |
| T10 | PENDING | — | `make test-e2e` | после ревью; stub-прогон показал ошибки полей |
| T11 | PENDING | — | `make test-e2e` скриншоты `login-*` | stub-скриншоты desktop/mobile без горизонтального скролла |
| T12 | PASS | 2026-09-25 | `npm run check`, `npm run test:commerce` | pass 42 / 207 |
