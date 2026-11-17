# Прогресс — этап 7, электронный архив учреждения

## Точка продолжения

- Ветка `codex/ops-institution-digital-archive`, worktree `/home/user/rabit-api/.worktrees/ops-institution-digital-archive`, PR [#242](https://github.com/rebit-pro/rabit-api/pull/242) в `main`. План восьми этапов — OPS-telemos-20261107-prompt. Этап 6 merged #241, зависимости #237/#239 merged.
- Проверенный `main` и merge-base `b826a157d608c63cbf9e50aab5ebc0b54de228bb`, code head `1459df1336f318905eaf5f6085daff1147243c47`, проверенные QA-документы в `00e004982dc7f11aca9183168ea379920a3cf273`. Финальная запись меняет только plan.md и progress.md.
- Готовность подтверждена. Авторский gate, отдельный QA gpt-6-astra/max и независимое ревью gpt-6.1-sol/max — PASS. Ревью охватывает весь исходный diff и документы `00e0049`. Изменений исходников после проверенной версии нет.
- Результат поставки подтверждается в PR #242. Следующий шаг после merge — обновить #235 от нового `main` и повторить проверки, затем начать этап 8 при выполнении всех зависимостей. Подтверждённых блокеров и открытых решений этапа 7 нет.
- При возобновлении проверить `gh api repos/rebit-pro/rabit-api/pulls/242`, `git status --short` и `git diff --check`. Команда E2E и результаты сохранены ниже. На момент подготовки merge изменены только эти два документа. Рабочее дерево этапа 1 ведётся отдельно.

## Результаты тест-кейсов

Актуальный итог 17.11.2026. Команда **E2E-FULL**: `python3 tools/run-browser-e2e.py run --groups a,b --kernel /home/user/rebit-p2p/api/public/bitrix --vendor /home/user/rabit-api/api/vendor --php-cli rabit-api-e2e-php-cli:local --php-fpm rabit-api-e2e-php-fpm:local --nginx rabit-api-e2e-nginx:local`. Run `rabit-e2e-7c941de7cfb1`; полный лог/исходные результаты в `api/var/e2e/rabit-e2e-7c941de7cfb1/`, переносимый итог в artifacts/verification.json. PHPUnit выполнялся runner командой `vendor/bin/phpunit` по phpunit.xml, включая unit/functional; дополнительные адресные команды и прежние FAIL сохранены в журнале ниже.

| ID | Статус / дата | Команда / доказательство |
| --- | --- | --- |
| T14-SELECT | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Native Commerce/SQL + ZIP: только eligible paid digital/bundle, old buyer key остаётся expired, gifts/unpaid/late не вошли. |
| T14-BUNDLE | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Четыре исходника двух групп, только купленный ребёнок, пересечение bundle/digital дедуплицировано; все основания в CSV. |
| T14-SNAPSHOT | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Unit testPersistedCompositionDoesNotGrowDuringBuild; native CSV/состав неизменны после cleanup. |
| T12-NAMES | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Native точные bytes/hash, 0001.JPG и две одинаково названные группы разнесены; unit file/directory collision. |
| T12-MISSING | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Real HTTP/UI failed ORIGINAL_MISSING; download 404, без частичного ready; native + unit before/after snapshot. |
| T12-RECOVERY | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Native GET_LOCK двух сессий, lease/owner/CAS, crash after write и stale owner; unit lost queue/503; CommandTester exits. |
| T12-LARGE | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Native 64 МиБ ZIP, дополнительный PHP peak 0 bytes / RSS 0 KiB; unit явных 10001/20ГиБ+1/50001 и sentinel. |
| T12-RETENTION | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Native + clock unit: readiness+7 суток, expiry до cron, все ZIP/tmp убраны; CSV/originals/buyer expiry сохранены. |
| T15-ACCESS | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Real HTTP 401/403/404, organizer create, curator own read, create denied, revoke; unit foreign curator/initiator revoke. |
| T15-TICKET | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Real content: curator link works before revoke, 404 after revoke; native ZIP expiry; unit transfer/signature/time boundary. |
| T01-T02-REG | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. J1/J4 buyer original+ZIP, J2/J5 letters и соответствующие native verifier PASS; прежние права/сроки сохранены. |
| ARCH-HTTP | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Architecture/request unit, PHPStan/PHP lint, реальные route/DTO/DI/auth/content проходят полный gate. |
| MIGRATION | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. Изолированная schema up, up replay сохраняет снимки, down запрещён при данных; MySQL native PASS. |
| UI-STATE | PASS / 17.11.2026 | E2E-FULL + включённые PHPUnit/frontend проверки. UI tests 129, real ready/error/no-releases/download; desktop/mobile/footer просмотрены. Empty/expired — unit/native. |

Границы: максимальный ZIP 20 ГиБ / 10000 файлов не бенчмаркировался; измерен один реальный файл 64 МиБ. Ready/error и scroll проверены реальным UI/visual, empty/expired дополнительно unit/native без отдельной визуальной серии. Это авторские проверки.

## Журнал

### 17.11.2026 — начало этапа

- Пользователь сообщил, что этап 6 завершён и следующий — 7. Найден согласованный план восьми этапов; stage 7 — служебный архив купленных электронных кадров учреждения.
- Проверены корень/ветка/HEAD/dirty исходного checkout. `gh pr list`/`gh pr view 241` подтвердили merge этапа 6 16.11.2026; `git fetch origin main` подтвердил актуальный base `b826a157d608c63cbf9e50aab5ebc0b54de228bb`.
- Создан отдельный worktree командой `git worktree add -b codex/ops-institution-digital-archive /home/user/rabit-api/.worktrees/ops-institution-digital-archive origin/main`. Правила AGENTS.md побайтно совпадают с предоставленными, CLAUDE.md импортирует их. Commit/push/PR/merge/deploy не поручены.
- Через уточняющие вопросы пользователь явно выбрал: «7 суток с момента готовности» и «Вся история оплаченных покупок». Решения перенесены в plan v1; согласование больше не требуется.
- `ast-index version`: v3.56.0-php.4. В новом дереве конфиг/индекс отсутствовали; `ast-index update` сообщил `Index not found` (успех обновления не заявляется). Затем `ast-index rebuild` успешно создал производный индекс в пользовательском cache, без нового файла конфигурации проекта: 2772 файлов, 28165 символов, 174626 refs, 38.75 MB. Vendor/Bitrix/node_modules отсутствуют в рабочем охвате; контрольные outline OrderRepository/InstitutionArchiveAccess/BuildArchiveMessageHandler совпали с исходниками. Watcher не включался.
- Изучены поставщики Commerce OrderEntitlements/OrderRepository, Media ChildPhotos/OriginalFiles, Access InstitutionAccess, Organization GroupDirectory/PrintInstitutionDirectory, текущие Files consumer/dispatch/purge и общий AtomicZipWriter. OrderFileAccess намеренно включает J4/подарки и непригоден как состав этапа 7; выделяется узкий контракт купленных строк. Новый архив будет жить в Files; private-код Production не заимствуется.
- Сохранены два основных документа задачи. Продуктовые изменения, миграции runtime и тестовые прогоны пока не выполнялись.

### 17.11.2026 — состав решения v2

- До первой продуктовой правки уточнены контракт Commerce, состояния, маршруты staff/content/CSV, HMAC с actor, фоновые подключения, сохраняемые пути попыток и технические границы 10000 файлов / 20 ГиБ / 50000 связей. Это ограничения одного ZIP, без тихого усечения всей истории.
- Переиспользуются публичные Organization GroupDirectory/PrintInstitutionDirectory (метаданные учреждения, без бизнес-правила печати), Access InstitutionAccess, Media ChildPhotos/PhotoFilenames/OriginalFiles. AST usages PhotoFilenames показал 29 употреблений; существующие сигнатуры/DTO не меняются. Карта Files и OrderRepository сверена с исходниками. Индекс актуализирован синхронно, 0 изменённых исходников.
- Из существующего Files взяты очередь/фабрика, защищённое хранилище и HMAC; общий AtomicZipWriter поддерживает пути/текстовые записи и атомарный rename. Новый ZIP не меняет покупательскую таблицу и OrderFileAccess.
- Доступ к тестовым зависимостям подтверждён адресно: vendor исходного checkout и frontend/node_modules существуют. Docker-команды будут использовать этот worktree и read-only mount vendor; основной Compose с именем rabit-api не запускается, чтобы не затронуть чужой dev-стенд.

### 17.11.2026 — первая реализация и покупательская регрессия

- Добавлены узкий Commerce-контракт/адаптер/SQL, сохраняемый файловый снимок, раскладка исходных имён, собственная таблица/codec/repository, lease/owner/CAS, сборка и CSV через AtomicZipWriter, signed staff download, чистые controller/request/result DTO, DI и маршруты. Dispatch/purge подключены к существующим командам, новый message/handler — к существующему Files consumer. URI ticket исключён из nginx access log.
- Для большой истории источник уточнён с `forGroup` на `forInstitution`: одна выборка оплаченных строк учреждения; купленные комплекты раскрываются пакетами по съёмкам/детям, без запроса на каждую строку. Plan v2 обновлён до соответствующей правки.
- `docker run --rm --pull=never --network none -v /home/user/rabit-api/.worktrees/ops-institution-digital-archive/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local vendor/bin/phpunit --testsuite=unit --filter='FileAccessTest|DownloadFlowTest|OrderEmail.*Test|FilesArchitectureTest|FilesRequestContractTest'` — PASS, 46 tests / 1070 assertions. Лог: ignored `var/institution-digital-archive/buyer-regression.log`. T01-T02-REG PASS (unit, без заявления о реальной доставке писем). В логе прежние предупреждения metadata cache; тесты не падали.
- Первый общий PHPStan запущен, результат ещё PENDING. Новые адресные тесты и HTTP/native E2E ещё не выполнены. Для frontend применяются rebit-vue-frontend/vue-best-practices, существующие Vuetify/дизайн-токены, отдельные composable и карточка выпуска.

### 17.11.2026 — адресные и первые общие проверки

- `docker run --rm --pull=never --network none -v /home/user/rabit-api/.worktrees/ops-institution-digital-archive/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local vendor/bin/phpunit --testsuite=unit --filter=InstitutionDigital` — PASS, 15 tests / 95 assertions, один PHPUnit Notice. Лог digital-unit-initial.log; лишний mock writer заменён stub, повтор ещё PENDING. Покрыты состав/раскрытие комплекта/дедупликация, исходные имена и ZIP-байты, неизменность снимка, отсутствующий/изменившийся оригинал, retry, 7 суток и purge, replay, empty, curator/ticket/revoke и границы размера. Это unit и реальная локальная ZIP-запись, не MySQL/HTTP.
- Первый PHPStan — FAIL, 1 `nullsafe.neverNull` в DigitalArchiveSnapshot, лог phpstan-initial.log. Проверка nullable-оригинала переписана явно; повтор PENDING.
- Точечный PHP CS Fixer — PASS, 54 явно перечисленных PHP-файла; точные argv в ignored formatter-commands.json, лог formatter-write.log. Массовый make fix не применялся.
- Общий unit `... vendor/bin/phpunit --testsuite=unit --display-notices` — FAIL, 1177 tests / 52717 assertions, два дефекта нового architecture test: ложное совпадение `Bitrix\\` внутри разрешённого Rebit ControllerJson; runtime readonly снимается BypassFinals. Allowlist зависимостей сохранён, вместо неприменимой reflection-проверки проверяются StrictRequest/типизированные public-свойства/единственный конструктор. Лог backend-unit.log; повтор PENDING.
- `npm --prefix frontend run check` — FAIL на lint: standalone Prettier использовал defaults вместо опций eslint.config.js. Новые и три изменённых Vue-файла переформатируются только точечным ESLint --fix по проектной конфигурации; typecheck/unit/build в этом запуске не выполнялись. Логи frontend-check.log/eslint-fix.log. Предварительный prettier exit 0 не считается успешным project lint.
- Новое действие/диалог/карточка, composable с отменой и проверкой сессии перед DOM-скачиванием, typed API/rules и два руководства реализованы. Служебный ticket исключён также из обоих frontend nginx access log; прежние чужие URL не менялись. План v3 уточняет реальную E2E-фикстуру и порядок миграции/consumer до её реализации.

### 17.11.2026 — ограниченные проекции и подготовка реального стенда

- При самопроверке большого состава найден прежний `GalleryPhotoRepository::children LIMIT 5001`. Использовать его как полный состав этапа 7 нельзя. До правок план v4 дополнен BoundedChildPhotosInterface: проверяемый limit+1, прежние покупательские вызовы сохраняют поведение. AST callers показал одного точного потребителя children — ChildPhotos; unresolved строковое совпадение SalesReportHtml проверено как другой метод. Изменены этот поставщик/репозиторий/DI, а не покупательские права. Добавлены регрессия legacy-вызова и отдельный тест bounded-вызова; в Files — проверка переполнения до чтения оригиналов.
- Отдельное наблюдение вне scope: прежняя покупательская/служебная проекция ChildPhotosInterface может усекать очень большой набор на 5001 назначении без сигнала. Поведение потребителей этого контракта сейчас не исправлялось, issue без поручения не публиковалось.
- Список выпусков, due и stale переведены на короткие проекции/ID: двадцать/сто больших JSON одновременно не декодируются. Сохранённые снимки не изменены. Раскладка также закрывает конфликт файл/каталог, сохраняя basename и линейное разрешение цепочки коллизий.
- Добавлены real browser spec и native verifier, привязаны к группе b/runner. В fixture prepare включена новая миграция. Фикстура использует настоящие Media private storage/process/assignment и отдельные исторические снимки заказов по уже принятому L1 helper с собственными quote/key/consent/attempt/fact; PAID_RECORDED_AT=NULL исключает автоматическую рассылку. Это проверка отбора исторических покупок, не новое доказательство внешнего эквайринга. Все данные только в disposable БД/runtime.
- Для fixture выбраны две группы с одинаковым именем, коллизии 0001.JPG, оплаченный старый bundle, digital, J4/подарки/coveredByGift, pending/late. Тест проверяет роль/отзыв при content, скачивание UI и HTTP, реальные ZIP-байты/CSV, отсутствие оригинала и desktop/mobile. Native проверяет реальные GET_LOCK на двух сессиях, lease/CAS recovery, empty, migration replay/guarded down, purge/сохранность исходников и expiry покупателя; отдельно ZIP 64 МиБ и память. Все эти новые runtime-проверки пока PENDING.

### 17.11.2026 — общие backend-проверки и зависимости frontend

- Повтор `docker run --rm --pull=never --network none -v /home/user/rabit-api/.worktrees/ops-institution-digital-archive/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local vendor/bin/phpunit --testsuite=unit --display-notices` — PASS: **1180 tests / 52774 assertions**, без PHPUnit Notices/Failures; backend-unit-final.log. После этого устранены две замеченные PHPStan избыточные конструкции только в тесте, повтор выполняется общим gate.
- `... vendor/bin/phplint --no-cache` — PASS: **1828 PHP files**, PHP 8.4.26, лог php-lint.log.
- `... vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` — FAIL только на двух новых тестовых конструкциях (`nullsafe.neverNull`, `assertNotNull alreadyNarrowedType`), phpstan-final.log; исправлены явно, новый прогон PENDING.
- `npm --prefix frontend run check` после исправления formatter прошёл lint и остановился на `stylelint: not found` (exit 127). Скопированные node_modules исходного checkout оказались неполными относительно актуального lockfile. `npm --prefix frontend ci --no-audit --no-fund` — PASS, 510 packages / 10 s; package.json/lockfile не менялись. Используется собственный node_modules этого worktree; исходная папка зависимостей не изменена.
- Точечный formatter последней версии: PHP 64 явно перечисленных файлов PASS, ESLint --fix 11 явно перечисленных файлов PASS. Логи formatter-write-final.log/eslint-fix-final.log. Runtime-код не меняется во время запуска стенда; docs/progress и proof-артефакты от runtime manifest отделяются.

### 17.11.2026 — проверка перед реальным HTTP/браузером

- PHPStan после исправления двух тестовых конструкций — PASS (`phpstan-final.log`). Затем в тест добавлен сценарий временной недоступности Identity (503): он требует retry вместо ложного отзыва доступа. Уборка под lock пропускает уже очищенный failed; это уточнение заранее отражено в плане.
- `npm --prefix frontend run check` — PASS: lint, stylelint, TypeScript приложения/E2E, UI **129 tests**; `npm --prefix frontend run test:commerce` — PASS **253 tests**; `npm --prefix frontend run build-only` — PASS. Логи `frontend-check-ready.log`, `frontend-commerce.log`, `frontend-build.log`.
- `docker run --rm --pull=never --network none -v /home/user/rabit-api/.worktrees/ops-institution-digital-archive/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local vendor/bin/phpunit --testsuite=unit --filter='InstitutionDigital|PurchasedDigital|ChildPhotos'` — PASS **25 tests / 552 assertions** (`targeted-ready.log`). `python3 -m unittest discover -s tools/tests -p 'test_run_browser_e2e.py'` — PASS **28 tests** (`runner-unit.log`).
- Первый preflight E2E остановился до создания БД: лицензированного install.sql нет в новом checkout. По записи этапа 6 найден существующий ресурс ядра `/home/user/rebit-p2p/api/public/bitrix`; это только read-only dependency, не смена checkout.
- Повтор `python3 tools/run-browser-e2e.py run --groups b --kernel /home/user/rebit-p2p/api/public/bitrix --vendor /home/user/rabit-api/api/vendor --php-cli rabit-api-e2e-php-cli:local --php-fpm rabit-api-e2e-php-fpm:local --nginx rabit-api-e2e-nginx:local` — FAIL на предварительном PHPStan за 73.2 s: один `nullsafe.neverNull` в добавленном тесте 503. Остальные параллельные предварительные проверки PASS (PHPUnit, PHP lint, frontend lint/styles/types/UI/commerce/build). Браузер ещё не запускался. Run `rabit-e2e-e972166d0aa8`, исходный отчёт `api/var/e2e/rabit-e2e-e972166d0aa8/phpstan.log`.
- Тест переписан с явным non-null локальным объектом; параметры сложных DTO названы, исходный компактный формат groups.json восстановлен. Повтор E2E PENDING. Подготовленные runtime-тесты по-прежнему не отмечены PASS.

### 17.11.2026 — авторская самопроверка и промежуточный E2E

- `git fetch origin main` повторно подтвердил `b826a157d608c63cbf9e50aab5ebc0b54de228bb`; base не изменился. Диапазон — только собственный dirty diff и новые файлы этой OPS-ветки. Сохранён SHA-256 manifest **82** runtime/config/test файлов (включая untracked) в artifacts/source-manifest.json.
- В повторе группы b (`rabit-e2e-1a485eea4f61`) предварительный gate PASS: **1400 PHPUnit tests / 53653 assertions**, PHP lint/PHPStan, frontend lint/styles/typecheck/typecheck:e2e/UI/commerce/build. Браузер дошёл до нового сценария; он FAIL на подготовке тестового старого ключа. OrderAccessKeys::issue не отзывает предыдущий активный ключ автоматически. В фикстуре требуется явный revoke прежнего checkout key и issue исторической recovery-даты; прежний ошибочный UPDATE CREATED_AT этой таблице не соответствует (действительное поле ISSUED_AT). Продуктовый отбор/ZIP этим запуском ещё не доказаны.
- Пофайлово прочитаны новые/изменённые контракты/DTO, UseCase/Service, repository/codec/layout/writer, controller/request/result/DI/route, Media bounded projection, команды/сообщения/worker factory, frontend files/action/guide, unit/fakes и real fixture/spec/verifier. Проверены сквозные права, cutoff/история, immutable snapshot, publish/retry/owner, сроки/CSV, collision и ограничение памяти. Это авторская самопроверка; независимого review не было, точная модель/уровень не раскрыты контекстом.
- Дополнительно в изменённых CLI-командах выявлен прежний безусловный SUCCESS при failed в отчёте; новые batch-ошибки этапа 7 должны быть видны cron. План до исправления уточнён: суммарный failed даёт FAILURE, проверка — CommandTester. Код во время активного браузерного прогона не изменяется.

### 17.11.2026 — исправление тестовой истории и результатов cron

- Промежуточный b завершился FAIL за **622.2 s**: браузер **78 PASS / 2 skipped / 3 FAIL**. Помимо fixture этапа 7, прежний G1-T14 не дождался статуса отказа СБП тестового магазина за 30 s (остался «Проверяем оплату»); зависимый G2-T14 не нашёл ожидаемый оплаченный sandbox-заказ/чек. Это отдельный незелёный результат, в продуктовом коде этапа 7 платежи/чеки не изменены. Готовность merge не заявляется. Native verifiers этим failed gate не выполнялись.
- Фикстура исторического заказа теперь явно отзывает checkout key и выдаёт recovery key исторической датой через OrderAccessKeys; ручной UPDATE несуществующего CREATED_AT удалён. Пользовательские/рабочие ключи не затрагиваются: только собственные disposable-заказы.
- Dispatch/purge теперь дают FAILURE и warning при failed любой из двух частей отчёта. В DownloadsMaintenanceCommandTest оба CommandTester-сценария проверяют success, buyer failure и institution failure. Сложные DTO в новых тестах переведены на named arguments; метрики большого ZIP включены в лог native verifier; добавлена уборка имитированного временного файла прерванной попытки.
- Точечный formatter — PASS, **65** перечисленных PHP-файлов, argv `var/institution-digital-archive/formatter-stage7-argv.json`, лог formatter-stage7.log. Адресный `docker run --rm --pull=never --network none -v /home/user/rabit-api/.worktrees/ops-institution-digital-archive/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local vendor/bin/phpunit --testsuite=unit --filter='InstitutionDigital|PurchasedDigital|ChildPhotos|DownloadsMaintenanceCommand'` — PASS **31 tests / 583 assertions** (targeted-after-fixture.log).
- Manifest первой браузерной попытки сохранён отдельно; текущий source-manifest.json покрывает **83** runtime/config/test файла. Следующий полный a+b — PENDING; исходники замораживаются на время прогона.

### 17.11.2026 — полный a+b: предварительный gate

- Полный запуск `python3 tools/run-browser-e2e.py run --groups a,b --kernel /home/user/rebit-p2p/api/public/bitrix --vendor /home/user/rabit-api/api/vendor --php-cli rabit-api-e2e-php-cli:local --php-fpm rabit-api-e2e-php-fpm:local --nginx rabit-api-e2e-nginx:local`, run `rabit-e2e-e5f5759f4cd9`: предварительные проверки PASS, в том числе **1406 tests / 53683 assertions** PHPUnit, PHPStan без ошибок; frontend полный набор прошёл. Изолированные стенды a/b созданы, браузер выполняется. Состав команд и логи сохранены runner в api/var/e2e/rabit-e2e-e5f5759f4cd9.
- Авторская самопроверка оформлена в artifacts/self-check.md: 23 файла Files core, 28 Files boundary/storage/tests, 32 provider/UI/tooling, каждый пакет до 40. Это не независимое review. Runtime/visual результаты в этом отчёте пока PENDING; последующий итог фиксируется отдельно.
- Для визуальной проверки подключён interface-design и прочитаны действующие token/UI/base источники; сохраняется утверждённое оформление «Кадр и мазок v2», нового дизайна/паттерн-файла не вводится. SHA-256 manifest подтвердил отсутствие runtime-правок во время gate.

### 17.11.2026 — первый полный gate и визуальная правка

- Full `rabit-e2e-e5f5759f4cd9` завершился FAIL за **561.0 s**. Группа a прошла, включая verify-access/support/photo-deletion. В b новый T14/T12/T15 сценарий PASS **23.6 s**: UI/API, полный купленный состав двух групп, hash UI/HTTP ZIP, CSV, expired buyer key, curator read/create denial/revocation и missing original. G1/G2 sandbox-оплаты/чеки в этом повторе PASS; прежний J1-T13 упал при page.goto страницы ЮMoney с **net::ERR_CONNECTION_CLOSED**, следующий serial J1-тест skipped. Native verifiers b по правилам runner при failed browser не выполнялись; это не зелёный полный gate.
- Реально просмотрены desktop 1440x1000 и mobile 390x844 PNG этапа 7. Desktop: состояния/даты/действия читаются, поля/пути не раскрыты. На телефоне success alert сжался по высоте в длинном flex-диалоге — визуальная проверка FAIL, не закрыта самим browser PASS.
- После обновления плана запретили shrink прямых дочерних блоков только нового digital-диалога. В spec добавлены assertions полной высоты notice и доступности Закрыть после прокрутки; отдельный mobile-footer screenshot. Два frontend-файла точечно обработаны ESLint --fix. Новый исходный manifest сохранён, предыдущий отдельно; backend/runtime PHP не менялся.
- Следующий полный a+b и native/повторный visual — PENDING. Повтор оправдан исправлением реального визуального дефекта и transient внешней навигацией; платежный продуктовый код и прежний spec не исправлялись.

### 17.11.2026 — повторный visual

- Run `rabit-e2e-f79f74bca9c0`: T14/T12/T15 browser этапа 7 PASS **24.7 s**, включая новые assertions notice без обрезки и Закрыть в viewport после прокрутки. Прежний оплаченный bundle/ZIP, J1 idempotency/token и J2/J5 письма в этом повторе PASS. Группа a и её native verifiers PASS; полный b ещё выполняется.
- Через view_image реально просмотрены все три новых PNG: desktop 1440x1000, mobile 390x844 (основной вид и footer). Мобильный alert теперь целиком показывает две строки, карточка/даты/ZIP/CSV читаются; footer достижим естественной прокруткой, горизонтального overflow нет. Visual **PASS**. Точные снимки без редактирования скопированы в artifacts/institution-digital-{desktop,mobile,mobile-footer}.png.
- Runtime PHP/TS/Vue/config/test файлы по SHA-256 совпадают с source-manifest.json; исходный checkout снова проверен: HEAD/ветка/ahead 3 и чужой untracked OPS-stage-gallery-cleanup не изменились.

### 17.11.2026 — native этапа 7 прошёл, исправлены assignment фикстуры

- В run `rabit-e2e-f79f74bca9c0` обе browser-группы прошли без skipped/failed. Native `verify-institution-digital.php` — **PASS, 69 assertions**: реальный состав и точные ZIP/CSV, владельцы/права, GET_LOCK двух сессий, lease/owner/CAS recovery после записи, empty, migration replay/guarded down, expiry до cron, purge всех attempt/tmp, сохранность исходников/CSV и старого срока buyer key. ZIP **64 МиБ**, добавочный PHP peak **0 bytes**, RSS delta **0 KiB** на этом процессе. Это измерение добавочного пика, не обещание нулевой полной памяти.
- Full gate всё же FAIL **601.7 s**: после успешных native stage7/storefront/sales-windows/handoff/orders/markup-correction/email-only/links/gallery-openings остановился D3 verifier на child-photo связи строк новых исторических заказов. Фикстура заменяла CHILD_ID, но оставляла ASSIGNMENT_PUBLIC_ID прототипа. Ограничение D3 не ослаблялось.
- До исправления дополнен план. Теперь setup берёт ID реального Media assignment и сохраняет его во всех собственных строках; coveredByGift указывает собственный B2 assignment. Перед записью результата setup выполняется scoped SQL-проверка связей child/assignment/photo. Прочитаны оставшиеся глобальные G1/J1/J2/G2 проверки: денежные факты/суммы/late, buyer keys, письма и receipt snapshots согласованы с helper.
- Точечный formatter одного `tools/e2e/institution-digital-fixture.php` — PASS, 0 изменений (formatter-fixture-links.log). Продуктовый backend/frontend и native verifier не менялись. Текущий source-manifest.json отличается от предыдущего только исправленной фикстурой. Следующий полный gate — PENDING.

### 17.11.2026 — итог: полный gate PASS

- Финальный run **rabit-e2e-7c941de7cfb1**, E2E-FULL — **PASS, exit 0, 602.6 s**. Backend **1406 tests / 53683 assertions**, PHPStan/PHP lint PASS; frontend lint/stylelint/typecheck/typecheck:e2e, UI **129**, commerce **253**, production build PASS.
- Browser **164 PASS**: a **81**, b **83**, **0 failed / 0 skipped / 0 flaky**; тестовый магазин YooKassa включён, G1/G2 прошли. **Все 22 native verifier PASS**, включая этап 7 и глобальный D3 после исправления реальных связей fixture. Прежние failed runs остаются в журнале; финальный результат их не скрывает.
- Этап 7 native **69 assertions**: ZIP/CSV/покупки/семь суток/GET_LOCK/CAS/recovery/purge/tmp/originals/buyer expiry/schema. 64 МиБ: дополнительный PHP peak 0 bytes, RSS 0 KiB. Это добавочная память на текущем процессе, не полная память и не benchmark предела 20 ГиБ.
- Все три финальных PNG снова просмотрены через view_image (desktop/mobile/footer) и скопированы без редактирования. Переносимые артефакты: verification.json, source-manifest.json, native-verification.log, три PNG и self-check.md; исходный run содержит полные локальные отчёты. Токены/пароли/runtime dump в план и артефакты не переносились.
- Повторный `git fetch origin main` подтвердил тот же b826a157d608c63cbf9e50aab5ebc0b54de228bb; source manifest подтвердил все **83** runtime/config/test хеша после прогона. `git diff --check` PASS. Runner завершил свои disposable-ресурсы (`stopped=true`), рабочая БД/чужой checkout не затронуты.
- Локальная задача завершена. Независимое review не выполнялось; commit/push/PR/merge/deploy не поручены и не выполнялись. Перед поставкой: foundation-миграция до нового backend/consumer, действующие dispatch/purge и frontend обновляются вместе. Отдельное прежнее наблюдение ChildPhotos LIMIT 5001 остаётся в журнале; вне текущей выдачи покупателя оно не исправлялось.

### 17.11.2026 18:37 МСК — пользователь поручил merge этапа 7 и старт 8

- Прямое поручение: «Давай этап 7 смержим в маин и начнем 8 этап». Оно разрешает необходимые commit/push/PR/merge этапа 7; отдельное подтверждение этих действий не требуется. Поставка приложения не поручена.
- `git fetch origin main` и настроенный `gh` подтвердили main b826a157d608c63cbf9e50aab5ebc0b54de228bb, stage 7 PR ещё отсутствует. Все 83 source/config/test SHA-256 совпадают с прошедшим полным gate. Функциональный код с предыдущего завершения не менялся.
- Применяются rebit-os, rebit-code-review, rebit-quality-gate. Для merge по AGENTS/OS организуются свежий независимый Reviewer (без E2E) и отдельный QA другой моделью (полный E2E с изолированными БД). Результаты до фактического получения PENDING.
- Обнаружено условие этапа 8: канонический implementation-stages.md требует этапы 1–7 в main, но #235 этапа 1 OPEN. Пользователю задан асинхронный вопрос о предварительной сверке с PENDING зависимых частей либо предварительном завершении этапа 1; merge этапа 7 продолжается независимо.

- Перед delivery commit: `git diff --check` PASS; точечный `php-cs-fixer ... --dry-run --diff` по 65 PHP-файлам PASS (var/institution-digital-archive/precommit-format.log). В коммит включаются только 83 проверенных source/config/test файла и собственная папка задачи; исходный checkout/OPS-stage-gallery-cleanup не участвуют.

### 17.11.2026 — delivery commit, PR и порядок следующих этапов

- `git commit -m "feat(files): export purchased institution digital photos"` PASS: **1459df1336f318905eaf5f6085daff1147243c47**, 95 собственных файлов (83 runtime/config/test + папка задачи). `git push --set-upstream origin codex/ops-institution-digital-archive` PASS. Создан draft PR **#242** https://github.com/rebit-pro/rabit-api/pull/242, привязан к текущему T3 thread.
- На code head 1459df1 запущены свежий независимый Reviewer **gpt-6.1-sol / max** (только чтение, без E2E) и отдельный QA **gpt-6-astra / max** (полный E2E/visual). Это разные роли; фактические результаты PENDING. Модель/уровень текущего автора теперь явно доступны через runtime T3: gpt-6.1-sol / max; это не меняет прошлую запись о недоступных тогда метаданных.
- Пользователь ответил на вопрос о зависимости этапа 8: **сначала завершить и смержить этап 1 (PR #235), затем переходить к этапу 8**. Порядок поставки: #242 → обновление #235 на main и его merge → отдельный этап 8. Реализация этапа 8 до этого не начинается.
- Для #235 подготовлен собственный detached worktree /home/user/rabit-api/.worktrees/ops-order-photo-delivery-final от локального b570d27419bba6b0169d3741966fd161c580cf3f с тремя ещё не отправленными коммитами. Исходный checkout/чужой untracked каталог не меняются. `git merge-tree --write-tree b570d27419bba6b0169d3741966fd161c580cf3f origin/main` PASS без конфликтов на прежнем b826a15; фактическая интеграция делается после merge #242.

### 17.11.2026 — отдельный предmerge QA другой моделью PASS

- QA gpt-6-astra / max завершён на code head **1459df1336f318905eaf5f6085daff1147243c47**. Run **rabit-e2e-40b49eeedfbe**, полный gate **PASS**, exit 0, **580.0 s**, все **47 stages**, **164 browser (81+83)** без failed/skipped/flaky, **22 native verifier**, PHPUnit **1406 / 53683 assertions**, frontend UI **129** / commerce **253**, lint/styles/types/build, stage7 native **69 assertions**. Свежие три PNG просмотрены QA, visual PASS.
- Первый run **rabit-e2e-480386667006** остаётся FAIL: G1 не дождался статуса тестовой СБП, G2 зависим; дополнительно runner прервался с 143. Первопричины не установлены, не названы доказанным transient. Один повтор на том же неизменном head разрешил родитель в рамках пользовательского поручения merge; код/ожидания/таймауты не изменялись. Оба исхода и исследование прерывания сохранены.
- Родитель сверил фактические state/results и 83/83 SHA-256, status/scope/stopped. По отчёту QA оба disposable run очищены, исходные 56 контейнеров сохранены. Это отдельный QA, не code review. Независимое review code head 1459df1 ещё ожидается; преждевременный merge не выполняется.
- Переносимая копия отчёта/JSON/counts/cleanup/двух attempts/диагностики/visual и PNG сохранена в artifacts/premerge-qa/. Полные логи остаются в ignored var и api/var/e2e; они не переносятся в Git. Runtime-код не изменялся.

- Перед ожиданием review точка продолжения обновлена фактическими PR/code head/QA. GitHub REST через `gh api repos/rebit-pro/rabit-api/pulls/242` подтвердил OPEN/DRAFT, head 1459df1, base b826a15, mergeable/clean; два отдельных GraphQL read ранее завершались timeout/EOF, данные из них не считались подтверждёнными. Кода в новую head-версию не переносилось.

### 17.11.2026 — уведомление T3 об отсутствии GitHub checks

- На уведомление «No checks on 1459df1 after 17 minutes» проверены PR head/state, YAML и фактические GitHub настройки через настроенный gh. Head 1459df1, PR OPEN/DRAFT, mergeable/clean; 83 runtime hash и QA PASS прежние.
- `gh api repos/rebit-pro/rabit-api/actions/permissions` — enabled=true. Отдельный `gh api repos/rebit-pro/rabit-api/actions/workflows/makefile.yml` — **disabled_manually**, workflow **355706697**, updated_at 25.09.2026. `gh api .../actions/runs?head_sha=1459df1...` — total_count=0. Это подтверждает ручное отключение workflow, а не ошибку trigger YAML. Сам workflow и настройки не менялись.
- Первые API read/отдельный check-runs endpoint возвращали TLS handshake timeout; они не считались доказательством настроек. Успешные targeted API read выше подтверждают причину отсутствия запусков. Ожидание CI не заменяет локальный gate/независимое review.
- Подготовлено обновление описания #242: отдельный QA PASS с сохранённым первым FAIL, точный code head/run/source hash и причина пустого GitHub checks. Независимое review ещё ожидается, merge не выполняется.

- PATCH описания #242 через gh REST PASS: PR остаётся draft, head 1459df1 не изменён. Зафиксированы отдельный QA PASS и подтверждённый disabled_manually workflow; ожидание checks не считается новым QA. Сводка API сохранена в artifacts/premerge-actions.json. Workflow не включался, повторный gate без изменения кода не запускался.
- Перед возвратом снимается наблюдение пустого GitHub CI по текущему уведомлению T3; PR остаётся привязан. Независимый Reviewer должен прислать собственное completion; он остаётся единственным незакрытым предmerge шагом этапа 7. Далее сохраняется порученный порядок #242 → #235 → этап 8.

### 17.11.2026 21:21 МСК — сверка точки продолжения по вопросу пользователя

- Read-only `gh api repos/rebit-pro/rabit-api/pulls/242`: OPEN/DRAFT, head `1459df1336f318905eaf5f6085daff1147243c47`, merged=false, mergeable_state=clean. Независимый Reviewer прочитан через `t3_thread_read` по запросу статуса: running, pendingRequestCount=0; итогового отчёта пока нет. Предmerge QA другой моделью остаётся PASS на том же code HEAD.
- Следующий шаг прежний: получить независимое review и при PASS завершить разрешённый merge #242; затем #235 и этап 8. Код, GitHub workflow и PR не менялись, новые проверки не запускались.

### 17.11.2026 21:30 МСК — продолжение по поручению пользователя

- Пользователь поручил продолжать согласованную поставку #242 → #235 → этап 8. Независимый review остаётся ожидаемым отдельным результатом; блокеры заранее не считаются закрытыми.
- Plan v5 приведён к фактическому состоянию: draft PR уже создан, отдельный QA PASS, выбранный порядок #235 перед этапом 8 явно зафиксирован. Подготовлен собственный metadata commit с переносимыми QA-отчётами и подтверждением вручную отключённого Actions workflow. Runtime/config/test код не меняется; review и QA относятся к code head 1459df1.

### 17.11.2026 21:32 МСК — проверка metadata перед commit/push

- `git fetch origin main` PASS: origin/main остаётся b826a157d608c63cbf9e50aab5ebc0b54de228bb. `gh api .../pulls/242` — OPEN/DRAFT, head 1459df1, mergeable/clean; `gh api .../pulls/242/reviews` и `.../issues/242/comments` вернули пустые массивы.
- Python-проверка manifest/report: **PASS**, 83/83 SHA-256 неизменны, QA report привязан к code head 1459df1 и точному manifest SHA-256, 10 JSON читаются. Credential-shaped scan подготовленных текстовых QA-артефактов PASS, значения не выводились. `git diff --check` PASS, tracked diff только в собственной папке задачи. Нового E2E не требовалось: код после QA не менялся.
- Выполняется отдельный commit/push только delivery-метаданных. Независимый Reviewer продолжает проверку зафиксированной версии 1459df1; его результат и merge пока PENDING. После push PR связывается с текущим T3 thread и ставится на ожидание ревью. Следующий шаг — обработать итог review, при PASS выполнить разрешённый merge #242, затем продолжить #235.

### 17.11.2026 21:41 МСК — QA-отчёты отправлены, ожидание независимого review

- `git commit -m "docs(files): record independent stage 7 QA results"` PASS: **00e004982dc7f11aca9183168ea379920a3cf273**, только 16 файлов собственной папки задачи; после commit дерево clean.
- Стандартный SSH push завершился `Connection closed ... port 22`; один bounded повтор с `ConnectTimeout=15` — `Connection timed out during banner exchange`, exit 128. Это сбой SSH-соединения, а не результат проверки кода. Повтор через HTTPS с credential helper настроенного `/home/user/.local/bin/gh auth git-credential`, без изменения remote/config, **PASS**: remote branch 1459df1 → 00e0049.
- Сразу после push выполнен `link_pull_request` #242, alreadyLinked=true. REST `gh api .../pulls/242` подтвердил head 00e004982dc7f11aca9183168ea379920a3cf273, OPEN/DRAFT. PATCH описания PASS: явно разделены code head 1459df1 и metadata head 00e0049, добавлен путь переносимого QA-отчёта, независимое review обозначено PENDING.
- Все прежние PASS относятся к неизменному runtime (83 SHA-256). Новые тесты не запускались: изменены только отчёты. Merge #242, интеграция/merge #235 и этап 8 ещё не выполнены. Наблюдение PR возобновляется по правилу T3 для разрешённого merge, ожидающего review; завершение делегированного Reviewer отдельно возобновит работу.
- Единственный dirty файл — этот собственный журнал. Следующий шаг: обработать итог независимого review, исправить подтверждённые блокеры при наличии, затем сохранить review/журнал, сверить PR/main и выполнить разрешённый merge #242.

### 17.11.2026 21:57 МСК — независимое ревью PASS, этап готов к merge

- Независимый Reviewer gpt-6.1-sol/max проверил `b826a15..1459df1` по всем 95 файлам и применимым правилам. Сверены 16 документов `1459df1..00e0049`. Все 83 исходных SHA-256 совпали. Подтверждённых дефектов текущего diff и вопросов до merge нет. Существующие предупреждения кеша метаданных DTO за пределами diff остаются наблюдением.
- Адресные проверки Reviewer PASS: PHPUnit 31 тест / 583 assertions и `node --experimental-strip-types --test frontend/tests/ui/institution-digital-archive.test.mjs` 3/3. E2E gpt-6-astra/max сверено по готовым артефактам. Первый FAIL G1/G2 и прерывание 143 сохранены в QA, единственный разрешённый повтор на том же коде прошёл.
- Полный локальный отчёт: `var/institution-digital-archive/independent-review-1459df1.md`, SHA-256 `8926422af0ff19de45b717b3d5ca786d4ade6a5cf8422a0f0fc20e8269880f63`. Сводка сохранена здесь.
- `gh api repos/rebit-pro/rabit-api/pulls/242` перед финальной записью: OPEN/DRAFT, head `00e0049`, base `b826a15`, mergeable/clean. Финальная правка содержит только результаты ревью и состояние плана. После сверки точного head и base выполняется разрешённый merge.
