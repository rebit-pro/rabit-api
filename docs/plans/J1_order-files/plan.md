# J1 — выдача купленных оригиналов и ZIP (ранний срез)

## Цель и контекст

Покупатель оплаченного заказа открывает заказ по личной ссылке. Там он скачивает купленные электронные фотографии: по одной или все сразу архивом ZIP. Файлы — оригиналы без водяного знака. Доступ открыт в течение календарного месяца от первой подтверждённой оплаты (D10). Ссылка на скачивание временная, и перед каждой выдачей сервер заново проверяет ключ, оплату, состав и срок.

- Ветка `codex/j1-order-files` от `main` `4fc9dce`; рабочая копия `/home/user/rabit-api-worktrees/j1-order-files`.
- Граф: `docs/waves/graph.json`, волна J1 (legacy W25). Канонический план: соседний `MoreFoto/docs/04-bitrix-modules/backend-waves.json`, API — `MoreFoto/docs/05-rest-api/endpoints.json` (FIL-01…04).
- Основание: оплата G1 проверена пользователем на stage 26.09.2026. Пользователь выбрал ранний срез J1 вместо цепочки G2 → I1 → I2 → I3 (решение J1-GRAPH).
- Активация: sandbox/stage с тестовым магазином. Реальные продажи — после N2.

## Решения

### Принято пользователем 26.09.2026

- **J1-GRAPH.** J1 зависит от D1, E5 и G1 (все слиты), а не от I3. Отзыв файлов по возврату (`files=keep/selected/all` из SET-04) и по решениям исполнения (SET-07) подключают I2/I3. Для этого они получают обязательство: повторно проверить FIL-01/04 и отозвать права в Files. До I2/I3 состояния `refund`/`revoked` не выдаются. Возврат, сделанный вручную в кабинете ЮKassa, доступ к файлам не закрывает — для sandbox это допустимо. `D12` остаётся у J1 только в узкой части G1-D12-SCOPE: поздняя оплата (`latePayment=true`) даёт `state=review` и файлов не открывает.

### Приняты пользователем 26.09.2026 (все — рекомендованный вариант)

- **J1-DEC-01. Состав права.**
  - Строка `digital` даёт свой кадр.
  - Строка `bundle` или ребёнок из `GIFTS` заказа даёт все `ready`-кадры этого ребёнка в съёмке заказа.
  - `physical` файлов не даёт.
  - Состав комплекта динамический: читается в момент запроса через `ChildPhotosInterface`, поэтому кадр, догруженный после оплаты, тоже попадает в комплект.
  - Кадр, удалённый сотрудником, пропадает из списка.
  - Отдельное неблокирующее issue: запрет удалять купленные кадры (`MediaMutationRepository::deletablePhotos` не учитывает заказы).
- **J1-DEC-02. Сборка ZIP.**
  - `kind=file` не собирается: загрузка сразу `ready`.
  - `kind=zip` собирается асинхронно: очередь RabbitMQ `filesArchive`, команда `app:files:consume`, сервис `api-files-consumer` по образцу media.
  - Архив хранится без сжатия (`CM_STORE`, JPEG уже сжат) в приватном каталоге `MOREFOTO_PRIVATE_FILES_PATH`.
  - Если архив с тем же составом уже готов и не истёк, он переиспользуется.
  - До 3 попыток, затем `failed`. Новый FIL-02 создаёт новую сборку, повторная оплата не нужна.
- **J1-DEC-03. Отдача байтов.**
  - FIL-03 в статусе `ready` отдаёт `contentUrl` с подписанным токеном: HMAC по ID загрузки и сроку, TTL 10 минут.
  - FIL-04 принимает `X-Order-Key` или этот токен и перед каждой отдачей повторно проверяет право.
  - Байты отдаёт nginx через `X-Accel-Redirect` из `internal` location. Для этого приватные каталоги media и files монтируются в nginx только на чтение.
  - Так скачивание идёт обычной ссылкой через менеджер загрузок браузера, работает докачка (Range), а PHP не держит поток на сотни мегабайт. Постоянного публичного URL нет.
- **J1-DEC-04. Числа.**
  - Лимит ZIP — сумма оригиналов не больше 2 ГиБ и не больше 500 файлов, иначе 413 `ARCHIVE_TOO_LARGE`.
  - Готовая загрузка живёт 24 часа, но не дольше срока права.
  - Токен ссылки действует 10 минут.
  - На заказ одновременно собирается не больше одного ZIP (409 `ARCHIVE_IN_PROGRESS` с ID текущей сборки).
  - Уборка просроченных архивов — cron раз в час.
- **J1-DEC-05. Срок ключа заказа.**
  - D07: ключ оплаченного заказа действует до конца срока файлов. При применении оплаты Commerce продлевает активный ключ: `EXPIRES_AT = max(EXPIRES_AT, filesAvailableUntil)`.
  - Миграция продлевает ключи уже оплаченных заказов.
  - Правило D10 (`paidAt` + календарный месяц, Europe/Moscow, с отсечкой по последнему дню месяца) живёт в `OrderCalendarPolicy` Commerce. Files получает готовую дату через контракт, чтобы ключ и файлы не расходились.

### Замечания ревью PR #143 (26.09.2026) и решения

- R1 (P1): подписанный URL попадал в access-лог frontend nginx (штатный `VITE_API_URL=/api`, запрос идёт через frontend-прокси). Путь `/api/v1/public/orders/current/downloads/` исключён из лога в production и development конфигурациях frontend nginx; регрессия — `tests/ui/private-links-log.test.mjs` и `FilesArchitectureTest`.
- R2 (P2): новый ключ, получивший переиспользованную готовую или собирающуюся загрузку, не сохранялся. Ключи вынесены в `mf_file_download_request` (ключ → хеш тела → загрузка); весь FIL-02 заказа выполняется под именованной блокировкой MySQL в одной транзакции (`OrderDownloadGuardInterface`). Повтор ключа возвращает закреплённую загрузку даже после изменения состава; другое тело — 409.
- R3 (P2): после `rename` архива сбой `markReady()` или падение процесса оставляли ZIP без пути в БД. Путь архива записывается в строку при создании загрузки; повтор сборки пишет по тому же пути, `failed`/`ready` с истёкшим сроком убирает purge вместе с `.tmp`.
- База обновлена на `main` с #135: в `VERIFIERS` сохранены `verify-photo-deletion.php` и `verify-files.php`.

## Scope

1. **Контракты `rebit.share`:**
   - `Contracts/Commerce/OrderEntitlementInterface` + DTO: заказ по ключу, `paymentStatus`, `paidAt`, `latePayment`, `filesAvailableUntil`, `shootId`, кадры digital-строк, дети с комплектом (bundle или gift).
   - `Contracts/Media/OriginalFilesInterface` + DTO: оригиналы по `photoId` (код, имя, mime, байты, путь хранения) с учётом `duplicate → existing`, только `ready`.
2. **Commerce:** реализация `OrderEntitlementInterface`, `filesAvailableUntil` в `OrderCalendarPolicy`, продление ключа при `paid`, миграция продления.
3. **Media:** реализация `OriginalFilesInterface` поверх `PhotoRepository` + `PrivatePhotoStorageInterface`.
4. **Новый модуль `morefoto.files`:**
   - Каркас: `install`, `.settings.php`, `di/`, `include.php`, `routes.php`, регистрация в `init.php`, `routes/rabit-api.php`, E2E `prepare.php`. `LogChannelEnum::FILES`.
   - Хранение, миграция foundation: таблица `mf_file_download` — PUBLIC_ID, ORDER_ID, KIND, STATUS, PHOTO_IDS (JSON), COMPOSITION_HASH, IDEMPOTENCY_HASH + REQUEST_HASH (уникальность по заказу), ARCHIVE_PATH, BYTES, FILENAME, ERROR, ATTEMPTS, EXPIRES_AT, READY_AT, CREATED_AT, UPDATED_AT.
   - Domain: состояние доступа (`unpaid/review/empty/expired/available`), правило состава, лимиты, переходы статуса загрузки.
   - Application:
     - UseCase `GetOrderFiles` (FIL-01), `RequestDownload` (FIL-02), `GetDownload` (FIL-03), `OpenDownloadContent` (FIL-04);
     - `BuildArchive` (consumer), `PurgeExpiredDownloads` (cron);
     - сервис `FileEntitlementResolver`.
   - Infrastructure: SQL-репозиторий, `ZipArchive`-сборщик, хранилище архивов, подпись токена, маппинг путей в `X-Accel-Redirect`, messenger-фабрика.
   - Presentation: чистый `PublicFileController`, `*RequestDto`, маппер; команды `app:files:consume`, `app:files:purge`.
5. **Share Infrastructure:** ответ `InternalRedirectResponse` (`X-Accel-Redirect`, `Content-Disposition`, `no-store`) в общей обвязке.
6. **Docker/nginx:**
   - `internal` locations и read-only mounts в nginx (dev/e2e/prod);
   - mount private-files в fpm, consumer и cron;
   - сервис `api-files-consumer` (compose + prod + Makefile);
   - строка cron уборки;
   - `Access-Control-Expose-Headers: Content-Disposition`.
7. **Frontend:** блок «Электронные фотографии» на странице заказа (`OrderLiveScreen`):
   - состояние и срок «доступно до…»;
   - список файлов с кнопкой «Скачать»;
   - «Скачать всё архивом» с опросом FIL-03 и переходом по `contentUrl`;
   - честные тексты для `unpaid/review/empty/expired`.
   - `orders/live/files-api.ts`, типы, вынесенные правила и unit-тесты. Alert «Электронные файлы подключаются отдельным этапом» заменяется.
8. **Документация и графы:**
   - J1 `inProgress`, зависимости и решения в обоих графах; обязательства отзыва в I2/I3; генераторы API/Postman канонического плана;
   - `docs/waves/j1/README.md`, `verification.json`, `visual.json`, `morefoto-contract.patch`;
   - `deploy/secrets/README.md` (ключ подписи), инструкция выкладки (новый сервис, mounts).

## Исключено

- Возвраты и отзыв файлов (I2/I3).
- Письмо со ссылкой (J2).
- Восстановление доступа и смена получателя (J3).
- Защита купленных кадров от удаления (issue #142).
- Превью-миниатюры в списке файлов, если для них нужен новый endpoint: покупатель видит код и имя кадра.
- Боевой магазин.

## Зависимости, риски и ограничения

- D1, E5, G1 слиты в `main`; I3 не требуется (J1-GRAPH).
- Выкладка на stage меняет инфраструктуру: nginx mounts, новый consumer, mount в cron. Нужен рецепт отката.
- Большие архивы: диск stage. Архивы удаляются по сроку, лимит 2 ГиБ на архив.
- Нельзя подставить произвольный путь: `X-Accel-Redirect` строится только из путей хранилища после `realpath`-проверки корня.
- Динамический состав комплекта: если сотрудник удалит или перенесёт кадр между оплатой и скачиванием, состав изменится. Готовый ZIP перед выдачей сверяется с текущим составом: при расхождении 409 `COMPOSITION_CHANGED`, и frontend предлагает собрать заново.

## Checklist

- [x] S0. План, progress, согласование J1-DEC-01…05.
- [x] S1. Графы: J1 dependsOn D1/E5/G1, обязательства I2/I3, проверка DAG/ID, patch канонического плана.
- [x] S2. Контракты Share + реализации Commerce/Media, продление ключа, миграция.
- [x] S3. Модуль `morefoto.files`: хранение, Domain, UseCase FIL-01…04, контроллер.
- [x] S4. Сборка ZIP: messenger, consumer, уборка, docker/nginx/cron.
- [x] S5. Frontend: блок файлов на странице заказа.
- [x] S6. Unit/architecture-тесты, PHPStan, cs-fixer, frontend check.
- [ ] S7. E2E-сценарий и verifier написаны; полный `make test-e2e` — после ревью без блокеров.
- [x] S8. Документация волны, PR.

## Критерии приёмки

- Неоплаченный заказ, чужой или неверный ключ, поздняя оплата без решения не дают ни одного оригинала.
- Оплаченный заказ отдаёт ровно купленные кадры: digital — свои, bundle или подарок — все кадры ребёнка. Физические строки файлов не дают.
- Одиночный файл и ZIP скачиваются настоящими оригиналами: байты совпадают с загруженными, водяного знака нет.
- На границе D10 доступ закрыт: `expired`, FIL-04 — 410.
- Токен ссылки не работает для другой загрузки, после истечения и после потери права.
- Повтор FIL-02 с тем же `Idempotency-Key` возвращает ту же загрузку, с другим телом — 409.
- Сбой или рестарт consumer не ломает сборку: повтор, затем `failed` и новая сборка без оплаты.
- Просроченные архивы удаляются с диска.

## Тест-кейсы

| ID | Предусловия / действие | Ожидаемый результат | Команда |
| --- | --- | --- | --- |
| J1-T01 | Граф после правки | DAG валиден, J1 готова из main, 118 ID, I2/I3 содержат обязательство отзыва | `python3 tools/verify-wave-graph.py`; канонический `wave_graph.py`, `validate.py` |
| J1-T02 | Unit D10 | 31.01 10:00 МСК → 28.02 10:00; високосный год; ровно на границе доступ закрыт | `make test` (PHPUnit) |
| J1-T03 | Unit состава | digital/bundle/gift/physical, duplicate-кадр, удалённый кадр | PHPUnit |
| J1-T04 | FIL-01 по состояниям | unpaid, pending, late → review, paid → available, пустой → empty, после срока → expired | PHPUnit UseCase + E2E |
| J1-T05 | FIL-02 валидация | file без ID или с 2 ID → 422; чужой photoId → 422 `PHOTO_NOT_ENTITLED`; неоплаченный → 409 `FILES_UNAVAILABLE`; повтор ключа → та же загрузка; другое тело → 409 | PHPUnit + E2E |
| J1-T06 | Лимиты ZIP | более 500 файлов или более 2 ГиБ → 413; вторая сборка при pending → 409 `ARCHIVE_IN_PROGRESS` | PHPUnit |
| J1-T07 | Сборщик ZIP | реальный архив из 3 файлов: состав, имена, байты совпадают; повтор после сбоя; `failed` после 3 попыток | PHPUnit (tmp dir) + E2E |
| J1-T08 | FIL-04 | заголовок `X-Accel-Redirect` только внутри корня; 410 после срока; 409 при изменении состава; токен чужой загрузки → 404; просроченный токен → 403 | PHPUnit |
| J1-T09 | Продление ключа | оплата продлевает ключ до `filesAvailableUntil`; миграция продлевает ключи оплаченных заказов | PHPUnit + verifier |
| J1-T10 | Architecture | контроллер без Bitrix/HttpRequest; Files не читает таблицы Commerce/Media; UseCase и сервисы с phpDoc | PHPUnit Architecture |
| J1-T11 | Уборка | просроченные архивы удаляются, статус `expired` | PHPUnit + E2E verifier |
| J1-T12 | Frontend unit | состояния блока, тексты, опрос статуса | `npm run check`, `npm run test:commerce` |
| J1-T13 | E2E браузер | оплата тестовой картой → блок файлов → скачать один файл и ZIP; содержимое сверяется с загруженными оригиналами; неоплаченный заказ — «после оплаты» | `make test-e2e` |
| J1-T14 | Визуальная проверка | блок файлов desktop/mobile во всех состояниях | `make test-e2e` скриншоты → `docs/waves/j1/visual.json` |
| J1-T15 | Verifier БД | `mf_file_download` согласована с диском; нет архивов вне корня | `verify-files.php` в gate |
