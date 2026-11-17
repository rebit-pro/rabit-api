# Прогресс этапа 2

## Точка продолжения

- Ветка `codex/ops-gallery-original-filenames`, worktree `/home/user/rabit-api/.worktrees/ops-gallery-original-filenames`, base `90755f3a3700d55dae94befabea387ca8637c217`; проверяемый HEAD — коммит этапа 2 этой ветки, точный SHA: `git rev-parse HEAD`. PR этого этапа ещё нет. Источник требований — одобренные документы этапов из PR #235, полный scope перенесён в plan.md.
- Завершено: выбор этапа, сверка удалённого main и PR, изоляция, чтение кода и правил, план до реализации. Реализация, разрешённый quality gate и авторская самопроверка завершены. Следующий шаг: независимое ревью рабочего diff; перед merge отдельный реальный HTTP/E2E и visual.
- Блокеров реализации нет. E2E/стенды запрещены в этой сессии; real HTTP/visual остаётся до merge. AST update недоступен из-за read-only служебного кеша; используется rg/исходники именно этого дерева.
- Дерево: 24 собственных файла PHP/Vue/TS/guide/E2E/unit и plan/progress подготовлены к разрешённому коммиту и push; состояние после публикации проверяется `git status --short`. Исходный `/home/user/rabit-api` с локальными исправлениями R1/R2 PR #235 и чужим OPS-stage-gallery-cleanup сохранён без изменений. Worktree не содержит продуктового кода PR #235.
- Следующие проверки: `git diff --check 90755f3a3700d55dae94befabea387ca8637c217...HEAD`, `git diff 90755f3a3700d55dae94befabea387ca8637c217...HEAD` для независимого ревью коммита в другой сессии. Для новых PHP-правок — контейнер PHP 8.4 с api этого worktree в /app:ro и общим vendor отдельно в /app/vendor:ro. Этап не объявлен готовым к merge без реального E2E/visual.

## Хронология

### 07.11.2026

- `gh pr view 235 --json state,isDraft,headRefOid,baseRefOid,mergedAt`: OPEN/DRAFT, HEAD acb1363; `gh pr view 231 ...`: OPEN, e5506f2; `gh api repos/rebit-pro/rabit-api/commits/main --jq .sha`: 90755f3a. У этапа 2 нет зависимости от merge этапа 1.
- `git worktree add -b codex/ops-gallery-original-filenames /home/user/rabit-api/.worktrees/ops-gallery-original-filenames 90755f3a3700d55dae94befabea387ca8637c217`: PASS после разрешённой эскалации записи Git; исходное dirty-дерево сохранено.
- Прочитаны новые AGENTS.md/CLAUDE.md, архитектурный профиль и код Media/gallery, UI, PR #231, профильные skills. Порядок A…Z/AA… нужен, прежний фильтр отменён утверждёнными требованиями. Код незамерженных PR не переносится.
- `ast-index update` в новом корне: BLOCKED, read-only `/home/user/.cache/ast-index/.leases/layout.lock`; индекс не читается и не создаётся.

## Результаты

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| T08-NAME | PASS | 07.11.2026 | Полный Docker PHP unit: 1075/1075, в том числе GalleryFilenameTest (4 исходных имени и legacy-конструктор) и ChildPhotosTest |
| T08-ALL | PASS | 07.11.2026 | npm run test:ui: 25/25 файлов, включая gallery-photos.test.mjs: полный порядок, сохранение назначений/имён, отсутствие мутации |
| T08-URL | PENDING | 07.11.2026 | typecheck:e2e PASS; подготовлены gallery.feature/steps и real short-links с child=ZZ999, reload, именем и MAX. E2E не запускался |
| T03 | PENDING | 07.11.2026 | test:commerce 32/32 и полный PHP unit PASS; реальные быстрые действия/покупка до merge ещё не проверены |
| T08-UI | PENDING | 07.11.2026 | lint/typecheck/stylelint/build PASS; desktop/mobile/длинные имена проверены по коду, реальные visual/E2E не запускались |
| T08-DOC | PASS | 07.11.2026 | parent/faq/помощь прочитаны; удалены старые указания и две иллюстрации фильтра, anchors сохранены; rg старых указаний — нет совпадений, git diff --check PASS |

### Реализация и первые проверки

- UF_FILENAME добавлен в обе выборки GalleryPhotoRepository, GalleryAssignmentMapper/DTO и JSON галереи. Реальные имена не строятся по MIME/коду/пути. Последний параметр DTO с default сохраняет совместимость. Изменений OriginalFileOutputDto/PR #235 нет.
- Удалены GalleryFilters и фильтрация query.child. galleryPhotos сохраняет назначения и порядок снимков, группирует A…Z/AA…; карточка/просмотр показывают имя, legacy API без имени показывает код. Demo берёт ManagedPhoto.filename. Обновлены parent/faq/помощь, подготовлены mock/live E2E без запуска.
- `node --experimental-strip-types --test frontend/tests/ui/gallery-photos.test.mjs`: PASS, файл тестов порядка/полноты/неизменности входа.
- Первый Docker unit был BLOCKED до запуска PHP: не было пустой точки /app/vendor внутри ro mount. Созданы только игнорируемые api/vendor и api/var/phpstan; повтор использует vendor как отдельный ro mount, не автозагружает продуктовый код исходного дерева.
- `docker run --rm --network none --read-only --tmpfs /tmp:rw --user 1000:1000 -v /home/user/rabit-api/.worktrees/ops-gallery-original-filenames/api:/app:ro -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local vendor/bin/phpunit --colors=never --do-not-cache-result --testsuite=unit`: PASS, PHP 8.4.26, 1075 тестов, 50639 assertions.
- Первый `npm run lint` нашёл только Prettier в GalleryScreen.vue и новом unit-файле; применён `node_modules/.bin/prettier --write src/modules/morefoto/gallery/components/GalleryScreen.vue tests/ui/gallery-photos.test.mjs`. Повтор общего frontend gate выполняется.
- Для frontend подключён только node_modules исходного checkout (symlink, игнорируемый Git); исходники выполняются из нового worktree. Stylelint отсутствует в исходных зависимостях, объявленная версия 16.26.1 устанавливается отдельно в /tmp/rabit-stage2-stylelint без изменения package/lockfile проекта.

### Итог разрешённого quality gate

- cwd frontend нового worktree, `npm run lint && npm run typecheck && npm run typecheck:e2e && npm run test:ui && npm run test:commerce && npm run build-only -- --configLoader runner`: PASS; UI 25/25 файлов, Commerce 32/32, сборка 1306 модулей. Runtime/E2E этим не подтверждается.
- Обычный Prettier CLI без настроек ESLint сначала применил свои кавычки/ширину. `node_modules/.bin/eslint src/modules/morefoto/gallery/components/GalleryScreen.vue tests/ui/gallery-photos.test.mjs --fix` адресно вернул настройки проекта; общий lint после этого PASS. Другие файлы formatter не менял.
- После самопроверки подготовленных E2E уточнены ожидания literal `?` в URL и точного имени viewer; `node_modules/.bin/eslint e2e/steps/gallery/gallery.steps.ts e2e/live/zzzz-short-links.spec.ts && npm run typecheck:e2e`: PASS. Это статическая проверка, не E2E-прогон.
- `docker run --rm --network none --read-only --tmpfs /tmp:rw --tmpfs /app/var/phpstan:rw,mode=1777 --user 1000:1000 -v /home/user/rabit-api/.worktrees/ops-gallery-original-filenames/api:/app:ro -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app --entrypoint php rabit-api-e2e-php-cli:local vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress`: PASS, ошибок нет.
- Та же изоляция с `vendor/bin/phplint --no-cache`: PASS, 1709 PHP-файлов.
- Точечный CS Fixer сначала потребовал выравнивание PHPDoc GalleryResultDto; shape записан многострочно и выровнен. Финальная команда с теми же Docker mounts: `vendor/bin/php-cs-fixer fix --dry-run --diff --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=intersection public/local/modules/rebit.share/lib/Contracts/Media/Dto/GalleryAssignmentOutputDto.php public/local/modules/morefoto.media/lib/Domain/Gallery/Repository/GalleryPhotoRepository.php public/local/modules/morefoto.media/lib/Application/Gallery/Mapper/GalleryAssignmentMapper.php public/local/modules/morefoto.media/lib/Presentation/Gallery/GalleryResultMapper.php public/local/modules/morefoto.media/lib/Presentation/Gallery/Dto/GalleryResultDto.php public/local/modules/morefoto.media/tests/Unit/ChildPhotosTest.php public/local/modules/morefoto.media/tests/Unit/GalleryFilenameTest.php`: PASS, 0/7.
- В /tmp/rabit-stage2-stylelint отдельно установлены `stylelint@16.26.1`, `postcss-html@1.8.0`, `postcss-scss@4.0.9` через npm с `--ignore-scripts --package-lock=false --no-audit --no-fund --cache /tmp/rabit-stage2-npm-cache`. Первые попытки lint:styles не нашли синтаксические модули; после установки `env PATH="/tmp/rabit-stage2-stylelint/node_modules/.bin:$PATH" npm run lint:styles`: PASS по исходному stylelint.config.mjs. package/lockfile и node_modules исходного проекта не изменены.
- `git diff --check`: PASS. Поиск GalleryFilters/selectCode/route.query.child в frontend src/tests и старых указаний фильтра в parent/faq/gallery не дал совпадений (rg exit 1 ожидаем).
- Самопроверка сначала по файлам (PHP стиль/shape/DTO/SQL/HTTP и frontend типы/состояния/вывод), затем сквозная: UF_FILENAME → обе выборки → mapper → публичный DTO → JSON → карточка/просмотр; все старые конструкторы совместимы, новые поля прочитаны потребителем. Динамические импорты удалённого компонента/остатки фильтра не найдены. Назначения/коды/preview URL не меняются; чистая сортировка сохраняет общие кадры, токен/фото в URL и код покупки не менялись. Это авторская самопроверка, не независимое review.
- `git diff --name-only 90755f3a -- api/public/local/modules/morefoto.files api/public/local/modules/morefoto.production api/public/local/modules/rebit.share/lib/Contracts/Media/Dto/OriginalFileOutputDto.php`: пусто. Код этапа 1 не перенесён. Исходный checkout остаётся на codex/ops-order-photo-delivery с прежними R1/R2 и чужой untracked папкой.
- Commit/push/PR/merge/deploy, E2E/visual и стенды не выполнялись. Новые скриншоты руководства не создавались; отдельные старые обзорные иллюстрации потребуют сверки при визуальной приёмке. Этап 4 по именам/расширениям печатного ZIP не начат.

### Публикация коммита по поручению пользователя

- Пользователь разрешил commit и push этапа 2; ревью коммита будет выполнено в другой сессии. Создание PR, merge, deployment и E2E этим поручением не разрешены.
- Перед коммитом: активная ветка и worktree подтверждены, HEAD исходной базы `90755f3a3700d55dae94befabea387ca8637c217`, `git diff --check`: PASS. `gh api repos/rebit-pro/rabit-api/commits/main --jq .sha` подтвердил ту же актуальную базу; запрос удалённой ветки этапа 2 вернул 404 — она ещё не создана.
- Продуктовый код после прошедшего quality gate не менялся; актуализированы только план и этот журнал для разрешённых commit/push. Повторное ревью и E2E не запускались. В индекс добавляются ровно 24 файла этапа 2 явными путями; исправления R1/R2 и чужие файлы исходного checkout в коммит не входят.
- Коммит: `feat(gallery): show original filenames without series filter`. Публикация: обычный push ветки `codex/ops-gallery-original-filenames` в origin, без force. Фактический SHA и результат push проверяются сравнением `git rev-parse HEAD` с `gh api repos/rebit-pro/rabit-api/git/ref/heads/codex/ops-gallery-original-filenames --jq .object.sha` и сообщаются в чате.
