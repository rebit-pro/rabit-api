# D3 — прогресс

## Точка продолжения

- Ветка `codex/d3-stage-media-recovery`, base E4 merge `7e606e53cc6b347e5c7b70e217ab7f8eb8a45875`, HEAD `1a04eb3`; draft PR #32, issue массовой загрузки #31.
- Завершено: stage получил E4, исправления DI, очередь, nginx 413 и логи. Авторизованная загрузка пользователя сохранила два оригинала; оба затем упали на обработке из-за отсутствия WebP в старом stage PHP image.
- Сейчас: три пользовательских кадра готовы; 404 миниатюр устранён mount `/app/public/upload` в stage FPM. Защищённые GET после обновления FPM завершились `RESPONSE` в live логе. Следующий шаг: сохранить журнал в PR и передать пользователю путь галереи.
- Риски: остальные файлы первого пакета были прерваны; готовые кадры ещё не привязаны к ребёнку, ссылка группы ещё не передана. Stage images временно основаны на локальном D1 build с отключённым Xdebug, для последующего релиза нужны production images. Основной site_* не менялся.
- Рабочее дерево: после commit `a1f9a86` изменяется только этот журнал. D3-DI/QUEUE/LOG/HTTP-LIMIT/F1-INVALID/VISUAL/WEBP/RECOVERY/PREVIEW — PASS; повторная ручная загрузка полного пакета пользователем ещё PENDING.

## Хронология

### 2026-09-21
- Авторизованный POST со скриншота подтвердил приём `IMG_0590.jpg` и `IMG_0591.jpg`: записи ID 1/2 и оригиналы в приватном хранилище есть. Прочие файлы пакета не завершили отправку. Ручной `app:media:dispatch-pending --limit=10` отправил две задачи; consumer исчерпал три попытки, статус обеих — `failed`/`PROCESSING_FAILED`.
- Диагностика `GdPreviewRenderer::render` в stage CLI: `Image renderer is unavailable`; `function_exists('imagewebp')=no` в старых stage CLI/FPM images, в локальных D1 CLI/FPM images — yes. В stage FPM pool `clear_env=no`, это не причина задержки. План расширен до обновления images и восстановления оригиналов.
- `bash backup-d3.sh` на stage: новая копия БД `database-before-d3-recovery.sql.gz`, gzip/SHA и содержимое проверены; 39264 байт. D3-RECOVERY пока PENDING.
- `docker save ... | gzip | ssh ... docker load`: stage получил CLI/FPM `d3-webp`, gzip проверен. `docker run ... php -r 'function_exists("imagewebp")'`: yes в обоих images. Обновлены только `morefoto_stage_media_consumer` и `morefoto_stage_fpm`; создан `morefoto_stage_media_dispatcher` с минутным `dispatch-pending`, отдельным stage vhost и прежними mounts/secret. `docker exec php -r ...` в трёх контейнерах: webp=yes; D3-WEBP PASS.
- У stage images включён Xdebug; для FPM, consumer и dispatcher задан `XDEBUG_MODE=off`, сервисы сошлись. Требуется отдельный production build в плановом релизе.
- После проверенной копии БД SQL update строго двух `failed` записей с оригиналами: `ROW_COUNT()=2`, статус переведён в `processing/pending`, попытки обнулены. `docker exec ... docker-entrypoint.sh php ... app:media:dispatch-pending --limit=10`: опубликовано 2. Контроль SQL: обе записи `ready/done`, `UF_ATTEMPTS=0`, thumb/preview заданы; четыре WebP файла существуют и ненулевого размера. D3-RECOVERY PASS.
- Публичный прямой URL `/upload/morefoto/previews/...` ответил 404: это ожидаемая защита в backend nginx; защищённый `/api/v1/photos/{id}/thumb` без Bearer отвечает 401. В БД у обоих готовых кадров 0 привязок к ребёнку, у группы `UF_SENT_AT IS NOT NULL=0`; публичная галерея пока на стадии подготовки.
- `docker service ls --filter name=morefoto_stage`: backend, FPM, consumer и dispatcher — все 1/1. Логи dispatcher показывают успешные проходы; повторный `dispatch-pending` публикует 0. Небольшой GD benchmark на исходном JPEG 3000×4500 в текущем consumer с `XDEBUG_MODE=off`: декодирование, масштабирование до 320 px и WebP за 0,95 с. Полная пользовательская передача 8 МБ зависит также от сети; повтор её после восстановления ещё не наблюдался.
- Новый скриншот показывает три готовых кадра (`IMG_0577.jpg`, `IMG_0591.jpg`, `IMG_0590.jpg`), но все миниатюры отвечают 404. В stage FPM логи `ManagedPreviewController::getAction` указывают `PreviewContent.php:23` (файл отсутствует по пути FPM). `docker service inspect morefoto_stage_fpm` подтвердил отсутствие mount `/app/public/upload`, тогда как consumer его имеет. `docker service update --mount-add type=bind,src=.../runtime/public/upload,dst=/app/public/upload morefoto_stage_fpm`: сервис сошёлся; `docker exec` проверил 3/3 WebP внутри нового FPM, размеры 16026/16628/16830 байт. Защищённый HTTP GET из пользовательской сессии после обновления ещё PENDING.
- `docker service logs --since 2m morefoto_stage_fpm | grep ManagedPreviewController`: после обновления FPM новые GET в 16:46:39, 16:46:43 и 16:46:44 UTC завершились `media.INFO: RESPONSE`, без `PreviewContent.php` 404. Это реальные запросы через HTTP, D3-PREVIEW PASS. PR #32 body обновлён описанием stage recovery; commit `a1f9a86` опубликован.

## Тест-кейсы

- D3-DI: PASS, 2026-09-21, `docker exec stage-fpm ... ServiceLocator::get(UploadPhotoUseCase::class)`, сервис разрешён.
- D3-HTTP-LIST: PASS, 2026-09-21, `make test-e2e ...`, реальный HTTP в изолированном fixture; пользовательский stage GET после исправления отображает кадры.
- D3-HTTP-UPLOAD: PASS, 2026-09-21, `make test-e2e ...` и пользовательский авторизованный POST на stage; два оригинала сохранены, после исправления обработки оба `ready`.
- D3-HTTP-LIMIT: PASS, 2026-09-21, `curl` 8 МиБ через app.morefoto36.ru без Bearer: 401 JSON вместо прежнего 413 HTML.
- D3-QUEUE: PASS, 2026-09-21, `docker service ls`, `app:media:dispatch-pending --limit=10`, SQL: 2 опубликованы и обработаны.
- D3-LOG: PASS, 2026-09-21, проверка stage `runtime/logs/logstash/media-2026-09-21.log`.
- D3-F1-INVALID: PASS, 2026-09-21, `make test-e2e ...`, код `A0001` отклонён в форме до POST.
- D3-VISUAL: PASS, 2026-09-21, Playwright desktop/mobile screenshots и ручной просмотр `docs/waves/d3/visual/`.
- D3-WEBP: PASS, 2026-09-21, `docker exec ... php -r 'function_exists("imagewebp")'` в stage FPM/consumer/dispatcher: yes.
- D3-RECOVERY: PASS, 2026-09-21, SQL read-only по ID 1/2: `ready/done`, 4 WebP файла ненулевого размера.
- D3-PREVIEW: PASS, 2026-09-21, `docker exec stage-fpm php -r 'is_file/filesize'`: 3/3 файла читаются; `docker service logs --since 2m morefoto_stage_fpm`: реальные защищённые GET завершились `media.INFO: RESPONSE` без 404.
- По скриншотам пользователя: девять POST файлов отклонены 413; экран кадров показывает `Cannot read properties of null (reading 'items')`.
- Stage FPM `ServiceLocator::get(UploadPhotoUseCase::class)` выбрасывает `MESSENGER_TRANSPORT_DSN не задан или пуст`; это ломает создание MediaController для GET и POST.
- В stage FPM отсутствуют DSN и media consumer. Основной site_rabbitmq существует; отдельного stage vhost нет.
- `gh issue create --body-file /tmp/morefoto-bulk-upload-issue.md`: #31 создан, ZIP/импорт по ссылке.
- Stage логи текущих HTTP-запросов видны через `docker service logs morefoto_stage_fpm`; каталог stage runtime/logs пуст. D3-LOG ещё PENDING до проверки настройки обработчиков.
- Создан RabbitMQ vhost `morefoto_stage`, права stage на него, stage FPM получил DSN через существующий Docker secret; пароль не выводился. `UploadPhotoUseCase`, `MediaPublisherInterface` и `AmqpConnectionFactory` через stage ServiceLocator — PASS.
- Создан `morefoto_stage_media_consumer` (1/1). Команда `app:media:consume` работает, очередь `mediaProcessing` в отдельном vhost существует. D3-QUEUE PASS по запуску и созданию очереди; фактическая обработка файла ещё PENDING.
- `curl` с телом 8 МБ на публичный POST вернул HTTP 413 до PHP. Frontend nginx не имел `client_max_body_size`; код изменён на 30m. Публичный повтор — после деплоя.
- Stage runtime не содержал `local/.settings_extra.php`; создана ссылка на конфиг релиза. После HTTP запроса появились `runtime/logs/logstash/media-2026-09-21.log` и `runtime/logs/bx_error.log`. D3-LOG PASS.
- Frontend `photosApi.list` теперь выдаёт понятную ошибку при `data:null` вместо обращения к `items`. Добавлен браузерный тест этого ответа.
- `docker run --rm -v "$PWD/frontend:/app" -w /app node:24-alpine sh -lc 'npm ci --no-audit --no-fund && npm run check && npm run build'`: PASS (lint, typecheck, e2e typecheck, build).
- Первый `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`: FAIL, 59/60 browser. Новый D3 тест использовал неоднозначный `getByRole('alert')`; продуктовые тесты прошли. Селектор уточнён до точного текста, нужен повтор.
- По новому скриншоту F1 `INVALID_ROW` для `A0001`: backend принимает код ребёнка `A` либо снимка `A001` (ровно три цифры). Frontend теперь подсвечивает поле до POST и отдельно объясняет код `INVALID_ROW`; browser spec расширен.
- Stage frontend nginx обновлён отдельным bind-шаблоном из текущего diff, syntax `nginx -t` PASS, сервис 2/2. `curl` POST с телом 8 МиБ через app.morefoto36.ru: до изменения 413 HTML; после изменения 401 JSON без Bearer. D3-HTTP-LIMIT PASS по устранению прокси-лимита; авторизованный upload ещё PENDING.
- Повтор `make test-e2e ...` выполняется; результат PENDING.
- Повтор `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`: browser 60/60 PASS (0 skipped/unexpected), PHPUnit 420/1608 PASS, frontend lint/typecheck/commerce/build PASS, PHPStan/PHP lint PASS, E4/F1 post-browser MySQL integration PASS; fixture остановлен. D3-HTTP-LIST/UPLOAD/F1-INVALID PASS на изолированном HTTP.
- `git diff --check`: PASS. Stage `curl` без Bearer подтверждает устранение 413 и разрешение MediaController, но не обработку файла от авторизованного пользователя. Пользователю направлен запрос повторить JPEG около 8 МБ.
- `git commit -m 'fix(morefoto): restore stage photo uploads and clarify handoff validation'`: `c9c7fc4`. SSH `git push` истёк по timeout; `gh auth setup-git` и HTTPS `git push` опубликовали ветку без раскрытия токена.
- Docs commit `a92910a` опубликован; `gh pr create --draft --base main --head codex/d3-stage-media-recovery --body-file /tmp/d3-pr-body.md`: PR #32 создан. Merge/deploy нового frontend image не выполнялись; live stage nginx исправлен bind-шаблоном.
- `make e2e-up ...` создал изолированный fixture `rabit-e2e-726c1040e2e8`; Playwright из контейнера прошёл фото-ошибку и F1 `A0001` на 1440×1000 и 390×844. Четыре PNG сохранены в `docs/waves/d3/visual/`; ручной просмотр подтвердил читаемость, отсутствие наложений и видимость ошибки F1 на mobile после прокрутки внутреннего диалога. D3-VISUAL PASS. `make e2e-down E2E_STATE=.../state.json`: PASS.
- Visual/docs commit `ec35ad8` опубликован в PR #32; `gh pr view 32 --json number,isDraft,state,headRefOid,baseRefOid,url`: draft OPEN, base `7e606e5`, head `ec35ad8`. `gh issue view 31`: OPEN. Все четыре stage-сервиса 2/2, 1/1, 1/1, 1/1; stage logstash содержит media, handoff, access, organization, cli файлы.
