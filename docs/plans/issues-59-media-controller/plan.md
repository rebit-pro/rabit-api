# Issue #59 — чистая DTO-граница для MED-03…06

## Цель и контекст

[#59](https://github.com/rebit-pro/rabit-api/issues/59) — follow-up правила CLAUDE.md о ранее слитых загрязнённых контроллерах. В #54 (PR #60) список MED-02 вынесен в чистый `PhotoListController`. Остальные действия `MediaController` модуля `morefoto.media` остались в прежнем виде:

- MED-03 `POST /api/v1/shoots/{shoot_id}/photos` — загрузка кадра (202);
- MED-04 `GET /api/v1/photos/{photo_id}` — статус кадра;
- MED-05 `POST /api/v1/groups/{group_id}/photo-assignments` — разметка;
- MED-06 `PUT /api/v1/groups/{group_id}/cover` — обложка.

Это issue-ветка, не продуктовая волна: `docs/waves/graph.json` не меняется.

- Ветка: `codex/issues-59-media-controller`.
- Worktree: `/home/user/rabit-api-worktrees/issues-59-media-controller`.
- Base: `origin/main` `5dcb0e0` (merge PR #84; ветка создана от `d92b4c4` и перебазирована до push, пересечений с #84 нет).

## Установленные факты (main d92b4c4)

- `MediaController` наследует `BaseJsonController` и сам собирает технологию: `MediaRequestFactory` над `HttpRequest` (multipart, JSON, route через `Application::getCurrentRoute()`), `BearerTokenFilter` и `LoggerFilter` в `configureActions()`, свой `getExceptionResponse()` и `Cache-Control: no-store` в `finalizeResponse()`.
- Все четыре действия создаются одним конструктором с `UploadPhotoUseCase`. Он требует `MediaPublisherInterface` → AMQP-транспорт (`MESSENGER_TRANSPORT_DSN`), поэтому без брокера падает и `GET` статуса кадра (`docs/plans/OPS-stage-media-recovery`).
- `MediaRequestFactory` используется только `MediaController`.
- Общая обвязка `AuthenticatedApiJsonController` уже даёт Bearer (401 `UNAUTHORIZED`), `LoggerFilter`, `ApiJsonExceptionResponse` и `no-store`. JSON-путь `RequestToDtoMapper` + `RequestHelper::collectJsonRequestValues` + `StrictRequestValues` отдаёт те же коды, что и `MediaRequestFactory::json()`: `JSON_REQUIRED` (query или не JSON), `PAYLOAD_TOO_LARGE` (32 KiB), `MALFORMED_JSON`, `VALIDATION_FAILED` (не объект, неверный wire-тип), `UNKNOWN_FIELD` (лишнее или отсутствующее поле).
- Общего механизма для multipart «файл + текстовые поля» нет:
  - `RequestImageToDtoMapper` (аватары) запрещает любые поля формы и отдаёт `ONE_IMAGE_REQUIRED`/`IMAGE_UPLOAD_FAILED`;
  - `RequestFileToDtoMapper` (`FileController`) требует `moduleId` и отдаёт текстовые исключения.
- `StrictRequestValues` для `@var string[]` отклоняет нестроковый элемент кодом `VALIDATION_FAILED`, а MED-05 для такого элемента `photoIds` отдаёт `INVALID_PHOTO_IDS`. Нетипизированный массив в `DtoMetadataService` выражается только через `@var array[]`.
- Статус 202 с телом общий API контроллера не оформляет: есть `json()`, `createdJson()`, пустой `accepted()`.
- Прежний exception mapping `MediaController` для непредвиденного сообщения давал `MEDIA_REQUEST_FAILED`; общий — `VALIDATION_FAILED` (422) или `SERVICE_UNAVAILABLE`. После #42 (PR #70) источники отказа доступа бросают коды `UNAUTHORIZED`/`FORBIDDEN`/`NOT_FOUND`, остальные ошибки media — кодовые, поэтому видимых отличий нет. `MEDIA_REQUEST_FAILED` не используют frontend и E2E.

## Решения

- **R1. Три контроллера по зависимостям сценария.** Чтение не должно тянуть транспорт сообщений:
  - `PhotoUploadController::uploadAction` (MED-03) — `UploadPhotoUseCase`;
  - `PhotoDetailController::detailAction` (MED-04) — только `GetPhotoUseCase`;
  - `GroupMediaController::assignmentAction`/`coverAction` (MED-05/06) — `AssignPhotosUseCase`, `SetGroupCoverUseCase`.
  Имена action не меняются, пути в `routes.php` те же. `MediaController` и `MediaRequestFactory` удаляются.
- **R2. Multipart — общий минимальный маппер в `rebit.share`.** Только он может подключиться к автосборке `RequestParameterFactory`:
  - `RequestUploadDtoInterface` (Shared/Interface): DTO с одним файлом в поле `file`; маппер заполняет `tmpName`, `filename`, `bytes`;
  - атрибут класса `#[MultipartFile(missingCode, failedCode)]` — предметные коды «нет ровно одного файла» и «файл не принят PHP»;
  - атрибут параметра `#[FormField(errorCode, pattern)]` — текстовое поле формы: нестроковое, отсутствующее при не-nullable параметре или не совпавшее с `pattern` значение → `errorCode` (422). Это аналог `RouteParameter(pattern, errorCode)`;
  - `RequestUploadToDtoMapper`: `MULTIPART_REQUIRED` → файл → поля формы в порядке параметров → route/headers (`RequestTechnicalValues`) → `ArrayToDtoMapper`. Незаявленные поля формы и query игнорируются, как в `MediaRequestFactory` (контракт не ужесточается);
  - чтение multipart-тела (POST — списки Bitrix, иначе `request_parse_body()`) выносится из `RequestImageToDtoMapper` в internal `MultipartRequestBody` без изменения поведения аватаров.
  Так порядок проверок MED-03 совпадает с прежним: `MULTIPART_REQUIRED` → `ONE_PHOTO_REQUIRED` → `PHOTO_UPLOAD_FAILED` → `GROUP_REQUIRED` → `INVALID_FINGERPRINT` → `INVALID_ROUTE`. `is_uploaded_file()` не добавляется: прежний MED-03 его не проверял, а размер и содержимое проверяет `PhotoFileInspector`.
- **R3. `mixed[]` — нетипизированный список.** `DtoMetadataService` понимает `@var mixed[]` как `ARRAY` (элементы не приводятся), чтобы `photoIds` MED-05 проверял элементы в presentation mapper и сохранял `INVALID_PHOTO_IDS`. Остальные типы не затрагиваются.
- **R4. `acceptedJson()`** добавляется в общий `CreatedJsonTrait` рядом с `createdJson()`: 202 с телом, без `setStatus()` в concrete controller.
- **R5. Presentation mappers.** `PhotoInputMapper` (`upload()`, `assignment()`, `cover()`, `key()`) и `PhotoResultMapper` (`upload()`, `photo()`, `assignment()`, `cover()`); все проверки тела MED-05/06 и коды — прежние. Для загрузки добавляется `UploadPhotoInputDto`, `UploadPhotoUseCase::execute(int $userId, UploadPhotoInputDto $input)`.
- **R6. Result DTO повторяют прежние тела поле в поле:** `UploadPhotoResultDto`, `PhotoResultDto` (вложенные `assignments` — `PhotoAssignmentOutputDto`, как `PhotoPageResultDto::items` в MED-02), `PhotoAssignmentsResultDto`, `GroupCoverResultDto`. Unit-тест сравнивает сериализацию result DTO с сериализацией прежнего output DTO.
- **R7. MED-04 без `#[StrictRequest]`.** Прежний MED-04 игнорировал query; строгий DTO ответил бы 422 `UNKNOWN_FIELD`. Нестрогий `RequestToDtoMapper` отбрасывает лишние поля так же. Ужесточение — отдельное решение пользователя. MED-05/06 — `#[JsonBody]` + `#[StrictRequest]`, как MED-07.
- **R8.** Порядок проверок MED-05: тело (`VALIDATION_FAILED`, `INVALID_PHOTO_IDS`) раньше ключа идемпотентности (`INVALID_IDEMPOTENCY_KEY`), как в `MediaRequestFactory`. Параметры маршрута теперь проверяются до тела (`INVALID_ROUTE`); это различие видно только при одновременно неверных пути и теле, у маршрутов Bitrix пути всегда непустые.
- **R9.** Архитектурные тесты новых контроллеров — через `Rebit\Share\Tests\Support\CleanControllerSource`, плюс проверка, что конструкторы `PhotoDetailController` и `GroupMediaController` не зависят от `UploadPhotoUseCase`/`MediaPublisherInterface`. Live E2E не меняются.

## Scope

1. `rebit.share`: `RequestUploadDtoInterface`, атрибуты `MultipartFile`/`FormField`, `RequestUploadToDtoMapper`, `MultipartRequestBody` (+ перевод `RequestImageToDtoMapper`), регистрация в `RequestParameterFactory`, `mixed[]` в `DtoMetadataService`, `acceptedJson()`.
2. `morefoto.media`:
   - request DTO `UploadPhotoRequestDto`, `PhotoRequestDto`, `AssignPhotosRequestDto`, `SetGroupCoverRequestDto`;
   - result DTO (R6), `PhotoInputMapper`, `PhotoResultMapper`, `UploadPhotoInputDto`;
   - три контроллера, `routes.php`, `di/media.php`; удаление `MediaController`, `MediaRequestFactory`;
   - `UploadPhotoUseCase` — новый вход, обновить `PhotoWorkflowTest`, `PhotoPipelineDiagnosticsTest`.
3. Тесты: unit маппинга и кодов (input/result), маппер multipart, `mixed[]`, архитектура контроллеров.

### Исключено

- Изменение путей, статусов, тел и кодов MED-03…06; ужесточение query у MED-04 (R7) и лишних полей формы у MED-03.
- Перевод `RequestImageToDtoMapper`/аватаров на новый маппер.
- Frontend и live E2E (ожидания не меняются).

## Риски и ограничения

- Unit-тесты используют стабы Bitrix; реальный разбор multipart и маршрутов проверяет только live E2E (`zz-media`, `zzzzzz-transfers`, `zzzz-links`, также `zzz-handoff` использует MED-05). По правилу пользователя полный `make test-e2e` запускается после review — до этого PENDING.
- `CreatedJsonTrait` и `DtoMetadataService` общие; изменения аддитивны.
- Непредвиденные текстовые исключения теперь получают общий код (`VALIDATION_FAILED`/`SERVICE_UNAVAILABLE`) вместо `MEDIA_REQUEST_FAILED` — как у всех чистых контроллеров (прецедент MED-02, PR #60).

## Checklist

- [x] S1. План и прогресс.
- [x] S2. `rebit.share`: multipart-маппер, атрибуты, `mixed[]`, `acceptedJson()`, тесты.
- [x] S3. `morefoto.media`: DTO, mappers, `UploadPhotoInputDto`, контроллеры, routes, DI, удаление `MediaController`/`MediaRequestFactory`.
- [x] S4. Unit и архитектурные тесты media.
- [x] S5. Быстрые проверки: PHPUnit, PHPStan, phplint, php-cs-fixer по изменённым файлам.
- [ ] S6. Коммиты, push, PR в `main` (`Closes #59`), без merge.
- [ ] S7. После review — полный `make test-e2e` (PENDING).

## Критерии приёмки

- Concrete controllers MED-03…06 проходят `CleanControllerSource`; `MediaController` и `MediaRequestFactory` удалены.
- Коды и статусы прежние: `MULTIPART_REQUIRED` 400, `ONE_PHOTO_REQUIRED`/`PHOTO_UPLOAD_FAILED`/`GROUP_REQUIRED`/`INVALID_FINGERPRINT` 422, `JSON_REQUIRED` 400, `PAYLOAD_TOO_LARGE` 413, `MALFORMED_JSON` 400, `VALIDATION_FAILED`/`INVALID_PHOTO_IDS`/`UNKNOWN_FIELD` 422; загрузка 202; `no-store`; отказы `UNAUTHORIZED`/`FORBIDDEN`/`NOT_FOUND`.
- Тела ответов MED-03…06 совпадают с прежними сериализациями output DTO.
- `PhotoDetailController` не зависит от транспорта сообщений.
- PHPUnit, PHPStan, phplint, php-cs-fixer — зелёные.

## Тест-кейсы

| ID | Предусловие / действие | Ожидаемый результат | Проверка |
|---|---|---|---|
| T01 | multipart-маппер: не multipart Content-Type | 400 `MULTIPART_REQUIRED` | PHPUnit `RequestUploadToDtoMapperTest` |
| T02 | нет файла / два файла / поле не `file` | 422 `ONE_PHOTO_REQUIRED` | PHPUnit |
| T03 | ошибка загрузки PHP или `file[]` | 422 `PHOTO_UPLOAD_FAILED` | PHPUnit |
| T04 | `groupId` отсутствует, не UUID или массив | 422 `GROUP_REQUIRED` | PHPUnit |
| T05 | `fingerprint` массив | 422 `INVALID_FINGERPRINT`; отсутствует → `null` | PHPUnit |
| T06 | корректный запрос с лишним полем формы и маршрутом | DTO с `tmpName`/`filename`/`bytes`/`groupId`/`shootId`, лишнее поле проигнорировано | PHPUnit |
| T07 | `PhotoInputMapper::upload`: пустой `fingerprint` → `null`, верхний регистр → нижний | `UploadPhotoInputDto` | PHPUnit `PhotoMediaContractTest` |
| T08 | `assignment`: неверные shootId/revision/пустой или >100 `photoIds`/childCode | 422 `VALIDATION_FAILED` | PHPUnit |
| T09 | `assignment`: дубль, не UUID, нестроковый элемент `photoIds` | 422 `INVALID_PHOTO_IDS` | PHPUnit |
| T10 | `cover`: revision < 1, photoId не UUID | 422 `VALIDATION_FAILED` | PHPUnit |
| T11 | `key`: неверный ключ идемпотентности | 422 `INVALID_IDEMPOTENCY_KEY` | PHPUnit |
| T12 | result DTO MED-03…06 | JSON совпадает с сериализацией прежних output DTO | PHPUnit |
| T13 | `@var mixed[]` в strict JSON-DTO | элементы не приводятся, объект вместо списка → `VALIDATION_FAILED` | PHPUnit |
| T14 | архитектура `PhotoUploadController`, `PhotoDetailController`, `GroupMediaController` | нет нарушений `CleanControllerSource`; чтение и разметка без `UploadPhotoUseCase`/`MediaPublisherInterface`; канал логов `media` | PHPUnit |
| T15 | `acceptedJson()` и отсутствие `setStatus` в concrete controller | загрузка отвечает общим helper | PHPUnit (архитектурный тест) |
| T16 | статический анализ и стиль | PHPStan OK, phplint OK, php-cs-fixer 0 файлов | docker-команды из progress |
| T17 | live E2E `zz-media`, `zzzzzz-transfers`, `zzzz-links`, `zzz-handoff` | зелёные без изменения ожиданий | `make test-e2e` после review |
