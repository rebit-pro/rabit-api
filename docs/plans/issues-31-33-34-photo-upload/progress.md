# Issues #31, #33 и #34 — журнал

## Точка продолжения

- Дата: 2026-09-22.
- Ветка: `codex/issues-31-33-34-photo-upload`, upstream `origin/codex/issues-31-33-34-photo-upload`.
- Worktree: `/home/user/rabit-api-worktrees/issues-31-33-34-photo-upload`. Основной checkout `/home/user/rabit-api` остаётся на `main`.
- Base: `bb35665` (`origin/main` на 2026-09-23, включает D3 и записи её деплоя). Прежний base — `5b750c0`.
- Issues: [#31](https://github.com/rebit-pro/rabit-api/issues/31), [#33](https://github.com/rebit-pro/rabit-api/issues/33), [#34](https://github.com/rebit-pro/rabit-api/issues/34) — OPEN, закроются merge PR (`Closes`).
- PR: [#47](https://github.com/rebit-pro/rabit-api/pull/47) MERGED 2026-09-23T07:38:51Z, merge commit `aee6808`; проверенный HEAD ветки — `4ddc1aa`.
- Production: релиз `issues47-20260923073920-aee6808`, backend 4/4 сервиса и frontend 2/2 на новом коде.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md).
- Завершено: разведка пайплайна, чтение production-агрегатов, решения пользователя, план.
- Сейчас: задача на production. Открыт только замер по реальной загрузке.
- Следующий шаг: пользователь загружает реальную съёмку, после чего снимаются p50/p95 по логам `media` (T15) и принимается решение о числе media workers. Открыт также opt-in бенч T13.
- Блокеров нет. Открыто: согласие пользователя на opt-in замер 50 кадров (T13) и на чтение production-логов после деплоя (T15).
- Рабочее дерево: закоммичено (`bdb7be8` #34, `0a273dd` #33/#31, `c536f85` docs и эта запись). Пустые `api/vendor` и `api/var` — точки монтирования для проверок, в git не попадают.

## Хронология

### 2026-09-22 — решения и разведка

- Пользователь взял в реализацию #31 и #33, затем добавил #34 («34 сюда же»).
- Read-only разведка через субагента, факты сверены выборочно:
  - `photoLimits.batch = 50`;
  - общий axios timeout 15000 без переопределения в upload;
  - publisher при занятом ключе дедупа выходит молча, dispatcher затем ставит `published`;
  - `catch (\Throwable) {}` в `UploadPhotoUseCase`.
- Решение пользователя по #31 (AskUserQuestion): «Снять лимит 50, без ZIP».
- Production, только чтение через `ssh rebit-pro`:
  - dispatcher: последние 454 строки лога (с 14:09 UTC), 113 запусков, 0 публикаций;
  - `MESSENGER_TRANSPORT_DSN` задан в спецификации FPM (проверено только имя переменной);
  - агрегаты `b_hlbd_mf_photo` за 7 дней: 8 фото 21.09, `ready/done`; до 5 с — 0, 5–40 с — 3, больше 40 с — 5, максимум 878 с.
- Worktree: `git worktree add -b codex/issues-31-33-34-photo-upload /home/user/rabit-api-worktrees/issues-31-33-34-photo-upload origin/main`, затем `git branch --unset-upstream`.

### 2026-09-22 — реализация

- #34 backend (`morefoto.media`):
  - `UploadPhotoUseCase`: logger `media`, этапы `publish`/`markPublished`, только классы исключений (и `previous`), длительности приёма, phpDoc;
  - `DispatchPendingPhotoJobsUseCase`: изоляция строк, порог 45 с, `DispatchPendingOutputDto(published, failed)`, phpDoc;
  - команда выводит `Опубликовано задач: N, ошибок: M.`;
  - `PhotoRepository::pendingJobs(limit, minAgeSeconds)` с `PENDING_SECONDS`, `processingJob()` с возрастом в SQL (без часовых поясов PHP);
  - `ProcessPhotoMessageHandler`: `sinceAcceptedSeconds`, `sinceQueuedSeconds`, `decodeMs`, `thumbMs`, `previewMs`, попытка, мегапиксели;
  - `PreviewOutputDto` расширен полями замеров, `GdPreviewRenderer` их заполняет;
  - DI: `Log::channel(LogChannelEnum::media)` для трёх сервисов.
- #33/#31 frontend (`photos`):
  - `usePhotoQueue`: пул 2 POST, статус `uploading`, отслеживание не больше 2 запросов в секунду с интервалом 2 → 5 → 10 с;
  - «Обработка задерживается» через 10 минут вместо ошибки;
  - запись в `sessionStorage` не чаще раза в секунду и сразу при смене статуса;
  - обновление списка не чаще раза в 5 с, пауза, лимит 2000;
  - `api.ts`: таймаут загрузки 300 с, прогресс 0–100;
  - `UploadQueue`: подписи, `v-memo`, неопределённый индикатор обработки. `PhotoUpload`: «Пауза», «В работе», текст лимита.
- Live E2E `zz-media.spec.ts`: партия из 5 валидных и 1 битого PNG. Проверки:
  - не больше 2 одновременных POST;
  - не меньше 3 POST завершаются до первой проверки статуса;
  - «Ошибка» только у битого файла;
  - после reload принятый кадр доходит до «Готово» без нового POST.

  Уникальные PNG получаются добавлением чанка `tEXt` (CRC32 в спеке).
- Инцидент: инструмент записи превратил `'\u0000'` в сырые NUL-байты в `usePhotoQueue.ts`. Ключ заменён на `JSON.stringify([...])`. Скан `src`/`e2e` — сырых NUL нет.
- Backend-проверки в `rabit-api-php-cli:d1-local` (vendor-volume `rabit-issues313334-vendor`, `composer dump-autoload`, монтирование как в `tools/run-browser-e2e.py`):
  - phplint 802 файла OK;
  - PHPStan `[OK] No errors` (сначала 4 ошибки в тесте: `expects()` на хелпере с типом `final`-класса, хелпер убран);
  - PHPUnit OK (516 тестов, 2423 assertions, без notice после `createStub`);
  - php-cs-fixer применён к изменённым файлам.
- Frontend: `npm run lint:fix` exit 0; `npm run check` exit 0 (после замены индексации `checkDelays` из-за `noUncheckedIndexedAccess`); `npm run test:commerce` 169/169.

- Opt-in бенч `frontend/e2e/live/zz-media-bench.spec.ts`:
  - 50 JPEG 6000×4000 с подмешанным шумом генерируются в браузере через OffscreenCanvas;
  - отчёт `media-bench.json`: время передачи и путь «принят → готово» (p50/p95), общее время, параллельность;
  - исключён из обычного прогона через `testIgnore`, потому что гейт падает при `skipped > 0`;
  - `tools/run-browser-e2e.py` передаёт `E2E_MEDIA_BENCH`/`E2E_MEDIA_BENCH_COUNT` в контейнер браузера.
- Mock BDD фото: `TS_NODE_PROJECT=tsconfig.e2e.json npx cucumber-js --config cucumber.mjs --tags @r08` при `npm run e2e:server` в контейнере Playwright.
  - С `--network none` все 24 сценария падают на логине (остаётся `/login`), это окружение: код авторизации ветка не трогает.
  - С сетью: 24 scenarios passed, 48 steps passed.
  - Позиционный путь к feature не заменяет `paths` из `cucumber.mjs`, поэтому фильтр — только через `--tags`.
- Логирование: на стенде нет ошибок файлового обработчика Monolog (FPM и media). На production в канал `media` уже пишет `LoggerFilter`, новые записи не добавляют точек отказа.

### 2026-09-23 — обновление base и путь к production

- Пользователь: задачу довести до production.
- Состояние production до выкатки (чтение): frontend `morefoto-frontend:d3-20260922191601-21311db`, backend-сервисы монтируют `/srv/morefoto/releases/d3-20260922191601-21311db/app`, логи и upload — из общего runtime `stage-20260919-b2-d788622`. Изменений #47 там нет.
- `morefoto_stage_media_consumer` показывал 0/1: это не авария. Команда `app:media:consume` живёт до 300 с, штатно завершается и перезапускается Swarm, между циклами реплика на несколько секунд пустая.
- `git merge origin/main` (`bb35665`) в ветку: один конфликт в `frontend/src/modules/morefoto/photos/api.ts` — D3 добавила интерфейсы переноса ребёнка там же, где мой `uploadTimeout`. Сохранено и то и другое, merge-коммит `f935ea1`.
- php-cs-fixer по всем изменённым PHP (74 файла, включая пришедшие из D3): 0 требующих правок.
- Запущен полный `make test-e2e` на обновлённом base.

- Первый прогон gate упал: в сценарии #33 `maxInFlight` показал 3 при лимите 2. Причина — в тесте: параллельность считалась по событиям Playwright `request`/`requestfinished`, а они доставляются в тест в собственном порядке. Исправлено: перекрытие вычисляется из сетевых таймингов каждого запроса (`request.timing()`), тот же приём применён в бенче. Код приложения не менялся.
- Повторный полный `make test-e2e`: exit 0, `expected` 77, `unexpected` 0, `flaky` 0, 5 мин 10 с. Сценарий «#33: партия отправляется по два файла…» — PASS (9,9 с), остальные media-сценарии PASS.

### 2026-09-23 — merge и деплой на app.morefoto36.ru

- **Merge.** `gh pr merge 47 --merge --match-head-commit 4ddc1aa…` — MERGED, `aee6808`. Issues #31/#33/#34 закрылись по `Closes`.
- **Сборка релиза** из merge-коммита: `git archive aee6808 api` (845 КБ) и образ `morefoto-frontend:issues47-20260923073920-aee6808` с `VITE_API_MOCKS_ENABLED=false`. В образе есть строки новой очереди.
- **Подготовка на сервере.** `services-before.json` для пяти сервисов сохранён. `prepare-release.sh`: распаковка, проверка маркерного файла `DispatchPendingOutputDto.php`, совпадение `composer.lock` с предыдущим релизом, vendor скопирован оттуда же, созданы точки монтирования. Итог — `app` 411 МБ.
- **Backend.** `switch-backend.sh` (только `--mount-add`, проверка источника `/app` и файла внутри контейнера): `morefoto_stage_fpm`, `morefoto_stage_backend`, `morefoto_stage_media_consumer`, `morefoto_stage_media_dispatcher` — все переключены, код виден в контейнерах. Миграций нет, схема БД не менялась, дамп не делался.
- **Smoke backend.** `/health` 200, `/api/v1/me` 401 JSON. `app:media:dispatch-pending --limit=5` в контейнере dispatcher: `[OK] Опубликовано задач: 0, ошибок: 0.` — новый формат вывода и DI с логгером работают.
- **Frontend.** `switch-frontend.sh`: прежний образ `morefoto-frontend:d3-20260922191601-21311db` записан в `frontend-before.txt`, новый загружен, сервис 2/2.
- **Smoke frontend.** `/health`, `/cabinet/users`, `/cabinet/orders`, `/cabinet/links` — 200; SHA-256 отдаваемого `index.html` совпадает с файлом в образе; отдаваемый чанк `PhotoWorkspacePage` содержит новый статус «Загружается оригинал».
- **Журнал.** `runtime/logs/logstash/media-2026-09-23.log` пишется; записи `Photo upload accepted.` и `Photo previews ready.` появятся после первой реальной загрузки.
- **Откат.** Backend: `docker service rollback` для четырёх сервисов (прежний релиз `d3-20260922191601-21311db`). Frontend: `docker service rollback morefoto_frontend` (образ из `frontend-before.txt`).
- **Инцидент процесса.** Запись деплоя сначала ушла коммитом в чужой checkout `/home/user/rabit-api`, переключённый на ветку `codex/design-ux-plan`. Коммит снят через `git reset --soft` с восстановлением только своего файла; незакоммиченные правки соседней сессии не тронуты. Запись сделана из отдельного worktree от `origin/main`.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда / доказательство |
|---|---|---|---|
| T01–T05 | PASS | 2026-09-22 | PHPUnit `PhotoPipelineDiagnosticsTest` (5 тестов) в полном прогоне 516/516 |
| T06–T08 | PASS | 2026-09-23 | полный `make test-e2e` 77/77; сценарий партии с битым файлом и reload — PASS |
| T09 | PASS | 2026-09-22 | mock BDD `--tags @r08`: 24/24 сценария, 48/48 шагов (с сетью) |
| T10 | PENDING | — | лимит 2000 — константа `photoLimits.batch`, проверка выбором >50 файлов в live E2E/бенче |
| T11 | PASS | 2026-09-22 | `npm run check` exit 0, `npm run test:commerce` 169/169 |
| T12 | PASS | 2026-09-22 | phplint OK, PHPStan No errors, PHPUnit 516/516, CS Fixer применён |
| T13 | PENDING | — | нужно согласие пользователя |
| T14 | PASS | 2026-09-23 | `make test-e2e` exit 0 на base `bb35665` |
| T15 | PENDING | — | после реальной загрузки: чтение `media-*.log` на production |
