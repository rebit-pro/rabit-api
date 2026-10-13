# F3 — прогресс

## Точка продолжения

- Дата: 13.10.2026. Ветка `codex/f3-short-group-link` от `origin/main` 0d02adf (merge #160); рабочая копия `/home/user/rabit-api-worktrees/f3-short-group-link`. PR — см. журнал «PR».
- Связанное: README волны `docs/waves/f3/README.md`, граф `docs/waves/graph.json`, план владельца `docs/plans/OPS-telemos-feedback-plan/` (R05), F2 `docs/waves/f2/README.md`.
- Завершено: S0–S4 и документация S5 — реализация (314533f, 8d62da9), E2E и верификатор (1357d85), README, ручной чек-лист, граф (J4 `merged`); быстрые проверки зелёные.
- Сейчас: S5 — PR и ревью.
- Следующий шаг: ревью PR; без блокеров — полный `make test-e2e` и visual (S6), затем merge по команде пользователя.
- Блокеры: нет.
- Рабочее дерево: чистое после коммита документации.
- После merge F3 — выкладка на stage всего слитого (E7, D4, E8, J4, #160, F3) по указанию пользователя 12.10 и 13.10.2026. Юридический вопрос E8 закрыт 13.10.2026 (см. журнал).
- Команды: PHP — scratchpad `php-f3.sh` (php-cli `rabit-api-php-cli:d1-local`, volumes `rabit-e7-vendor`, `rabit-e7-phpstan`); frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce && npm run test:ui && npm run typecheck:e2e'`; гейт — `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor make test-e2e`; граф — `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Журнал

### 12.10.2026 — старт

- J2 приостановлена пользователем; из готовых взята F3. Других веток и PR по F3 нет.
- Worktree: `git worktree add -b codex/f3-short-group-link /home/user/rabit-api-worktrees/f3-short-group-link origin/main`.
- Инвентаризация — раздел «Факты» плана. Главное: псевдоним должен сводиться к тому же `TOKEN_HASH` (квоты, заказы, вопросы), лимитов запросов нет, переиздания ключа нет.

### 12.10.2026 — решения

- Пользователь выбрал: 10 символов (вместо рекомендованных 12), домен app.morefoto36.ru/g/…, копируется только короткая ссылка, адрес заменяется на длинный.

### 12.10.2026 — реализация

- 314533f: `mf_gallery_alias`, миграция 20261012180001 (в списке `api/tools/e2e/prepare.php`), `GalleryAlias`, `attachAlias()/tokenByAlias()`, HND-02 `galleryAlias`, MED-01 принимает псевдоним и отдаёт `token`, фикстура E4 пишет `alias`.
- 8d62da9: frontend — копирование короткой ссылки (`galleryLinkKey`), замена адреса на длинный в `useGallery`.

### 13.10.2026 — возобновление и тесты

- Журнал отставал от кода («Сейчас: S2»), точка продолжения исправлена. Результатов проверок S2/S3 не было — прогнаны заново.
- 1357d85: E2E `zzzz-short-links` (группа b), правки `z-links-preparation`, `cabinet-tables`, `f2-links`; `verify-links.php` — один псевдоним на ключ, псевдоним у каждого копируемого ключа, повтор миграции.
- Тест access-лога frontend дополнен `/g/<псевдоним>`; backend nginx не меняется (T-05 в плане уточнён).
- Проверки (13.10.2026):
  - `php-f3.sh php vendor/bin/phpunit` → OK, 1134 теста, 49368 assertions;
  - `php-f3.sh php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress -c tools/e2e/phpstan.neon` → No errors;
  - php-cs-fixer `--dry-run` по 22 изменённым PHP-файлам → 0 исправлений; `php -l tools/e2e/verify-links.php` → без ошибок;
  - frontend `npm run check` exit 0, `test:commerce` 239/239, `test:ui` 88/88, `typecheck:e2e` OK, ESLint E2E-файлов OK;
  - `python3 tools/verify-wave-graph.py docs/waves/graph.json` → exit 0, 59 волн, readyMvpFromMain [J2, F3].
- Документация: `docs/waves/f3/README.md`, раздел F3 в `docs/testing/manual-wave-checklist.md`, граф — J4 `merged` (PR #184, b085e8e), `decisionEvidence` F3-DEC-01-04 и E8-LEGAL-2026-10-13.

### 13.10.2026 — ответ по E8

- Пользователь проверил: для продажи фото физлицу достаточно email, ФИО и телефон не нужны (ст. 4.7 54-ФЗ). Кассовый чек уходит на email — волна G2; ссылка на заказ письмом — J2. Отправку писем пока не включаем; текущая форма отправку не обещает («Выбор канала для чека появится позже»). Выкладка E7/D4/E8/J4 больше не ждёт ответа.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| F3-T01 | PASS | 13.10.2026 | PHPUnit `GalleryAliasTest::testAliasIsTenSymbolsOfTheUnambiguousAlphabet`. Повтор при коллизии — UNIQUE + `INSERT IGNORE`, проверяется на MySQL в T08 |
| F3-T02 | PASS | 13.10.2026 | PHPUnit `testPreparationGivesAnAliasOnlyWhereItIsMissingAndKeepsTheKey` (повтор). Параллельность — блокировка группы HND-03 + UNIQUE `TOKEN_HASH`; E2E повтора — T06 |
| F3-T03 | PASS | 13.10.2026 | PHPUnit `testGalleryOpensByAliasAsTheSameKey`, `testLongKeyIsUnchangedAndUnknownAliasIsNotFound`; отозванный ключ — T07 |
| F3-T04 | PASS | 13.10.2026 | PHPUnit `GroupLinkContractTest` |
| F3-T05 | PASS | 13.10.2026 | `npm run test:commerce` (`galleryLinkKey`), `npm run test:ui` (access-лог), `npm run check` |
| F3-T06 | PENDING | — | `make test-e2e` после ревью: `z-links-preparation`, `cabinet-tables`, `zzzz-short-links` |
| F3-T07 | PENDING | — | `make test-e2e` после ревью: `zzzz-short-links` |
| F3-T08 | PENDING | — | `make test-e2e` → `verify-links.php` |
| F3-T09 | PENDING | — | `make test-e2e` → `docs/waves/f3/visual.json` |
| F3-T10 | PENDING | — | J4 `merged` готово, verify exit 0; F3 `review` — после открытия PR |
