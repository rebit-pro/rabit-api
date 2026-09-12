# C2 — API учреждений и назначений

Рабочие `GET /api/v1/institutions`, `POST /api/v1/institutions` и `PATCH /api/v1/institutions/{institution_id}` (ORG-02/03/05). Ветка основана на `main` `83528338146828176eaa59b954c0c85942d986da`: необходимые B1/C1 уже влиты, изменений E2 в ней нет. После слияния C2 можно начать C3 — съёмки, группы и календарь.

Организатор создаёт и изменяет учреждения, назначает curator/head. Curator и head видят только свои учреждения; пустая область даёт пустой список, teacher получает 403. Ограничение области применяется в SQL до подсчёта и пагинации. Ответ содержит UUID, название, адрес, revision, curatorId/headId, реальную assignmentSignature и метаданные страницы; UF-поля не выходят в HTTP. Поиск по буквальному префиксу сохраняет экранирование `%`, `_` и кавычек. По умолчанию 50 записей, максимум 100.

PATCH требует актуальную revision учреждения. Пропущенное назначение сохраняется, явный null снимает его. Изменение назначений требует актуальную assignmentSignature; замена или снятие занятого места также требует replaceAssignments=true. Указанный сотрудник должен быть активным, с включённым профилем и нужной ролью. Создание пустого учреждения не требует подписи; первую подпись можно получить из списка. ACC-06 добавит редактор сотрудников в B2, полная карточка ORG-04 появится в C4.

Изменение учреждения, слотов, revision/accessRevision сотрудников, отзыв их сессий, AccessChange, OrganizationChange и запись результата идемпотентности фиксируются одной транзакцией. Порядок блокировок: AccessState → учреждение → профили сотрудников по ID → Auth по ID. Bearer повторно проверяется после блокировки. Уровень READ COMMITTED исключает чтение старого токена из snapshot; deadlock повторяет всю транзакцию до трёх попыток, внешняя транзакция не принимается. Вход Auth также блокирует актуальные credentials до проверки пароля и записи токена: это закрывает гонку с отзывом сессии при назначении. Только служебные UF_TOKEN/UF_TOKEN_EXPIRES_AT записываются атомарным UPSERT: исключение больше не оставляет вложенную CUser-транзакцию и блокировку пользователя. Проверено также создание UTS для старого пользователя без строки. Активация и пользовательские поля сохраняют CUser-путь.

`Idempotency-Key` — 32 hex-символа. Журнал различает исполнителя, метод и ресурс, хранит хеш нормализованного запроса и исходный результат. Повтор после нового процесса возвращает тот же результат; другой запрос с тем же ключом даёт 409. Права и сессия проверяются заново, в том числе при повторе. POST возвращает 201 и Location. Некорректный JSON даёт 400, поля/типы — 422; ошибки содержат error.code/message и meta.requestId. Все ответы имеют Cache-Control: no-store. Штатный LoggerFilter не читает значения запроса или ответа.

## Хранение

Новая активная миграция `Version20260912210001` добавляет native HL InstitutionAssignment, AccessChange и OrganizationChange, технический журнал mf_institution_operation, ограничения и индексы InnoDB. Ссылки на native Institution.ID используют BIGINT, пользовательские ID — INT. Unique institution/role ограничивает слот; FK запрещают потерю ссылок на учреждение/профиль. Повторный up допустим, down отказывается при данных или истории. Старые миграции сохранены. Миграции выполнялись только на одноразовой тестовой БД.

## Проверка

Команды из корня API:

```sh
cd api
vendor/bin/phpunit
vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --memory-limit=512M
bash tools/run-c2-institutions-integration.sh
bash tools/run-w02-auth-integration.sh
```

Для runner нужны `W02_VENDOR_ROOT`, доступный PHP CLI image, read-only Bitrix kernel и Docker/MySQL 8. Проверка создаёт отдельную internal Docker network и БД на tmpfs без опубликованных портов; данные рабочего окружения не используются. [Native-сценарии](../../../api/tools/verify-c2-institutions-api.php) используют настоящие Router, Controller.run, DI, main validation, Auth, HL/ORM и MySQL. Raw JSON передаётся через HttpRequest-подкласс, поскольку CLI не читает php://input как HTTP. Это не сквозной прогон Nginx/PHP-FPM или frontend.

Итоговые результаты — в [verification.json](verification.json). [Совместная проверка с E2](cohort-verification.json): оба порядка слияния дают одно дерево; PHPUnit 249/854, PHPStan и обе native suites (136/95) проходят на объединённом коде. [Результаты слияния предшественников](merged-predecessors.json) подтверждают исходную базу. GitHub Actions остаётся отключённым, YAML сохранён. Развёртывание не выполнялось.

## Контракт и план

[Активный граф](../graph.json) отмечает в main A1–A7/B1/C1/E1 и независимую работу C2/E2. [Patch MoreFoto](morefoto-c2.patch) содержит исходники обновлённого графа и уточнения ORG-02 для редактора: revision, назначения и начальная подпись. Количество и владельцы API не меняются. MoreFoto не является Git-репозиторием; [manifest](morefoto-sync.json) хранит before/after-хеши и проверку воспроизведения десяти файлов.

После сверки before-хешей patch применяется из корня MoreFoto через `git apply --check`, затем `git apply`. Генерация: `python3 docs/04-bitrix-modules/render-waves.py`, `python3 docs/05-rest-api/build.py`, `python3 docs/05-rest-api/validate.py`, `node docs/05-rest-api/validate-postman.cjs`. Проверки Postman выполняются офлайн и отдельно от native API-тестов.
