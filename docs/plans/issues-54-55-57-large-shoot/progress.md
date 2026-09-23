# Issues #54, #55 и #57 — журнал

## Точка продолжения

- Дата: 2026-09-23.
- Ветка: `codex/issues-54-55-57-large-shoot`, upstream `origin/codex/issues-54-55-57-large-shoot`.
- Worktree: `/home/user/rabit-api-worktrees/issues-54-55-57-large-shoot`. Основной checkout `/home/user/rabit-api` занят другой сессией (`codex/design-ux-plan`), в нём не работать.
- Base: `origin/main` `2cc2360` (merge PR #58, оптимизация E2E), влит в ветку merge-коммитом `cd8c655`. Прежний base — `5f658e5`.
- Issues: [#54](https://github.com/rebit-pro/rabit-api/issues/54), [#55](https://github.com/rebit-pro/rabit-api/issues/55), [#57](https://github.com/rebit-pro/rabit-api/issues/57) — CLOSED при merge. Follow-up: [#59](https://github.com/rebit-pro/rabit-api/issues/59) (`MediaController`), [#62](https://github.com/rebit-pro/rabit-api/issues/62), [#63](https://github.com/rebit-pro/rabit-api/issues/63) (неблокирующие замечания review).
- PR: [#60](https://github.com/rebit-pro/rabit-api/pull/60) MERGED 2026-09-23T10:20:21Z, merge commit `5e2df6a`; проверенный head ветки — `b9e381a` (gate на `52d1b50`).
- Production: релиз `issues60-20260923102153-5e2df6a`, backend 4/4 сервиса и frontend 2/2 на новом коде.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md), предыдущий журнал [#47](../issues-31-33-34-photo-upload/progress.md).
- Завершено:
  - #57 (`4edc593`), backend #54 (`e2663c4`), frontend #55 (`e73c72d`), frontend #54 (`f727d24`), live E2E-сценарий (`d025312`);
  - все быстрые проверки и stub-проверка UI.
- Сейчас: задача на production. Открыт только замер по реальной загрузке (T16).
- Следующий шаг: после реальной загрузки съёмки прочитать на production `media-*.log`: записи `Photo upload accepted.` и `Photo previews ready.` с длительностями (T16; закрывает замер #34/#47).
- Блокеров нет.
- Открыто: каноническое описание MED-02 в `../MoreFoto` (D9): query `status`, поле `summary`.
- Рабочее дерево: закоммичено. Пустые `api/vendor`, `api/var` и `frontend/node_modules` — точки монтирования docker-проверок, в git не попадают.
- Команды проверок:
  - backend: vendor-том `rabit-issues545557-vendor` (засеян из `/home/user/rabit-api/api/vendor`, `composer.lock` совпадает, `composer dump-autoload --no-scripts --no-plugins`), затем `docker run --rm --network none --memory 1536m --cpus 2 --env XDEBUG_MODE=off --mount type=bind,source=<worktree>/api,target=/app,readonly --mount type=volume,source=rabit-issues545557-vendor,target=/app/vendor --tmpfs /app/var:rw,size=256m --workdir /app --entrypoint php rabit-api-php-cli:d1-local vendor/bin/phpunit --colors=never` (так же `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` и `vendor/bin/phplint`);
  - frontend: том `rabit-issues545557-node` (`npm ci` текущего lockfile), затем `docker run --rm --network none -v <worktree>/frontend:/app -v rabit-issues545557-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`.

## Хронология

### 2026-09-23 — разведка и решения

- Пользователь взял в работу #54, #55, #57 и отдельно готовит оптимизацию E2E. E2E запускать только по его команде.
- Worktree: `git worktree add -b codex/issues-54-55-57-large-shoot /home/user/rabit-api-worktrees/issues-54-55-57-large-shoot origin/main`, затем `git branch --unset-upstream`.
- Разведка:
  - MED-02 уже фильтрует `groupId`, `childCode`, `assigned` на сервере, но отдаёт кадры всех статусов, а сводки по группе нет;
  - `MediaController` загрязнён (`MediaRequestFactory` над `HttpRequest`);
  - `PhotoImage` запускает XHR сразу при монтировании, общий таймаут 15 с;
  - санитайзер вырезает все 6 сообщений media и, дополнительно, 3 сообщения `rebit.notification`.
- Решения пользователя (AskUserQuestion):
  - D1 — «Чистый контроллер»: список уходит в новый чистый контроллер, MED-02 получает `status` и `summary`;
  - D2 — «Да, в этой ветке»: записи notification чинятся здесь же.

### 2026-09-23 — #57 и backend #54

- #57 (`4edc593`):
  - `LogSanitizer`: 6 сообщений media, 3 сообщения notification, 22 ключа с типовой проверкой;
  - разрешённый ключ со значением `null` пропускается без пометки `redacted`: иначе каждая запись dispatcher без `previous` получала бы ложный флаг;
  - `UploadPhotoUseCase`: `status` → `photoStatus`.
- Тесты #57 прогоняют записи реальных UseCase через `CommonLoggerProcessor`. Негативная проверка: с исходным `LogSanitizer` (git stash) все 7 новых тестов падают, с исправлением проходят.
- Backend #54 (`e2663c4`):
  - `PhotoListController` (чистый);
  - `ListPhotosRequestDto` (`#[StrictRequest]`), `PhotoListInputMapper`, `PhotoListResultMapper`, `PhotoPageResultDto`, `PhotoGroupSummaryResultDto`;
  - `ListPhotosUseCase`: `status` и `summary`;
  - `PhotoRepository`: `status` в выборке и счёте, `childCodes()`;
  - из `MediaController`/`MediaRequestFactory` удалены `listAction`/`listing()`/`positive()`;
  - маршрут и DI.
- Побочный эффект: GET списка больше не создаёт `UploadPhotoUseCase`, поэтому не зависит от `MESSENGER_TRANSPORT_DSN` (ср. `OPS-stage-media-recovery`).

### 2026-09-23 — PR и статус E2E

- Follow-up [#59](https://github.com/rebit-pro/rabit-api/issues/59): остальные действия `MediaController` привести к чистой DTO-границе.
- Push ветки и [PR #60](https://github.com/rebit-pro/rabit-api/pull/60). В теле PR: контракт MED-02, порядок выкатки backend → frontend, проверки, ограничения, `Closes #54 #55 #57`.
- Пользователь сообщил: «Оптимизация E2E тестов завершена и уже в ветке main».
  - Проверено `git ls-remote` и `gh pr view 58`: PR #58 (`codex/ops-e2e-optimization`, head `dbba991`) ещё OPEN, `origin/main` = `5f658e5`.
  - Обновлять base не на что. Gate запускается после merge #58 и команды пользователя.

### 2026-09-23 — merge #58 и gate

- Пользователь разрешил слить #58 и запустить E2E.
  - Первая попытка `gh pr merge 58` отклонена классификатором авто-режима («Merge Without Review»), следом отклонена и read-only команда. Работа остановлена, пользователю предложены варианты.
  - После прямого поручения («58 сливай в мейн, к 60 подключай мейн новый и прогоняй E2E») `gh pr merge 58 --merge --match-head-commit c0166b4…` → MERGED, merge commit `2cc2360`.
- `git merge origin/main` в ветку: без конфликтов, `cd8c655`, push. Ревью #60 идёт параллельно.
- Запущен полный gate: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`. Новый сценарий в `zz-media.spec.ts` входит в группу `a` из `groups.json`, правка групп не нужна.

- Gate 1 (`rabit-e2e-f027c8f31037`, 239,7 с): FAIL.
  - Все проверки до браузера PASS.
  - Группа `b` PASS: 31 тест и 4 MySQL-верификатора.
  - Группа `a`: 46 из 47 PASS. Упал новый сценарий «#54/#55» на строке `peakOverlap(thumbs) <= 6`: получено 7.
  - До этой строки сценарий прошёл: один GET списка с верными параметрами, 48 карточек, сводка, все 48 превью видны, «Кадр не загрузился» нет, сбойное превью запрошено ровно 2 раза.
- Разбор: HAR из `trace.zip` показывает пик одновременных `/thumb` = 6, то есть лимит соблюдён. Причина — в замере.
  - `request.timing()` даёт начало с точностью до 1 мс, а конец с дробной частью.
  - Очередь запускает следующее превью сразу после завершения предыдущего, поэтому соседние запросы ложно накладывались.
  - Исправление только в тесте: пик считается по Resource Timing API страницы (один монотонный таймер, субмиллисекундная точность — «браузерные тайминги» из приёмки #55). Буфер Resource Timing поднят до 1000 записей.

### 2026-09-23 — блокирующее замечание review PR #60

- Gate 2 (`rabit-e2e-947adbe2ac07`) остановлен по поручению пользователя на браузерных группах. Все проверки до браузера к этому моменту PASS.
  - Runner пометил группы `cancelled`, но оставил 4 контейнера.
  - Они убраны штатно: `make e2e-down E2E_STATE=<worktree>/api/var/e2e/rabit-e2e-947adbe2ac07/state.json`. После этого не осталось ни контейнеров, ни томов, ни сетей запуска.
- Review head `cd8c655`: одно блокирующее замечание P1 в `previews.ts`. Неблокирующие вынесены в #62 (данные прежней группы при ошибке смены) и #63 (смена группы во время подготовки переноса).
- P1: токен проверялся только при следующей загрузке превью, а начатый запрос переживает размонтирование кадра. Выход и новый вход оставляли запрос старой сессии в полёте; его 401 проходил через общий interceptor и сбрасывал новую сессию.
- Исправление: при первой загрузке `previews.ts` подписывается на `auth.token` watcher с `flush: 'sync'`. Любая смена или очистка токена сразу вызывает `loader.clear()`: запросы прежней сессии прерываются, кеш сбрасывается. Прежняя сверка токена при загрузке удалена как избыточная.
- Stub-проверка сценария ревьюера (`session.mjs`: два превью задержаны, `clearSession()` и новая сессия в store, затем задержанные запросы отвечают 401):
  - с исправлением оба запроса отменены (`net::ERR_ABORTED`), токен новой сессии и страница сохранены — PASS;
  - контроль на `previews.ts` из `37fe7b1` (исходники с этим файлом смонтированы поверх `/app/src`): запросы не отменены, токен `null`, переход на `/login?reason=session-expired` — ошибка воспроизведена.
- Live-регрессия T18 в `zz-media.spec.ts`: настоящие «Выйти» и вход, отмена задержанного превью, 401 после нового входа не сбрасывает сессию.
- `npm run check` exit 0, `npm run test:commerce` 182/182.
- Коммит `a33b99b`, push, ответ на замечание в PR.
- Gate 3 (`rabit-e2e-6f3ef4f6a48c`, 243 с): FAIL.
  - Все проверки до браузера PASS. Группа `b` PASS: 31 тест и 4 верификатора.
  - Группа `a`: 47 из 48, T18 (сессия) PASS. Большой сценарий «#54/#55» прошёл пик превью по Resource Timing, страницы, кеш, reload, разметку и возврат, но упал на последней проверке: `scrollWidth <= clientWidth` на 390 px.
- Разбор по скриншоту падения: сразу после `setViewportSize` с 1440 на 390 боковое меню ещё уезжало, контент был сдвинут на время анимации Vuetify. Это переходное состояние живой страницы, а не вёрстка экрана: в stub-прогоне с паузой после ресайза прокрутки нет.
- Исправление теста по образцу соседних спек (`staff`, `D1/D2`): мобильный размер, затем reload той же страницы 2 и проверка `scrollWidth <= innerWidth` на свежей отрисовке. Коммит `52d1b50`, push.
- Gate 4 (`rabit-e2e-aaae2a1e023c`, 242,6 с): **PASS**, exit 0.
  - Все проверки PASS: npm ci, lint, типы, `test:commerce`, build, php-lint, PHPStan, PHPUnit, миграции, Notification на MySQL.
  - Группа `a`: expected 48, unexpected 0, flaky 0, skipped 0.
  - Группа `b`: expected 31, unexpected 0, flaky 0, skipped 0.
  - MySQL-верификаторы storefront, orders, links, transfers — PASS.
  - Итог runner: `Browser scenarios passed: 79 {'b': 31, 'a': 48} (full gate)`.
- Визуальная проверка скриншотов стенда:
  - `q54-desktop-photos.png`: настоящие превью с водяным знаком, «Показано 48 из 50», пагинация;
  - `q54-mobile-photos.png`: 390 px, страница 2, «Показано 2 из 50», A001 и «Без ребёнка», предложенный код B, без горизонтальной прокрутки.
  На мобильном снимке превью ещё в состоянии загрузки: снимок сделан сразу после reload, раскладку это не затрагивает.
  Артефакты: `api/var/e2e/rabit-e2e-aaae2a1e023c/a/a/artifacts/zz-media--54-55-*/`.
- Mock BDD `@r08`: 24/24 сценария, 48/48 шагов. Demo-режим фото проверен: локальные страницы, предпросмотр и перенос с набором по запросу, превью из IndexedDB.

### 2026-09-23 — frontend #55 и #54

- #55:
  - `preview-loader.ts` — чистое ядро: очередь 6, один повтор, дедупликация, LRU по байтам, отмена ожидающих, приоритет крупного кадра;
  - `previews.ts` — axios с таймаутом 30 с, `Accept: image/webp`, очистка кеша при смене токена, счётчик неудачных превью и «Повторить все»;
  - `PhotoImage` — IntersectionObserver (300 px), object URL принадлежит кешу;
  - `GalleryImage` — учёт неудачи в общем счётчике (sync-watcher и флаг, чтобы не уйти в минус при размонтировании) и реакция на «Повторить все».
- #54:
  - `photosApi.list(shootId, query)` с `status` и `summary`;
  - `paging.ts` — размер 48, разбор `page`/`filter` из URL, demo-страница;
  - `rules.freeChildCode`;
  - `usePhotoWorkspace` переписан: один ключ страницы (группа, фильтр, номер) задаёт и запрос, и URL; при открытии — ровно один GET; после разметки — GET только текущей страницы; обложка вне страницы строится по id;
  - «Предпросмотр» и «Перенести весь набор» читают кадры ребёнка по `childCode`;
  - `PhotoCollection`: «Показано N из M», `v-pagination` (5 кнопок на мобильном), прокрутка к началу списка, полоса загрузки без снятия сетки, «Повторить все».
- Live E2E `zz-media.spec.ts`:
  - новый сценарий «#54/#55»: 50 кадров, один GET с `groupId/status/page/pageSize`, страница 2 в URL, возврат без повторной загрузки превью, reload, разметка на стр. 2, возврат со страницы съёмки, пик превью ≤ 6, однократный сетевой сбой первого превью восстанавливается, 390 px;
  - общий `peakOverlap`/`span` вынесен из сценария #33.
  Не запускался: E2E — по команде пользователя.
- Stub-проверка в браузере (не E2E-набор): Vite dev в live-режиме и заглушки `page.route` в контейнере Playwright без сети, 110 кадров, скрипт в scratchpad сессии. Все 26 проверок PASS:
  - один GET списка с `status=ready&page=1&pageSize=48`;
  - до прокрутки 4 запроса превью, пик параллельных 6;
  - однократный сбой — 2 запроса, 404 — 1 запрос;
  - «Повторить все»;
  - страница 3 в URL и после reload;
  - возврат на страницу 1 без повторной загрузки;
  - разметка — один GET страницы 3, сводка «Детей: 1 · Без ребёнка: 109»;
  - предпросмотр набора с другой страницы, фильтр на сервере (`assigned=false`) со сбросом страницы, перенос набора;
  - 390 px без горизонтальной прокрутки, без ошибок консоли.
  Первый прогон упал на `/access-unavailable`: заглушке пользователя не хватало права `media.manage`. Код приложения не менялся.

### 2026-09-23 — merge и деплой на app.morefoto36.ru

- **Merge.** Пользователь: «можно сливать в main и делать деплой сразу». `gh pr merge 60 --merge --match-head-commit b9e381a…` → MERGED, `5e2df6a`, issues #54/#55/#57 закрылись. `main` после gate не сдвигался (`2cc2360`), поэтому gate 79/79 относится к слитому коду.
- **Сборка релиза** из merge-коммита:
  - `git archive 5e2df6a api` (1727 файлов: против #47 добавилось ровно 12 ожидаемых);
  - образ `morefoto-frontend:issues60-20260923102153-5e2df6a` с `VITE_API_MOCKS_ENABLED=false`. В чанке `PhotoWorkspacePage` есть «Повторить все», «Не загрузилось превью», `status:"ready"`.
- **Подготовка на сервере** `/srv/morefoto/releases/issues60-20260923102153-5e2df6a`:
  - SHA256SUMS OK, `services-before.json` для пяти сервисов;
  - `prepare-release.sh`: маркер `PhotoListController.php`, `composer.lock` совпал с `issues47-…`, vendor оттуда же; `app` 411 МБ;
  - миграций нет.
- **Backend.** Первая попытка `switch-backend.sh` отклонена классификатором авто-режима («Production Deploy»). После явного разрешения пользователя («разрешаю тебе опубликовать деплой») `morefoto_stage_fpm`, `…_backend`, `…_media_consumer`, `…_media_dispatcher` переключены на новый `/app`, код виден в контейнерах, реплики 1/1.
- **Smoke backend.**
  - `/health` 200, `/api/v1/me` 401 JSON.
  - `GET /api/v1/shoots/{uuid}/photos` без токена — 401 JSON с `status=ready` и без него: маршрут ведёт в `PhotoListController`, DI собирается.
  - Код ошибки 401 у списка теперь `SERVICE_UNAVAILABLE` вместо прежнего `MEDIA_REQUEST_FAILED`. Так отвечают все чистые контроллеры (превью, заявки сотрудников) — известный дефект #42 (коды 401/403/404 теряются в `error.code`). Frontend разбирает ошибки по HTTP-статусу, поведение не меняется.
  - Журнал `media-2026-09-23.log` пишется с новым кодом. Строк `[REDACTED]` после переключения нет: последняя — 07:56 UTC, из загрузки до выкатки.
- **Frontend.** `switch-frontend.sh`: прежний образ `morefoto-frontend:issues47-20260923073920-aee6808` в `frontend-before.txt`, новый образ на 2/2 задачах.
- **Smoke frontend.**
  - `/health`, `/cabinet/users`, `/cabinet/orders`, `/cabinet/links`, `/cabinet/institutions/…/photos` — 200.
  - SHA-256 отдаваемого `index.html` совпадает с образом.
  - Отдаваемый `PhotoWorkspacePage-D2h4Bv1S.js` содержит «Повторить все», «Не загрузилось превью», `status:"ready"`, «Показано».
- **Откат.** Backend: `docker service rollback` для четырёх сервисов (прежний релиз `issues47-20260923073920-aee6808`, спецификации в `services-before.json`). Frontend: `docker service rollback morefoto_frontend` (образ из `frontend-before.txt`).
- Запись сделана из временного worktree от `origin/main` (`/home/user/rabit-api-worktrees/deploy-record-60`), общий checkout не затронут.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда / доказательство |
|---|---|---|---|
| T01 | PASS | 2026-09-23 | `PhotoPipelineDiagnosticsTest::testDiagnosticRecordsSurviveTheCommonLogSanitizer` |
| T02 | PASS | 2026-09-23 | `DiagnosticLogSanitizerTest` (4 теста), `W01LogSanitizerTest` без изменений зелёный |
| T03 | PASS | 2026-09-23 | `DeliveryUseCasesTest::testPublishFailureRecordsSurviveTheCommonLogSanitizer`, `EmailLeadNotifierTest::testAcceptedLeadRecordSurvivesTheCommonLogSanitizer` |
| T04–T07 | PASS | 2026-09-23 | `PhotoListContractTest` (10 тестов с провайдером), `morefoto.media/tests/Unit` 58/58 |
| T11 | PASS (backend) | 2026-09-23 | phplint 849 файлов OK; PHPStan `[OK] No errors`; PHPUnit 584/584 (2776 assertions); CS Fixer dry-run 0 из 16 и 0 из 6 файлов |
| T08 | PASS | 2026-09-23 | `tests/commerce/photo-paging.test.mjs` (3 теста) в `npm run test:commerce` 182/182 |
| T09 | PASS | 2026-09-23 | `tests/commerce/photo-previews.test.mjs` (7 тестов) в `npm run test:commerce` 182/182 |
| T10 | PASS | 2026-09-23 | `npm run check` exit 0 (eslint, vue-tsc, tsc e2e) |
| T12 | PASS | 2026-09-23 | gate 4: сценарий «#54/#55»: один GET списка с `groupId/status/page/pageSize`, страница в URL, reload, разметка — один GET страницы, возврат со страницы съёмки |
| T13 | PASS | 2026-09-23 | gate 4: пик превью ≤ 6 по Resource Timing, однократный сбой восстановлен без «Повторить», повторный визит без повторной загрузки |
| T14 | PASS | 2026-09-23 | gate 4: 390 px без горизонтальной прокрутки, скриншоты desktop/mobile |
| T15 | PASS | 2026-09-23 | mock BDD `@r08`: 24 scenarios / 48 steps passed, 3 мин 06 с (`npm run e2e:server` + `TS_NODE_PROJECT=tsconfig.e2e.json npx cucumber-js --config cucumber.mjs --tags @r08` в контейнере Playwright с сетью, том `rabit-issues545557-node`) |
| T17 | PASS | 2026-09-23 | `make test-e2e …` на base `2cc2360` + diff, head `52d1b50`: 79/79, 242,6 с |
| T18 | PASS | 2026-09-23 | gate 4: «#55: превью прежней сессии отменяются при выходе…»; stub-контроль на коде до исправления воспроизводит сброс сессии |
| T16 | PENDING | — | после деплоя |
