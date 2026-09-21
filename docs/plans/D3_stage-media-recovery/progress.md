# D3 — прогресс

## Точка продолжения

- Ветка `codex/d3-stage-media-recovery`, base E4 merge `7e606e53cc6b347e5c7b70e217ab7f8eb8a45875`; runtime commit `c9c7fc4`, текущий HEAD — `headRefOid` draft PR #32 (`gh pr view 32 --json headRefOid`). Issue массовой загрузки: #31.
- Завершено: E4 развёрнут на app.morefoto36.ru; подтверждены HTTP 413 и ошибка DI; корень DI — пустой MESSENGER_TRANSPORT_DSN stage FPM.
- Сейчас: ожидание подтверждения авторизованной загрузки на stage. Следующий шаг: дождаться ответа пользователя по JPEG 8 МБ и завершить review PR #32.
- Риски: основной RabbitMQ общий инфраструктурно; stage сообщения находятся в отдельном vhost. Авторизованный HTTP upload на stage ещё не проверен, пользователь получил запрос на повтор.
- Рабочее дерево: чистое после публикации визуальных артефактов и этого журнала. D3-DI/QUEUE/LOG/HTTP-LIMIT/F1-INVALID/VISUAL — PASS; HTTP-LIST/UPLOAD — PASS в изолированном real E2E, PENDING на stage с авторизацией.

## Хронология

### 2026-09-21
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
