# Прогресс OPS-print-archive-layout

## Точка продолжения

- Worktree/ветка: `/home/user/rabit-api/.worktrees/ops-print-archive-layout`, `codex/ops-print-archive-layout`; base/main перед merge `952f9789595e4d9e6a9c73b9b03c3deb1cd139dc`, PR #237 MERGED. Коммит реализации `24bccd22e16d2683428a9e57f1b51c2f62004903`; завершающий коммит журнала не меняет исходники.
- Завершено: R1 функционально подтверждён независимым вторым кругом; R2 исправлен в двух файлах Share, plan версия 3. Адресные 74/1344, полный PHPUnit 1331/51778, PHPStan/lint и dry-run 0/30 PASS.
- Полный gate a+b актуального diff: PASS 161/161, `api/var/e2e/rabit-e2e-519bbd19944e/state.json`; native ZIP/CSV/SHA-256/миграция/expiry/rebuild и desktop/mobile PASS. Первый run 830327f939b5 FAIL на G1-T14/G2-T14 сохранён, свежий повтор без изменения кода прошёл; первопричина первых сбоев не установлена.
- Сейчас: реализация закоммичена и опубликована через [PR #238](https://github.com/rebit-pro/rabit-api/pull/238). Merge прямо разрешён пользователем. Этот журнал фиксируется перед завершающим merge; его фактическое состояние и merge SHA сверять через `gh pr view 238 --json state,mergedAt,mergeCommit,headRefOid`. Один следующий шаг при возобновлении — подтвердить MERGED PR #238 перед использованием этапа 4 как поставщика. Ведомость этапа 6 вне scope.
- Полномочия: пользователь после результатов проверок поручил «Делай коммит и merge». Разрешены commit/push/создание PR/merge текущего этапа 4; deployment не выполняется. Отдельного независимого post-fix review R2 не было, это ограничение сохраняется в отчёте; новое поручение на merge принято с учётом уже сообщённых результатов. Оба исходных отчёта review в /tmp сохранены.
- Рабочее дерево: весь проверенный diff зафиксирован в 24bccd2. После публикации обновлены только plan/progress/verification для номера PR и точки восстановления; они входят в завершающий документарный коммит. Временного .ast-index.yaml нет; продуктовые исходники/артефакты после gate не менялись.
- Блокеров реализации/проверок нет. R2 post-fix independent review отдельно; реальная инъекция небезопасных имён в HTTP gate не выполнялась (эти ветви покрыты unit). Оба одноразовых runner stopped=true / cleanupErrors=[].
- Команды для следующей сверки: `git status --short --untracked-files=all`; `git diff --check`; Python SHA-256 по текущим sourceSha256/artifactSha256 verification.json. При новом коде повторить затронутые проверки.

Команды проверки для продолжения из этого worktree:

```bash
git status --short --branch
git rev-parse HEAD
git diff --check
git diff 952f9789595e4d9e6a9c73b9b03c3deb1cd139dc
git ls-files --others --exclude-standard
/home/user/.local/bin/gh api repos/rebit-pro/rabit-api/commits/main --jq .sha
/home/user/.local/bin/gh api repos/rebit-pro/rabit-api/commits/7f4a5f3d06c70f23a204941127fa4c4811ca9ed7/pulls --jq '.[] | {number,state,merged_at,merge_commit_sha}'
/home/user/.local/bin/gh api repos/rebit-pro/rabit-api/compare/7f4a5f3d06c70f23a204941127fa4c4811ca9ed7...main --jq '{status,ahead_by,behind_by,merge_base: .merge_base_commit.sha}'
```

При squash/rebase не ограничиваться ancestry: найти фактический merged PR и подтвердить поставщика в содержимом main. Без подтверждения не переносить соседний код и не начинать реализацию.

## 09.11.2026, Europe/Moscow — проверка зависимости

1. Исходный каталог `/home/user/rabit-api`: ветка `codex/ops-order-photo-delivery`, HEAD `b570d27419bba6b0169d3741966fd161c580cf3f`, untracked `docs/plans/OPS-stage-gallery-cleanup/`. Прочитаны его `AGENTS.md`/`CLAUDE.md` и требования этапа. Нужных ветки/worktree/плана этапа 4 не было.
2. Подключены канонические `rebit-os`, `rebit-architect`, `rebit-quality-gate`; доступны нормы ReBit Studio OS, правила слоёв/доставки, workflow плана, архитектура проекта и команды. Никакие проектные правила не изменены.
3. `gh repo view --json nameWithOwner,defaultBranchRef` подтвердил `rebit-pro/rabit-api`, ветку `main`. `gh api repos/rebit-pro/rabit-api/commits/main` вернул `90755f3a3700d55dae94befabea387ca8637c217`, последний merge — PR #236, правила агентов, 07.11.2026. Сеть sandbox недоступна; read-only запросы успешно выполнены с эскалацией.
4. `gh api repos/rebit-pro/rabit-api/commits/7f4a5f3d06c70f23a204941127fa4c4811ca9ed7/pulls --jq '.[] | {number,title,state,merged_at,merge_commit_sha,html_url}'` — пустой результат. `gh pr list --state all --head codex/ops-gallery-original-filenames ...` — `[]`. `gh pr list --state all --head codex/ops-print-archive-layout ...` — `[]`. Общий список последних PR не содержит этап 2; текущий main — merge PR #236.
5. `gh api repos/rebit-pro/rabit-api/compare/7f4a5f3d06c70f23a204941127fa4c4811ca9ed7...main --jq '{status,ahead_by,behind_by,merge_base: .merge_base_commit.sha}'` — `status=behind`, `ahead_by=0`, `behind_by=1`, merge-base равен самому main `90755f3a…`. Это не слитая зависимость. Локально `git merge-base 90755f3a3700d55dae94befabea387ca8637c217 7f4a5f3d06c70f23a204941127fa4c4811ca9ed7` дал тот же main, `git rev-list --left-right --count 90755f3a3700d55dae94befabea387ca8637c217...7f4a5f3d06c70f23a204941127fa4c4811ca9ed7` дал `0 1`; `git diff --name-status` подтверждает отдельные изменения этапа 2, включая поле имени в контрактном DTO Media. Никакие файлы поставщика не переносились.
6. Заявление о готовой зависимости расходится с GitHub; пользователь уведомлён до реализации. По явному правилу зависимостей AGENTS.md работа над PHP остановлена до merge поставщика. Это блокер зависимости, не запрос разрешения на запрещённый обход.
7. Запущенный `git fetch origin main` не завершился и был остановлен (exit 130). Свежесть main подтверждена непосредственно `gh`, SHA уже присутствовал локально и совпадал с `origin/main`; успешный fetch не заявляется.
8. `git worktree add -b codex/ops-print-archive-layout .worktrees/ops-print-archive-layout 90755f3a3700d55dae94befabea387ca8637c217` выполнен успешно с эскалацией записи Git metadata. Прочитаны именно `AGENTS.md`/`CLAUDE.md` нового дерева, проверены его ветка/HEAD и исходно чистый status. В нём созданы только `plan.md` и `progress.md` задачи.
9. Прочитаны Makefile, `api/composer.json`, `api/phpunit.xml`, `frontend/package.json`, `docs/architecture.md`, порядок E2E в `docs/waves/a8/README.md`. Состав команд учтён в плане; Docker/стенды не запускались. Проверка CLI в основном checkout: `/home/user/.local/bin/ast-index version` — `v3.56.0-php.4`. Индекс нового worktree не создавался и не читался: анализ кода отложен до разблокировки; свежесть/охват не заявляются.
10. Проверка документации — PASS: `git diff --check`, чтение обоих файлов в UTF-8 и `git diff --no-index --check /dev/null docs/plans/OPS-print-archive-layout/plan.md`, аналогично для `progress.md`; у новых файлов код 1 без диагностик означает наличие diff, whitespace-ошибок нет. Первоначальная shell-цепочка завершилась на этом штатном коде 1; затем оба файла проверены отдельно через Python с проверкой вывода/кода. `git status --short --untracked-files=all` подтвердил только два документа задачи. Повторная проверка основного checkout: прежняя ветка/HEAD и та же чужая untracked-папка без изменений. Выполнена самопроверка документов по scope и фактам зависимости; независимое review не проводилось. Продуктовые тесты, formatter и стенды неприменимы к текущим изменениям документов; к реализации вернуться после merge этапа 2.

### Повторная проверка после подтверждения пользователя

- `gh api repos/rebit-pro/rabit-api/commits/main --jq '{sha,message:.commit.message,date:.commit.committer.date}'` снова подтвердил `90755f3a3700d55dae94befabea387ca8637c217`. Повторный compare зависимости — `behind`, `ahead_by=0`, `behind_by=1`; запрос связанных PR и список по head этапа 2 пусты. Состояние зависимости не изменилось.
- Worktree этапа 2 `/home/user/rabit-api/.worktrees/ops-gallery-original-filenames` прочитан без изменений: ветка `codex/ops-gallery-original-filenames`, HEAD `7f4a5f3d06c70f23a204941127fa4c4811ca9ed7`, рабочее дерево чистое. Его `plan.md`/`progress.md` содержат результаты PHP/frontend gate, но реальные HTTP/E2E/visual отмечены PENDING; независимое review в журнале не подтверждено. Старые результаты не объявляются вновь выполненными проверками.
- Пользователю объяснено, что ручной merge с его стороны не обязателен; задан один вопрос о конкретном разрешении на создание PR и merge зависимости после обязательных проверок. Основание уточнения — исходное явное ограничение внешних действий. До ответа PR/merge не выполняются, PHP этапа 4 не меняется.

### Создание PR зависимости по отдельному разрешению

- Пользователь: «Разрешаю создать». Создан только PR этапа 2, без merge/deployment или новых commit/push.
- `gh pr create --repo rebit-pro/rabit-api --base main --head codex/ops-gallery-original-filenames --draft --title 'Галерея: исходные имена файлов и просмотр всех кадров без фильтра серий' --body-file /tmp/rabit-stage2-gallery-pr-body.md` — [PR #237](https://github.com/rebit-pro/rabit-api/pull/237). Описание по шаблону репозитория содержит фактические записанные проверки, их дату и незавершённые review/HTTP/E2E/visual; готовность к merge не заявлена.
- `gh pr view 237 --repo rebit-pro/rabit-api --json number,title,url,state,isDraft,headRefName,headRefOid,baseRefName,mergedAt` — OPEN, draft, head `7f4a5f3d06c70f23a204941127fa4c4811ca9ed7`, base main, `mergedAt=null`.
- На момент создания удалённые head/main совпадали с ранее проверенными SHA, код этапа 2 не менялся, `git diff --check 90755f3a3700d55dae94befabea387ca8637c217...7f4a5f3d06c70f23a204941127fa4c4811ca9ed7` — PASS. После публикации актуализированы только локальные plan/progress этапов 2 и 4; эти изменения не закоммичены и не отправлены. Этап 4 не разблокирован созданием draft PR.

### Разрешённое слияние зависимости

- Пользователь: «ПродолжайВыполни слияние. Этап два мы проверили полностью». Подтверждение полной проверки принято как сообщение пользователя; собственный новый прогон или независимое review в этой сессии не заявляются.
- Перед действием `gh pr view 237 ...` подтвердил тот же head `7f4a5f3d06c70f23a204941127fa4c4811ca9ed7`, base `90755f3a3700d55dae94befabea387ca8637c217`, состояние OPEN/DRAFT, MERGEABLE/CLEAN. GitHub Actions/checks и reviews в PR отсутствуют; репозиторий допускает merge commit. Выполняется перевод PR в ready и merge с фиксацией проверенного head; deployment не входит в поручение.

## Результаты тест-кейсов

Дата всех записей: 09.11.2026, Europe/Moscow. Планируемые продуктовые команды приведены в `plan.md`; фактического запуска пока нет.

| ID | Статус | Фактическая команда / доказательство |
| --- | --- | --- |
| DEP-2 | PASS | `gh api repos/rebit-pro/rabit-api/compare/7f4a5f3d06c70f23a204941127fa4c4811ca9ed7...main` — первоначально FAIL, затем MERGED PR #237; ancestry 7f4a5f3d → main 952f9789 подтверждено |
| T10-GROUP | PASS | 54 адресных PHP теста и реальный ZIP/CSV/имена/SHA-256 native verifier; HTTP L1 входит в 80/80 группы B. Только уровень группы, без ведомости этапа 6 |
| T11 | PASS | PrintArchiveLayoutTest: quantity × printCount, повторные строки, общий кадр двух детских кодов и разные товары; native MySQL сверяет оплаченные строки/суммы/ZIP |
| T12 | PASS | Бинарные SHA-256, коллизии регистра/NFC/папок/состава, опасные имена, длинные entry, пропавший оригинал и 160 MiB ZIP при peak PHP 30 MiB; metadata O(строк), исходные изображения не загружаются целиком |
| T13 | PASS | Unit: формат 1, сохранённые имена при повторе, дополнение без прежних строк и с неизменным SHA-256 первого ZIP; native MySQL: expiry/renew/rebuild, тот же состав и новые ссылки |
| T15 | PASS | ProductionFlowTest: organizer/own curator/foreign curator/teacher и границы CSV; real L1: серверные роли, публичная capability, неверные/отозванные ссылки; прямой X-Accel путь остаётся internal |
| DOC-4 | PASS | guide HTTP/anchors/overflow и desktop/mobile; реальные L1 desktop/mobile просмотрены; четыре снимка в artifacts/ |

T10 целиком и сверка с будущей ведомостью этапа 6 не закрыты. Реализация и авторская проверка завершены; автоматическая готовность к merge не заявляется.

### Merge зависимости и решение до PHP

- `gh pr ready 237` и `gh pr merge 237 --merge --match-head-commit 7f4a5f3d06c70f23a204941127fa4c4811ca9ed7` — PASS, MERGED 09.11.2026 19:36:08 UTC; merge/main `952f9789595e4d9e6a9c73b9b03c3deb1cd139dc`. Проверка: `gh pr view 237 --json state,mergedAt,mergeCommit,headRefOid,url`. Deployment не выполнялся. Полная проверка этапа 2 подтверждена пользователем, не выдана за собственный запуск.
- `git -c credential.helper= -c 'credential.helper=!/home/user/.local/bin/gh auth git-credential' fetch https://github.com/rebit-pro/rabit-api.git main:refs/remotes/origin/main` + `git merge --ff-only origin/main` — PASS; `git merge-base --is-ancestor 7f4a5f3d06c70f23a204941127fa4c4811ca9ed7 HEAD` — PASS. Незакоммиченные документы этапа 4 сохранены; соседние рабочие изменения не перенесены. DEP-2 теперь PASS.
- `ast-index update` с обычным кешем — BLOCKED read-only layout.lock. Поддерживаемый `XDG_CACHE_HOME=/tmp/rabit-print-archive-index` устранил ограничение без изменения прав. Временный .ast-index.yaml ограничивает сторонний код/кеши, `rebuild` и `stats` — PASS: 2685 файлов, 26190 символов, индекс только этого дерева. Class/usages/callers подтвердили текущие зависимости; unresolved вызов теста CSV открыт по исходнику. Перед следующим чтением после изменений выполнить update.
- Решение версии 1 внесено в plan до PHP: явный layoutVersion и полные entry в новой версии; отдельная проекция имён Media сохраняет обработку отсутствующих оригиналов и не затрагивает OriginalFiles/код PR #235. Небезопасные имена явно запрещают снимок; порядок папок, NFC/case folding и каталог `_фото/<id>` разрешают коллизии без переименования basename. ENTRY расширяется до 512, исторические записи получают формат 1.

### Первый адресный прогон

- Docker socket без эскалации недоступен; тот же безопасный read-only контейнер запущен с эскалацией. Команда: `docker run --rm --network none --read-only --tmpfs /tmp:rw --user 1000:1000 -v /home/user/rabit-api/.worktrees/ops-print-archive-layout/api:/app:ro -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local vendor/bin/phpunit --colors=never --do-not-cache-result --testsuite=unit --filter='Production(Rules|Flow|Architecture)Test'`. FAIL: 20 тестов, 864 assertions, 1 старое ожидание `A001_x3` в проверке CSV после истечения срока. Исправлено на путь нового формата; поведение срока/ссылки не менялось.
- Добавлены тесты исходных имён Media, детерминизма/коллизий/опасных имён и реального ZIP/CSV/SHA-256 с повтором новых и исторических версий. Большой тест строит 160 MiB ZIP под ограничением памяти, не загружая исходные файлы целиком. Результат следующего прогона ещё не получен.

### Количество, совместимость и реальные файлы

- Адресный Docker PHP 8.4 с `-d memory_limit=128M` и фильтром `Production(Rules|Flow|Architecture)Test|PrintArchiveLayoutTest|PhotoFilenamesTest`: PASS, 46 тестов / 983 assertions, peak 30 MiB. Реально построен ZIP >160 MiB, проверены SHA-256, дерево/CSV, коллизии, общий кадр, отсутствие файла, повтор и формат 1. БД/HTTP эта проверка не доказывает.
- `docker run` с теми же read-only mounts, `/app/var/phpstan` в tmpfs: `vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` — PASS, ошибок нет.
- При самопроверке CSV обнаружен конфликт буквального пути и защиты Excel для папок, начинающихся с формулы. План уточнён до правки: такие папки получают безопасный префикс; отдельная колонка basename исключается как избыточная (оригинальное имя уже в полном пути), чтобы Excel-экранирование не меняло техническое поле имени. Путь ZIP/CSV должен оставаться побайтно одинаковым.

### Подготовка проверки HTTP/MySQL

- Добавлен независимый от доступности оригинала контракт Media и DI; публичные HTTP-маршруты не менялись. Инструкция организатора дополнена путём через «Ссылки и сроки → Печать», правилами Nшт, CSV, коллизий, сроков и дополнений. Для новых ошибок снимка добавлены сообщения в существующий TS mapper и assertions текущего UI unit; компоненты/props/state не менялись.
- `api/tools/e2e/verify-production.php` дополнен проверкой MySQL snapshot → ZIP → CSV, исходных имён/SHA-256, следов всех строк, версии формата и replay/запрета опасного down миграции. Это подготовленные проверки, не результат запуска.
- Точечный formatter: Python формирует собственные tracked+untracked PHP пути (24 файла) и запускает Docker PHP CS Fixer `fix --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=override <файлы>`. PASS, исправлены только 3 из 24 файлов; остальные не затронуты. Позже добавлен адресный кейс коллизии корневого `состав.csv`: проверяется до финального gate.
- Для авторской проверки реального сценария выбран существующий `tools/run-browser-e2e.py run --groups b`: группа B содержит все зависимости L1/J4, один изолированный MySQL и HTTP/браузер. Это часть gate, не полный a+b; независимое review этим не заменяется. Ядро читается только как внешний ресурс `/home/user/rebit-p2p/api/public/bitrix`, код проекта берётся исключительно из активного worktree, vendor `/home/user/rabit-api/api/vendor`. Локальных образов достаточно.

### Gate на изолированном стенде, промежуточный результат

- `python3 tools/run-browser-e2e.py run --groups b --kernel /home/user/rebit-p2p/api/public/bitrix --vendor /home/user/rabit-api/api/vendor --php-cli rabit-api-e2e-php-cli:local --php-fpm rabit-api-e2e-php-fpm:local --nginx rabit-api-e2e-nginx:local --mysql mysql:8.0` запущен с разрешённым Docker-доступом; state `api/var/e2e/rabit-e2e-acc28e7034e3/state.json`.
- PASS подготовительных этапов: npm ci, frontend lint/lint:styles/typecheck/typecheck:e2e/UI/commerce/build, PHP lint/PHPStan/полный PHPUnit, WebP capability, настоящее ядро/миграции Bitrix и fixture MySQL. Браузерная группа B ещё выполняется, E2E PASS пока не заявляется.
- После подготовительного gate уточнён только русский PHPDoc VersionSnapshot и усилены тестовые assertions дополнения/длинного имени и native проверки широкой ENTRY с rollback транзакции. Runtime-поведение не изменялось; новые assertions требуют отдельного адресного PHPUnit/lint перед завершением. Native verifier прочитает текущий файл из /app после браузера.

### Первый HTTP/E2E: FAIL и исправление подготовки схемы

- Группа B первого прогона `rabit-e2e-acc28e7034e3`: 76 PASS, 3 FAIL, 1 не запускался. Все три падения связаны с 503 production/groups или group: cabinet-tables, L1 и зависимая выдача G2. FPM лог показывает ProductionStorageException в запросе репозитория; `api/tools/e2e/prepare.php` явно перечисляет миграции и не содержал новую LAYOUT_VERSION. Ошибка подготовки внесена этой задачей; это не чужой дефект.
- План расширен файлом prepare.php до правки, миграция добавлена в список до запуска API. В native verifier исправлен путь require на /app (runtime не содержит php_interface) и включён sprint.migration для replay. Ни production БД, ни условия самой приёмки не меняются. Повторить ту же группу B на новой БД.
- Дополнительный запуск native verifier на первом стенде BLOCKED: после неуспешной браузерной группы runner уже удалил стенд; запись l1-archive.json не была создана. Фактический native PASS не заявлялся.
- Адресный итоговый PHP unit после усиления assertions: PASS, 52 теста / 999 assertions, peak 30 MiB; CS Fixer dry-run — 0/24; синтаксис native verifier PASS. Полный PHPUnit подготовительного gate: 1322 теста / 51633 assertions; frontend test:ui 113 PASS, test:commerce 253 PASS, остальные lint/types/build PASS. Новые изменения prepare/verifier требуют повторного gate.
- Guide HTTP/anchors/no horizontal overflow и screenshots desktop 1440/mobile 390 — PASS. Команда: `/tmp/ops-print-guide-visual.mjs` через Docker Playwright с node volume и browserNetwork первого одноразового стенда; снимки `artifacts/guide-print-desktop.png`, `artifacts/guide-print-mobile.png` просмотрены.

### Второй gate: статическое замечание в усиленном тесте

- Второй запуск группы B `rabit-e2e-cfd36809de28` остановился до HTTP: PHPStan выявил always-true assertNotNull в новом assertion дополнения. Предыдущая проверка nullsafe status уже сузила тип повторного pure-чтения. Исправлено одним чтением ArchiveVersion до assertions; runtime-код не затронут этим исправлением. Прочие отменённые шаги не отмечаются PASS.
- Самопроверка длинных имён добавила реальный крайний случай: 80 четырёхбайтовых символов в генерируемой папке плюс суффикс ID превышают переносимый лимит компонента. План уточнён, только генерируемая папка теперь обрезается по границе UTF-8 с резервом ID; исходный basename не меняется. Добавлен unit этого инварианта.

### Проверка точной идентичности путей в SQL

- Унаследованная collation ENTRY — utf8mb4_unicode_ci. COUNT(DISTINCT ENTRY) мог бы объединять разные пути `e.JPG` и `é.JPG`, хотя ZIP содержит разные файлы. До правки уточнён состав решения; сводка репозитория и native проверка теперь используют BINARY ENTRY, не меняя collation/сортировку исторических строк. Добавлены unit разных accented имён и native проверка соответствующей семантики MySQL.
- Третий gate продолжается; изменения затрагивают только точный счётчик сводки и тесты, новые проверки будут выполнены до завершения.

### Финальный gate — PASS

- Третий запуск `rabit-e2e-2ac8ea0adbcc`, та же точная команда с `--groups b`: PASS. 80 реальных сценариев группы B, 0 skipped/unexpected/flaky. Все native verifiers, включая verify-production.php, прошли. Стенд использовал test shop ЮKassa; это тестовое окружение, не production. Полный a+b gate не запускался.
- Подготовительные backend/frontend проверки PASS: PHP lint, PHPStan, PHPUnit 1324 теста / 51653 assertions; frontend lint/styles/types/E2E-types/build, UI 113 и commerce 253 теста. Последний добавленный accent-case дополнительно прошёл в адресном финальном наборе.
- Финальная команда read-only Docker с PHP 8.4.26: CS Fixer `fix --dry-run --diff --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=override <25 собственных PHP файлов>` — 0/25; `-d memory_limit=128M vendor/bin/phpunit --colors=never --do-not-cache-result --testsuite=unit --filter='Production(Rules|Flow|Architecture)Test|PrintArchiveLayoutTest|PhotoFilenamesTest'` — 54 / 1008, peak 30 MiB; `vendor/bin/phpstan analyse --configuration=tools/e2e/phpstan.neon --no-progress` — PASS; `php -l tools/e2e/verify-production.php` — PASS. Mounts и изоляция те же, что в первом адресном прогоне; cache PHPStan в tmpfs.
- `b/verify-production.log`: новый layout=2, исходные basename, SHA-256 каждого оригинала/записи ZIP, полный путь и все orderLineId в CSV, буквальный CSV export равен манифесту, миграция replay дважды, ENTRY >255 без усечения с rollback тестовой транзакции, отказ destructive down, истечение и повторная сборка — PASS. Копия без токенов/ключей в `artifacts/native-layout.log`.
- Четыре screenshots в artifacts/ открыты через view_image: архив и инструкция читаемы на 1440×1000 и 390×844, кнопки доступны, горизонтального скролла нет. UI не перерабатывался. `artifacts/verification.json` фиксирует результаты, ограничения и SHA-256 изменённых исходников/артефактов.
- Повторная проверка через gh: main остаётся 952f9789595e4d9e6a9c73b9b03c3deb1cd139dc, #237 MERGED. Docker ps/volume ls по уникальному префиксу успешного runner пусты — одноразовые ресурсы удалены. Рабочие БД/сервисы и deployment не затронуты.
- Plan/progress обновлены, временный профиль AST убран из diff и сохранён в /tmp. В соседнем worktree этапа 2 локальные plan/progress дополнены фактом merge без нового commit/push. Исходный основной checkout не менялся.

Пример дерева фактической интеграционной сборки:

```text
D3 Печать 85e47c1d/e4.jpg
E4 Печать 10×15/e4.jpg
E4 Печать 10×15/3шт/e4.jpg
состав.csv
```

Два e4.jpg одной продукции здесь относятся к разным photoId с разными тиражами; одно задание не продублировано в корне и Nшт. Идентичность/байты и полная трассировка проверены native verifier. Общий photoId разных детских назначений отдельно проверен unit и ZIP-интеграцией.

### Авторская самопроверка: Б, затем А

Base/HEAD/merge-base: `952f9789595e4d9e6a9c73b9b03c3deb1cd139dc`; проверен собственный dirty diff и все новые файлы из sourceSha256, а не незакоммиченные изменения соседних задач. 28 файлов исходников, делить диапазон по лимиту 40 не требуется. Модель: Codex; точный вариант и reasoning в рантайме не предоставлены. Это самопроверка автора, без независимого оппонента/публикации review.

Сокращения таблицы Б раскрывают ID карточек (перечитаны по действующей OS):

- D: CHANGE-01/02/03, STYLE-01/02, TEST-01/02/03. У исходников проверены контекст/diff/стиль/типы/покрытие и фактические результаты.
- L: LAYER-01/02/03/04, REUSE-01/02, DTO-01/02/03, DOC-01/02, EXC-01/02. Проверены обязанности, владельцы/типы/совместимость, использование новых полей, содержательные PHPDoc и отказ.
- I: DI-01/02/03/04. Ключ интерфейса PhotoFilenames использует подтверждённый по реальному ServiceLocator autowiring; специальная сборка VersionSnapshot — concrete constructorParams. Stateless singleton, без request/user state.
- S: REPO-01/02/03/04/05, PERF-01/02. Result/скаляры, owner SQL/экранирование/батчи, affected rows/атомарность/append-only, точное BINARY сравнение путей; metadata O(строк), файл стримится.
- M: MIG-01/02. Только активный каталог, replay/совместимость v1, защита down; миграция до кода, после v2 старый builder/CSV назад не включать.
- W: LOCK-01/02, CLI-01, PROC-01. Существующие claim/lease/unique keys/retry сохранены, scope сотрудника и системный запуск не расширены.
- H: HTTP-01/02/03/04/05/06, CMP-02/03 — применимость проверена по прежней входной обвязке и новым кодам ошибок/CSV. Новых controller/request/route нет; сериализация и доступ прежние.
- F: FE-01/02/03/04/05/06/07/08 — проверены TS error mapper, экранированный статический текст, anchors, доступность/адаптив и результаты. Props/state/SSR/стили не менялись; эти подчасти FE-03/04/07 неприменимы.
- Общие неприменимые ID: CACHE-01/02/03 — кеш не изменён; EVT-01/02 и MSG-01/02/03 — нет событий/сообщений; CMP-01 — нет Bitrix компонента; NOTE-01 — внешние замечания не публикуются.

Таблица: «ок» означает применённые карточки набора; «—» фиксирует остальные сценарии. Пути Production/Media/Share — соответствующие модули внутри `api/public/local/modules/`.

| Файл | Ок | — и причина |
| --- | --- | --- |
| `Media/di/media.php` | D, L, I | S/M/H/F/W — нет SQL, миграции, HTTP и выполнения сценария |
| `Media/lib/Application/Photo/Service/PhotoFilenames.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Media/lib/Domain/Photo/Repository/PhotoRepository.php` | D, L, S | I/M/H/F/W — не DI/миграция/входная обвязка/worker |
| `Media/tests/Unit/PhotoFilenamesTest.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/di/archive.php` | D, L, I | S/M/H/F/W — нет SQL, миграции, HTTP и выполнения сценария |
| `Production/lib/Application/Archive/Contract/ManifestWriterInterface.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/lib/Application/Archive/Service/ArchiveBuilder.php` | D, L, W | I/S/M/F — без DI-конфига/SQL/миграции/UI; HTTP-контроллер не меняется |
| `Production/lib/Application/Archive/Service/VersionSnapshot.php` | D, L, W | I/S/M/F — без DI-конфига/SQL/миграции/UI; HTTP-контроллер не меняется |
| `Production/lib/Application/Archive/UseCase/ExportProductionVersionUseCase.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/lib/Domain/Archive/Exception/ArchiveLayoutException.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/lib/Domain/Archive/Service/ArchiveLayout.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/lib/Domain/Archive/ValueObject/ArchiveLine.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/lib/Domain/Archive/ValueObject/ArchiveVersion.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/lib/Domain/Archive/ValueObject/NewVersion.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/lib/Infrastructure/Database/BitrixArchiveRepository.php` | D, L, S | I/M/H/F/W — не DI/миграция/входная обвязка/worker |
| `Production/lib/Infrastructure/File/CsvManifestWriter.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/tests/Unit/PrintArchiveLayoutTest.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/tests/Unit/ProductionFlowTest.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/tests/Unit/ProductionRulesTest.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/tests/Unit/Support/InMemoryArchives.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Production/tests/Unit/Support/ProductionFixture.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `Share/lib/Contracts/Media/PhotoFilenamesInterface.php` | D, L | I/S/M/H/F/W — нет DI-конфига, SQL, миграции, HTTP/UI и новых фоновых переходов |
| `api/public/local/php_interface/migrations.foundation/Version20261109200001.php` | D, L, S, M | I/H/F/W — нет DI, HTTP, UI и фоновой обработки |
| `api/tools/e2e/prepare.php` | D, L, S, W | I/M/H/F — не production DI/миграция/HTTP/интерфейс; SQL только в изолированном test runtime |
| `api/tools/e2e/verify-production.php` | D, L, S, W | I/M/H/F — не production DI/миграция/HTTP/интерфейс; SQL только в изолированном test runtime |
| `frontend/public/guide/organizer/index.html` | D, F, H | L/I/S/M/W — нет backend слоёв, DI, хранения и фоновой обработки |
| `frontend/src/modules/morefoto/production/live/archive-rules.ts` | D, F, H | L/I/S/M/W — нет backend слоёв, DI, хранения и фоновой обработки |
| `frontend/tests/ui/print-archive.test.mjs` | D, F, H | L/I/S/M/W — нет backend слоёв, DI, хранения и фоновой обработки |
| `docs/plans/OPS-print-archive-layout/plan.md` | CHANGE-01/02/03, TEST-03 | STYLE/DTO/SQL/HTTP/DI/MIG — документ решения; команды отделены от факта |
| `docs/plans/OPS-print-archive-layout/progress.md` | CHANGE-01/02/03, TEST-03 | STYLE/DTO/SQL/HTTP/DI/MIG — журнал; предыдущие FAIL сохранены |
| `artifacts/verification.json`, `native-layout.log`, четыре PNG | CHANGE-01/02/03, TEST-03, FE-08 | Исполняемых методов нет; источники/версии/хеши и отсутствие секретов просмотрены |
| Диапазон целиком | CHANGE-01/02/03, TEST-01/02/03, MIG-01/02 | NOTE-01 — нет внешнего review; независимость не заявляется |

Проход А: Commerce paid rows → Media filenames → чистая группировка Domain → транзакционный snapshot/layoutVersion → claim → OriginalFiles → атомарный ZIP и CSV → прежние access/capability/retention. Прослежены количество и общий кадр, разные товары одного формата, коллизии и источник имени, отсутствие оригинала, новый/старый формат, дополнение, конкурентные уникальные ключи и повтор. Контроллеры, маршруты, цены и цифровые права не изменялись. У нового контракта один реальный потребитель и отдельная обязанность: имя доступно независимо от оригинала; существующий Gallery DTO требует детских назначений, которых нет в печатном контракте.

По review-opposition повторно проверены исправления: пропуск миграции в prepare устранён и подтверждён новой БД/80 E2E; always-true assert устранён; SQL collation не объединяет разные пути, native BINARY case проверен. Открытых блокирующих находок в собственном diff не осталось. Независимое review и полный gate a+b этой записью не заменяются.

Наследуемое ограничение вне нового формата: по коду v1 CSV по-прежнему вычисляет расширение через OriginalFiles и использует прежний fallback `.jpg`, если метаданные оригинала уже недоступны. Для исторического PNG это может отличаться от ранее выданного ZIP; существующие снимки v1 не переписывались. В v2 расширение сохранено в entry, и CSV не зависит от наличия оригинала. Отдельное исправление истории не входит в этап 4.

### Финальная фиксация

- Итоговые действия: опубликован и слит только разрешённый PR этапа 2 #237. Для этапа 4 commit/push/PR/merge/deployment не выполнялись; все изменения оставлены в порученной ветке/worktree.
- После успешного gate изменено только оформление локального PHPDoc формы строки в новом PhotoFilenames по правилу многострочных shapes; runtime-код не менялся. Точечный CS Fixer проверяет этот файл, sourceSha256 обновляется. Дополнительного E2E ради комментария не требуется.

- Финальные проверки документов/артефактов: PASS — все 28 SHA-256 исходников и 5 SHA-256 артефактов совпали с verification.json; plan/progress/JSON/native log читаются как UTF-8, `git diff --no-index --check /dev/null <документ>` без замечаний. `git diff --check` — PASS; `git diff --cached --quiet` — PASS, staging пуст. CS Fixer по последнему PHPDoc — PASS 0/1.

## 10.11.2026, Europe/Moscow — возобновление готовой реализации

- Исходный checkout: `/home/user/rabit-api`, `codex/ops-order-photo-delivery`, HEAD `b570d27419bba6b0169d3741966fd161c580cf3f`, прежняя чужая untracked-папка `OPS-stage-gallery-cleanup`. Нужный worktree найден; в нём `codex/ops-print-archive-layout`, HEAD `952f9789595e4d9e6a9c73b9b03c3deb1cd139dc`, существующий собственный dirty diff. Прочитаны AGENTS.md/CLAUDE.md, plan/progress и исходные требования; AGENTS.md совпадает с основным checkout, вложенных правил не найдено. Чужие изменения не затрагиваются.
- Прочитаны канонические rebit-os/rebit-architect/rebit-quality-gate, нормы архитектуры, runtime, workflow, AST, delivery/layers/storage/processes/frontend/http и quality gate. Состав решения и правила имён/коллизий сохранены в плане до дальнейших правок.
- DEP-2 — PASS: `gh api repos/rebit-pro/rabit-api/commits/main --jq '{sha,message:.commit.message}'`, `gh pr view 237 --json state,mergedAt,mergeCommit,headRefOid,url`, compare `7f4a5f3d...main` — main `952f9789`, PR #237 MERGED, ahead_by=1/behind_by=0, merge-base `7f4a5f3d`. Список PR по head этапа 4 пуст. Read-only запросы выполнены с эскалацией сети после sandbox socket denial.
- Проверка Python SHA-256 по `artifacts/verification.json` — PASS: 28/28 исходников и 5/5 артефактов совпали. Каталог сохранённого реального E2E `api/var/e2e/rabit-e2e-2ac8ea0adbcc` существует. Это подтверждение сохранённых результатов, не новый запуск E2E.
- CLI AST по-прежнему `v3.56.0-php.4`, но временный профиль `/tmp/rabit-print-archive-ast.yaml` из предыдущей сессии отсутствует. Перед новым индексным чтением профиль будет восстановлен для этого дерева и выполнен rebuild; старый индекс не считается свежим.
- Повторные адресные проверки 10.11.2026 пока PENDING; результаты 09.11.2026 не переименовываются в новые прогоны. Commit/push/PR/merge/deployment этапа 4 не разрешены и не выполняются.

### Повторные проверки 10.11.2026 — PASS

- Индекс восстановлен для активного worktree: `XDG_CACHE_HOME=/tmp/rabit-print-archive-index /home/user/.local/bin/ast-index rebuild`, затем `stats` — PASS, 2529 файлов / 25078 символов. Охват исключает сторонний код, runtime и соседние worktree; отличается от старого временного профиля и не считается его продолжением. `class ArchiveLayout`, `outline` его файла, `usages 'Rebit\Share\Contracts\Media\PhotoFilenamesInterface'` (12 употреблений) и `callers 'Morefoto\Production\Application\Archive\Contract\ManifestWriterInterface::csv'` подтвердили реальные регистрации/потребителей. Два unresolved вызова `new CsvManifestWriter()->csv` прочитаны в unit-исходниках. Временный профиль сохранён в `/tmp/rabit-print-archive-ast.yaml` и убран из diff. Для будущего индексного чтения восстановить профиль и выполнить rebuild.
- Первый Docker unit не стартовал из-за отсутствующей точки монтирования `api/public/bitrix`; создан только пустой каталог. Второй запуск остановился на entrypoint, пытавшемся chown read-only logs. Исправлена только команда проверки (`--entrypoint php`); исходники, рабочие логи и данные не изменялись. Оба запуска не считаются выполненными тестами.
- Итоговые Docker-команды ниже выполнены из worktree. Сеть отключена, исходники/vendor/ядро read-only, запись только в tmpfs; база данных не подключалась. Образ PHP 8.4.26, PHPUnit 12.5.14.

```bash
php_check() {
  docker run --rm --network none --read-only --entrypoint php \
    --tmpfs /tmp:rw,size=256m --tmpfs /app/var:rw,size=256m \
    -v /home/user/rabit-api/.worktrees/ops-print-archive-layout/api:/app:ro \
    -v /home/user/rabit-api/api/vendor:/app/vendor:ro \
    -v /home/user/rebit-p2p/api/public/bitrix:/app/public/bitrix:ro \
    -w /app rabit-api-e2e-php-cli:local "$@"
}
php_check -d memory_limit=128M vendor/bin/phpunit --colors=never --do-not-cache-result --testsuite=unit '--filter=Production(Rules|Flow|Architecture)Test|PrintArchiveLayoutTest|PhotoFilenamesTest'
php_check vendor/bin/phpstan analyse --configuration=tools/e2e/phpstan.neon --no-progress
php_check vendor/bin/phplint
```

- Unit — PASS: 54 теста / 1008 assertions, 30 MiB peak при лимите 128 MiB. Включены настоящее дерево ZIP/CSV, побайтные basename и SHA-256, 160 MiB ZIP, общий кадр нескольких детей, printCount, коллизии и опасные имена, отсутствующий оригинал, дополнение, старые версии, повтор, сроки/права и architecture.
- PHPStan — PASS, No errors. Lint — PASS, 1714 файлов. Отдельный синтаксис миграции — PASS: `docker run --rm --network none --read-only --entrypoint php --tmpfs /tmp:rw,size=256m -v /home/user/rabit-api/.worktrees/ops-print-archive-layout/api:/app:ro -w /app rabit-api-e2e-php-cli:local -l public/local/php_interface/migrations.foundation/Version20261109200001.php`.
- Formatter — PASS, 0/25, без записи. Список сформирован из объединения `git diff --name-only -z` и `git ls-files --others --exclude-standard -z`, только `api/**/*.php`, после удаления префикса `api/`, сортировка уникальных путей; совпал с 25 PHP-файлами из `sourceSha256`. Запущен тот же Docker с `--entrypoint php`, аргументы: `vendor/bin/php-cs-fixer fix --dry-run --diff --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=override` и каждый из этих 25 путей отдельным argv.
- Frontend — PASS: из `frontend/` команда `node --experimental-strip-types --test tests/ui/print-archive.test.mjs`, exit 0, Node 24.15.0. Это адресный unit существующих действий/сообщений; полный frontend gate повторно не запускался, поскольку исходники не менялись.

| ID | Дата | Повторный результат / доказательство |
| --- | --- | --- |
| T10-GROUP | 10.11.2026 | PASS — адресные PHP ZIP/CSV/имена/SHA-256; снимок и отчёт реального HTTP 09.11 совпадают с текущими исходниками |
| T11 | 10.11.2026 | PASS — unit сумм quantity × printCount, общих фото и разных товаров |
| T12 | 10.11.2026 | PASS — unit коллизий/опасных имён, отсутствующего оригинала, 160 MiB ZIP с лимитом памяти и повторов |
| T13 | 10.11.2026 | PASS — unit v1/v2, дополнения без прежних строк, неизменного первого ZIP и frozen paths при повторе |
| T15 | 10.11.2026 | PASS — ProductionFlowTest/ArchitectureTest; сохранённый реальный HTTP группы B подтверждён отдельно, новый HTTP не запускался |
| DOC-4 | 10.11.2026 | PASS — исходник инструкции и четыре сохранённых desktop/mobile PNG просмотрены; новый браузерный/visual прогон не запускался |

- Подтверждены реальные артефакты 09.11: `api/var/e2e/rabit-e2e-2ac8ea0adbcc/state.json` указывает этот worktree, scope `PARTIAL run, not a full gate`, results b=80, stopped=true, cleanupErrors=[]; `b/b/results.json` — expected=80, skipped=0, unexpected=0, flaky=0. Прочитаны native-layout.log и сохранённые логи backend/frontend gate. Все 28 хешей исходников и 5 хешей артефактов совпали; сохранённые результаты не выданы за новый прогон. Миграция по-прежнему применялась только в одноразовой тестовой БД.
- Повторная авторская самопроверка Б по всем 28 исходникам/документам и А по цепочке Commerce → Media → Domain layout → snapshot/DB → ZIP/CSV → права/сроки выполнена по актуальным карточкам; таблица Б и перечень ID выше остаются применимыми, runtime diff не менялся. Новых блокирующих находок нет; это не независимое review. Сохранено известное ограничение исторического v1 CSV при утрате метаданных PNG (описано выше); v2 от него не зависит. Нового scope ради исправления истории не добавлено.
- Незавершённые проверки: полный gate a+b перед merge — PENDING; независимое review — PENDING; сверка с ведомостью этапа 6 — вне scope. Это не блокирует завершение локальной реализации этапа 4, но готовность к merge не объявляется.
- Финальная сверка 10.11.2026 — PASS: `git diff --check`, `git diff --cached --quiet`; 28/28 sourceSha256 и 5/5 artifactSha256; документы UTF-8 и whitespace без ошибок. Объединение tracked/untracked изменений api/frontend точно равно sourceSha256; лишних исходников нет. Исходный checkout остался на `b570d274` с прежней чужой untracked-папкой. В порученном worktree сохранены собственные незакоммиченные изменения этапа 4 и актуальные plan/progress/verification; новых commit/push/PR/merge/deployment нет.

Воспроизведение того же списка formatter (после определения `php_check` выше):

```bash
mapfile -t changed_php < <(python3 -c 'import json; j=json.load(open("docs/plans/OPS-print-archive-layout/artifacts/verification.json")); print("\n".join(sorted(p.removeprefix("api/") for p in j["sourceSha256"] if p.startswith("api/") and p.endswith(".php"))))')
php_check vendor/bin/php-cs-fixer fix --dry-run --diff --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=override "${changed_php[@]}"
```

## 10.11.2026 — исправление HIGH R1 после независимого review

- Получено отдельное поручение исправить R1; прочитан `/tmp/rabit-print-archive-review-20261110-952f9789.md`. Worktree/ветка/HEAD совпали с поручением, существующий dirty diff сохранён. Все 28 исходных sourceSha256 совпали перед правками; копия прежнего verification в `/tmp/rabit-print-archive-before-r1.json`, tracked diff в `/tmp/rabit-print-archive-before-r1.patch`. Это локальные резервные артефакты, без изменения Git history/staging.
- R1 подтверждён исходниками: schedule вызывается до due/expired, новый отказ layout выходит наружу через HttpException. Обработка builder к этому моменту не начинается. Исключение ручного snapshot остаётся корректным, изменяется только автоматический планировщик.
- До кода обновлён plan версии 2: постоянный курсор удерживается перед первым layout-отказом; локальная keyset-позиция проходит следующие страницы, не теряя проблемную группу. Лимит относится к новым версиям/сборкам/удалениям, готовые snapshots не повторяются. Ошибка логируется по groupId/errorCode и входит в failed. Новых очередей/схемы/DI нет; цена решения — повторное чтение хвоста от первого блокера до исправления имени. Несвязанные ошибки источника не подавляются.
- AST: восстановлен временный профиль именно этого worktree; `XDG_CACHE_HOME=/tmp/rabit-print-archive-index ast-index rebuild` + stats — PASS, 2529 файлов / 25078 символов, CLI v3.56.0-php.4. class/outline/usages подтверждают UseCase, DTO и fixture; callers execute содержит нерелевантные unresolved receivers, поэтому команда/DI/test fixture прочитаны адресно через исходники/rg. После изменений перед новым индексным чтением нужен update; временный профиль будет удалён из итогового diff.
- T12-R1 и T12-R1-PAGE — PENDING. Предыдущий PASS T12 не покрывал межгрупповой отказ: независимое review нашло пробел; старые результаты не подтверждают исправление. E2E/visual и стенды не запускаются, полный gate a+b PENDING, этап 6 вне scope.

### R1: красная регрессия до исправления

- Добавлены T12-R1 (три входа: CON.jpg, пустое имя, отсутствующий ключ имени) и T12-R1-PAGE (limit=1). Fixture принимает необязательный logger только для проверки диагностического события.
- Команда: `docker run --rm --network none --read-only --entrypoint php --tmpfs /tmp:rw,size=256m --tmpfs /app/var:rw,size=256m -v /home/user/rabit-api/.worktrees/ops-print-archive-layout/api:/app:ro -v /home/user/rabit-api/api/vendor:/app/vendor:ro -v /home/user/rebit-p2p/api/public/bitrix:/app/public/bitrix:ro -w /app rabit-api-e2e-php-cli:local -d memory_limit=128M vendor/bin/phpunit --colors=never --do-not-cache-result --testsuite=unit '--filter=PrintArchiveLayoutTest::testRejectedGroup'`.
- Результат до исправления: FAIL — 4 теста / 12 assertions / 4 errors, exit 2. Все четыре завершаются UNSAFE_FILENAME/ORIGINAL_MISSING из VersionSnapshot через schedule/execute. Это ожидаемое воспроизведение R1, не успешная проверка. База/HTTP/стенды не запускались.

### R1: реализация и адресная проверка

- `RunProductionUseCase::schedule` изолирует только HttpException с причиной ArchiveLayoutException. Для проблемной группы пишет `Print archive snapshot rejected.` с groupId/errorCode, увеличивает failed и удерживает постоянный курсор; локальная позиция продолжает следующие страницы. Сохраняются firstOnly/уникальность версий и строк, явная ручная 409 и запрет неполного ZIP. Due/expired выполняются после планирования даже при отклонённых снимках.
- Лимит новых версий сохранён, отказавшие и уже сохранённые группы не расходуют его; уточнена CLI-подсказка и PHPDoc failed без изменения публичных сигнатур/DTO. Новых таблиц/DI/очередей/миграций нет. Добавлено ровно 3 ранее неизменённых исходника (RunProductionUseCase, ProductionRunOutputDto, RunProductionCommand); из прежнего diff изменены только PrintArchiveLayoutTest и ProductionFixture.
- Команда зелёного набора: тот же read-only Docker, PHP 8.4.26 / PHPUnit 12.5.14, `-d memory_limit=128M vendor/bin/phpunit --colors=never --do-not-cache-result --testsuite=unit '--filter=Production(Rules|Flow|Architecture)Test|PrintArchiveLayoutTest|PhotoFilenamesTest'`.
- Результат — PASS: 57 тестов / 1119 assertions, Memory 30 MiB. T12-R1 — PASS для CON.jpg, пустого и отсутствующего имени: pending построен, соседний snapshot/ZIP готов, expired-файл удалён, второй запуск повторяет диагностику без резервирования проблемных строк; после исправления имени все строки входят ровно один раз, прежние ZIP/SHA-256 не меняются. T12-R1-PAGE — PASS: новые группы на следующих страницах обслуживаются при limit=1, курсор восстанавливается после исправления, дублей нет. Прежние T10-GROUP/T11/T12/T13/T15 в границах этого unit-набора также PASS; новых HTTP-результатов не заявляется.
- Formatter — PASS: PHP CS Fixer 3.94.2, `fix --dry-run --diff --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=override`, ровно 28 PHP-файлов полного текущего diff (tracked+untracked; фильтр api/**/*.php, уникальные отсортированные пути без api/). 0/28 исправлений, исходники read-only; форматирование на запись не запускалось.
- AST после кода: `XDG_CACHE_HOME=/tmp/rabit-print-archive-index ast-index update` — PASS, 5 changed / 0 deleted; outline RunProductionUseCase соответствует исходнику. `git diff --check` — PASS. PHPStan ещё PENDING до завершения процесса.

### R1: итоговый static gate и авторская самопроверка

- PHPStan — PASS, `[OK] No errors`, exit 0. Точная команда из worktree: `docker run --rm --network none --read-only --entrypoint php --tmpfs /tmp:rw,size=256m --tmpfs /app/var:rw,size=256m -v /home/user/rabit-api/.worktrees/ops-print-archive-layout/api:/app:ro -v /home/user/rabit-api/api/vendor:/app/vendor:ro -v /home/user/rebit-p2p/api/public/bitrix:/app/public/bitrix:ro -w /app rabit-api-e2e-php-cli:local vendor/bin/phpstan analyse --configuration=tools/e2e/phpstan.neon --no-progress`. Имя конфигурации/образа не означает запуск E2E: только PHPStan без сети/БД/HTTP/стендов.
- Полная зелёная unit-команда совпадает с красной выше, кроме фильтра: `--filter=Production(Rules|Flow|Architecture)Test|PrintArchiveLayoutTest|PhotoFilenamesTest`. Фактический результат 57/1119 и 30 MiB описан выше. Formatter воспроизводится прежним `php_check` и `changed_php` из verification.json (теперь 28 PHP-путей); каждый путь передан отдельным argv, dry-run 0/28.
- Сохранность исходного diff — PASS: сравнение с `/tmp/rabit-print-archive-before-r1.json` подтвердило неизменность 26/28 прежних исходников, изменения только двух согласованных тестовых файлов и добавление трёх исходников R1 к diff. Все пять старых артефактов (native log / PNG) сохранены без изменения. `verification.json` теперь хранит `beforeR1SourceSha256`, текущие 31 `sourceSha256`, исторический `checksScope` и отдельные фактические результаты `r1.checks`; старый browser/visual PASS не выдан за проверку нового кода.
- Авторская самопроверка по rebit-code-review: сначала Б по пяти затронутым исходникам и документам, затем А по автоматическому/ручному пути. Base/HEAD/merge-base остаются 952f9789, проверяется собственный dirty diff, а не commit. Модель: Codex; точное runtime-наименование/уровень не предоставлены. Исходный независимый отчёт в /tmp не изменялся; данная самопроверка не закрывает R1 от имени reviewer.

Применены группы ID из таблицы Б выше: D (CHANGE/STYLE/TEST), L (LAYER/REUSE/DTO/DOC/EXC), W (LOCK/CLI/PROC). Для точности ниже перечислены существенные ID исправления; прежняя таблица остальных неизменённых файлов сохраняет только прежнее покрытие, не подменяет повторное независимое review.

| Файл R1 | Проверено / результат | Неприменимо |
| --- | --- | --- |
| `Production/.../UseCase/RunProductionUseCase.php` | D, L, W; CLI-01/CHANGE-03/TEST-01 — исправлено автором, 4 красных случая стали зелёными. EXC-01/02: только известная причина layout изолирована; иные ошибки пробрасываются. LOCK-01/02: прежние WithLock/claim и уникальность не меняются | DI/SQL/миграция/HTTP/UI — нет новых механизмов |
| `Production/.../Dto/ProductionRunOutputDto.php` | D, DTO-01/02/03, DOC-01/02 — только уточнение failed; сигнатура и форма сохранены, потребитель CLI прочитан | SQL/lock/HTTP/UI/DI/миграция — DTO без поведения |
| `Production/.../Presentation/Command/RunProductionCommand.php` | D, L, CLI-01, PROC-01, LOCK-01/02 — только точное описание лимита; команда, допустимый диапазон, поля вывода, WithLock и зависимости сохранены | HTTP/UI/SQL/миграция/DI — не изменены |
| `Production/tests/Unit/PrintArchiveLayoutTest.php` | D, L, TEST-01/02/03 — настоящие файлы/ZIP, предыдущие SHA-256, ручная 409, failed/диагностика, курсор, pending/expired, два повтора, восстановление и exact-once строк; provider unsafe/empty/unknown и limit=1 | Реальные SQL/HTTP/конкурентность — unit их не доказывает |
| `Production/tests/Unit/Support/ProductionFixture.php` | D, L, DTO-01/02/03, TEST-02 — только необязательная logger-зависимость run; прежние callers совместимы, builder/logger и repository semantics не подменены новым тестом | Production DI/SQL/HTTP/миграция — fixture не меняет runtime |
| `plan.md` | CHANGE-01/02/03, TEST-03 — состав/совместимость/ограничения R1 актуальны, чеклист закрыт фактическими результатами | Исполняемый код/миграция/UI |
| `progress.md` | CHANGE-01/02/03, TEST-03 — сохранены красный/зелёный прогоны, точная команда и актуальная точка продолжения | Исполняемый код/миграция/UI |
| `artifacts/verification.json` | CHANGE-01/02/03, TEST-03 — раздельные текущие/исторические хеши и результаты, E2E после R1 не заявлен | Исполняемый код/миграция/UI |
| Диапазон R1 целиком | CHANGE-01/02/03, TEST-01/02/03, CLI-01; Б → А выполнены, новых блокирующих находок автором не обнаружено | Новых событий/сообщений/кеша/публичных HTTP-контрактов/схемы/UI нет |

Проход А: ручной CreateProductionVersionUseCase → VersionSnapshot по-прежнему возвращает явную 409 без записи/idempotency-резерва; автоматический путь ловит только layout-причину, пишет диагностику, держит постоянный cursor и обходит следующие страницы локально. Независимые snapshots создаются/пропускаются через прежний firstOnly и уникальные ключи; due/expired работают, после исправления имени группа догоняется ровно один раз. Предел новых версий соблюдается при limit=1. Сканирование ограничено cutoff и памятью страницы, но при длительном отказе повторно читает хвост — ограничение внесено в план, новая очередь не добавлялась. Задача/формат/доступ/ручная ошибка/миграции не расширены.

| ID | Дата (Europe/Moscow) | Статус после исправления | Доказательство |
| --- | --- | --- | --- |
| T12-R1 | 10.11.2026 | PASS | 3 dataset в PrintArchiveLayoutTest; до исправления 3 errors, после — зелёный набор 57/1119 |
| T12-R1-PAGE | 10.11.2026 | PASS | limit=1 и две поздние группы, удержание/восстановление курсора и все строки ровно один раз |
| Unit T10-GROUP/T11/T12/T13/T15 | 10.11.2026 | PASS в границах unit | Тот же адресный набор 57/1119; никаких новых HTTP/E2E утверждений |
| PHPStan | 10.11.2026 | PASS | Команда выше, No errors |
| Formatter | 10.11.2026 | PASS | 0/28, dry-run, без записи |
| E2E/visual после R1 | 10.11.2026 | PENDING | Не запускались по прямому поручению |
| Полный gate a+b | 10.11.2026 | PENDING | Не запускался |
| Независимая повторная проверка R1 | 10.11.2026 | PENDING | Авторская самопроверка не заменяет reviewer |

Этап 6 вне scope. R1 исправлен в локальном diff; окончательное независимое подтверждение и готовность к merge не заявляются. Commit/push/публикация/merge/deployment не выполнялись.

### R1: точка передачи

- Финальная проверка — PASS: `git diff --check`, `git diff --cached --quiet`, UTF-8/whitespace plan/progress/verification/native log; совпали 31/31 текущих sourceSha256 и 5/5 artifactSha256, исторические 28 sourceSha256 до R1 сохранены отдельно. Состав sourceSha256 точно совпадает с полным tracked/untracked api/frontend diff; временный профиль AST убран, staging пуст, ветка/HEAD не изменены.
- Следующий шаг: независимое повторное review исправления R1 по актуальному dirty diff и verification.json. Full gate a+b PENDING; E2E/visual/стенды при исправлении не запускались. Всё оставлено в исходном worktree без commit/push/публикации.

## 10.11.2026 — R2 и разрешённый полный E2E

- Поручение пользователя: «Исправляй в этой сессии и проведи e2e тесты». Принято как переход от независимого Reviewer к авторской работе. Исходные отчёты review в /tmp не меняются; самостоятельная проверка R2 не называется независимой.
- Повторно подтверждены worktree/ветка/HEAD и существующий dirty diff; код чужих задач не затрагивается. План версии 3 обновлён до кода, выбраны delivery/layers/processes/quality и тестовый сценарий. Новый scope ограничен строгим whitelist логирования и проверками; алгоритм R1 сохранён.
- T12-R2 / T12-R2-NEG / GATE-AB — PENDING.

### R2 — красная регрессия и исправление

- AST: временный профиль восстановлен из /tmp, rebuild/stats — PASS, 2529 файлов / 25090 символов. Индекс ограничен этим worktree, стороннее ядро/runtime исключены; после правок перед следующим чтением будет update.
- До исправления `phpunit ... DiagnosticLogSanitizerTest.php --filter=PrintArchive` — ожидаемый FAIL: 3 теста / 8 assertions / 2 failures, exit 1. Fixed event отредактирован, UUID группы удалён. Третья отрицательная проверка прошла. Docker без сети/БД, исходники read-only.
- Исправлен только LogSanitizer: fixed event, UUID groupId и три разрешённых layout-кода. Тест использует реальный Monolog Logger/TestHandler и CommonLoggerProcessor, проверяет конечную запись, неизвестный код/UUID/массивные значения, исходное имя и произвольный message. Алгоритм R1/HTTP/права/сроки не менялись.
- Адресный зелёный PHPUnit выполняется; GATE-AB PENDING.

### R2 — адресный зелёный набор

- PASS: PHP 8.4.26 / PHPUnit 12.5.14, 74 теста / 1344 assertions, Memory 34 MiB при лимите 128 MiB. Команда: тот же read-only Docker, `-d memory_limit=128M vendor/bin/phpunit --colors=never --do-not-cache-result '--filter=Production(Rules|Flow|Architecture)Test|PrintArchiveLayoutTest|PhotoFilenamesTest|DiagnosticLogSanitizerTest|W01LogSanitizerTest'` (без --testsuite=unit, поскольку существующие logger-тесты находятся в tests/Infrastructure).
- AST update/outline LogSanitizer — PASS, новый runtime соответствует исходнику. T12-R2 / T12-R2-NEG — PASS в границах unit и реального Monolog processor.
- Начинается полный runner a+b по команде GATE-AB из plan; результаты ещё PENDING. Новые источники до завершения gate не меняются.

- Formatter R2 — PASS: PHP CS Fixer 3.94.2 `fix --dry-run --diff --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=override` по 30 PHP-файлам полного собственного diff; 0/30 исправлений, исходники read-only. Временный .ast-index.yaml удалён перед runner, профиль сохранён в /tmp.

### Полный gate a+b — запуск 830327f939b5

- Выполняется `python3 tools/run-browser-e2e.py run --groups a,b --kernel /home/user/rebit-p2p/api/public/bitrix --vendor /home/user/rabit-api/api/vendor --php-cli rabit-api-e2e-php-cli:local --php-fpm rabit-api-e2e-php-fpm:local --nginx rabit-api-e2e-nginx:local --mysql mysql:8.0`. State: `api/var/e2e/rabit-e2e-830327f939b5/state.json`, source указывает порученный worktree.
- Подготовка npm ci, WebP CLI/FPM и MySQL/RabbitMQ двух изолированных стендов — PASS; frontend/backend gate продолжаются. Browser/native PASS ещё не заявлены. Рабочие сервисы и данные не используются.

- Полный gate 830327f939b5: backend lint 1714 файлов, PHPStan и PHPUnit 1331 тест / 51778 assertions — PASS; frontend npm ci/lint/styles/typecheck/typecheck:e2e/test:ui/test:commerce/build-only — PASS. Новые Bitrix schema/fixtures обоих стендов и Notification MySQL-контракт — PASS. Реальные browser a и b запущены параллельно, их результат пока PENDING.

### R2 — авторская самопроверка Б → А

- Пофайлово (Б): LogSanitizer — CHANGE-01/02/03, STYLE-01/02, TEST-01/02/03, LAYER-01/02/03/04, REUSE-01/02, DOC-01/02, EXC-01/02, CLI-01; фиксированное событие и типизированный whitelist без новых зависимостей/состояния. DiagnosticLogSanitizerTest — те же применимые delivery/TEST/DOC; реальные Monolog processor и конечный record, позитивные и негативные значения, без утверждений о MySQL. Plan/progress — CHANGE-01/02/03, TEST-03; прежний diff и исторические результаты сохранены.
- Неприменимые к R2 ID: DTO/DI/REPO/CACHE/MIG/EVT/MSG/LOCK/HTTP/CMP/FE — R2 не меняет эти контракты, хранилище, способы запуска или UI; существующая цепочка DI и записи просмотрена как контекст. NOTE-01 внешней публикации — не разрешена и не выполняется.
- Сквозной проход А: RunProductionUseCase warning → production channel → обязательный CommonLoggerProcessor → fixed message + group UUID + один из трёх кодов → Monolog record. Произвольные имя/код/UUID/массив/динамический message не проходят. Прежние 31 api/frontend исходник побайтно совпадают с before-R2; scope ограничен двумя файлами Share и текущими документами.
- Проверка обоснованности: sanitizer не обходится/не отключается, логгер не создаётся в UseCase/controller, Domain/Production не импортируется в Share. Нового публичного контракта, route, схемы или зависимостей нет. Это авторская проверка R2, без независимого третьего review.

### Первый полный gate — FAIL (830327f939b5)

- Полный runner завершился exit 1; подготовительный backend/frontend gate PASS, группа a — PASS 81/81, skipped/unexpected/flaky=0; native verify-access/support/photo-deletion PASS.
- b: 76 PASS, 2 FAIL, 2 SKIPPED, flaky=0. G1-T14 (`zzzzzzzzz-payments.spec.ts:154`) ждал «Оплата не прошла», получил «Проверяем оплату»; чтение только безопасных полей одноразовой mf_payment_attempt показало sbp STATUS=unknown, CANCEL_REASON=provider_refused_retry, CHECK_COUNT=7. G2-T14 (`zzzzzzzzzzzzz-receipts.spec.ts:233`) не получил payment.provider=yookassa. Сценарий L1 печатного архива прошёл; native b-verifiers не запускались из-за failed browser group.
- Причина сбоев взаимодействия с тестовым магазином не установлена и требует отдельной диагностики; внешняя причина или дефект R2/печатного формата этим не доказаны. Payment/receipt-код и тестовые ожидания не меняются. Повторяется тот же полный gate на свежих БД без изменения исходников для проверки повторяемости сбоев взаимодействия с тестовым магазином; первая попытка сохраняется FAIL.

### Повтор полного gate — 519bbd19944e

- Та же команда a+b, те же исходники, новый state `api/var/e2e/rabit-e2e-519bbd19944e/state.json` и новые отдельные БД. Результат RUNNING, не PASS. Цель — проверить повторяемость падений тестового магазина без ослабления проверок/замены провайдера. Никаких правок Payment/Receipt или ожиданий браузерных тестов не внесено.

- Просмотрены актуальные desktop/mobile PNG L1 из первого запуска 830327f939b5 (сценарий печати PASS при failed всей группе b). Статус готовности, продукция/тираж, ссылки/CSV и доступные действия соответствуют источнику; новая UI-разметка/стили не менялись. Первый state stopped=true, cleanupErrors=[] — одноразовые ресурсы удалены. Повторный полный gate 519bbd19944e продолжается.

### R2 — итог полного gate и сохранённые доказательства

- Повтор той же команды a+b, run `519bbd19944e` — PASS, exit 0. Группа a: 81/81; группа b: 80/80; всего 161, skipped/unexpected/flaky=0. Scope `full gate`, test shop on. Никаких изменений исходников, настройки провайдера, исключений/ослаблений тестов между двумя запусками не было. Первый FAIL не отменён и сохранён отдельно; его первопричина не установлена, сбой не повторился.
- Все backend/frontend/native этапы — PASS. Полный PHPUnit: 1331 тест / 51778 assertions; lint 1714 файлов, PHPStan; frontend UI 113 / commerce 253, lint/styles/types/build. Адресные 74 / 1344 и formatter dry-run 0/30 сохранены выше.
- Native b verify-production: basename, реальные ZIP/CSV/строки заказа/SHA-256, replay миграции, длинный ENTRY без усечения, guarded down, expiry/renew/rebuild — PASS. `artifacts/native-layout-r2.log` скопирован из успешного run; исторический native-layout.log сохранён.
- Два новых PNG из успешного реального L1 просмотрены на 1440×1000 и 390×844, сохранены как `l1-r2-desktop-print-archive.png` / `l1-r2-mobile-print-archive.png`; статус, тираж, действия и адаптив соответствуют коду.
- Runner: stopped=true, cleanupErrors=[], все stages passed — одноразовые ресурсы успешного запуска удалены. Первый failed run также stopped=true / cleanupErrors=[]. Рабочие БД/сервисы не менялись.
- verification.json хранит текущие 33 sourceSha256, 8 artifactSha256, исторические before-R1/before-R2 и отдельные результаты каждой попытки. Полный gate больше не PENDING; независимая проверка R2 после авторской правки отдельно. Сверка с ведомостью этапа 6 не выполнялась; весь T10 не закрывается.

| ID | Дата (Europe/Moscow) | Итог | Доказательство |
| --- | --- | --- | --- |
| T12-R2 | 10.11.2026 | PASS | Конечный Monolog record, 3 допустимых кода и UUID; адресный набор 74/1344, полный 1331/51778 |
| T12-R2-NEG | 10.11.2026 | PASS | Неизвестные значения, массивы, filename и динамический message удаляются |
| GATE-AB | 10.11.2026 | PASS после свежего полного повтора | `519bbd19944e`: a81+b80, zero skipped/flaky/unexpected; `830327f939b5` сохранён FAIL |
| T10-GROUP/T11/T12/T13/T15 | 10.11.2026 | PASS в указанном покрытии | Unit и реальный L1/native ZIP/CSV/SHA-256/миграция/lifecycle/права. Межгрупповые отказ/recovery R1 — unit, без отдельной live failure injection |
| DOC-4 / L1 visual | 10.11.2026 | PASS | Новые desktop/mobile L1 PNG просмотрены; исходник guide прежний, исторические guide PNG сохранены |

- Авторская самопроверка R2 Б → А закрыта; новая независимость не заявляется. Commit/push/PR/merge/deployment не выполнялись.

### Финальная сверка R2

- PASS: `git diff --check`, `git diff --cached --quiet`; все 33/33 sourceSha256 и 8/8 artifactSha256 совпали. 31 прежний исходник сохранён побайтно, 44 изменённых tracked/untracked файла учтены. Plan/progress/verification/native-r2 читаются как UTF-8 и проходят `git diff --no-index --check /dev/null <файл>` без диагностик.
- Read-only gh main — всё ещё 952f9789595e4d9e6a9c73b9b03c3deb1cd139dc; checkout/ветка/HEAD неизменны. Docker ps -a и volume ls по меткам обоих уникальных запусков пусты. Временного AST-конфига нет; чужие изменения/основной checkout не тронуты.
- Поручение исправить R2 и выполнить E2E завершено. Точка продолжения актуальна: следующий отдельный шаг — независимая проверка R2, затем разрешённые публикация/merge. Самостоятельно эти действия не выполнялись.

## 10.11.2026 — разрешённые commit, PR и merge

- Пользователь: «Делай коммит и merge». Поручение относится ко всему текущему проверенному diff этапа 4; для merge выполняются push OPS-ветки и создание одного PR в main. Deployment не разрешён и не выполняется.
- Pre-commit: worktree/ветка/HEAD подтверждены; remote main 952f9789595e4d9e6a9c73b9b03c3deb1cd139dc, существующего PR этапа 4 нет. Staging пуст, git diff --check PASS; 33/33 sourceSha256 и 8/8 artifactSha256 совпали, полный перечень 44 файлов соответствует задаче. Сохранённый актуальный full gate 519bbd19944e — все stages PASS, a81+b80=161, без skipped/unexpected/flaky, stopped=true/cleanupErrors=[]. Новых исходников после тестов нет.
- План/прогресс актуализированы только по текущим полномочиям и исправлению устаревшей строки о повторном R1 review. Новая продуктовая область не добавлена; R2 повторно независимо не проверялся, авторские unit/static/E2E и самопроверка сохранены.

### Публикация PR #238 — снимок перед merge

- `git commit -m "feat(production): preserve filenames and group print quantities"` — PASS: `24bccd22e16d2683428a9e57f1b51c2f62004903`, 44 файла задачи, рабочее дерево после коммита чистое.
- Первая отправка через origin фактически использовала SSH и завершилась `Connection closed ... port 22`, exit 128; remote ветки ещё не было (gh git/ref — 404). Коммит/исходники не менялись.
- Успешная отправка: `GIT_TERMINAL_PROMPT=0 timeout 90 git -c credential.helper= -c 'credential.helper=!/home/user/.local/bin/gh auth git-credential' push https://github.com/rebit-pro/rabit-api.git HEAD:refs/heads/codex/ops-print-archive-layout` — PASS, новая remote ветка создана, без force.
- `gh pr create --repo rebit-pro/rabit-api --base main --head codex/ops-print-archive-layout --title ... --body-file /tmp/rabit-print-archive-pr-body.md` — PASS: https://github.com/rebit-pro/rabit-api/pull/238. Сразу после создания OPEN, не draft, head 24bccd2, base 952f9789; Actions/checks отсутствуют, локальные проверки и ограничения указаны в PR.
- До merge сохраняется этот документарный снимок с фактическими PR/commit и результатами. Поручение merge остаётся действующим; финальные состояние/merge SHA фиксируются GitHub в PR #238 и итоговом сообщении. Этот снимок не утверждает успешный merge до выполнения операции. После merge следующий шаг — использовать только подтверждённый main; deployment отдельно.
