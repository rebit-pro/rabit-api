# E4 — прогресс

## Точка продолжения

- 2026-09-21, перед merge: пользователь поручил закрыть review PR #30, переименовать раздел F1 по скриншоту, слить E4 и развернуть backend/frontend на app.morefoto36.ru. Ветка `codex/e4-private-storefront`, base `6b8164747e3ed3f6f73a7a91efc0d28b2eb365bc`, локальный/опубликованный runtime HEAD `d0a58a23f33a9fc4446c815de058b16961fe78b3`; PR #30 ready/CLEAN, оба треда закрыты. Правки и полный gate завершены: 59/59 browser, backend/frontend и real MySQL PASS; desktop/mobile F1/Gallery/Cart просмотрены. Сейчас — docs-only фиксация журнала. Следующий шаг — commit/push журнала, сверка HEAD PR и merge, затем stage backup/миграции/backend/frontend. Блокер деплоя: stage backend всё ещё старый, нужны отдельный бэкап stage DB и миграции F1/E4. В рабочем дереве перед docs-коммитом изменён только progress.md.

## Хронология

### 2026-09-21 — merge F1 и старт E4

- Пользователь разрешил merge и следующую волну; deployment не запрошен.
- Журнал повторного review сохранён docs-коммитом 1ac4c11 и отправлен в F1. Runtime совпадает с проверенным 74e44ea.
- Первая попытка gh pr merge: GraphQL Base branch was modified. Повторный fetch/gh pr view подтвердили неизменную базу 28bcad9; повтор gh pr merge 25 --merge --match-head-commit 1ac4c11e5eaf5d0935c5f994c27715c1a2826715 — PASS.
- gh pr view 25 --json state,mergedAt,mergeCommit: MERGED, 6b8164747e3ed3f6f73a7a91efc0d28b2eb365bc. git switch main; git merge --ff-only origin/main; git switch -c codex/e4-private-storefront — PASS.
- E4-BASE PASS по присутствию зависимостей в актуальном main и PR #25; отдельную автоматическую DAG проверку ещё не запускали.
- Найдено E4-DEC-01: D2 разрешает один photoId нескольким детям, API COM-09 не передаёт выбор ребёнка; SalesPolicy считает порог по childId. Это требует явного уточнения контракта.
- E4-CAPABILITY/MEDIA/QUOTE/STALE/ARCH/UI/GRAPH/PUBLISH: PENDING, тесты E4 не запускались. Тесты F1 не выдаются за покрытие E4.


### 2026-09-21 — первичная техническая сверка

- Прочитаны media routes, PrivatePhotoStorageInterface/LocalPrivatePhotoStorage, SalesPolicy и frontend GallerySnapshot/CartQuote. Публичных MED-01/COM-08/09 в коде ещё нет. Приватное хранилище существует; защищённую выдачу превью нужно реализовать в E4.
- `python3 tools/verify-wave-graph.py docs/waves/graph.json` и та же команда для ../MoreFoto/docs/04-bitrix-modules/backend-waves.json: exit 0, DAG/99 endpoints/negative fixtures PASS. Однако deliveryState устарел: E4 считается blocked, слитые D2/E3/F1 не отражены. E4-GRAPH остаётся PENDING по актуальности готовности. Перед кодом сверить merge receipts предшественников и обновить обе карты/генераторы по принятому порядку.
- E4-DEC-01 ожидает ответ пользователя. Независимый первичный разбор завершён. Перед commit документов `git diff --check` PASS; код не менялся, E4 runtime-проверки не запускались.

### 2026-09-21 — E4-DEC-01 согласовано

Пользователь подтвердил ID связи ребёнок—снимок. В плане зафиксировано assignmentId; MED-01 выдаёт его, COM-09/будущий COM-10 используют в строках. Сервер проверяет связь и область, выводит ребёнка/суммы. Соседний MoreFoto не является Git checkout; внешние изменения генераторов будут сохранены patch в ветке E4. Runtime ещё не менялся.


### 2026-09-21 — контракт и граф обновлены

- E4-DEC-01 PASS: build.py использует assignmentId/productId/quantity для COM-09 и будущего COM-10; MED-01 описывает выдачу assignmentId; assignment_id объявлен в окружениях Postman. Старый photoId сохранён для административных API.
- Подтверждены gh merge receipts C4 #16, B2 #18, D1 #19, E3 #20, D2 #21, H1 #22, F1 #25; git merge-base --is-ancestor каждого merge SHA HEAD — PASS. Графы синхронизированы, E4 inProgress; D02–D05 приняты по E3. Другие незамерженные волны не активированы.
- `python3 ../MoreFoto/docs/04-bitrix-modules/render-waves.py` и `python3 ../MoreFoto/docs/05-rest-api/build.py` — PASS, 40 волн / 99 API.
- `python3 tools/verify-wave-graph.py docs/waves/graph.json` — PASS: E4 единственная readyFromMain, DAG/99 API/negative fixtures PASS. Отдельная проверка Python равенства двух graph JSON — PASS.
- `python3 ../MoreFoto/docs/05-rest-api/validate.py` — PASS, registry/Markdown/Postman parity, 99 API. JSON Schema validation не запускалась.
- Первый `node ../MoreFoto/docs/05-rest-api/validate-postman.cjs` из rabit-api — FAIL: скрипт разрешает путь от cwd. Повтор `node docs/05-rest-api/validate-postman.cjs` из /home/user/MoreFoto — PASS: 144 scripts, 99 positive/198 negative fixtures, 43 idempotency checks, 14 platform guards.
- Дополнительная проверка Python: COM-09/COM-10 имеют ровно assignmentId/productId/quantity в строке, MED-01 описывает assignmentId — PASS.
- Точный diff двух внешних канонических источников сохранён в docs/waves/e4/morefoto-contract.patch. В MoreFoto обновлены также производные карты/Postman. Patch позволяет воспроизвести изменения вне Git; перед повторным применением проверять текущее состояние.
- E4-GRAPH PASS для текущего планирования; остальные E4 runtime/HTTP/UI проверки PENDING. Код приложения ещё не менялся. Найдены обязательные работы по UUID связи и закрытию прямой выдачи preview, добавлены в plan.
- Перед commit `git diff --check` PASS. Этот commit фиксирует согласованный контракт и готовность зависимостей, не объявляет продуктовую волну завершённой. PR пока не создаётся.

### 2026-09-21 — реализация связей

Начат runtime этап по указанию «Продолжи». Дерево чистое, ветка E4. Сначала mf_photo_assignment получает постоянный публичный UUID: backfill только отсутствующих значений, unique index, новые записи получают UUID в существующем write-пути. Повтор назначения сохраняет идентификатор. Следом capability lifecycle/галерея. Проверки пока PENDING.


- Реализованы migration assignment UUID/capability hash, внутренний lifecycle, разрешение галереи, DTO/mapper/controller, отдельные защищённые preview UseCase для capability и Bearer. Административные URL переведены на API; PhotoImage получает авторизованный Blob, nginx закрывает прямой каталог. COM-08/09 и live корзина ещё не реализованы.
- Адресный PHPUnit GalleryAvailabilityTest: PASS, 1 test/5 assertions; границы передачи/закрытия проверены. Первый PHPStan foundation PASS. После binary ответа PHPStan выявил 4 ошибки отсутствующего addHeader в stub; метод сверен с реальным Bitrix httpresponse.php:72, stub дополнен.
- Следующий шаг — подготовка реального E2E стенда для проверки схемы/DI/preview, параллельно продолжить серверный quote. Полная готовность E4 PENDING.

### 2026-09-21 — серверный quote и первый браузерный прогон

- Реализованы COM-08/09, хранение SHA-256 quote token, TTL 15 минут, fingerprint и повторная проверка через ValidateQuoteUseCase; транзакция SERIALIZABLE. Live UI использует серверный расчёт. DTO пассивны.
- Адресный PHPUnit (GalleryAvailabilityTest, StorefrontQuoteTest, StorefrontQuoteTokenTest, StorefrontArchitectureTest): PASS 14 tests / 94 assertions. PHPStan: PASS после дополнения проверенного stub HttpResponse::addHeader. Последующие изменения требуют повторного gate.
- Frontend check/build: сначала FAIL форматирования и TS possibly undefined; после eslint --fix и корректировки теста PASS. Логи /tmp/e4-frontend.log и /tmp/e4-stan.log. php-cs-fixer применён к 67 изменённым PHP, /tmp/e4-style.log.
- `make e2e-up` с указанными выше images/kernel/vendor: PASS, изолированный стенд rabit-e2e-ca1b3ab556a7. Seed сначала выявил отсутствующий DI GalleryGroupInterface, неверное поле PhotoRegistration и пропущенные миграции в явном списке; исправлены, повтор seed PASS. Fixture capability хранится только в игнорируемом var.
- Реальные HTTP GET галерей: open/preparing/closed 200 с ожидаемым состоянием, revoked 404; preview 200 image/webp. Полное E4-CAPABILITY/MEDIA пока PENDING до свежего gate.
- `npm run test:e2e:live -- zzzz-storefront.spec.ts` в Playwright-контейнере: FAIL 6/6. Quote возвращает Bitrix 500; desktop/mobile не находят карточку, retry — состояние корзины. E4-QUOTE/UI FAIL; диагностика продолжается. Готовность PR не заявляется.

### 2026-09-21 — адресные проверки исправлений

- Причина 500: Commerce include.php не подключал поставщиков GalleryAccessInterface/StaffEligibilityInterface. Явные зависимости добавлены; отдельный DI resolve PASS.
- Повтор browser `/tmp/e4-browser2.log`: 3 PASS / 3 FAIL; HTTP исправлен, UI тест кликал перекрытый input Vuetify, retry выдавал Network Error. Исправлены клик по видимому полю и сообщение сетевой ошибки.
- `npm run check` + production build после eslint --fix: PASS (/tmp/e4-frontend2.log). Адресный browser `/tmp/e4-browser3.log`: PASS 6/6, desktop/mobile корзина и повтор после сетевого сбоя.
- PHPStan: PASS (/tmp/e4-stan2.log). php-cs-fixer изменённых local PHP: PASS, исправлены 2/66 (/tmp/e4-style2.log).
- `docker exec rabit-e2e-ca1b3ab556a7-fpm php /app/tools/e2e/verify-storefront.php`: PASS — свежий quote, отсутствие сырых ключей в snapshot, другая галерея/состав/цена/версия фото/assignment/TTL/отзыв отклоняются; повтор назначения и миграции сохраняет assignmentId.
- URL capability теперь добавляет presentation mapper; persisted snapshot не содержит ключей. Staff provider оборачивает ошибки; явный false eligibility отклоняется. Добавлены unit-кейсы false eligibility и digital+bundle.
- Перед свежим полным gate добавлена проверка Referrer-Policy HTML: nginx add_header в location index.html отменял наследование. Исправлено. Адресный PASS относится к предыдущему source; финальная проверка нового source PENDING.

### 2026-09-21 — первый полный gate

- `make test-e2e ...` (стенд rabit-e2e-71cd705d21e1): FAIL на E2E TypeScript. Новая переменная document для HTTP-ответа перекрыла DOM document в browser evaluate; переименована в navigation. ESLint/Vue typecheck до этого прошли. Стенд автоматически удалён, cleanupErrors=[]; backend/browser этого прогона не запускались.
- Предварительный rabit-e2e-ca1b3ab556a7 остановлен через run-browser-e2e.py down. Четыре визуальных снимка просмотрены: desktop 1280×900 и mobile 390×844, галерея/корзина, без переполнения и сломанных изображений.
- Новая проверка F1-порта в verify-storefront использует запись, созданную реальным browser workflow F1; проверяет подтверждение только в своей съёмке. Команда полного gate проверит её после браузера.
- Ошибка загрузки live-галереи приведена к понятному русскому сообщению. Повтор полного gate PENDING.

### 2026-09-21 — проверка границы DTO

- Полный PHPUnit отдельной командой docker ... vendor/bin/phpunit --colors=never: FAIL, 419 tests / 1597 assertions, один architecture failure. Причина: новая проверка захватила старый CalendarCommandInputDto из C4, где конструктор выполняет валидацию. Сам DTO не менялся в E4; это найденный долг вне scope.
- Architecture-проверка уточнена на все DTO новой витрины и конкретные новые межмодульные Gallery DTO. Это соответствует ответственности E4; существующий CalendarCommandInputDto требует отдельного исправления/переноса валидации. На ревью сообщить разработчику.
- Повтор полного gate уже устанавливает зависимости; backend-проверки ещё не начинались, они прочитают исправленный тест. Runtime E4 не изменён этим исправлением.

### 2026-09-21 — context без чтения фотографий

- GalleryAccessInterface::context проверяет capability, область и состояние; resolve дополняет его назначениями. Catalog и preview переиспользуют context, убраны лишняя выборка полного набора и дублированная авторизация. Новый GalleryContextOutputDto пассивный; GalleryContextTest запрещает запрос фотографий в этом пути.
- `docker run --rm --network none --entrypoint php --mount type=bind,source=/home/user/rabit-api/api,target=/app,readonly --tmpfs /app/var:rw,size=256m --workdir /app rabit-api-php-cli:d1-local vendor/bin/phpunit --colors=never` до context: PASS 419 tests / 1598 assertions (/tmp/e4-unit-final2.log). Context войдёт в полный gate; повтор отдельно не нужен при зелёном gate.
- php-cs-fixer local diff: 68 файлов, исправлены 2 (/tmp/e4-style3.log). Новые e2e PHP tools отдельно проверены тем же config с --path-mode=override (/tmp/e4-style-tools.log). Минимальный stub HttpResponse::addHeader сохранён без стороннего форматирования всего файла.
- Сверка main через gh api: 6b8164747e3ed3f6f73a7a91efc0d28b2eb365bc, base актуален. DAG/99 API/готовность PASS, E4 единственная готовая волна. Два предыдущих стенда остановлены, cleanupErrors=[].

### 2026-09-21 — завершение проверки по указанию пользователя, draft PR

- Пользователь: «Завершай проверку. Токены закончились. Делай PR». Дополнительные итерации остановлены после текущего прогона, публикуется draft с явным блокером.
- `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`: FAIL, стенд rabit-e2e-8caca4c75f2e, /tmp/e4-full-gate2.log.
- E4-ARCH PASS: PHP lint 649, PHPStan 0 errors, PHPUnit 420 tests / 1608 assertions. Frontend ESLint/Vue/E2E TypeScript/build PASS, unit 158 PASS. Style dry-run PASS: 0/68 local PHP требуют исправлений, /tmp/e4-style-final.log.
- E4-CAPABILITY/QUOTE/UI browser PASS по 6 новым сценариям. Полный browser 56 PASS / 1 FAIL / 0 skipped; zz-media.spec.ts:101 ожидает 200 от анонимного GET нового Bearer preview, получает корректный 401. E4-MEDIA общий регресс FAIL до обновления теста и повторной проверки.
- E4-STALE: ранний real MySQL PASS зафиксирован выше; после выделения context финальный post-browser verifier не запускался. Повтор PENDING; подтверждение F1 через реальный порт также PENDING. Нельзя переносить прежний PASS на финальный source.
- E4-GRAPH PASS (40 waves / 99 API), main не изменился. E4-PUBLISH draft, готовность к merge BLOCKED. Все три собственных стенда остановлены, cleanupErrors=[]. Stash F1 не затронут.

- Публикация: commit a44d9c8, push ветки codex/e4-private-storefront и `gh pr create --draft` — PASS. PR https://github.com/rebit-pro/rabit-api/pull/30. Merge/deployment не выполнялись. Следующий docs-only commit фиксирует ссылку и точку продолжения; рабочее дерево после его push должно быть чистым.

### 2026-09-21 — отдельный deployment main по запросу пользователя

- SSH повторно проверен: `rebit-pro` доступен, single-node Swarm Ready/Leader/Active.
- В production stage `app.morefoto36.ru` выкатлено только содержимое `origin/main` (`6b8164747e3ed3f6f73a7a91efc0d28b2eb365bc`), E4 draft PR #30 в образ не включён.
- Образ `morefoto-frontend:main-20260921113950-6b81647` собран и загружен в `/srv/morefoto/releases/`; rolling update `morefoto_frontend` завершён `completed`, 2/2 реплики. `/srv/morefoto/current` переключён на новый релиз.
- Smoke PASS: `https://app.morefoto36.ru/health` HTTP 200, главная страница HTTP 200, service image/update/replicas подтверждены удалённо. `/api/health` вернул ожидаемый 401 stage Basic Auth; backend/site не изменялись.
- Rollback: `IMAGE_TAG=stage-20260919-b2-d788622` через штатный `stage-remote.sh`/`stage.sh rollback`. Локальные архивы и временный worktree удалены, рабочее дерево чистое.

### 2026-09-21 — исправление режима frontend после smoke-проверки

- Пользователь обнаружил, что первый deployment main был собран с `VITE_API_MOCKS_ENABLED=true`. Причина подтверждена: bundle не делал backend-запросов.
- Пересобран `origin/main` с `VITE_API_MOCKS_ENABLED=false`: `main-live-20260921115621-6b81647`. Из-за недоступности pull `nginx:1.28-alpine` использован локально доступный `nginx:1.29-alpine`; исходники не менялись.
- Rolling update `morefoto_frontend` завершён на 2/2 репликах; `/srv/morefoto/current` переключён на `main-live-20260921115621-6b81647`. `/health` и главная страница 200.
- Bundle проверен удалённо: mock adapter отсутствует, присутствуют реальные `/api/v1/auth/login` и `/api/v1/me`. Предыдущий релиз `main-20260921113950-6b81647` остаётся доступным для rollback.

### 2026-09-21 — устранение Basic Auth на login

- Причина подтверждена сетевой проверкой: `site_api` возвращал `401` и `WWW-Authenticate: Basic realm="Restricted"`; предназначенный для stage `morefoto_stage_backend` на том же запросе возвращает предметный JSON `400` без Basic Auth.
- Frontend переведён на `API_UPSTREAM=http://morefoto_stage_backend` и подключён к внешней сети `morefoto-stage-private`; Traefik-сеть сохранена. Развёрнут релиз `main-live-api-20260921120900-6b81647`, 2/2 реплики.
- Smoke PASS: `curl -k -X POST -H 'Content-Type: application/json' --data '{}' https://app.morefoto36.ru/api/v1/auth/login` → HTTP 400 JSON `В запросе не были переданы поля: email`, заголовок `WWW-Authenticate` отсутствует; `/health` → 200.
- Rollback: релиз `main-live-20260921115621-6b81647` (live API с прежним upstream) либо `main-20260921113950-6b81647` (mock mode). Рабочее дерево после docs-коммита должно быть чистым.

### 2026-09-21 — ручной чек-лист актуализирован

- `docs/testing/manual-wave-checklist.md` обновлён: добавлены опубликованный stage frontend без mock, устранение Basic Auth, разделение локальных E2E-логинов и stage-учётных записей, а также границы ручной проверки E4.
- Проверено: локальный `organizer@example.invalid` с паролем из E2E-набора на `app.morefoto36.ru` не принимается (`Invalid credentials`); рабочие stage-логины в репозитории отсутствуют и не публикуются.
- Команда `git diff --check` — PASS. Коммит документа `dc1dd30`. Push ветки повторно зависает по таймауту 20–30 секунд; удалённый PR #30 пока содержит предыдущий docs-коммит.

### 2026-09-21 — stage-учётные записи

- По запросу пользователя созданы две активные Bitrix/Auth учётные записи в stage: `elpopova15@mail.ru` (Попова Елена Евгеньевна) и `vidmon83@vk.com` (разработчик), обе с ролью `organizer` и полным набором разрешений кабинета.
- Проверка реальным HTTP login → `/api/v1/me` — PASS для обеих записей; токены и пароли в журнал не записываются.
- Учётная запись куратора отложена: адрес `rita@morefoto36@.ru` синтаксически некорректен, требуется подтверждение правильного email.

### 2026-09-21 — очистка stage-тестовых данных

- Создана `rita@morefoto36.ru` с ролью `curator`; HTTP login и `/api/v1/me` подтверждают активную учётную запись. Пароль в журнал не записывается.
- По явному запросу удалены тестовые пользователи ID 1–10, 24 тестовых учреждения (все, кроме реального ID 22 `ООО "МОЗАИКА-СИНТЕЗ"`), 75 съёмок, 71 группа, 32 тестовых товара, тестовые назначения, операции и журналы изменений. Реальные аккаунты ID 11–13 сохранены.
- После очистки проверено: 1 учреждение, 0 съёмок, 0 групп, 0 товаров, 3 staff-профиля. Пароли и токены в журнал не записываются.

### 2026-09-21 — заполнение продуктового каталога

- Из `frontend/src/modules/morefoto/commerce/mocks/catalog.ts` перенесены через реальный `POST /api/v1/catalog/products` 10 товаров: отпечатки 10×15/15×23/20×30/30×45, комплект из двух отпечатков, холст, календарь, магнит, электронный кадр и bundle всех кадров.
- Все 10 запросов получили HTTP 201. Повторная проверка реальным API: 10 активных товаров, revision 46, цены и типы соответствуют mock-каталогу. Пароли и токены в журнал не записываются.
### 2026-09-21 — исправление ревью и терминологии

- По inline-тредам PR #30 изменены `loadStorefront`: отказ COM-08 сохраняет просмотр MED-01, и `clearLive`: выбор удаляется только после успешного пустого quote. Название F1 в меню/маршруте/экране/переходе от съёмки уточнено до «Заявки на списки сотрудников» по скриншоту пользователя.
- E2E D1/D2 preview теперь проверяет anonymous 401 и Bearer 200. Проверки после правок ещё не запускались; E4-REVIEW-CATALOG/CLEAR/F1 и повтор полного gate — PENDING.
- `git diff --check` — PASS; `python3 tools/verify-wave-graph.py docs/waves/graph.json` — PASS, 40 волн / 99 API, E4 единственная readyFromMain. `npm run check` напрямую в host — BLOCKED (`eslint: not found`), поскольку node_modules живёт в изолированном Docker volume; та же проверка запущена штатным `make test-e2e` и дошла до браузерного E2E. В полном прогоне PHPStan и PHPUnit 420/1608 PASS, frontend check/build/unit PASS по фазам runner; итог browser ещё PENDING.
- По запросу пользователя SSH повторно проверен — PASS. Stage: `morefoto_frontend` main-live-api 2/2, `morefoto_stage_backend` и `morefoto_stage_fpm` — образ 20260911 1/1; отдельная база `morefoto_stage_c4_20260913` на общем MySQL. Текущий stage runtime в `/srv/morefoto/releases/stage-20260919-b2-d788622/runtime`, новые media/handoff symlink отсутствуют. Текущий composer vendor имеет те же 98 package/version, что и актуальный lock; stage FPM содержит GD. Backend выкатывать только с новым source, stage config, symlink, миграциями и сохранением текущих ServiceSpec/дампа.
- Первый полный `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`: FAIL, browser 51 PASS / 8 FAIL, `/api/var/e2e/rabit-e2e-2ddd74397c12/browser.log`. D1 preview 401/200 PASS, E4 catalog outage PASS. Семь F1 failure вызваны ожидаемой старой подписью меню/заголовка после переименования; тест обновлён. E4 clear failure — только неоднозначный locator трёх alert, тест сужен до диалога. Реальный эффект сохранения выбора не успел провериться из-за locator; повтор PENDING.
- Второй запуск той же команды: FAIL на frontend lint до сборки/HTTP (`/api/var/e2e/rabit-e2e-404e870df4c9/check.log`), Prettier требовал перенос аргумента в исправленном E4-тесте. Форматирование исправлено, `git diff --check` PASS; третий полный прогон запущен, результат PENDING.
- Третий полный запуск той же команды: PASS, browser 59/59 (`api/var/e2e/rabit-e2e-1a2eeff99ae3/browser.log`), PHPStan PASS, PHPUnit 420/1608, frontend check/unit/build PASS. Post-browser `storefront-integration.log`: PASS, F1 доверенная льгота, stale/TTL/revocation/assignment/миграция. E4-REVIEW-CATALOG/CLEAR/F1 PASS через реальные HTTP/browser.
- Просмотрены снимки F1 desktop/mobile и E4 gallery/cart. На F1 боковое меню обрезало новую подпись до многоточия. Добавлен перенос строки только в navigation item title; повтор затронутой визуальной проверки и browser gate PENDING. Остальные просмотренные экраны без горизонтального переполнения.
- Четвёртый полный запуск той же команды после переноса: PASS, browser 59/59, 0 skipped/unexpected/flaky, `api/var/e2e/rabit-e2e-8abe011ee786/browser.log`; post-browser `storefront-integration.log` PASS. PHPStan, PHPUnit 420/1608, frontend ESLint/Vue/E2E TS/build и unit PASS. Свежие F1 desktop/mobile screenshots просмотрены: название видно целиком в две строки, переполнения нет. Stage nginx конфиг для нового backend подготовлен отдельно и `nginx -t` PASS на сервере в сети `morefoto-stage-private`; сервисы пока не переключались.

### 2026-09-21 — подготовка merge

- `git diff --check`, JSON-проверка verification и актуальный `main` через `gh api` — PASS. Runtime/report commit `d0a58a2` опубликован в PR #30. `gh pr edit --body-file` обновил описание фактического gate и условия stage; `gh pr ready 30` — PASS, опубликованный HEAD `d0a58a2`, base `6b81647`, mergeable/CLEAN.
- В оба inline-треда отправлены ответы с commit и live E2E; оба review thread `isResolved=true` через GitHub CLI. Stage `nginx -t` в реальной сети `morefoto-stage-private` — PASS. Проверка backend миграций и deployment ещё не выполнялись.



## Результаты тест-кейсов

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| E4-BASE | PASS | 2026-09-21 | `gh pr view 25`, merge receipt `6b81647` присутствует в base `main` |
| E4-CAPABILITY | PASS | 2026-09-21 | `make test-e2e` final fixture `8abe011ee786`, MED-01 states/revoke browser 59/59 |
| E4-MEDIA | PASS | 2026-09-21 | Та же команда, D1 anonymous preview 401 / Bearer 200 и E4 protected derivatives |
| E4-QUOTE | PASS | 2026-09-21 | Та же команда, E4 quote browser cases и `storefront-integration.log` |
| E4-STALE | PASS | 2026-09-21 | Та же команда, post-browser `storefront-integration.log`: TTL/fingerprint/revoke/assignment |
| E4-ARCH | PASS | 2026-09-21 | Та же команда, PHPStan 0, PHPUnit 420/1608, frontend check/build/unit PASS |
| E4-UI | PASS | 2026-09-21 | Та же команда, browser 59/59, просмотр F1/Gallery/Cart desktop/mobile screenshots |
| E4-GRAPH | PASS | 2026-09-21 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 40 волн, 99 API ID |
| E4-PUBLISH | PASS | 2026-09-21 | `git diff --check` PASS, commit `d0a58a2` pushed, PR #30 ready/CLEAN; merge и deploy — отдельные следующие действия |
| E4-REVIEW-CATALOG | PASS | 2026-09-21 | `make test-e2e`, E4 catalog outage browser case 58 |
| E4-REVIEW-CLEAR | PASS | 2026-09-21 | `make test-e2e`, E4 failed clear browser case 59 |
| E4-REVIEW-F1 | PASS | 2026-09-21 | `make test-e2e`, F1 workflow и 8 navigation cases, визуально desktop/mobile; stage smoke после деплоя PENDING |
