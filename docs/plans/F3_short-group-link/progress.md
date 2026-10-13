# F3 — прогресс

## Точка продолжения

- Дата: 13.10.2026. F3 слита: PR #186 → main 68355a0 (по команде пользователя). Ветка `codex/f3-short-group-link`, рабочая копия `/home/user/rabit-api-worktrees/f3-short-group-link`.
- Связанное: README волны `docs/waves/f3/README.md`, граф `docs/waves/graph.json`, план владельца `docs/plans/OPS-telemos-feedback-plan/` (R05).
- Завершено: S0–S6, merge, выкладка на stage релизом `main-20261013105144-68355a0` вместе с E7, D4, E8, J4 и #160 (журнал «13.10.2026 — выкладка»).
- Сейчас: задача закрыта.
- Следующий шаг: следующая волна отмечает F3 `merged` в графе (PR #186, 68355a0a0df15ed9f4188abdd3c989019a98182d).
- Открыто вне волны: #187 (лимит обращений к галерее), #188 (база знаний `/guide/` после E7/D4/E8/J4/F3).
- Блокеры: нет. Рабочее дерево ветки чистое.
- Откат stage: `docker service rollback` для 11 backend-сервисов (спецификации в `services-before.json` релиза) и `morefoto_frontend` (прежний образ в `frontend-before.txt`). Миграции не откатывать: `down`/`redo` `20261012180001` сломает разосланные короткие ссылки.

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

### 13.10.2026 — PR

- Ветка запушена, открыт PR #186 в main; F3 `review` в графе, `verify-wave-graph` exit 0.

### 13.10.2026 — ревью

- Ревью 5500006083 на 7fd6844: код без замечаний; 1 блокер — README выкладки обещал псевдоним «следующей подготовкой» ключу, выданному между миграцией и переключением backend, хотя группу в этом окне могли уже передать (`ensure()` вызывает только HND-03), и не запрещал `down`/`redo` миграции после выдачи коротких ссылок.
- Исправлено в README: миграция и переключение backend подряд; ключ без псевдонима — только повторная подготовка, иначе длинная ссылка; `down`/`redo` `20261012180001` после выкладки запрещены. Ответ в треде.
- Неблокирующее: #187 — лимит неуспешных обращений к публичной галерее (риск F3-DEC-01).

### 13.10.2026 — полный гейт

- Команда: `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor make test-e2e` на d0c4c8b.
- Прогон `rabit-e2e-b00e58ba69e2` — exit 0 за 411,6 с: группа a 81 passed, группа b 66 passed (три новых сценария F3), 15 верификаторов passed, в том числе `verify-links.php`. Тестовый магазин ЮKassa выключен (#174).
- Visual: `f3-desktop-short-link.png`, `f3-mobile-short-link.png` — адрес заменён на длинный, кадр A001 и сроки E7 видны, горизонтального скролла нет; шапка mobile совпадает с E7.

### 13.10.2026 — выкладка на stage

- Пользователь разрешил merge и выкладку («Выкладывай!»); дату редакции оферты оставили 2026-10-12. Merge #186 → 68355a0.
- Релиз `/srv/morefoto/releases/main-20261013105144-68355a0`: `git archive 68355a0 api` (2578 файлов) и образ `morefoto-frontend:main-20261013105144-68355a0` (`VITE_API_MOCKS_ENABLED=false`, база знаний из main). `vendor` и `backend.conf` — из `main-20260927092151-9a4cce6` (`composer.lock` и nginx не менялись). До релиза на stage были backend 9a4cce6 и frontend `guide-refresh-20260927113203-82a6672-18d10b1`.
- `sha256sum --check` PASS; `prepare-release.sh` PASS; `backup.sh` — 267 786 байт; `restore-check.sh` — 175 таблиц PASS.
- `preview.sh` (только чтение): E7 — групп с повторным открытием электронных продаж нет; D4 — 0 плохих строк истории; J4 — 1 оплаченный заказ печати, 1 кадр; F3 — 6 ключей получат псевдоним.
- `migrate.sh ls`: Installed 32, Created 10 (4 новых и 6 старых неотмеченных — не трогали). Точечно `up` Version20261011120001, Version20261011180001, Version20261012100001, Version20261012180001 — все success.
- Сразу FPM: DI-smoke 25/25. Затем backend и 9 воркеров/диспетчеров (11 backend-сервисов), frontend 2/2. Все 12 сервисов 1/1 и 2/2.
- Живая проверка:
  - `/`, `/login`, `/health`, `/guide/`, `/guide/parent/`, `/legal/offer`, `/g/zzzzzzzzzz` — 200; бандл `index-nh7dIl4a.js` совпадает со сборкой;
  - MED-01 по неизвестному псевдониму — 404 `GALLERY_NOT_FOUND`, `/catalog` по псевдониму — 404;
  - `mf_gallery_alias` — 6 строк, ключей без псевдонима 0; все 6 псевдонимов на сервере открывают галерею (200) и возвращают ключ, чей `sha256` совпадает с `TOKEN_HASH` (значения не выводились);
  - оферта: текущая редакция `2026-10-12`, «Редакция от 12.10.2026», текст об email и копии печати;
  - Playwright desktop 1440×1000 и mobile 390×844: «Ссылка недействительна» для неизвестного псевдонима, страница оферты без горизонтального скролла, ошибок страницы нет;
  - логи сервисов за 15 минут: фатальных ошибок нет, в FPM только 404 от проверочных запросов.
- Не проверено вживую: оформление заказа с одним email и оплата (не создавали данные на stage), исправление разметки D4, копирование короткой ссылки в кабинете — покрыто гейтами волн.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| F3-T01 | PASS | 13.10.2026 | PHPUnit `GalleryAliasTest::testAliasIsTenSymbolsOfTheUnambiguousAlphabet`. Повтор при коллизии — UNIQUE + `INSERT IGNORE`, проверяется на MySQL в T08 |
| F3-T02 | PASS | 13.10.2026 | PHPUnit `testPreparationGivesAnAliasOnlyWhereItIsMissingAndKeepsTheKey` (повтор). Параллельность — блокировка группы HND-03 + UNIQUE `TOKEN_HASH`; E2E повтора — T06 |
| F3-T03 | PASS | 13.10.2026 | PHPUnit `testGalleryOpensByAliasAsTheSameKey`, `testLongKeyIsUnchangedAndUnknownAliasIsNotFound`; отозванный ключ — T07 |
| F3-T04 | PASS | 13.10.2026 | PHPUnit `GroupLinkContractTest` |
| F3-T05 | PASS | 13.10.2026 | `npm run test:commerce` (`galleryLinkKey`), `npm run test:ui` (access-лог), `npm run check` |
| F3-T06 | PASS | 13.10.2026 | гейт `rabit-e2e-b00e58ba69e2`: `z-links-preparation`, `cabinet-tables`, `zzzz-short-links` (API и desktop/mobile) — passed |
| F3-T07 | PASS | 13.10.2026 | гейт `rabit-e2e-b00e58ba69e2`: `zzzz-short-links` — окна `open`/`digital`, отозванный и неверные псевдонимы 404, `/catalog` по псевдониму 404 |
| F3-T08 | PASS | 13.10.2026 | гейт `rabit-e2e-b00e58ba69e2` → `verify-links.php` passed |
| F3-T09 | PASS | 13.10.2026 | `docs/waves/f3/visual.json`, скриншоты desktop 1440×1000 и mobile 390×844 просмотрены |
| F3-T10 | PASS | 13.10.2026 | J4 `merged`, F3 `review` (PR #186); `python3 tools/verify-wave-graph.py docs/waves/graph.json` exit 0 |
