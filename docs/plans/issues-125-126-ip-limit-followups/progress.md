# Issues #125 и #126 — журнал

## Точка продолжения

- Дата: 2026-09-26.
- Ветка `codex/issues-125-126-ip-limit-followups`, worktree `/home/user/rabit-api-worktrees/issues-125-126-ip-limit-followups`.
  Общий checkout `/home/user/rabit-api` не трогать.
- Base: `origin/main` `4fc9dce` (merge PR #121). Issues #125, #126. PR: https://github.com/rebit-pro/rabit-api/pull/132 (не слит, не выкачен).
- Завершено: реализация (S2–S6), быстрые проверки (S7), unit-тесты гонки подтверждены красными на старом коде.
- Сейчас: независимое ревью без блокеров (неблокирующее → #136), полный gate PASS на ветке с main `1be46fe` (T14, T15). PR сливается.
- Следующий шаг: деплой только backend (`rebit.share`, `morefoto.support`), smoke гостевой формы на `/login`.
- Блокеры: нет. Открыто:
  - R4: одновременные запросы с одним ключом с разных адресов — второй получает `503 SUPPORT_UNAVAILABLE` без дубля
    (как до PR #121). Закрытие требует резерва ключа и миграции `QUESTION_ID NULL`, на решение пользователя.
  - Попутно: PHP также считает зарезервированными `64:ff9b::/96` (NAT64), `::a.b.c.d`, `::ffff:0:a.b.c.d` — вне scope.
- Рабочее дерево: после commit чисто (игнорируемые `api/var/`, пустой root-owned `api/vendor/` от docker-монтирования).
- Команды следующей проверки:
  - быстрые — см. журнал «проверки»;
  - gate: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Журнал

### 2026-09-26 — разведка

- Прочитаны `CLAUDE.md`, `AGENTS.md`, issues #125/#126, план и журнал #111, код resolver, хешера, UseCase, репозитория,
  транзакции, тестовые double, `verify-support.php`, решение #27 (handoff, резерв ключа).
- Проба PHP 8.4.25 в `rabit-api-php-cli:d1-local`: `::ffff:8.8.8.8` валиден и не публичен по
  `NO_PRIV|NO_RES`, `inet_ntop` сохраняет mapped-форму — дефект #125 подтверждён. Также не публичны
  `64:ff9b::8.8.8.8`, `::8.8.8.8`, `::ffff:0:8.8.8.8` (вне scope, попутное наблюдение).
- `mf_support_idempotency.QUESTION_ID NOT NULL` + FK: резерв ключа требует миграции → выбран R3 (replay под
  блокировкой адреса).

### 2026-09-26 — реализация

- #125: `ClientAddressResolver::normalize()` сводит `::ffff:0:0/96` к IPv4 после `inet_pton`, до `trusted()`;
  phpDoc класса дополнен. Тесты: публичный mapped remote (включая пример issue `::ffff:8.8.8.8` + `1.1.1.1`,
  верхний регистр и полная запись), приватный mapped remote, публичный и приватный mapped hop в XFF, обычный IPv6
  (в т.ч. `2001:db8::ffff:…` вне mapped-префикса) не меняется.
- `GuestAddressHasher`: mapped-ветка оставлена (R2), в phpDoc записано почему.
- #126: в `SendGuestFeedbackUseCase` replay читается после `lockGuestAddress()` и до проверки лимитов; ранняя
  проверка убрана (одна проверка). phpDoc UseCase обновлён.
- Double `InMemoryQuestions::$whileWaitingForAddress` — однократный колбэк «параллельная транзакция фиксируется, пока
  эта ждёт блокировку адреса». Тесты `GuestFeedbackTest`: повтор (T06), другое тело (T07), другой ключ (T08) на
  границе 4/5; повтор с другого исчерпанного адреса (T09).
- E2E-verifier `verify-support.php` раздел 7: mapped публичный hop (T14), 4 параллельных одинаковых запроса через
  `curl_multi` на границе 4/5, затем 409 и 429 (T15).

### 2026-09-26 — commit и PR

- Commit `a972d5a` (код), `1e54b36` (docs), push `origin/codex/issues-125-126-ip-limit-followups`.
- Дубли: `gh pr list --state all --search "125 in:title"` и `"126 in:title"` → пусто. Создан PR #132 в main
  (`Closes #125`, `Closes #126`). Не слит, не выкачен.

### 2026-09-26 — проверки

Префикс команд: `docker run --rm --network none -e XDEBUG_MODE=off -v $PWD/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app rabit-api-php-cli:d1-local`
(`$PWD` — корень worktree).

- Красный прогон на старом коде (UseCase и resolver временно возвращены к `4fc9dce`, тесты новые):
  `php vendor/bin/phpunit public/local/modules/morefoto.support/tests/Unit/GuestFeedbackTest.php` →
  `Tests: 14, Errors: 1, Failures: 1` (`testParallelRepeatAtTheAddressLimitGetsTheStoredNumber` — 429,
  `testParallelRequestWithAnotherBodyConflicts` — 429 вместо 409);
  `php vendor/bin/phpunit --filter ClientAddressResolverTest --testsuite=functional` → `Tests: 11, Failures: 3`
  (все три mapped-теста). Код восстановлен.
- `php vendor/bin/phpunit public/local/modules/morefoto.support/tests/Unit` → OK (47 tests, 476 assertions).
- `php vendor/bin/phpunit --testsuite=unit` → OK (768 tests, 45380 assertions).
- `php vendor/bin/phpunit --testsuite=functional` → OK (216 tests, 861 assertions), в т.ч. `ClientAddressResolverTest` 11 тестов.
- `php -d memory_limit=4G vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` → `[OK] No errors`.
- `php vendor/bin/php-cs-fixer fix --config=public/local/php-cs-fixer.php --dry-run --diff --allow-risky=yes --path-mode=intersection <7 изменённых .php>`
  → `Found 0 of 6 files that can be fixed` (`tools/e2e/verify-support.php` вне finder конфига).
- `php -l tools/e2e/verify-support.php` → `No syntax errors detected`.
- Frontend не менялся. `make test-e2e` / `make e2e-up` не запускались: gate после ревью запускает координатор.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-26 | PHPUnit `--testsuite=functional` | `testPublicMappedPeerIsTheClientLikeItsIpv4Form`; на старом коде FAIL |
| T02 | PASS | 2026-09-26 | то же | `testPrivateMappedPeerIsAProxyLikeItsIpv4Form`; на старом коде FAIL |
| T03 | PASS | 2026-09-26 | то же | `testMappedHopsAreJudgedAsIpv4`; на старом коде FAIL |
| T04 | PASS | 2026-09-26 | то же | `testMappedHopsAreJudgedAsIpv4` |
| T05 | PASS | 2026-09-26 | то же | `testPlainIpv6KeepsItsForm`, `testIpv6IsNormalized` |
| T06 | PASS | 2026-09-26 | PHPUnit `morefoto.support/tests/Unit` | `testParallelRepeatAtTheAddressLimitGetsTheStoredNumber` (номер 104); на старом коде 429 |
| T07 | PASS | 2026-09-26 | то же | `testParallelRequestWithAnotherBodyConflicts`; на старом коде 429 |
| T08 | PASS | 2026-09-26 | то же | `testParallelRequestWithAnotherKeyIsLimited` |
| T09 | PASS | 2026-09-26 | то же | `testRepeatFromAnotherAddressIsNotLimitedThere` |
| T10 | PASS | 2026-09-26 | PHPUnit unit + functional | 768 + 216 тестов OK, `GuestAddressHasherTest` без изменений |
| T11 | PASS | 2026-09-26 | PHPUnit `morefoto.support/tests/Unit` | `SupportArchitectureTest` |
| T12 | PASS | 2026-09-26 | `phpstan analyse --configuration=phpstan.neon` | `[OK] No errors` |
| T13 | PASS | 2026-09-26 | php-cs-fixer `--dry-run` | 0 of 6 |
| T14 | PASS | 2026-09-26 | `make test-e2e` (`verify-support.php` раздел 7) | `rabit-e2e-46f2372acbc1`: `verify-support.php` passed (1.1 s), 126 браузерных сценариев |
| T15 | PASS | 2026-09-26 | `make test-e2e` (`verify-support.php` раздел 7) | `rabit-e2e-46f2372acbc1`: `verify-support.php` passed (1.1 s), 126 браузерных сценариев |

### 2026-09-26 — ревью и полный gate

- Независимое ревью PR #132: блокирующих нет; неблокирующее — #136 (конкурентный повтор с разных IP → 503).
- Ветка обновлена от main `1be46fe` (merge `fc9374f`).
- `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`
  — `rabit-e2e-46f2372acbc1`: exit 0, Total 407.9 s, 126 браузерных сценариев (a 78, b 48), все verify-*.php passed. T14, T15 PASS.
