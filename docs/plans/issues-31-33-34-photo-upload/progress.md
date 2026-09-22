# Issues #31, #33 и #34 — журнал

## Точка продолжения

- Дата: 2026-09-22.
- Ветка: `codex/issues-31-33-34-photo-upload`, upstream снят до первого push.
- Worktree: `/home/user/rabit-api-worktrees/issues-31-33-34-photo-upload`. Основной checkout `/home/user/rabit-api` остаётся на `main`.
- Base: `5b750c0` (`origin/main`, включает PR #45 и запись его деплоя).
- Issues: [#31](https://github.com/rebit-pro/rabit-api/issues/31), [#33](https://github.com/rebit-pro/rabit-api/issues/33), [#34](https://github.com/rebit-pro/rabit-api/issues/34) — OPEN. PR ещё нет.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md).
- Завершено: разведка пайплайна, чтение production-агрегатов, решения пользователя, план.
- Сейчас: backend #34 и frontend #33/#31 реализованы, быстрые проверки зелёные. Идёт mock BDD фото.
- Следующий шаг: результат mock BDD → opt-in бенч → commit, push, PR.
- Блокеров нет. Открыто: согласие пользователя на opt-in замер 50 кадров (T13) и на чтение production-логов после деплоя (T15).
- Рабочее дерево: изменения backend `morefoto.media` и frontend `photos` не закоммичены. Пустые `api/vendor` и `api/var` — точки монтирования для проверок, в git не попадают.

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

## Результаты тест-кейсов

| ID | Статус | Дата | Команда / доказательство |
|---|---|---|---|
| T01–T05 | PASS | 2026-09-22 | PHPUnit `PhotoPipelineDiagnosticsTest` (5 тестов) в полном прогоне 516/516 |
| T06–T10 | PENDING | — | — |
| T11 | PASS | 2026-09-22 | `npm run check` exit 0, `npm run test:commerce` 169/169 |
| T12 | PASS | 2026-09-22 | phplint OK, PHPStan No errors, PHPUnit 516/516, CS Fixer применён |
| T13 | PENDING | — | нужно согласие пользователя |
| T14 | PENDING | — | после review |
| T15 | PENDING | — | после деплоя, нужно согласие |
