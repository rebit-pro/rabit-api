# Issues #54, #55 и #57 — большая съёмка: постраничный список, надёжные превью и видимая диагностика

## Цель и контекст

Все три дефекта найдены 2026-09-23 на production после первой загрузки съёмки на 85 кадров (релиз `issues47-20260923073920-aee6808`).

- [#54](https://github.com/rebit-pro/rabit-api/issues/54): рабочее место фотографий выкачивает все страницы MED-02 и рисует карточку на каждый кадр.
- [#55](https://github.com/rebit-pro/rabit-api/issues/55): большинство превью показывает «Кадр не загрузился», хотя сервер отвечает за p95 302 мс.
- [#57](https://github.com/rebit-pro/rabit-api/issues/57): общий `LogSanitizer` вырезает диагностику media из #34, поэтому на production нет записей `Photo upload accepted.` и `Photo previews ready.`.

Объём «#54, #55, #57 одной веткой» задал пользователь 2026-09-23. Это issue-ветка, не продуктовая волна: `docs/waves/graph.json` не меняется, прецеденты — #29, #45, #47.

- Ветка: `codex/issues-54-55-57-large-shoot`.
- Worktree: `/home/user/rabit-api-worktrees/issues-54-55-57-large-shoot`.
- Base: `origin/main` `5f658e5` (включает PR #47 и запись его деплоя).

## Установленные факты (main 5f658e5)

**#54, frontend** (`frontend/src/modules/morefoto/photos`):
- `usePhotoWorkspace.refreshPhotos()` листает MED-02 по 100 записей, пока не наберёт `meta.total` (до 1000 страниц), и оставляет на клиенте только `ready`. Этот цикл запускается при открытии, после каждой разметки и на каждое `photosChangedEvent` очереди загрузки.
- От полного списка зависят счётчики «Кадров / Детей / Без ребёнка», коды детей в фильтре, `nextChildCode`, миниатюра обложки, предпросмотр набора и `expectedPhotoIds` переноса.

**#54, backend** (`morefoto.media`):
- MED-02 `GET /api/v1/shoots/{shoot_id}/photos` уже фильтрует на сервере `groupId`, `childCode`, `assigned` и отдаёт `page`/`pageSize` (до 100) и `meta.total`.
- Список содержит кадры всех статусов. Повторная загрузка создаёт постоянную строку `duplicate`, сбой — `failed`. Поэтому без серверного фильтра статуса страницы клиента заполнялись бы неравномерно, а `meta.total` расходился бы с числом карточек.
- Сводки по группе (коды детей, счётчики) в API нет.
- `MediaController` — «загрязнённый» контроллер: ручной `MediaRequestFactory` над `HttpRequest`, свои фильтры и exception mapping. Правило CLAUDE.md запрещает менять такой контроллер без приведения к чистой границе.

**#55** (`PhotoImage.vue`, `GalleryImage.vue`, `src/api/http.ts`):
- Защищённое превью (`/api/v1/photos/{id}/thumb|preview`) загружается XHR через общий axios: общий таймаут 15 с, без очереди, повтора и кеша.
- XHR стартует сразу при монтировании. Атрибут `loading="lazy"` на `<img>` для него ничего не меняет, поэтому 85–1000 запросов уходят одновременно.
- Production за Traefik с TLS, то есть скорее всего HTTP/2. Тогда узкое место не 6 соединений браузера, а очередь FPM-воркеров: время ожидания в ней входит в клиентские 15 с. Ограничение параллельности на клиенте решает оба случая.
- Любая ошибка сразу показывает «Кадр не загрузился». Object URL отзывается при размонтировании, поэтому возврат на экран всё перекачивает.
- `PhotoImage` используют также заказы (`OrderDownloads`, `OrderComposition`), корзина (`CartLineItem`) и заявки сотрудников (`RequestReview`). Публичная галерея родителей отдаёт незащищённые URL и грузится нативным `loading="lazy"`.

**#57** (`rebit.share/lib/Infrastructure/Logger/LogSanitizer.php`):
- `CommonLoggerProcessor` применяется ко всем каналам: сообщение не из `MESSAGES` заменяется на `[REDACTED]`, ключ не из `FIELDS` выбрасывается.
- Под это попадают все 6 сообщений media и их ключи. Ключ `status` принимает только HTTP-код 100–599, поэтому строковый статус фото выбросится даже после добавления сообщения.
- Тот же дефект в `rebit.notification` (H1): `Notification operation remains pending after publish failure.`, `… after recovery publish failure.`, `Заявка передана почтовому транспорту` и ключи `operationId`, `exception`, `event`.

## Решения

- **D1** (пользователь, 2026-09-23). Список переносится в новый чистый `PhotoListController` по правилам CLAUDE.md. MED-02 расширяется обратно совместимо:
  - query `status` (`processing|ready|failed|duplicate`);
  - в ответе `summary` = `{photos, unassigned, children}` для группы запроса.
  Остальные действия `MediaController` не меняются. Для них заводится follow-up issue.
- **D2** (пользователь, 2026-09-23). Вырезаемые записи `rebit.notification` исправляются в этой ветке, тем же механизмом и тем же тестом.
- **D3.** `summary` считает только готовые кадры группы, независимо от фильтров страницы. Без `groupId` поле `summary` = `null`.
- **D4.** Размер страницы 48: полные ряды на 2, 3, 4 и 6 колонках. `page` и `filter` хранятся в query URL, значения по умолчанию не пишутся. Выбор кадров относится к текущей странице и сбрасывается при смене страницы, фильтра или группы.
- **D5.** Загрузчик защищённых превью:
  - общая очередь на 6 одновременных запросов;
  - собственный таймаут 30 с на попытку;
  - один автоматический повтор через ~1 с при сетевой ошибке, таймауте, 5xx, 408, 429;
  - дедупликация одинаковых URL;
  - LRU-кеш object URL до 64 МБ на время жизни SPA, очищается при смене access token;
  - ленивая загрузка по IntersectionObserver с запасом 300 px;
  - `eager` (крупный кадр предпросмотра) встаёт в начало очереди.
  Локальные превью demo-режима (IndexedDB) остаются как есть.
- **D6.** В записи `Photo upload accepted.` ключ `status` переименовывается в `photoStatus`: общий `status` в санитайзере означает HTTP-код.
- **D7.** Нечисловые `page`/`pageSize` теперь дают `VALIDATION_FAILED` (строгий слой запроса), как у других чистых списков. Выход за диапазон по-прежнему `INVALID_PAGE`, остальные коды ошибок сохраняются.
- **D8.** Порядок выкатки: backend, затем frontend. Новый frontend шлёт `status=ready`, старый backend ответил бы `UNKNOWN_FIELD`.
- **D9.** Каноническое описание MED-02 в `../MoreFoto/docs/05-rest-api` (не под git) в ветке не правится. Дельта (query `status`, поле `summary`) фиксируется в PR, перенос — после merge по поручению.

## Scope

### #57 — санитайзер логов

1. `LogSanitizer::MESSAGES`: 6 сообщений media и 3 сообщения notification.
2. `LogSanitizer::FIELDS` с типовой проверкой значений:
   - UUID: `photoId`, `operationId`;
   - метки (`label()`): `stage`, `exception`, `previous`, `event`;
   - `photoStatus` — одно из `processing|ready|failed|duplicate`;
   - `published` — bool;
   - неотрицательные int: `bytes`, `revision`, `attempt`, `inspectMs`, `storeMs`, `registerMs`, `publishMs`, `decodeMs`, `thumbMs`, `previewMs`, `sinceAcceptedSeconds`, `sinceQueuedSeconds`, `pendingSeconds`;
   - `megapixels` — неотрицательное конечное число.
3. `UploadPhotoUseCase`: `status` → `photoStatus`. Запись о приёме — 8 ключей при `MAX_FIELDS` 32.
4. Тесты:
   - реальные вызовы логгера media (приём, сбой публикации, dispatcher, готовность и сбой обработки) проходят через `CommonLoggerProcessor` без потерь;
   - посторонний ключ выбрасывается, незнакомое сообщение даёт `[REDACTED]`;
   - то же для `QueueEmailUseCase`, `DispatchPendingEmailUseCase`, `EmailLeadNotifier`.

### #54 — постраничный список

**Backend:**
1. `PhotoListController::listAction(ListPhotosRequestDto)` на `AuthenticatedApiJsonController`.
   - `ListPhotosRequestDto` (`#[StrictRequest]`, `RouteParameter shoot_id`);
   - `PhotoListInputMapper` с прежними кодами `INVALID_GROUP`, `INVALID_CHILD_CODE`, `INVALID_ASSIGNED_FILTER`, `INVALID_PAGE` и новым `INVALID_STATUS`;
   - `PhotoListResultMapper` → `PhotoPageResultDto`. `meta` остаётся внутри `data`, как сейчас.
2. `routes.php`: GET списка → новый контроллер. Из `MediaController` и `MediaRequestFactory` удаляется `listAction`/`listing()`.
3. `ListPhotosInputDto.status`. `PhotoRepository::photos()/count()` получают фильтр статуса, `childCodes()` — коды детей группы с готовыми кадрами.
4. `ListPhotosUseCase` собирает `summary` через `PhotoGroupSummaryOutputDto`. Class-level phpDoc обновляется.
5. DI `di/media.php`. Миграций нет: индекс `ix_mf_photo_shoot (UF_SHOOT_ID, UF_GROUP_ID, ID)` покрывает выборку.
6. Тесты:
   - маппинг и коды ошибок;
   - передача `status` и сборка `summary` в UseCase;
   - публичные ключи сериализованного ответа;
   - архитектурная граница контроллера.

**Frontend:**
1. `photosApi.list(shootId, query)` с `groupId`, `childCode`, `assigned`, `status`, `page`, `pageSize` и типом `summary`.
2. `usePhotoWorkspace`:
   - один запрос текущей страницы со `status=ready` и серверным фильтром;
   - `page`/`filter` в URL;
   - счётчики, коды детей и `suggestedCode` из `summary`;
   - обложка по `covers[groupId]` без поиска в списке;
   - при выходе за последнюю страницу номер поджимается;
   - после разметки, обложки, переноса и события очереди перечитывается только текущая страница.
3. Предпросмотр набора и «Перенести весь набор» загружают кадры одного ребёнка по требованию (`childCode`, страницы по 100).
4. `PhotoCollection`: «Показано N из M», `v-pagination` (компактная на мобильном), прокрутка к началу списка при смене страницы, индикатор загрузки без снятия сетки.
5. Demo-режим: та же страница и сводка считаются локально чистой функцией (`paging.ts`).
6. Unit-тесты: локальная страница и сводка, свободный код ребёнка.

### #55 — надёжная загрузка превью

1. `preview-loader.ts` — чистое ядро без Vue и axios: очередь, повтор, дедупликация, LRU-кеш, отмена ожидающих.
2. `previews.ts` — подключение ядра к axios (таймаут 30 с, `Accept: image/webp`), `URL.createObjectURL`, токену сессии. Там же общий счётчик неудачных превью и «Повторить все».
3. `PhotoImage.vue`: IntersectionObserver, загрузка через очередь, object URL не отзывается при размонтировании (им владеет кеш).
4. `GalleryImage.vue`: регистрирует неудачу в общем счётчике и реагирует на «Повторить все». Кнопка «Повторить» на карточке остаётся.
5. `PhotoCollection`: «Повторить все (N)», пока есть неудачные превью.
6. Unit-тест ядра: предел параллельности, повторы, дедупликация, кеш, отмена, приоритет, вытеснение, очистка.

### E2E (написать; запуск — по команде пользователя после оптимизации E2E)

- Live `zz-media.spec.ts`:
  - группа из 50 кадров через API: один запрос списка с `status=ready&page=1&pageSize=48`, «Показано 48 из 50»;
  - переход на страницу 2 пишет `page=2` в URL и переживает reload;
  - разметка на странице 2 перечитывает только её;
  - не больше 6 одновременных запросов превью по `request.timing()`;
  - однократный 503 превью восстанавливается без «Повторить»;
  - desktop и 390 px без горизонтальной прокрутки.
- Mock BDD `photos.feature` `@r08`: существующие сценарии с новыми подписями.

### Исключено

- Виртуализация списка, выбор диапазона и групповая разметка по папкам: это #56, продуктовое обсуждение.
- Остальные действия `MediaController` (upload, detail, assignment, cover) — follow-up issue.
- Публичная галерея родителей: незащищённые URL и нативная ленивая загрузка.
- Число media workers — по замеру T16 (закрывает T15 из #47).

## Риски и ограничения

- **R1.** Ленивая загрузка меняет поведение `PhotoImage` в заказах, корзине и заявках. Изображение вне области просмотра начнёт грузиться только при приближении. Для крупного кадра в диалоге задаётся `eager`.
- **R2.** Отзыв вытесненного object URL не ломает уже отрисованный `<img>`. Повторное монтирование перекачает кадр.
- **R3.** Переход на новый контроллер меняет только класс, обрабатывающий маршрут. Контракт проверяется unit-тестом сериализации и live E2E. Отличие для малформированного ввода — в D7.
- **R4.** До команды пользователя E2E не запускаются. Браузерные, визуальные и интеграционные кейсы остаются PENDING, merge блокирован до gate.
- **R5.** Выкатка: backend раньше frontend (D8). Миграций нет.

## Checklist

- [x] Разведка кода и production-фактов из issues.
- [x] Решения пользователя D1, D2.
- [x] Worktree и ветка от `origin/main` `5f658e5`, upstream снят.
- [ ] #57: санитайзер, `photoStatus`, тесты media и notification.
- [ ] #54 backend: чистый контроллер списка, `status`, `summary`, DI, routes, тесты.
- [ ] #55 frontend: ядро загрузчика, подключение, `PhotoImage`/`GalleryImage`, «Повторить все», unit-тест.
- [ ] #54 frontend: страница, URL, сводка, наборы по требованию, demo-пейджинг, unit-тест.
- [ ] E2E-спеки live и mock BDD обновлены (без запуска).
- [ ] Быстрые проверки: phplint, PHPStan, PHPUnit, php-cs-fixer по изменённым PHP; `npm run check`, `npm run test:commerce`.
- [ ] Follow-up issue на остальные действия `MediaController`.
- [ ] Commit, push, PR в `main`.
- [ ] По команде пользователя: полный `make test-e2e`, desktop/mobile.
- [ ] Review, merge и деплой (backend → frontend) по поручению.
- [ ] После деплоя: записи media на production, закрытие замера #34.

## Критерии приёмки

1. На съёмке из 300+ кадров открытие экрана делает один запрос списка (`page`, `pageSize=48`, `status=ready`, фильтр группы).
2. Номер страницы и фильтр сохраняются при reload и при возврате на экран. После разметки обновляется только текущая страница, прокрутка не сбрасывается.
3. Счётчики «Кадров / Детей / Без ребёнка», коды детей, обложка, предпросмотр и перенос набора верны для группы целиком при любом номере страницы.
4. Одновременно выполняется не больше 6 запросов превью. Однократный сетевой сбой восстанавливается без нажатия «Повторить». Возврат на экран не перекачивает уже показанные превью.
5. «Повторить все» перезапускает все неудачные превью на странице.
6. В `media-*.log` видны записи о приёме и готовности кадра с длительностями, при сбое публикации — этап и класс исключения. Посторонние ключи вырезаются, незнакомое сообщение даёт `[REDACTED]`.
7. Desktop и 390 px без горизонтальной прокрутки.

## Тест-кейсы

| ID | Предусловия | Действие | Ожидаемый результат | Команда |
|---|---|---|---|---|
| T01 | Реальные вызовы логгера media | Записи через `CommonLoggerProcessor` | Сообщение и ключи сохранены, `previous: null` отброшен | PHPUnit `morefoto.media` |
| T02 | Контекст с посторонним ключом, не-UUID `photoId`, отрицательными мс, объектом в `exception` | `LogSanitizer::context` | Недопустимое выброшено, `redacted: true`. Незнакомое сообщение → `[REDACTED]` | PHPUnit `rebit.share` |
| T03 | Сбой publisher в очереди писем; письмо лида передано транспорту | Записи через `CommonLoggerProcessor` | Сообщения и `operationId`, `exception`, `event` сохранены | PHPUnit `rebit.notification` |
| T04 | `ListPhotosRequestDto` с неверными значениями | `PhotoListInputMapper::list` | `INVALID_GROUP`, `INVALID_CHILD_CODE`, `INVALID_ASSIGNED_FILTER`, `INVALID_STATUS`, `INVALID_PAGE` | PHPUnit |
| T05 | Группа с готовыми кадрами | `ListPhotosUseCase` | `status` передан в выборку и счёт; `summary` = готовые, без ребёнка, коды детей; без группы `summary` null | PHPUnit |
| T06 | Результат страницы | Сериализация `PhotoPageResultDto` | Ключи `items, groups, covers, revision, meta, summary{photos, unassigned, children}` | PHPUnit |
| T07 | `PhotoListController` | Архитектурная проверка | Нет Bitrix, HttpRequest, фабрик, фильтров и exception mapping; канал `media` | PHPUnit |
| T08 | Demo-состояние на 3 страницы | `localPhotoPage` | Страница, `total`, сводка и фильтры как у сервера | `npm run test:commerce` |
| T09 | Ядро загрузчика | Параллельные загрузки, сбои, отмена, повтор URL | ≤ предела, один повтор для 5xx, без повтора 404, одна загрузка на URL, кеш, отмена ожидающих, приоритет, LRU, очистка | `npm run test:commerce` |
| T10 | Ветка | Линт и типы | exit 0 | `npm run check` |
| T11 | Изменённые PHP | Статика и тесты | phplint OK, PHPStan 0 ошибок, PHPUnit зелёный, CS Fixer без правок | docker `rabit-api-php-cli:d1-local` |
| T12 | Live, группа из 50 готовых кадров | Открыть экран, перейти на стр. 2, reload, разметить кадр | Один GET списка на экран, `page=2` в URL и после reload, после разметки один GET текущей страницы, счётчики из `summary` | live E2E `zz-media.spec.ts` (PENDING) |
| T13 | Live, 48 карточек; первый ответ одного превью 503 | Открыть страницу | ≤ 6 одновременных превью по таймингам, все превью видимы, «Кадр не загрузился» нет | live E2E (PENDING) |
| T14 | Live | Desktop 1440 и 390 px | Скриншоты, нет горизонтальной прокрутки | live E2E (PENDING) |
| T15 | Mock BDD `@r08` | Сценарии фото | Зелёные | `npm run test:e2e:run -- --tags @r08` (PENDING) |
| T16 | Production после деплоя, реальная загрузка | Чтение `media-*.log` | Есть `Photo upload accepted.` и `Photo previews ready.` с длительностями | чтение логов (с согласия) |
| T17 | Полный gate | `make test-e2e` | PASS, desktop/mobile | по команде пользователя |
