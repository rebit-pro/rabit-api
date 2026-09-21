# F1 — списки детей сотрудников

## Цель

Подключить `morefoto.handoff` к реальному API для списка, карточки, создания, изменения и уточнения служебной заявки. Сервер проверяет автора, область учреждения/группы, ребёнка и право сотрудника на скидку; каждая смена состояния остаётся в истории.

## Scope

- HND-06 `GET /api/v1/staff-requests`: фильтры `institutionId`, `shootId`, `status`, пагинация и серверная область видимости.
- HND-07 `POST /api/v1/staff-requests`: создание автором с `Idempotency-Key`.
- HND-08 `GET /api/v1/staff-requests/{id}`: карточка, строки и история.
- HND-09 `PUT /api/v1/staff-requests/{id}`: исправление автором с optimistic lock и идемпотентностью.
- HND-12 `POST /api/v1/staff-requests/{id}/clarifications`: запрос уточнения организатором/куратором с optimistic lock и идемпотентностью.
- Live frontend для списков сотрудников с сохранением mock-режима.
- Локальные unit/integration/E2E и desktop/mobile visual-проверки.
- Второй круг review: чистый HTTP-контроллер на presentation request DTO и UseCase без прямой работы с Bitrix request/route, фильтрами, serializer и logger.
- Общая инфраструктурная обвязка Bearer, JSON-ошибок, `no-store`, request-id и Monolog; явное описание точки конфигурации логов.

## Не входит

- HND-10 preview, HND-11 перенос наборов и любые изменения фотографий/заказов — волна D3.
- Расчёт скидки в заказе и изменение перечня льготных товаров — E3/последующие commerce-волны.
- Деплой: выполняется отдельным общим этапом после согласованного пакета волн.
- Массовый рефакторинг уже слитых `CatalogController`, `ConditionsController` и остальных legacy-контроллеров: для них фиксируется отдельный follow-up после появления общего эталона в F1.

## Зависимости и решения

- B2: серверная роль и область сотрудника — уже в `main`.
- D2: стабильный ребёнок, его код и назначения фото — уже в `main`.
- E3: признак льготного товара — уже в `main`.
- D05: ставка 50% согласована; F1 подтверждает право конкретного автора заявки, не принимает право от клиента.
- D08: O/K/V и область доступа определяются Access на каждом запросе.
- D11: F1 сохраняет исходную группу/ребёнка; правила переноса полного набора реализует D3.

## Архитектурные решения

- Новый модуль `morefoto.handoff` владеет заявкой, строками, историей и идемпотентностью.
- Публичные ID заявки и строк — UUID; внутренние FK не выходят в HTTP.
- Право на скидку — серверный снимок `staffEligibility` с источником `verified_staff_assignment`, актором и временем проверки.
- Учитель видит только свои заявки в назначенных группах; куратор — учреждения своей области; организатор — всю компанию.
- Создавать и исправлять может учитель либо организатор-автор. Уточнение запрашивает куратор или организатор.
- Изменение после уточнения возвращает заявку в `submitted`, повышает revision и дописывает историю. `transferred` неизменяем, хотя F1 его не создаёт.
- Транзакция охватывает заявку, строки, историю и запись идемпотентности.
- Ошибки существования вне области маскируются как 404; stale revision и повтор ключа с другим телом дают 409.
- Action получает один presentation `*RequestDto`; разбор JSON, route-параметров и HTTP-заголовков выполняет общий infrastructure mapper.
- Конкретный контроллер содержит только вызовы UseCase и формирование успешного presentation-ответа; Bearer, фильтры, error mapping, `Cache-Control` и логирование предоставляет общий базовый контроллер.
- Monolog уже подключён глобально через `local/.settings_extra.php` → `local/php_interface/settings_extra.php`; F1 обязан использовать именованный канал `handoff`, не создавать logger/filter внутри контроллера и не писать payload/токены в логи.
- Правило чистых контроллеров фиксируется на верхнем уровне в `AGENTS.md` и `CLAUDE.md` как обязательное для следующих волн.

## Карта frontend-компонентов

- `StaffRequestsScreen.vue` — route-level композиция списка/карточки и диалогов; получает состояние только из `useHandoff`/`useHandoffEditor`.
- `RequestFields.vue` — управляемые поля заявки через props/emits; не вызывает API.
- `RequestReview.vue` — остаётся только для D3 и не показывается в live-режиме F1.
- `useHandoff.ts` — загрузка workspace и отмена устаревших ответов.
- `useHandoffEditor.ts` — черновик, submit и ошибки формы.
- `api.ts` — HTTP и `Idempotency-Key`; `service.ts`/`requests-service.ts` выбирают live или mock реализацию.

## Риски

- Не доверять `institutionId`, `shootId`, `groupId`, коду ребёнка и праву из payload без серверного разрешения связей.
- Не раскрыть наличие чужой заявки через 403/фильтры.
- Не потерять историю при полной замене строк.
- Не превратить повтор запроса после timeout в вторую заявку.
- Не активировать прежний mock-перенос в live UI до D3.

## Чек-лист реализации

- [x] Миграция таблиц заявки, строк, истории и идемпотентности.
- [x] Domain/Application/Infrastructure/Presentation модуля с русскими class-level docblock у use case/service.
- [x] Маршруты, DI, установка модуля и disposable prepare.
- [x] Backend unit/integration на роли, scope, ребёнка, статус, revision и idempotency.
- [x] Live frontend API, mapper и UI без действий D3.
- [x] Frontend unit и Playwright E2E.
- [x] Desktop/mobile visual-проверка.
- [x] Полный локальный disposable quality gate.
- [x] Документация волны для review; публикация PR выполняется следующим шагом.

- [x] Заменить ручной `StaffRequestFactory` на типизированные presentation request DTO и общий infrastructure mapper.
- [x] Вынести Bearer/error/no-store/Monolog из `StaffRequestController` в общую инфраструктурную обвязку.
- [x] Зафиксировать верхнеуровневое правило чистого controller и фактическую конфигурацию Monolog.
- [x] Повторить unit/static/full browser E2E после архитектурной правки и обновить review-артефакты.
## Acceptance и стабильные test ID

- `F1-HND-06-SCOPE`: список не раскрывает чужую область; учитель видит только свои заявки.
- `F1-HND-07-CREATE`: подтверждённый сотрудник создаёт заявку с доступной группой и ребёнком.
- `F1-HND-07-UNVERIFIED`: неподтверждённый/заблокированный сотрудник получает отказ.
- `F1-HND-07-GROUP`: недоступная или не принадлежащая съёмке группа отклоняется без утечки.
- `F1-HND-07-IDEMPOTENCY`: повтор равен первому результату, иное тело с тем же ключом — 409.
- `F1-HND-09-REVISION`: устаревшая revision исправления отклоняется с 409 без частичной записи.
- `F1-HND-12-CLARIFICATION`: куратор с доступом и явным подтверждением сохраняет причину в истории.
- `F1-HISTORY`: повторная подача возвращает статус `submitted`, повышает revision и сохраняет три события истории.
- `F1-ARCH-CONTROLLER`: concrete controller зависит только от UseCase, presentation DTO и общего controller API; Bitrix request/route, auth/filter/logger/serializer/error mapping в нём отсутствуют.
- `F1-HTTP-CONTRACT`: typed DTO сохраняют строгие JSON/query/path/header-проверки, коды ошибок, `201 + Location` и `Cache-Control: no-store`.
- `F1-OBS-MONOLOG`: `REQUEST`, `RESPONSE` и `HTTP_EXCEPTION` проходят через глобальную конфигурацию Monolog в канал `handoff` с request-id без request payload и Authorization.

## Сбор контекста MoreFoto — 2026-09-20

Цель: по запросу пользователя изучить MoreFoto и RaBit API как основной backend; прямой MySQL в репозиториях, DTO-контракты, приоритет производительности PHP.
Scope: сценарий и план соседнего ../MoreFoto, фактические модули/маршруты, SQL/DTO-границы, main против локальной F1. Вне scope: runtime-правки, продолжение F1, бизнес-операции, commit/push/merge/deployment, нагрузочный аудит.
Решения/риски: сохранить прежние изменения; устаревшую документацию сверять с кодом; внутренние Result/скаляры отделять от внешних DTO; скорость без измерений не обещать. GitHub CLI недоступен по TLS timeout.

- [x] Прочитать инструкции, ветку и существующие plan/progress.
- [x] Сопоставить продуктовый сценарий, план, модули и историю main.
- [x] Проследить чтение/запись и найти Objectify-вызовы.
- [x] Зафиксировать подтверждённую карту, расхождения и ограничения.

Приёмка: карта продукта/backend подтверждена файлами; объяснены SQL/DTO-границы; main отделён от F1; непроверенное отмечено.

1. CTX-01: при доступных checkout прочитать сценарий, graph.json/routes.php и Git; ожидается разделение реализованного и планируемого. Команды: `git log --first-parent origin/main --oneline -25`, `git ls-tree -d origin/main api/public/local/modules/`, `Get-Content docs/waves/graph.json`.
2. CTX-02: при доступных модулях проследить репозитории и UseCase; ожидается фактическая карта SQL/Result/DTO и Objectify-исключений. Команды: `rg -n 'fetchCollection\(|fetchObject\(|wakeUpObject\(|Objectify|EntityObject|EO_' api/public/local/modules -g '*.php' -g '!sprint.migration/**'` и адресное чтение файлов.
3. CTX-03: после изучения сверить diff; ожидается сохранение прежних правок и только изменения plan/progress в этой сессии. Команды: `git diff --check -- docs/plans/F1_staff_requests/plan.md docs/plans/F1_staff_requests/progress.md`, `git status --short`.
## Возобновление F1 и актуальное имя проекта — 2026-09-20

Цель: подтвердить календарные D2/H1, закрепить актуальный checkout RaBit API и завершить проверки архитектурных изменений F1 перед review. Scope: F1 и необходимая общая HTTP-обвязка; инструкции проекта и навигационная карта ReBit OS. Вне scope: реализация E4/F2, массовый рефакторинг слитых контроллеров, merge и deployment.

- [x] Сверить календарь 20 сентября и локальные merge-коммиты: D2 #21, H1 #22; F1 — текущая незавершённая волна.
- [x] Закрепить `/home/user/rabit-api` и разделение общей основы `rebit.*` / специализации `morefoto.*`.
- [x] Проверить текущий remote/PR (попытки HTTPS/SSH заблокированы сетевыми timeout); при недоступности явно отметить границу локальной проверки.
- [x] Проверить clean-controller diff, исправить обнаруженные регрессии и закрыть F1-ARCH-CONTROLLER/F1-HTTP-CONTRACT/F1-OBS-MONOLOG по указанным ниже границам покрытия.
- [x] Повторить штатный локальный gate и desktop/mobile visual; обновить README/verification/progress.

Риск: GitHub ранее недоступен; проверка только локального origin/main не подтверждает актуальность удалённого base. Sandbox запуска команд даёт helper_unknown_error; чтение через разрешённый exec вне sandbox работает. Предыдущие незакоммиченные изменения сохраняются.

1. RESUME-01: календарь доступен; прочитать `calendar.cmd list --from 2026-09-20T00:00 --to 2026-09-21T00:00 --category rebit --json` и `git log --first-parent origin/main`; ожидаются D2/H1 и F1 как текущая работа.
2. RESUME-02: инструкции и карта существуют; проверить `rg -n 'rabit-api|morefoto\.' AGENTS.md CLAUDE.md` и чтение studio-map.md; ожидается актуальный путь, старое имя только как историческое.
3. RESUME-03: доступен Docker; выполнить `python3 tools/run-browser-e2e.py run --php-cli rabit-api-php-cli:d1-local --php-fpm rabit-api-php-fpm:d1-local`; ожидаются зелёные backend/frontend/HTTP проверки и очистка только ресурсов этого запуска.
4. RESUME-04: после проверки выполнить `git diff --check` и `git status --short`; ожидаются корректный diff и актуальная точка продолжения без потери прежних правок.

### Найденные регрессии HTTP и план исправления

- Strict JSON/GET должен читать исходный URI query, не служебные GET-параметры роутера Bitrix.
- До гидрации проверять реальные типы JSON, обязательные поля и неизвестные вложенные ключи; header/path не принимаются из body/query. Использовать существующие кешируемые DTO-метаданные, не переносить разбор HTTP в controller.
- Исправить ложное срабатывание architecture-теста на общий `Rebit\Share\Infrastructure\Bitrix\ControllerJson`: проверять namespace Bitrix как зависимость через PHP tokens.
- F1-HTTP-CONTRACT: дополнить проверки числовыми строками вместо revision, строковым confirmed, лишними полями строки ребёнка, rows-объектом, подменой технического поля; сохранить успешные GET/PUT/clarification через native router.


### Приёмка возобновления — 2026-09-20

- [x] Выполнен полный штатный gate: PHP lint 581, PHPStan 0 ошибок, PHPUnit 400/1279, frontend unit 158; собственный F1 E2E и desktop/mobile PASS.
- [x] Monolog: REQUEST/RESPONSE/HTTP_EXCEPTION, request-id и отсутствие тестовых текстов/токена подтверждены сохранённым FPM log.
- [x] ESLint/TypeScript изменённого E2E и PHP CS Fixer по изменённым файлам завершены.
- [x] Получить зелёный общий browser gate: 43/43; прежний FAIL E3 не воспроизвёлся без изменения теста, его историческая причина не установлена.
- [x] Подтвердить свежий remote main, решить оставшиеся выходные DTO списка/карточки и закрыть пробелы role/scope acceptance до готовности к review; публикация PR после этого.

Существующий E2E проверяет отказ неназначенному сотруднику при чтении списка, но не прямой POST этого сотрудника; не считать это полным F1-HND-07-UNVERIFIED. Проверка списка не доказывает исчерпывающую фильтрацию всех чужих заявок (F1-HND-06-SCOPE). Эти границы требуют отдельных assertions.

## Публикация F1 — 2026-09-21

Пользователь поручил создать отдельный issue по ранее слитым контроллерам, завершить F1, сделать commit и PR в main, затем перейти к review. Массовое исправление остальных контроллеров не включать в F1; merge/deployment не выполнять.

- [x] Создать follow-up issue с проверенными примерами и критериями приёмки.
- [x] Завершить Application OutputDto / Presentation ResultDto F1; усилить проверку границы контроллера.
- [x] Закрыть прямой POST неназначенного сотрудника и фильтрацию чужой заявки в HTTP E2E.
- [x] Проверить актуальный main и полный disposable gate; исследовать прежний FAIL E3 без ослабления проверки.
- [x] Обновить plan/progress и артефакты, выполнить self-review, commit/push и один PR в main.

1. F1-OUTPUT-CONTRACT: UseCase возвращает типизированный OutputDto; ResultDto сохраняет JSON списка, карточки и мутации, pagination meta и Location; команда PHPUnit morefoto.handoff/tests/Unit.
2. F1-PUBLISH: после зелёного gate git diff --check, git status, git fetch origin и проверка PR; ожидается одна ветка F1, commit с проверенными изменениями и PR со ссылкой на follow-up issue. Незелёные проверки исключают объявление готовности к merge.

## DTO без поведения — 2026-09-21

Требование пользователя: DTO содержат только типизированную сигнатуру — публичные readonly-свойства и пустой конструктор. Методы преобразования, валидации, сериализации и вычислений запрещены. Scope: все DTO F1 и затронутые межмодульные DTO; массовый рефакторинг чужих волн исключён.

- [x] Вынести преобразования request/result в stateless presentation mapper, сборку OutputDto — в Application mapper; offset вычислять в workflow.
- [x] Сохранить чистоту controller: только явное отображение DTO, UseCase и общий response API, без HTTP parsing/валидации/инфраструктурной сборки.
- [x] Закрепить запрет поведения DTO архитектурным тестом и инструкциями.
- [x] Выполнить unit/static и полный make test-e2e, проверить desktop/mobile, обновить артефакты.
- [x] Commit/push и PR в main; merge/deployment исключены.

1. F1-DTO-SIGNATURE: DTO доступны; reflection/token-проверка всех DTO F1 и затронутых контрактов; ожидаются только public readonly свойства и пустой constructor, без методов. Команда: PHPUnit morefoto.handoff/tests/Unit.
2. F1-DTO-COMPAT: преобразовать корректные/некорректные запросы и сериализовать ответы общим serializer; прежние поля, ошибки, meta и Location сохранены. Команды: PHPUnit и make test-e2e.

### Исправление данных E2E — 2026-09-21

Первый полный gate после DTO-правки: 42/43, F1 упал на создании второй заявки. Новая проверка чужого автора копировала UUID строки первой заявки, но миграция задаёт глобальный UNIQUE PUBLIC_ID строки. Исправить fixture второй заявки: отдельный crypto.randomUUID() для новой строки; role/scope assertions не менять. Затем повторить полный make test-e2e.

3. F1-FOREIGN-AUTHOR-FIXTURE: предусловие — заявка воспитателя существует; создать другим автором заявку другого ребёнка с новым UUID строки; ожидается 201, чужая заявка отсутствует в списке воспитателя и её карточка даёт 404. Команда: make test-e2e.

### Подсветка методов Bitrix в IDE — 2026-09-21

По вопросу пользователя сверены hasCurrentRoute/getCurrentRoute с лицензированным ядром: оба объявлены в Bitrix Main Application. В текущем checkout ядро пусто, статический stub Application не содержит этих сигнатур. Дополнить только статический контракт Routing/ Application и пояснить appendTechnicalValues русским phpDoc; поведение HTTP не менять. Повторить PHPStan и lint stub.

4. F1-ROUTE-STUB: сравнить static signatures с ядром, запустить PHP lint/PHPStan; ожидается доступность сигнатур hasCurrentRoute/getCurrentRoute/getParameterValue без подавлений IDE. Проверка фактической индексации IDE остаётся за разработчиком.

Итог 2026-09-21: реализация, DTO-signature, HTTP-контракт, полный gate и visual закрыты. PR #25 опубликован; исторические неудачные прогоны сохранены в progress.md.

### Публикация и .gitignore — 2026-09-21

- [x] Опубликован один PR F1 в main: https://github.com/rebit-pro/rabit-api/pull/25; состояние OPEN, не draft, mergeable.
- [x] По дополнительному указанию пользователя включить существующую правку `.gitignore` (`var/`) в F1.

5. F1-GITIGNORE: существует локальный var/img.png; `git check-ignore var/img.png` должен вернуть путь, файл не попадает в staging. Проверка PASS.

## Строгое review PR #25 — 2026-09-21

Scope: проверить опубликованный HEAD ba250e1927111b522a4aec297304f63495f9041a относительно origin/main 28bcad9e575489deb1113d7ce2ac459a43dd7132; архитектуру, DTO, DI, доступ, SQL/миграцию, API и frontend. Код не исправлять, merge/deployment не выполнять. GitHub — только gh CLI.

- [x] Проверить diff и историю обсуждения; отдельно воспроизвести подозрения.
- [x] Перезапустить достаточные локальные проверки в disposable среде.
- [x] Опубликовать блокирующие замечания по строкам PR, неблокирующие — отдельными issues; итог review сохранить в GitHub (COMMENTED: GitHub запретил REQUEST_CHANGES автора).

1. F1-REVIEW-BASE: git fetch origin, gh pr view 25; ожидается совпадение опубликованного и локального HEAD, актуальный main.
2. F1-REVIEW-GATE: штатный run-browser-e2e.py up и test на свежей изолированной БД; ожидаются зелёные backend/frontend и browser проверки. Для подозрений использовать отдельные локальные скрипты в игнорируемом api/var, без исправления исходного кода.
3. F1-REVIEW-PUBLISH: gh api / gh pr review; все подтверждённые замечания и вердикт остаются в PR/issues. Проверить отсутствие runtime diff после review.

4. F1-REVIEW-NAV: после обычного входа назначенного воспитателя открыть F1 через доступный элемент интерфейса без прямого goto на staff-requests, desktop/mobile; ожидается путь к подаче заявки. В ходе review обнаружен FAIL; исправление выполняется отдельным следующим ходом, не reviewer.

## Исправление P1 по review #25 — 2026-09-21

Цель: назначенный воспитатель входит в F1 через live-навигацию после login на desktop/mobile. Scope: CabinetLayout (единое desktop/mobile меню), существующий F1 E2E, role-based navigation E2E и helper login для head. API/DTO и demo-навигация не меняются. Неблокирующие #26/#27/#28 остаются отдельными задачами; merge/deployment исключены.

Решение: показывать live-пункт «Списки сотрудников» только organizer/curator/teacher, как разрешено MainRoutes. CabinetLayout остаётся композицией оболочки, новый компонент/состояние не нужен. Использовать существующие Vuetify drawer, aria-label и реактивную auth.role.

- [x] Добавить role-ограниченный live-пункт F1.
- [x] Заменить первоначальный прямой переход teacher в F1 E2E кликом по меню.
- [x] Проверить login → меню → список → форма на desktop/mobile; сохранить curator/organizer/head ограничения.
- [x] Полный make test-e2e и просмотр desktop/mobile; актуализировать результаты.
- [ ] Commit/push в существующий PR #25 и ответить на P1 с доказательствами.

1. F1-REVIEW-NAV-DESKTOP: назначенный teacher; login на 1280×720, ссылка меню, «Новый список»; ожидается открытая форма без прямого goto к F1. Команда make test-e2e.
2. F1-REVIEW-NAV-MOBILE: тот же путь на 390×844 через «Открыть меню»; ожидается доступная ссылка, drawer закрывается после перехода, форма открывается без overflow. Команда make test-e2e.
3. F1-REVIEW-NAV-ROLES: organizer/curator имеют ссылку, создать может organizer; head не имеет ссылки и прямой HTTP GET возвращает 403. Проверить обе ширины, команда make test-e2e.
4. F1-REVIEW-FIX-PUBLISH: commit/push и gh api replies к discussion_r4060560246; PR содержит исправление и фактические проверки, merge не выполняется.

### Уточнение browser-assertion drawer

Первый прогон P1-fix: 48/51. Три mobile-проверки некорректно ожидали not.toBeVisible для Vuetify drawer, который закрывается translateX(-248px), сохраняя DOM и CSS visibility. Снимок и trace подтверждают закрытие. Заменить проверку открытого/закрытого меню на toBeInViewport/not.toBeInViewport; критерий закрытия сохраняется. Повторить полный gate без изменения runtime.

### Стабильный визуальный захват

Полный повтор прошёл 51/51, но четыре новых screenshots захватили CSS transition меню/диалога. Добавить animations: disabled только при screenshot, без изменения функциональных assertions или runtime. Отдельно поднять свежий disposable fixture и выполнить F1 spec (9 сценариев), затем вручную просмотреть стабильные кадры. Полный gate 60d07b962945 остаётся доказательством регрессии; отдельный visual-прогон документируется отдельно.
