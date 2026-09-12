# E2 — защищённый HTTP-каталог

Волна реализует COM-01/02/03 поверх уже объединённых B1 и E1. База — `83528338146828176eaa59b954c0c85942d986da`; незамерженный код C2 не используется. Товары и цены не создаются миграцией; D02 и условия продаж не утверждаются этой волной.

## HTTP

| Операция | Ответ |
| --- | --- |
| GET `/api/v1/catalog/products` | 200, `data.items`, `data.revision`, `meta.page/pageSize/total` |
| POST `/api/v1/catalog/products` | 201, `data.id/revision`, `Location: /api/v1/catalog/products/{id}` |
| PATCH `/api/v1/catalog/products/{product_id}` | 200, `data.id/revision` |

Все три действия требуют действующего Bearer и активного организатора. Появляется permission `catalog.manage`; `/me` организатора возвращает его вместе с прежними правами. Неактивные товары сохраняются и доступны в служебном списке. DELETE и публичный покупательский каталог не добавлены.

HTTP-пагинация: только `page` и `pageSize`, по умолчанию 1/50, предел страницы 1..1000000, размера 1..100. Справочник сортируется `name ASC + internal ID ASC`; внутренний E1-сценарий сохраняет прежнюю сортировку ID ASC. `revision` в query не является опубликованным фильтром и возвращает 422. Полный набор товаров для редактора условий относится к будущему COM-04, текущая страница не подменяет его.

Локальная фабрика создаёт типизированные RequestDto из raw JSON; общий приводящий типы mapper Share не используется. POST требует все девять реквизитов Product. PATCH требует глобальную `revision` и хотя бы одно изменяемое поле; null для этих полей запрещён, `false`, `0`, пустые строки сохраняются. Неизвестные поля, подмешивание query в мутацию, строки вместо чисел/boolean и float вместо integer отклоняются. Ограничения реквизитов и целочисленных копеек — принятые технические ограничения E1; максимальное тело JSON 32768 байт.

Все ответы контроллера имеют `Cache-Control: no-store`. Ошибки нового модуля: `error.code/message`, `meta.requestId`, без legacy `data:[]`, stack trace и секретов. Сломанный JSON — 400 `MALFORMED_JSON`; корректный JSON неправильной формы, значения и фильтры — 422 `VALIDATION_FAILED`; 401/403 — `UNAUTHORIZED`/`FORBIDDEN`; отсутствующий товар — 404 `NOT_FOUND`; stale revision — 409 `REVISION_CONFLICT`; другой payload под тем же ключом — 409 `IDEMPOTENCY_CONFLICT`; недоступность Access/каталога — 503; непредвиденная ошибка — 500 с безопасным сообщением.

## Транзакционная авторизация и повтор

POST/PATCH требуют `Idempotency-Key`: ровно 32 hex-символа, регистр нормализуется. Область ключа — actor + HTTP method + нормализованный resource path. Хеш считается по типизированным полям в устойчивом порядке, для PATCH учитываются только переданные поля и revision. Результат UUID/revision сохраняется в `mf_catalog_idempotency` в той же InnoDB-транзакции, что товар и глобальная ревизия. История сохраняется долговечно, TTL/очистка не вводятся.

Повтор с тем же payload возвращает исходные id/revision/status/Location, даже если каталог уже изменился. Запись или подтверждённая операция не дублируется после потери ответа или перезапуска PHP. Ошибка записи истории откатывает и товар, и ревизию; такой ключ можно повторить.

Перед поиском сохранённого ответа выполняется актуальная авторизация **внутри** транзакции. Commerce обращается к `Share/Contracts/Access/CatalogAccessGuardInterface`; реализация Access блокирует AccessState → StaffProfile → Auth identity и повторно разрешает токен. Прямых запросов Commerce к чужим таблицам нет. Отзыв токена или роли после HTTP prefilter всё равно запрещает запись. Mutex actor удерживается до commit/replay, поэтому два одинаковых параллельных запроса не создают два результата.

E1-сценарии получили явно обозначенные transaction participants без start/commit/rollback. Их прежний `execute()` продолжает владеть своей транзакцией; HTTP вызывает только `AuthorizedCatalog`, объединяющий guard, запись и идемпотентность. Внешняя уже начатая транзакция по-прежнему отклоняется до её изменения.

## Установка и проверка

Применить активную миграцию `Version20260912220001` после E1. `init.php` включает Commerce после Access; реестр добавляет его маршруты отдельным блоком после существующего foreach. DI Access подключён отдельным `di/catalog.php`. Общие composer, PHPStan и тестовые стабы не менялись. Down миграции возможен только без истории операций; при записях откат останавливается, сохраняя защиту от повторов.

- PHPUnit: **245 тестов, 832 assertions**. Сохраняются два прежних сообщения логгера Share о metadata DTO.
- Native E2: **95 проверок** на Bitrix 25.750.0, MySQL 8.0.45, PHP 8.4.23. Центральный route registry, Router, controller, filters, строгий JSON, mapper, native DI/HL, HTTP-коды и заголовки; реальные два PHP workers с подтверждённым ожиданием в `performance_schema.data_lock_waits`; replay из нового процесса; отзыв токена/роли после prefilter; rollback при отказе записи истории.
- Native W06 regression: **50 проверок**, включая обновлённое ожидаемое permission `catalog.manage`.
- PHPStan, PHP lint, PHP CS Fixer и shell syntax: результаты в `verification.json`.

Native-команда: `bash api/tools/run-e2-catalog-integration.sh`; переменные `E2_VENDOR_ROOT`, `E2_KERNEL_ROOT`, `E2_PHP_IMAGE` позволяют указать readonly зависимости. Фикстура использует собственные internal network/container `rabit-e2-*`, tmpfs-БД, не открывает host-порты и удаляет только созданные ею ресурсы. Основной seed повторно использует W02 bootstrap без изменения. Workers подключаются к уже созданному временному document root; native Main services регистрируются штатным `registerByModuleSettings('main')`.

Граница проверки: CLI реконструирует HttpRequest, только источник `php://input` заменён raw-строкой теста; пауза вокруг настоящего Access guard синхронизирует гонки. Это проверка настоящих маршрутов/контроллеров/БД, без полного nginx/FPM запроса, production-данных, внешних интеграций или deployment. Реальные результаты — `integration.json` и `access-regression.json`.
