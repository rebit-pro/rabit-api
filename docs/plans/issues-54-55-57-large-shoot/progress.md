# Issues #54, #55 и #57 — журнал

## Точка продолжения

- Дата: 2026-09-23.
- Ветка: `codex/issues-54-55-57-large-shoot`, push в `origin` выполняется вместе с этой записью.
- Worktree: `/home/user/rabit-api-worktrees/issues-54-55-57-large-shoot`. Основной checkout `/home/user/rabit-api` занят другой сессией (`codex/design-ux-plan`), в нём не работать.
- Base: `origin/main` `5f658e5`.
- Issues: [#54](https://github.com/rebit-pro/rabit-api/issues/54), [#55](https://github.com/rebit-pro/rabit-api/issues/55), [#57](https://github.com/rebit-pro/rabit-api/issues/57) — закроются merge PR. Follow-up по `MediaController` — [#59](https://github.com/rebit-pro/rabit-api/issues/59).
- PR: открывается сейчас (номер — в следующей записи).
- Документация: [план](plan.md), [A8](../../waves/a8/README.md), предыдущий журнал [#47](../issues-31-33-34-photo-upload/progress.md).
- Завершено:
  - #57 (`4edc593`), backend #54 (`e2663c4`), frontend #55 (`e73c72d`), frontend #54 (`f727d24`), live E2E-сценарий (`d025312`);
  - все быстрые проверки и stub-проверка UI.
- Сейчас: PR и ожидание E2E.
- Следующий шаг: после merge PR #58 (оптимизация E2E) обновить base ветки до нового `main`, повторить быстрые проверки и по команде пользователя запустить полный `make test-e2e`.
- Блокер gate: пользователь сообщил, что оптимизация E2E в `main`, но на 2026-09-23 PR #58 ещё OPEN, `origin/main` = `5f658e5`. Gate до merge #58 не запускается: правило «актуальный main плюс собственный diff».
- Открыто: каноническое описание MED-02 в `../MoreFoto` (D9) — после merge.
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
| T12–T15, T17 | PENDING | — | E2E по команде пользователя |
| T16 | PENDING | — | после деплоя |
