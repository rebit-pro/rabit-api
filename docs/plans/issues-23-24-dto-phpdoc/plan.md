# Issues #23 и #24 — план phpDoc и чистой HTTP-границы

## Цель и текущий объём

Подготовить согласованный план для [#23](https://github.com/rebit-pro/rabit-api/issues/23)
и [#24](https://github.com/rebit-pro/rabit-api/issues/24).
По уточнению пользователя от 2026-09-21 текущий PR содержит только этот план и журнал:
реализация, новые тесты и запуск runtime-проверок отложены до отдельного поручения.

Будущая цель: содержательные русские комментарии UseCase/сервисов и чистые
CatalogController/ConditionsController с прежними HTTP-контрактами.
Один PR для двух issues разрешён прямым указанием пользователя; это не новая продуктовая волна.
Текущий PR с планом не закрывает issues и не подтверждает готовность реализации.

## Контекст и зависимости

- Ветка: `codex/issues-23-24-dto-phpdoc`.
- Worktree: `/home/user/rabit-api-worktrees/issues-23-24-dto-phpdoc`.
- Base: `origin/main`, `6b8164747e3ed3f6f73a7a91efc0d28b2eb365bc`.
- F1 уже слит: [PR #25](https://github.com/rebit-pro/rabit-api/pull/25),
  merge от 2026-09-21. Незамерженный E4 не используется.
- Контекст phpDoc: [PR #21](https://github.com/rebit-pro/rabit-api/pull/21) и issue #23.
- Инструкции: корневые `AGENTS.md` и `CLAUDE.md`; они совпадают на base.
- Реализованный образец: `morefoto.handoff/Presentation/Controller/StaffRequestController`,
  общий `AuthenticatedApiJsonController` и `RequestToDtoMapper` в `rebit.share`.
- Порядок приёмки: [A8](../../waves/a8/README.md).
  История решений F1: [plan](../F1_staff_requests/plan.md) и
  [progress](../F1_staff_requests/progress.md).
  Исторический журнал F1 описывает состояние до merge; фактическое состояние проверено через gh.
- Перед будущей реализацией повторно обновить main, issues и зависимости, сверить этот план с кодом.
  При изменении base повторить затронутые проверки только на main плюс собственный diff.

## Будущий scope

### Issue #23

Проверить все актуальные классы по путям:

- `api/public/local/modules/**/lib/Application/**/*UseCase.php`;
- `api/public/local/modules/**/lib/{Application,Domain}/**/Service/*.php`.

На base механически найдено 47 UseCase и 23 сервиса, всего 70 файлов, включая D2/H1/F1.
Числа 35 и 20 в issue относятся к прежнему снимку и не являются лимитом.
Это инвентаризация путей, а не завершённая смысловая проверка комментариев.

Для каждого файла прочитать ответственность и существующий class-level phpDoc.
Сохранить хороший комментарий; отсутствующий или формальный заменить 1–3 короткими
русскими предложениями непосредственно перед классом. Объяснить назначение и сценарий/правило,
не перечислять текущих потребителей и реализации. Ширина новых строк — до 120 символов.
Полный список просмотренных файлов и результат для каждого записать в progress при реализации.
В части #23 не менять PHP-токены, кроме комментариев и пробелов.

### Issue #24

Изменить только HTTP-границу каталога и условий продаж и необходимые для неё зависимости:

| Метод | Путь | Сценарий |
| --- | --- | --- |
| GET | /api/v1/catalog/products | Список и пагинация каталога |
| POST | /api/v1/catalog/products | Создание товара, 201 и Location |
| PATCH | /api/v1/catalog/products/{product_id} | Изменение товара |
| GET | /api/v1/catalog/conditions | Общие условия |
| PUT | /api/v1/catalog/conditions | Сохранение общих условий |
| GET | /api/v1/groups/{group_id}/conditions | Условия группы |
| PUT | /api/v1/groups/{group_id}/conditions | Сохранение условий группы |

1. Concrete controller наследует общую инфраструктурную обвязку F1.
   Каждый action принимает один presentation RequestDto, передаёт данные в Application UseCase
   через stateless mapper и оформляет ответ через `json()`/`createdJson()`.
2. Query, JSON, route и разрешённые headers собираются до action инфраструктурным mapper.
   Контроллер не разбирает payload/token и не получает HttpRequest.
3. Request/Result DTO содержат только public readonly свойства и пустой constructor.
   Преобразования и сериализация принадлежат mapper, предметная валидация — Application/Domain.
4. Вызовы существующих AuthorizedCatalog/AuthorizedConditions оформить через законченные
   Application UseCase, сохранив текущую авторизацию, транзакции и дедупликацию.
   Не вызывать из controller внутренние UseCase в обход authorized-сценария.
5. Auth, filters, no-store, logging, requestId, сериализация ошибок остаются в Infrastructure.
   Расширения общего механизма минимальны и покрыты регрессией F1.
6. DI и необходимые DTO/mapper меняются вместе с законченным сценарием.
   У каждого затронутого UseCase/Service обновляется русский class-level phpDoc.

### Исключено

Массовая миграция прочих контроллеров; исправления F1 из #26/#27/#28;
новые продуктовые API, изменение бизнес-правил, схемы, миграций, графа волн,
редизайн frontend, deployment и merge.
Обнаруженные посторонние дефекты фиксируются отдельно, без расширения diff.

## Решения, риски и точки уточнения перед кодом

- **Совместимость ошибок.** Текущие commerce-контроллеры имеют собственные status/code/message,
  включая 400 MALFORMED_JSON, 422 VALIDATION_FAILED, 409 REVISION_CONFLICT/IDEMPOTENCY_CONFLICT,
  503 CATALOG_UNAVAILABLE/CONDITIONS_UNAVAILABLE/ACCESS_UNAVAILABLE и 500 INTERNAL_ERROR.
  Общий ApiJsonExceptionResponse F1 выбирает другой default status и message=code.
  Простая смена базового класса меняет контракт. Сначала зафиксировать полную таблицу ошибок,
  затем предусмотреть инфраструктурную политику преобразования commerce-исключений;
  controller не должен переопределять exception mapping.
- **Wire-валидация.** Сохранить строгие JSON-типы, различие отсутствующего поля и null,
  запрет лишних полей/query/body, размер и глубину JSON, тип Content-Type, пагинацию.
  CatalogRequestFactory использует лимит 32768 байт/глубину 16;
  ConditionsRequestFactory — 262144 байт/глубину 32.
  Общие StrictRequest/JsonBody должны воспроизвести прежние статусы, коды и сообщения.
  Параметры маршрута, добавленные Bitrix в GET, не считать пользовательским query.
- **Авторизация в транзакции.** AuthorizedCatalog/AuthorizedConditions вызывают
  `lockOrganizer(actorId, token)` внутри транзакции, до чтения результата идемпотентности.
  Это защищает и replay после отзыва доступа. Нельзя заменить этот шаг одним Bearer-prefilter.
  Перед кодом выбрать передачу авторизованного контекста из Infrastructure в UseCase
  без чтения/разбора заголовка в concrete controller и зафиксировать решение в этом плане.
- **Ошибки token resolver.** CatalogTokenResolver переводит сбой провайдера в 503;
  общая сборка controller должна сохранить различие неверного токена и недоступного auth.
- **DTO за пределами HTTP.** Например, ProductInputDto сейчас создаёт ProductDetails в constructor.
  Перепроверить связанные DTO при выборе mapper. Если они затрагиваются, сделать их пассивными
  и перенести валидацию/создание VO в соответствующий слой; не расширять это на все модули.
- **Повторные запросы.** Сохранить resource/key/hash, replay, Location, revisions,
  optimistic locking и отсутствие второй записи после потери ответа.
- **Регрессия общей инфраструктуры.** Любые расширения Share не должны менять F1, auth,
  обработку неизвестных полей, no-store и глобальное логирование.
- **Окружение.** Нужны PHP 8.4, Docker, лицензированное ядро и vendor текущего composer.lock.
  Их доступность для нового worktree ещё не проверялась: runtime не запускался.
  В runner остались исторические defaults с rebit-p2p; задавать актуальные пути явно.
  Исправление defaults не входит в эту задачу.

## Пошаговый checklist

### Текущий PR — только план

- [x] Прочитать issues, локальные инструкции и review-контекст.
- [x] Проверить merge F1 и создать отдельный worktree от актуального main.
- [x] Зафиксировать scope, подход, зависимости, риски и тест-кейсы.
- [x] Проверить два документа и отсутствие runtime-изменений.
- [ ] Закоммитить, опубликовать ветку и открыть PR в main без закрытия issues.

### Будущая реализация — не начата, требуется отдельное поручение

- [ ] Обновить base, перечень файлов и контрактную матрицу; закрыть решения по auth/error mapping.
- [ ] Добавить/уточнить регрессионные тесты прежнего HTTP-контракта и архитектурных границ.
- [ ] Выполнить смысловой аудит phpDoc; сохранить список и результат каждого файла.
- [ ] Реализовать Request/Result DTO и stateless mapper, Application-входы и DI.
- [ ] Перевести два controller на общую обвязку без изменения контрактов.
- [ ] Запустить адресные backend-проверки и CS Fixer только по изменённым PHP-файлам.
- [ ] Пройти полный backend/frontend gate, реальные HTTP/Chromium и desktop/mobile.
- [ ] Перенести доказательства всех проверок в progress и подготовить реализацию к review.

## Критерии приёмки

Для текущего PR: ровно два основных документа в папке задачи; нет изменений кода,
тестов, конфигурации или схемы; будущие шаги явно отложены; PR ссылается на оба issue
без `Closes/Fixes/Resolves`; issues остаются OPEN.

Для будущей реализации: каждый актуальный UseCase/Service просмотрен; phpDoc содержателен;
контроллеры и DTO соблюдают границы; действующие ответы, ошибки, авторизация, no-store,
идемпотентность и хранение подтверждены проверками. Все обязательные проверки зелёные.
Пройденные проверки предыдущих волн не считаются результатом этой задачи.

## Нумерованные тест-кейсы

Статусы и фактические команды ведутся в progress. T01–T03 относятся к текущему PR.
T04–T12 запланированы для будущей реализации и сейчас имеют статус PENDING.

| ID | Предусловия и действие | Ожидаемый результат | Планируемая проверка |
| --- | --- | --- | --- |
| T01 | Документы подготовлены; проверить staged diff и список файлов | Только plan.md/progress.md; нет whitespace-ошибок | `git diff --cached --check`; `git diff --cached --name-only` |
| T02 | План и журнал прочитаны; сверить scope, риски, стабильные ID и статусы | Нет заявлений о реализации/непроведённых PASS, все T01–T12 представлены | Read-only проверка файлов PowerShell и ручное review |
| T03 | Ветка опубликована и PR создан; проверить base/head, files и issues | PR в main, только два документа, оба issue OPEN, локальный HEAD опубликован | `gh pr view --json url,state,baseRefName,headRefName,headRefOid,files`; `git status --short`; `gh issue view 23 --json state`; `gh issue view 24 --json state` |
| T04 | Актуальная инвентаризация phpDoc; прочитать каждый класс и diff | Все комментарии содержательны; для #23 функциональные токены идентичны base | Инвентаризация путей, ручное review и сравнение `token_get_all` без T_COMMENT/T_DOC_COMMENT/T_WHITESPACE |
| T05 | Реализованы DTO/controller/DI; запустить architecture и contract unit | Один RequestDto/action, нет Bitrix/request/filter/logger/exception mapping; DTO пассивны, mapper stateless | В PHP 8.4 из api: `php vendor/bin/phpunit public/local/modules/morefoto.commerce/tests/Unit` |
| T06 | Disposable API с organizer; выполнить 7 успешных маршрутов, перезагрузить данные | Прежние поля/meta/revisions; POST 201 + Location; данные сохранены; no-store | `make test-e2e`, включая catalog.spec.ts и conditions.spec.ts |
| T07 | Disposable API; malformed JSON, неверные типы/null/размеры, лишние поля/query, GET body, path spoofing | Прежние status/code/message; отказ без записи; настоящий route не считается query | Commerce unit + расширенные live catalog/conditions tests в `make test-e2e` |
| T08 | Нет/невалидный/отозванный токен, чужая роль, auth/provider failure, replay после отзыва | Прежние 401/403/503, no-store/error/meta; отказ до чтения replay/записи | Unit адаптеров + реальные HTTP-сценарии `make test-e2e` |
| T09 | Свежая БД; повтор key/payload, тот же key с иным payload, stale revisions и отсутствующие ID | Replay без дубля, прежние 409/404 и сообщения; ревизии не растут при отказе | Commerce unit + реальные HTTP-сценарии `make test-e2e` |
| T10 | Итоговый PHP diff и PHP 8.4; lint/PHPStan/PHPUnit/CS Fixer | Ноль ошибок; formatter проверяет только изменённые PHP-файлы | Команды backend ниже; общий backend входит в `make test-e2e` |
| T11 | Итоговый frontend с настоящим API; check/unit/build и полный live-набор, включая F1 | Нет регрессии общего mapper/auth/error/logging; failed/skipped/flaky=0 | `make test-e2e`; внутри frontend: `npm run check`, `npm run test:commerce`, `VITE_API_MOCKS_ENABLED=false npm run build` |
| T12 | Свежий E2E-стенд; пройти UI из меню на desktop 1440px и mobile 390px | Каталог/редактор/общие и групповые условия доступны, сохранение/ошибки понятны, нет overflow; снимки просмотрены | `make e2e-up`, `make e2e-test E2E_STATE=/absolute/path/to/state.json`, ручной браузер, `make e2e-down E2E_STATE=/absolute/path/to/state.json` |

### Команды будущей проверки

Из корня worktree, после проверки наличия актуальных каталогов:

```sh
export E2E_KERNEL_ROOT=/home/user/rabit-api/api/public/bitrix
export E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor
make test-e2e
```

Runner поднимает отдельную БД и изолированные ресурсы, запускает backend/frontend и браузер.
Рабочие .env, БД и внешние интеграции не используются. Логи остаются в игнорируемом
`api/var/e2e/`, browser-отчёты — в `frontend/reports/e2e-live/`.
Сохранить результаты и безопасные визуальные доказательства в журнале/PR; токены и traces не коммитить.
Для визуальной приёмки использовать новый стенд по процедуре A8.

Адресные команды внутри PHP 8.4 runtime с vendor данного worktree, рабочий каталог api:

```sh
php vendor/bin/phplint
php vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --memory-limit=1G
php vendor/bin/phpunit --colors=never
```

CS Fixer выполнять из корня worktree в Bash с PHP 8.4 и vendor;
список строится после добавления новых PHP-файлов в индекс:

```sh
mapfile -d '' changed_php < <(git diff --name-only -z --diff-filter=ACMR origin/main -- 'api/**/*.php')
if (("${#changed_php[@]}")); then
    php api/vendor/bin/php-cs-fixer fix --config=api/public/local/php-cs-fixer.php \
        --path-mode=intersection --dry-run --diff --allow-risky=yes -- "${changed_php[@]}"
fi
```

После изменения base или исправлений повторить затронутые проверки.
В текущем PR эти команды не запускаются: он документирует будущую работу.