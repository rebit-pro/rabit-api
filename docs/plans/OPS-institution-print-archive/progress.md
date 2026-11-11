# OPS-institution-print-archive — прогресс

## Точка продолжения

- Дата: 11.11.2026, Europe/Moscow.
- Worktree: `/home/user/rabit-api/.worktrees/ops-institution-print-archive`; ветка `codex/ops-institution-print-archive`.
- Base/HEAD: `7b9de6ed82b7786e4b5e957a3d44a004fcb92935`; main/PR #238 перепроверены через gh тем же SHA, зависимость MERGED. Собственного PR задачи нет.
- Документы: plan.md v1.2, архитектура проекта; artifacts/verification.json, source-hashes-review-fixes.json и source-hashes-final.json — текущие результаты. Прежние verification/final manifest сохранены как *-before-review-fixes.json.
- Завершено: все три замечания независимого review REV-01/02/03 исправлены; unit/native регрессии добавлены. Полный gate 56eab0e1d064 PASS (162/162 browser и все native), backend 1348/52478, targeted unit 72/1800, PHPStan/CS dry-run 0/52 и весь frontend PASS. Desktop/mobile просмотрены, 66/66 хешей совпадают, после gate runtime/test не менялись.
- Сейчас: пользователь поручил merge в main. Готовится коммит и PR всего этапа 5; повторное независимое review исправлений выполняется перед слиянием.
- Один следующий шаг: закоммитить проверенный срез, получить повторное независимое review исправлений, создать PR и выполнить разрешённый merge при зелёных условиях. Commit/push/PR/merge разрешены поручением 11.11.2026; deployment не поручен.
- Блокеров и открытых бизнес-решений нет. Накопительный состав/история сохранены; ротация/PDF/этапы 6–8 вне scope. Для восстановления по-прежнему нужны оригиналы.
- Dirty state: весь этап 5 незакоммичен и не staged. От reviewed manifest изменены ровно 9 runtime/test файлов; остальные 57 неизменны. Plan/progress и новые доказательства обновлены. Чужие деревья/изменения не трогались.
- Runtime: полный runner a+b завершился exit 0 за 662.9 s; изолированные ресурсы остановлены (`state.stopped=true`). Текущий state — api/var/e2e/rabit-e2e-56eab0e1d064/state.json. Рабочая БД и deployment не использовались.
- Следующая проверка: `git status --short --untracked-files=all`, `git rev-parse HEAD`, сравнение SHA-256 из source-hashes-final.json; перед чтением AST выполнить `XDG_CACHE_HOME=/tmp/rabit-institution-print-archive-ast /home/user/.local/bin/ast-index update`. Новый E2E без изменений повторять не нужно.


## Журнал

### 10.11.2026 — проверка контекста и зависимости

1. В исходном checkout выполнены `pwd`, `git rev-parse --show-toplevel`, `git branch --show-current`, `git rev-parse HEAD`, `git status --short`, `git worktree list`. Целевых ветки/worktree и папки задачи не было. Чужая папка OPS-stage-gallery-cleanup не удалялась/не перемещалась; другие worktrees не менялись.
2. Прочитаны AGENTS.md/CLAUDE.md, оба документа требований, планы L1 и этапа 4, архитектура и фактические команды Makefile/composer/phpunit/package.json. Подключены rebit-os, rebit-architect, rebit-bitrix, rebit-vue-frontend, vue-best-practices, rebit-quality-gate и применимые нормы ОС delivery/layers/http/storage/processes/frontend, task-workflow, AST retrieval, architecture/runtime/solution-outline/quality-gate. Реализация Vue ещё не начата.
3. Первый сетевой запрос `gh repo view ...` в песочнице заблокирован доступом к proxy socket (operation not permitted). Повтор тех же read-only действий с эскалацией прошёл; auto-review не отклонял эти операции.
4. `/home/user/.local/bin/gh repo view --json nameWithOwner,url` — `rebit-pro/rabit-api`. `gh pr view 238 --json number,title,state,mergedAt,mergeCommit,baseRefName,headRefName,url,body` — MERGED в main, нужный SHA. `gh api repos/rebit-pro/rabit-api/git/ref/heads/main --jq .object.sha` — `7b9de6ed82b7786e4b5e957a3d44a004fcb92935`. `gh pr list --state open --json number,title,headRefName,baseRefName,url` — открыты #235/#231/#230/#229/#36/#29; ни один не перенесён в задачу.
5. `git ls-remote origin refs/heads/main` и `git fetch origin main` по настроенному SSH завершились exit 128: Connection closed by github.com port 22. Успешный SSH-fetch не заявляется. Безопасная альтернатива `git -c credential.helper= -c 'credential.helper=!/home/user/.local/bin/gh auth git-credential' fetch https://github.com/rebit-pro/rabit-api.git main:refs/remotes/origin/main` выполнена с эскалацией и завершилась exit 0. Локальный origin/main совпадает с проверенным API SHA.
6. `git cat-file -t 7b9de6ed82b7786e4b5e957a3d44a004fcb92935` — commit. `git merge-base --is-ancestor 7b9de6ed82b7786e4b5e957a3d44a004fcb92935 origin/main` — exit 0. Рабочее дерево создано `git worktree add -b codex/ops-institution-print-archive .worktrees/ops-institution-print-archive origin/main`; первая попытка не могла писать .git (read-only), разрешённый повтор с эскалацией — exit 0. Reset/clean/stash/rebase чужого дерева не выполнялись.
7. Проверены корень/ветка/HEAD/status нового worktree: нужные значения, исходно чисто. CLAUDE.md импортирует AGENTS.md; sha256 AGENTS.md совпадает с прочитанным исходным checkout (`cad19e628544c2d84ae1aa93423b08ff3b0d8f4a23515561380901cddf289627`). Вложенных AGENTS/CLAUDE нет.

### 10.11.2026 — инвентаризация и предложение границ

1. Текстовым поиском по проектным docs, guide и соседнему MoreFoto `docs/04-bitrix-modules`/`docs/05-rest-api` не найдено принятого правила партии учреждения. Упоминания будущих волн/демо передачи не являются реализацией Production. Промпт прямо требует отдельного решения до кода. L1-DEC-08 про старые группы и автоматическую сборку не определяет партии учреждения.
2. `/home/user/.local/bin/ast-index version` — v3.56.0-php.4. Обычный `ast-index update` не мог открыть `/home/user/.cache/ast-index/.leases/layout.lock` (read-only). Без изменения прав использован временный `XDG_CACHE_HOME=/tmp/rabit-institution-print-archive-ast`: `ast-index rebuild` + `stats` — exit 0, 2685 файлов/27132 символа. Перед первым чтением `ast-index update` — exit 0, 0 изменённых файлов.
3. AST `class ArchiveBuilder`, `usages 'Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface'` — 41/41 употребление без усечения; `outline` BitrixArchiveRepository и InstitutionScreen подтвердили реальные точки расширения. Исходники VersionSnapshot/CreateProductionVersionUseCase/ArchiveVersion/ArchiveBuilder/RunProductionUseCase/ProductionAccess/ProductionLookup/CSV/routes и контрактов Organization прочитаны адресно. Производственный состав — существующие версии/строки, scope группы/учреждения, дедупликация строк по group+deadline; скачивание не считается передачей.
4. Календарь Organization: GroupCalendar::extend запрещает продление после наступления closesAt (`PRINT_SALES_CLOSED`). В партии нужен актуальный срок после разрешённого продления до закрытия, включая повторную серверную проверку при сборке; текущий предметный запрет не меняется.
5. Пользователю отправлен конкретный вариант BOUNDARY-5: явный выбор групп; сохранённый конечный состав; история только по явному выбору ещё не переданных версий; новые съёмки отдельной партией; повтор/восстановление без новых строк; late-confirmed своевременная оплата отдельным дополнением; одна версия L1 не назначается двум партиям. Это предложение, а не принятое решение.
6. До ответа выполняется только независимая подготовка. Сохранены plan/progress и состав решения версии 0.1. Код, миграции, API и frontend не менялись; продуктовые тесты и стенды не запускались.

### 10.11.2026 — проверка документов и точка передачи

- `python3` (stdlib Path/subprocess) прочитал оба новых файла как UTF-8, проверил конечный newline и ровно два файла в папке задачи. Для каждого выполнен `git diff --no-index --check /dev/null <абсолютный путь>`: код 1 без stdout/stderr, что означает наличие нового diff без whitespace-ошибок; PASS. `git diff --check` — exit 0.
- `git merge-base --is-ancestor 7b9de6ed82b7786e4b5e957a3d44a004fcb92935 HEAD` — exit 0. `git status --short --untracked-files=all` в новом worktree показывает только plan.md/progress.md задачи; в исходном checkout — только прежние plan.md/progress.md чужой OPS-stage-gallery-cleanup. Продуктовых изменений нет.
- Применён rebit-quality-gate для фактического объёма документов; проверены source/scope/зависимость/статусы и следующий шаг. Прочитаны code-review.md/review-opposition.md; выполнена авторская самопроверка двух документов, независимое review не заявляется. Тесты продукта, fixer и стенд не нужны для этой подготовки; все обязательные продуктовые проверки остаются PENDING.
- На завершение хода ответ по BOUNDARY-5 ещё не получен. Пользователю передаётся конкретный вариант для подтверждения, как прямо требует его поручение и раздел 6 implementation-prompt.md. Возобновление — с ответа и актуализации состава решения до кода, без повторного создания worktree.

## Результаты тест-кейсов

Дата: 10.11.2026, Europe/Moscow. Статусы относятся только к этой задаче, результаты этапа 4 повторно не засчитываются.

| ID | Статус | Фактическая команда / доказательство |
| --- | --- | --- |
| DEP-4 | PASS | `gh pr view 238 ...`, `gh api .../git/ref/heads/main --jq .object.sha`, HTTPS fetch через gh credential helper, `git merge-base --is-ancestor 7b9de6ed... origin/main` — MERGED/main/base совпадают |
| BOUNDARY-5 | PASS | Прямые ответы пользователя: «прежние задания плюс новую съёмку», «включать все существующие». Состав решения v1 обновлён до кода |
| T09 | PASS | Адресный unit 69/1735, final gate 62559a39d294: browser open/new/closed, 409 прямого API, блокеры; native реальные source rows/ZIP. `artifacts/gate-3.log` |
| T09-EXT | PASS | `InstitutionPrintArchiveTest::testExtendedPrintDeadlineIsReadAgainAndDigitalDoesNotBlock`, повторная проверка builder до/после ZIP; HTTP-календарь поставщика в полном a+b. +1 час с управляемыми часами |
| T09-SCOPE | PASS | Unit и новый browser/native: все исторические группы, первая версия без дат отсечения, новая открытая съёмка сохраняет прежний ZIP, закрытая даёт накопительный full; старый SHA-256 не меняется |
| T10-INSTITUTION | PASS | Unit real ZIP + b/verify-institution-production.php: basename/Media SHA-256, дерево без съёмки, точные 16 колонок BOM/CSV/trace IDs. PDF и полная T10 этапа 6 не заявляются |
| T11 | PASS | Адресный unit: quantity×printCount/общий кадр/коллизии/разные продукты; native сверил сумму оплаченных Commerce rows, L1 lines, CSV и ZIP на реальной БД |
| T12 | PASS | Unit 69/1735 под 128 MiB, реальный ZIP >160 MiB; unsafe/missing metadata/stale lease; native missing disk retries 1/2/3 без ready, explicit failed, restore/rebuild same snapshot |
| T12-CONCURRENT | PASS | Native MySQL GET_LOCK в двух PHP соединениях, два параллельных cron: один выпуск, нет дублей L1. BEFORE INSERT SIGNAL проверил rollback create/renew+key и повтор после сбоя; unit stale-owner CAS |
| T13 | PASS | Unit timely late confirmation, late excluded, pending count без блокировки; native фактическое дополнение только новых L1 строк, replay и следующий full включает его без дублей |
| T13-LIFECYCLE | PASS | Unit +1 час/14 дней/ticket 120 s/generation/CSV after expiry; native expire/delete, отказ старого доступа, rebuild исходного snapshot без нового задания |
| T15 | PASS | Unit/architecture и HTTP browser: organizer manage, curator own read, curator foreign 404, teacher/head 403, anonymous 401; повторная проверка назначения при bytes после revoke даёт 404 |
| DOC-5 | PASS | Organizer/curator guide обновлены, HTML anchors проверены; финальные PNG 1440×1000/390×844 просмотрены через view_image. `artifacts/institution-{desktop,mobile}.png` |
| GATE-AB | PASS | Полная runner-команда a+b (ниже), run 62559a39d294, exit 0, 830.4 s, a81+b81=162, zero skipped/flaky/unexpected; все backend/frontend/native PASS. `artifacts/verification.json` |

Авторская реализация/приёмка завершены; независимое review — отдельно. Самопроверка не означает разрешения merge/deployment.

### 10.11.2026 — уточнение автоматического сценария пользователем

- Пользователь: после окончания приёма печати последней съёмки учреждения появляется кнопка/действие скачивания архива всего учреждения. Добавление новой съёмки не убирает действие. После окончания приёма новой съёмки архив обновляется.
- Уточнение принято как изменение целевого решения: ручной выбор групп/партий исключён. Прежний готовый ZIP остаётся доступен, пока новая съёмка открыта; ограничение открытой печати применяется к следующему включаемому составу, а не к повторной выдаче прошлого выпуска. +1 час и остальные правила L1 сохраняются.
- `plan.md` актуализирован до 0.2 до любых изменений продукта: цель, BOUNDARY-5, состав решения, сценарии API и тест-кейсы переписаны под автоматическое планирование. Предложение 0.1 больше не применяется.
- В одном асинхронном запросе заданы два оставшихся вопроса: (UPDATE) полный накопительный ZIP с прежними заданиями либо только новые задания; (HISTORY) какие существующие съёмки включать на старте с учётом явного запрета автоматически включать всю историю. Ответ не подменяется предположением о дате/сезоне или факте передачи лаборатории.
- Повторно проверены активный checkout/ветка/HEAD/status: HEAD `7b9de6ed...`, только два новых документа задачи. `gh api repos/rebit-pro/rabit-api/git/ref/heads/main --jq .object.sha` и `gh pr view 238 --json state,mergeCommit,mergedAt` с разрешённой эскалацией — прежний main, PR MERGED нужным SHA.
- `XDG_CACHE_HOME=/tmp/rabit-institution-print-archive-ast ast-index update` — PASS, 0 изменений. Адресный outline несуществующего пути ListInstitutionsUseCase не использовался как доказательство; фактические GroupDirectoryQueryInputDto/GroupDirectoryPageOutputDto и ArchivePolicy прочитаны по исходникам. Нужный каталог UseCase при реализации находить через индекс/rg, не угадывать путь.
- Прочитаны четыре обязательных reference vue-best-practices. Продуктовые изменения, миграции, backend/frontend/runtime/E2E/visual по-прежнему не запускались.
- После актуализации `python3` с Path/subprocess повторил проверку ровно двух файлов, UTF-8/newline и `git diff --no-index --check /dev/null <файл>` для каждого — PASS; `git diff --check` — exit 0, status только plan/progress. На завершение хода UPDATE/HISTORY ещё ожидают ответа; следующий шаг в точке продолжения актуален.

### 10.11.2026 — BOUNDARY-5 закрыт, решение v1 до кода

- Пользователь явно подтвердил полный накопительный архив и включение всех существующих съёмок. Это заменяет исходный запрет автоматического включения истории; отдельной даты отсечения нет. Ротация — будущая задача, удаление данных не разрешено.
- План переписан под окончательное решение: ссылки на существующие задания L1, сохранённые пути/CSV, полный ZIP и отдельные дополнения, актуальные сроки и прежний доступный выпуск, actor-bound download с повторной проверкой прав, lease owner/CAS и изолированный путь каждой попытки. Все новые файлы/контракты/таблицы/API перечислены до реализации.
- Повторная gh-проверка main — `7b9de6ed82b7786e4b5e957a3d44a004fcb92935`, зависимость неизменна; рабочее дерево перед кодом содержит только два документа задачи. Подключён interface-design для расширения существующего UI; токены и Vuetify переиспользуются.

### 10.11.2026 — backend/API и интерфейс, первый статический прогон

- Реализованы контракт каталога учреждений у Organization, миграция метаданных доставки, ссылки на L1/сохранённые пути, автоматический runner, ZIP builder с owner/CAS и отдельным путём попытки, staff API/DI и actor-bound download с повторной проверкой прав. Сами групповые задания и старые ссылки не переписываются.
- Добавлены диалог учреждения, отдельная карточка версии, composable/API/types/rules; действие подключено к списку/карточке учреждения для organizer/curator. Права управления приходят с backend. Накопительный архив и дополнения явно различаются. План дополнен карточкой версии до её создания.
- Первый `docker run --rm --network none --read-only ... php ... phpstan analyse --configuration=phpstan.neon --no-progress` — FAIL окружения: отсутствовала точка mount /app/vendor. Создан пустой игнорируемый api/vendor; повтор — FAIL окружения: tmpfs /app/var принадлежал root и uid1000 не мог создать кеш.
- Точная работающая форма запуска: `docker run --rm --network none --read-only --tmpfs /tmp:rw,mode=1777 --tmpfs /app/var:rw,mode=1777 --user 1000:1000 -v /home/user/rabit-api/.worktrees/ops-institution-print-archive/api:/app:ro -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress`. Фактический результат: FAIL, 5 ошибок (ResponseDtoInterface у 3 новых result DTO и избыточный nullsafe). Исправлены, повтор ещё не запускался.
- UI и runtime ещё не проверены; тесты и реальная E2E-проверка добавляются, PASS не заявляется.

### 10.11.2026 — адресные проверки и подготовка живого сценария

- Адресный PHP 8.4: `docker run --rm --network none --read-only --tmpfs /tmp:rw,mode=1777 --tmpfs /app/var:rw,mode=1777 --user 1000:1000 -v /home/user/rabit-api/.worktrees/ops-institution-print-archive/api:/app:ro -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local -d memory_limit=128M vendor/bin/phpunit --colors=never --do-not-cache-result --testsuite=unit --filter='InstitutionPrintArchiveTest|Production(Rules|Flow|Architecture)Test|PrintArchiveLayoutTest'`: PASS 66 тестов/1604 assertions, 32 MiB, но 2 PHPUnit notices у новых writer mocks без expectations. Двойники заменены на stubs, подготовлен повтор с --display-notices.
- Повтор PHPStan после исправления ResponseDtoInterface — PASS. После добавления тестов обнаружен один избыточный nullsafe в assertion; исправлен, следующий gate проверит актуальные исходники.
- Точечный CS Fixer: Python собирает только собственные tracked/untracked PHP и вызывает Docker PHP `vendor/bin/php-cs-fixer fix --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=override <51 явный путь>`. PASS, исправлено 22/51 файла, чужие файлы не включались.
- Подготовлены HTTP/browser и native проверки: существующий spec L1 дополнен сценарием учреждения (уже входит в группу b), request-hook и отдельный native verifier добавлены в runner. Это пока подготовка, не результат E2E.
- Изолированная fixture создаёт отдельное учреждение с историческими снимками на основе оплаченного J4-заказа, отдельными quote/строками/денежными фактами тестового provider. PAID_RECORDED_AT=NULL и RECEIPT=NULL отражают исторические заказы до автоматической доставки и не создают писем/чеков; исходные браузерные заказы/платежи не меняются. Checkout/оплата таких исторических фикстур не являются самостоятельной приёмкой нового checkout — его проверяют прежние сценарии gate.
- Native verifier подготовлен для SHA-256/CSV/реальных source rows, параллельных PHP/MySQL mutex/runner, expiry/renew/rebuild/пропавшего оригинала и replay/guarded down миграции. Все изменения оригинала ограничены disposable runtime и восстанавливаются finally.
- Инструкции organizer/curator описывают накопительную полную выгрузку, отдельные дополнения, +1 час/14 дней/права; исправлена вложенность нового HTML-раздела при самопроверке до браузера.

### 10.11.2026 — frontend PASS и запуск полного gate 1

- После targeted Prettier первый `npm --prefix frontend run check` завершился FAIL (542 замечания prettier): standalone formatter не унаследовал inline-настройки ESLint. Переформатированы только 11 собственных TS/Vue/MJS с `--single-quote --trailing-comma none --print-width 140`, как в eslint.config.js; чужие файлы не форматировались.
- `npm --prefix frontend run check && npm --prefix frontend run test:commerce && npm --prefix frontend run build-only` — PASS: lint/styles/typecheck/typecheck:e2e; локальный Node 24.15.0 сообщил 26/26 UI test files (включая новый), 32/32 commerce test files, zero skipped; Vite 1315 modules, build PASS. Это фактические file-based totals данного Node, не число assertions или прежние totals контейнера.
- Повтор адресного PHPUnit с --display-notices — PASS 66 тестов/1653 assertions, notices отсутствуют. PHPStan актуальных DTO/тестов перед последним усилением — PASS. Затем добавлены адресные тесты unsafe/recovery и >160 MiB ZIP, которые проверит полный gate.
- При самопроверке ошибка планирования перенесена внутрь mutex снимка: более старый неудачный тик не может перезаписать результат нового успешного. Supplement без исходного выпуска отклоняется до создания новых L1-версий. После этого targeted CS Fixer 51 файл — PASS, исправлены только 2.
- Полный реальный gate запущен: `python3 tools/run-browser-e2e.py run --groups a,b --kernel /home/user/rebit-p2p/api/public/bitrix --vendor /home/user/rabit-api/api/vendor --php-cli rabit-api-e2e-php-cli:local --php-fpm rabit-api-e2e-php-fpm:local --nginx rabit-api-e2e-nginx:local --mysql mysql:8.0 > /tmp/rabit-institution-gate-1.log 2>&1`. Состояние выполнения ожидается; PASS пока не заявляется.
- Хеши 65 собственных runtime/test файлов сохранены до запуска в `artifacts/source-hashes-gate-1.json`. HEAD остаётся base 7b9de6ed, запускается dirty worktree; commit не разрешён. Во время gate runtime-исходники не изменяются, журнал можно дополнять.

### 10.11.2026 — gate 1: браузер PASS, общий gate FAIL

- Run `30622002cb4c`: browser a 81/81 и b 81/81, всего 162, zero skipped/unexpected/flaky; новый сценарий учреждения прошёл целиком, включая исходный и накопительный ZIP, блокировку нового выпуска, текущие права на байты и desktop/mobile PNG. Backend: lint/PHPStan PASS, полный PHPUnit 1344/52384, peak 68.5 MiB. Обязательный gate в целом **FAIL**: b/verify-orders.php сообщил `Order, key and receipt invariants are broken`; дальнейшие native verifier не запускались. Это дефект новой тестовой подготовки, не подтверждённый дефект runtime API.
- Причина: исторические fixture-заказы были созданы с quote/оплаченными строками/денежными фактами, но без checkout receipt, активного buyer-key и журнала согласий; также их ID не были зарегистрированы в общем browser-списке ожидаемых заказов. Старый verifier не ослаблялся. Fixture дополнена настоящей выдачей ключа, sealed replay, согласиями и регистрацией ID; даты quote/order/payment приведены к последовательной исторической шкале. Письма и чеки для исторических NULL-моментов по-прежнему не создаются.
- PNG первого run просмотрены через view_image: desktop/mobile без перекрытий и горизонтального overflow, актуальная полная версия выделена. Найдены и исправлены новые подписи счётчиков («3 групп», «1 позиций»); используются существующие plural-правила. Пустой сохранённый выпуск больше не утверждает отсутствие всей печати при новых внешних строках. Требуется новая visual-проверка актуальных подписей.
- Хеши gate 1 оставлены как доказательство именно первого диапазона; после исправлений будет отдельный manifest и новый полный a+b. Старые native проверки не объявляются PASS и не подменяются браузерным результатом.

### 10.11.2026 — gate 2 и уточнение native-проверки до первого запуска

- Повторный полный a+b запущен той же командой, лог `/tmp/rabit-institution-gate-2.log`, run `683edf5faffe`. Хеши до старта — `artifacts/source-hashes-gate-2.json`; production/API/browser исходники заморожены.
- На текущем изолированном стенде выполнен smoke CLI-команды: prolog `/runtime/public`, resolve `RunProductionCommand` из настоящего ServiceLocator, Symfony Application/ArrayInput `app:production:run --limit=20`. Exit 0: групповых/учрежденческих новых версий/ошибок 0, оба runner доступны из реального DI. Рабочая БД не использовалась.
- При сверке native verifier с фактическим контрактом Media установлено: OriginalFiles возвращает сохранённые метаданные и путь, а отсутствие файла на диске обнаруживает общий ZIP writer. Это штатный технический retry L1, не ошибка ORIGINAL_MISSING на чтении метаданных. До первого исполнения native verifier исправлена только эта проверка: три реальные попытки с принудительным наступлением retry-time в disposable БД, ни на одной нет ready/доступного ZIP, затем обязательные FAILED/ARCHIVE_FAILED и восстановление того же состава. Условия приёмки не ослаблены; проверка скачивания усилена.
- Единственное изменение после стартового manifest gate 2 — native verifier, до его первого вызова. Фазовый manifest `artifacts/source-hashes-gate-2-native.json` фиксирует точный исполняемый файл; product/API/browser не менялись. Это исправленная ожидаемая семантика теста, не исправление runtime или сокрытие FAIL первого gate.

### 10.11.2026 — gate 2 PASS, самопроверка атомарности

- Полный run `683edf5faffe` завершён exit 0 за 675.0 s: browser a 81/81, b 81/81, всего 162, все native verifier PASS. Backend 1344/52384, peak 68.5 MiB; frontend UI 118/118, Commerce 253/253, zero skipped. Свежие desktop 1440×1000 и mobile 390×844 PNG просмотрены: исправленные счётчики, без перекрытий/горизонтального overflow, мобильный диалог прокручивается.
- Авторская самопроверка (не независимое review) обнаружила REPO-04/HTTP-06: операция и remember выполнялись последовательно под mutex, но без общей транзакции. При сбое INSERT ключа renewal мог увеличиться повторно, дополнение — вернуться NOTHING_TO_BUILD вместо исходного ответа. Успешный replay тест этого crash window не доказывал.
- До исправления актуализирован состав решения 1.1: immutable request VO передаётся в запись create/renew; только метаданные доставки и ключ атомарны. L1 сохраняет собственные транзакции; обёртка над ними не вводится (Bitrix nested rollback имеет особую семантику). Добавляется реальная ошибка INSERT ключа в disposable MySQL и восстановление/повтор; после runtime-изменений весь a+b запускается заново.

### 10.11.2026 — атомарность 1.1, адресные проверки PASS, gate 3

- `InstitutionArchiveRequest` передаётся из command в create/renew. Выпуск/поколение и request-key теперь коммитятся вместе, до освобождения mutex учреждения. При ошибке INSERT ключа транзакция откатывает только delivery; ранее созданные L1 source rows остаются доступны для безопасного повтора. Общий L1 repository и его транзакции не изменялись.
- Добавлена регрессия с failRemember: renewal сохраняет прежнее поколение/доступ; supplement не оставляет выпуск без ключа; тот же ключ после восстановления создаёт единственный результат и не дублирует L1. Native verifier вызывает настоящий UseCase с временным MySQL BEFORE INSERT SIGNAL, удаляет trigger finally и проверяет обе ветви с реальным rollback/replay.
- Исправлено оформление новых shape PHPDoc и named arguments сложных VO-конструкторов. Targeted CS Fixer 52 PHP-файла: PASS, изменено 5.
- Точная адресная Docker-команда предыдущего раздела с `--filter='InstitutionPrintArchiveTest|Production(Rules|Flow|Architecture)Test|PrintArchiveLayoutTest' --display-notices`: PASS 69 тестов/1735 assertions, 32 MiB, zero notices. Тот же изолированный Docker PHPStan: PASS, no errors.
- Gate 3 запускается той же полной командой a+b; лог `/tmp/rabit-institution-gate-3.log`. `artifacts/source-hashes-gate-3.json` фиксирует 66 собственных runtime/test файлов до старта. До завершения прогона runtime/test исходники не меняются.

### 10.11.2026 — дополнительные проверки во время gate 3

- Штатный functional-набор выполнен отдельно без сети/рабочей БД: `docker run --rm --network none --read-only --tmpfs /tmp:rw,mode=1777 --tmpfs /app/var:rw,mode=1777 --user 1000:1000 -v /home/user/rabit-api/.worktrees/ops-institution-print-archive/api:/app:ro -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local -d memory_limit=256M vendor/bin/phpunit --colors=never --do-not-cache-result --display-notices --testsuite=functional` — PASS 219/874, 22 MiB. Набор использует проектные Bitrix stubs и не заменяет реальные HTTP/MySQL проверки gate.
- Во время functional выданы два существующих Monolog TODO WARNING о кеше не-readonly `DtoClassMetadata`/`DtoParameterMetadata` без __clone. Файлы не затронуты; это наблюдение вне scope, не PHPUnit warning/failure. Исправление и внешняя issue не выполнялись.
- Повторная gh-проверка main — прежний SHA `7b9de6ed82b7786e4b5e957a3d44a004fcb92935`; merge-base/HEAD совпадают. Исходный checkout и чужие два untracked файла неизменны. `git diff --check` PASS.
- AST update после исправлений: 16 изменённых файлов. Один usages ошибочно запущен без XDG_CACHE_HOME и завершился read-only cache lock; повтор с `/tmp/rabit-institution-print-archive-ast` PASS, 21/21 usages нового request VO без усечения. Ни пустой результат, ни неуспешное чтение не считались доказательством.

### 10.11.2026 — финальная статика и замороженный диапазон gate 3

- Targeted CS Fixer dry-run: тот же Python-сбор собственного tracked+untracked PHP и Docker-prefix, аргументы `vendor/bin/php-cs-fixer fix --dry-run --diff --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=override <52 файла>` — PASS, 0/52 к исправлению. Массовые make fix/cs-fix не выполнялись.
- Manifest gate 3 повторно сравнен SHA-256 каждого из 66 файлов с диском: изменений нет. `ast.parse(Path('tools/run-browser-e2e.py').read_text())` — PASS. Plan/progress UTF-8/newline и no-index whitespace — PASS.
- Подготовлен `artifacts/self-check.md`: авторские Б (66 файлов в 3 частях, профили ID) и А (8 сквозных сценариев), независимость не заявляется. Старый комментарий приватного ArchiveLayout::folder следует уточнить после завершения замороженного прогона: только DOC-01, без изменения кода.
- Повторное адресное чтение требований подтвердило: исходные implementation-{prompt,stages}.md находятся в явно указанном `/home/user/rabit-api/docs/plans/OPS-telemos-20261107-prompt/artifacts/`, а не в base нового worktree. В плане уточнён абсолютный путь; требования не копировались и не менялись.

### 10.11.2026 — финальный gate 3 PASS и завершение авторской приёмки

- Точная команда: `python3 tools/run-browser-e2e.py run --groups a,b --kernel /home/user/rebit-p2p/api/public/bitrix --vendor /home/user/rabit-api/api/vendor --php-cli rabit-api-e2e-php-cli:local --php-fpm rabit-api-e2e-php-fpm:local --nginx rabit-api-e2e-nginx:local --mysql mysql:8.0 > /tmp/rabit-institution-gate-3.log 2>&1`. Exit 0; run `62559a39d294`, 830.4 s. Все шаги runner PASS, включая старые E5/Payment/Files/L1 и новый institution verifier.
- Backend: lint/PHPStan PASS, PHPUnit 1345 тестов/52413 assertions, peak 68.5 MiB. Frontend: lint/styles/typecheck/typecheck:e2e/build PASS, UI 118/118, Commerce 253/253, zero skipped. Browser: a81/81+b81/81=162, zero skipped/unexpected/flaky по обоим results.json. YooKassa test shop включён штатным gate; рабочие БД/платежи не использовались.
- Native `verify-institution-production.php` PASS, в том числе SHA-256 оригиналов/ZIP и CSV, L1/Commerce counts, реальный mutex и конкурирующие cron, missing-disk retries/expiry/recovery, временный MySQL trigger с отказом INSERT ключа для supplement и renewal. После удаления trigger тот же ключ даёт ровно один результат; L1 строки не дублируются, следующий full включает дополнение. Повтор up миграции и guarded down PASS.
- Финальные PNG run 3 просмотрены через view_image; desktop 1440×1000 и mobile 390×844 без перекрытий/overflow, текст/счётчики/действия читаемы. Скопированы только два тестовых снимка; сырые browser traces/credentials не добавлялись в docs.
- До завершения gate 66 исходников оставались строго неизменны (SHA-256 manifest). После gate закрыт DOC-01: два комментария ArchiveLayout теперь описывают любой каталог. PHP 8.4 `token_get_all(TOKEN_PARSE)` до/после с исключением T_COMMENT/T_DOC_COMMENT/T_WHITESPACE — PASS, hash исполняемых токенов `ce81947fb882c25b1a7ce45179bf84827370785c2e1fb4d45d4a86f4f5305300` совпал. Это не runtime-изменение, полный gate повторно не запускался.
- AST update после комментариев — PASS, 1 файл; финальный источник/отличие зафиксированы в `artifacts/source-hashes-final.json`. `artifacts/commands.json` содержит точные argv Docker/runner, включая 52 пути targeted CS dry-run. `verification.json`, gate/native/unit logs и self-check.md — итоговые доказательства.
- Ограничения: unit/functional используют проектные stubs, реальные HTTP/MySQL доказаны отдельным gate; исторические fixture-заказы скопированы из настоящего оплаченного J4 snapshot, это не повторная приёмка checkout; метаданные материализуются пропорционально числу строк, байты изображений идут потоково; восстановление требует доступных оригиналов. Ротация/этапы 6–8/PDF и новый физический учёт передачи не реализованы.
- Авторская самопроверка Б/А завершена, найденная атомарность исправлена и доказана. Независимое review не проводилось и остаётся отдельной свежей сессией. Commit/push/PR/merge/deployment не выполнялись.

### 10.11.2026 — финальная проверка артефактов

- CS Fixer dry-run после правки комментариев отдельно на ArchiveLayout — PASS, 0/1; до этого весь собственный PHP — 0/52. `token_get_all` одновременно проверил синтаксис TOKEN_PARSE.
- Финальный whitespace/check, JSON/manifest и набор собственных файлов проверяются ниже последней командой; итог сохранён перед завершением. Снимки относятся к финальному run, post-gate исполняемые изменения отсутствуют.

- Итог последней проверки: **PASS** — SHA-256 всех 66 runtime/test файлов совпал с final manifest; всего 81 собственный файл включая docs/artifacts; UTF-8/newline/JSON/PNG и whitespace без ошибок, staged diff пуст; все 14 стабильных ID имеют фактический PASS, guide anchors валидны. Независимое review остаётся отдельным шагом.

### 11.11.2026 — независимое review и поручение исправлений

- Пользователь поручил исправить review и провести E2E; работа только на чтение завершена, теперь разрешены реализация и изолированные проверки.
- Checkout/ветка/HEAD/dirty перепроверены; AGENTS/CLAUDE, текущие plan/progress и порядок a8 прочитаны. Применены rebit-os/rebit-architect/rebit-bitrix/rebit-quality-gate и ранее прочитанные карточки delivery/layers/storage/processes/http.
- До правки runtime сохранено решение 1.2: FIRST_READY_AT, насыщаемый счётчик lease и транзакционно сохраняемый OBSOLETE_PATH. Это исправления принятых инвариантов, без новых продуктовых возможностей.
- REV-01 PENDING; REV-02 PENDING; REV-03 PENDING. Все предыдущие PASS остаются историческими. Новый полный E2E пока не запускался.

### 11.11.2026 — исправления 1.2 и адресные регрессии

- REV-01: FIRST_READY_AT сохраняется первой ready-CAS записью, не сбрасывается renewal; guard больше не использует generation как признак публикации.
- REV-02: claim разрешает повторный служебный захват после исчерпания попыток, счётчик насыщается на 4; builder завершает его в failed без новой ZIP-сборки. Тест прерывает пять захватов и проверяет активную аренду, выход из due и последующее восстановление.
- REV-03: OBSOLETE_PATH записывается вместе с renewal+request, сохраняется при сбое очистки и повторном renewal; builder очищает прежний файл до сборки, ready-CAS сбрасывает obsoletePath. Unit покрывает отказ INSERT ключа, replay, три сбоя удаления, повтор renewal и восстановление без лишнего ZIP.
- Добавлены native MySQL регрессии REV-01/02/03 в существующий verifier полного gate; готовность по real DB до их исполнения не заявляется.
- `gh api repos/rebit-pro/rabit-api/git/ref/heads/main --jq .object.sha` и `gh pr view 238 --json state,mergeCommit,mergedAt` — прежний main/base, PR MERGED.
- Targeted CS Fixer через Docker-prefix из artifacts/commands.json, без --dry-run, только 9 перечисленных исправленных PHP — PASS, 0/9 изменений.
- Адресная команда artifacts/commands.json:targetedUnit (PHP 8.4.26, network none/read-only, 128 MiB, unit filter InstitutionPrintArchiveTest|Production(Rules|Flow|Architecture)Test|PrintArchiveLayoutTest) — PASS: 72 теста/1804 assertions, 32 MiB. Лог artifacts/review-fixes-unit.log. REV-01/02/03 unit PASS; native PENDING.
- AST rebuild активного worktree — 2734 файлов/27746 символов; после правок update 9 файлов, usages InstitutionArchive 52/52 без усечения. Нового конфига нет, код других деревьев не используется.
- `git diff --check` PASS. PHPStan запущен в таком же одноразовом PHP 8.4-контейнере без сети, результат пока PENDING. Полный E2E ещё не запускался.

- Первый PHPStan исправлений — FAIL: 6 замечаний в новом тесте к повторным find (анализатор считал чтение mutable fake чистым и сохранял прежнее narrowing). Find тестового репозитория явно помечен phpstan-impure по фактическому поведению; проверки и assertions не ослаблялись. Требуется повтор.

- Повтор PHPStan с impure-аннотацией — FAIL: impureMethod.pure (чтение fake без побочных эффектов не отвечает семантике этой аннотации). Аннотация убрана; в регрессии используется небольшой helper saved() с проверкой обязательного наличия записи, избыточные nullsafe/assertNotNull удалены. Третий PHPStan и повтор unit/CS dry-run выполняются до замораживания gate.

### 11.11.2026 — адресные проверки PASS, заморозка полного gate

- Повторный адресный unit после уточнения assertions — PASS: 72 теста/1800 assertions, 32 MiB. `artifacts/review-fixes-unit.log`.
- PHPStan — PASS, no errors; `artifacts/review-fixes-phpstan.log`. CS Fixer dry-run по 52 собственным PHP — PASS, 0/52; `artifacts/review-fixes-cs-check.log`. `git diff --check` PASS.
- Manifest `artifacts/source-hashes-review-fixes.json`: 66 runtime/test файлов; ровно 9 отличаются от проверенного review исходного manifest. Во время полного прогона runtime/test не меняются.
- Обычный sandbox-исполнитель временно не создавал процесс (Text file busy, os error 26), в том числе для pwd с /bin/sh. Разрешённый прямой exec pwd прошёл; это технический сбой launcher, не auto-review rejection. До следующей команды E2E не запускался.
- Запускается полный runner: `python3 tools/run-browser-e2e.py run --groups a,b --kernel /home/user/rebit-p2p/api/public/bitrix --vendor /home/user/rabit-api/api/vendor --php-cli rabit-api-e2e-php-cli:local --php-fpm rabit-api-e2e-php-fpm:local --nginx rabit-api-e2e-nginx:local --mysql mysql:8.0 > docs/plans/OPS-institution-print-archive/artifacts/review-fixes-gate.log 2>&1`. Реальные HTTP/Bitrix/MySQL/browser и native регрессии — пока PENDING.

### 11.11.2026 — полный gate 56eab0e1d064 в работе

- Все backend/frontend подготовительные проверки runner PASS: PHP lint/PHPStan/PHPUnit, npm lint/styles/typecheck/typecheck:e2e/test:ui/test:commerce/build-only. Реальные миграции/учётки двух БД выполняются; браузерные/native результаты ещё PENDING.
- State: `api/var/e2e/rabit-e2e-56eab0e1d064/state.json`; лог artifacts/review-fixes-gate.log. Повторная проверка 66 замороженных SHA-256 — совпадают.
- Дополнительный rg из прямого exec тоже получил Text file busy у бинарника codex-path/rg; это не пустой поиск. Связи уже подтверждены AST 52/52 и адресными исходниками, для дальнейшего обхода доступен Python. AST update после финальных правок PASS.

### 11.11.2026 — исправления review: полный gate PASS и завершение

- Полный runner a+b из предыдущего раздела завершился exit 0: run `56eab0e1d064`, 662.9 s. Все стадии PASS. Browser a81/81+b81/81=162, zero skipped/unexpected/flaky в обоих results.json. Stateful smoke/native и реальные HTTP/Bitrix/MySQL проверки пройдены; YooKassa test shop включён штатным gate, рабочие данные не использовались.
- Backend: PHP lint/PHPStan PASS; штатный PHPUnit (все suites phpunit.xml) 1348 тестов/52478 assertions. Frontend: lint/styles/typecheck/typecheck:e2e/build PASS, UI 118/118, Commerce 253/253, zero skipped. Адресный unit отдельно 72/1800 под 128 MiB; CS dry-run 0/52.
- REV-01 PASS — 11.11.2026, targetedUnit из commands.json + полный gate/native verifier: после COMPOSITION_CHANGED повторный renewal неопубликованного выпуска не даёт ready/download, закрытие нового состава создаёт корректный full, прежний готовый ZIP остаётся доступен. `review-fixes-native.log`: REV-01 unpublished scope after renewal passed.
- REV-02 PASS — 11.11.2026, тот же targetedUnit + полный gate/native MySQL: пять прерванных захватов, активная аренда защищена; счётчик 4, штатный runner ставит failed, запись выходит из due, renewal/rebuild успешно восстанавливает. `review-fixes-native.log`: REV-02 interrupted exhausted lease passed.
- REV-03 PASS — 11.11.2026, targetedUnit + полный gate/native MySQL: renewal до cron сохраняет старый путь; unit также проверяет rollback ключа, replay и три сбоя удаления. После успешного восстановления старый ZIP удалён, obsoletePath очищен, FIRST_READY_AT и CSV сохранены. `review-fixes-native.log`: REV-03 renewal before expiry cleanup passed.
- Финальные PNG текущего run сохранены как review-fixes-institution-desktop.png (1440×1000) и review-fixes-institution-mobile.png (390×844), оба просмотрены через view_image: текст/действия читаемы, наложений и горизонтального overflow нет. UI в исправлениях не менялся.
- Повторяемая миграция и guarded down прошли в native verifier. Метаданные FIRST_READY_AT/OBSOLETE_PATH не попадают в API; контракты владельцев, правила L1 и формат этапа 4 сохранены.
- Авторская самопроверка изменённых 9 файлов по delivery/layers/storage/processes/http: состав до первой публикации, owner/CAS/lease, атомарность renewal+ключа, сохранение пути и повтор очистки, восстановление/CSV, зависимости и источник данных сопоставлены. Найденных незакрытых замечаний в исправлениях нет; это не повторное независимое review.
- Хеши всех 66 runtime/test файлов строго совпадают с pre-run manifest; post-gate изменений исходников нет. Старые proof-артефакты сохранены отдельно, verification.json и final manifest указывают текущий run. Backend-лог скопирован с редактированием диагностических WARNING, сырые traces/credentials не добавлялись.
- Runner остановил изолированные ресурсы (`state.stopped=true`); commit/push/PR/merge/deployment не выполнялись. Ограничения прежние: retained originals для rebuild, материализация метаданных пропорциональна строкам/истории, будущая ротация отдельно.
- Финальная проверка перед ответом — PASS: 92 собственных файла, все 66 runtime/test файлов полностью покрыты final manifest и совпадают по SHA-256; JSON/UTF-8/newline/PNG dimensions валидны, whitespace без ошибок, staged diff пуст. AST update: 0 изменений, индекс актуален. Поручение исправлений и E2E завершено.

### 11.11.2026 — поручение merge в main, подготовка коммита

- Пользователь прямо поручил «Делай мерж в main»; разрешены необходимые commit/push/PR/merge. Deployment отдельный и не выполняется.
- Worktree/ветка/HEAD и весь dirty diff перепроверены. GitHub только через настроенный gh: PR ветки отсутствует, main по API по-прежнему 7b9de6ed82b7786e4b5e957a3d44a004fcb92935.
- Предкоммитный quality gate: 92 собственных файла (около 1.12 MB), 66 runtime/test хешей совпадают с полным E2E 56eab0e1d064, никакого post-gate runtime diff. `git diff --check` PASS, staged пуст до явного добавления. Backend/frontend/E2E повторно не запускаются при неизменных base/исходниках.
- По rebit-code-review и code-review.md OS повторное независимое review исправлений поручается свежему субагенту без истории реализации; он работает на чтение, без E2E/стендов/публикаций. Его вывод будет проверен до merge.
