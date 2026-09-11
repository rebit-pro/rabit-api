# W05 — проект схемы Access и Organization

**Предложение для ревью, таблицы и миграции не созданы.** Связано с [моделью](../02-domain/README.md) и [решениями W05](../waves/w05/decisions.md). Каноническая глава БД MoreFoto обновляется после согласования. UF-поля ниже — проект хранения, не форма API.

## Общие правила

Предметные записи — highload-блоки Bitrix; identity/пароль/сессии остаются в штатном хранилище Auth. Технические строки версии/повтора могут быть отдельными SQL-таблицами Infrastructure/Persistence. Не дублировать b_user и таблицу токенов в MoreFoto. Перед миграцией проверить фактический DDL UF-полей: logical user-field type не гарантирует нужные NOT NULL, размер и индекс.

Все таблицы общей транзакции, включая затрагиваемые Auth, должны быть InnoDB. Моменты времени хранятся UTC; timezone группы отдельно. Для публичных Institution/Shoot/Group предлагается UUID в строке 36 символов с отдельным уникальным индексом; внутренний HL ID не раскрывается как API ID. Staff API userId остаётся целым Bitrix ID. Имена блоков и физические названия ниже предварительные, конкретный migration ID назначается в W06/W07/W08/W09.

Общие поля предметных блоков: `ID` — внутренний PK; `UF_CREATED_AT`, `UF_UPDATED_AT` — непустые UTC datetime. У изменяемого агрегата `UF_REVISION` — положительное целое, начальное 1, увеличивается только при успешном изменении. ID инициатора хранится для аудита, а не берётся на веру из HTTP.

## Access: блоки и ограничения

| Блок / предлагаемая таблица | Поля сверх общих | Ограничения и запросы |
| --- | --- | --- |
| MfStaffProfile / b_hlbd_mf_staff_profile | UF_USER_ID int; UF_ROLE string(16); UF_ACTIVE boolean; UF_REVISION int; UF_ACCESS_REVISION int | UNIQUE(userId); role только organizer/curator/head/teacher; revision/accessRevision >= 1. INDEX(role,active,userId), список сотрудников/активных организаторов |
| MfInstitutionAssignment / b_hlbd_mf_institution_assignment | UF_INSTITUTION_ID int (внутренний HL ID); UF_ROLE string(16); UF_USER_ID int | UNIQUE(institutionId,role), role только curator/head; INDEX(userId,role,institutionId), выбор области. Назначаемый профиль активен и имеет совпадающую роль |
| MfGroupAssignment / b_hlbd_mf_group_assignment | UF_GROUP_ID int; UF_USER_ID int | UNIQUE(groupId); INDEX(userId,groupId), область teacher. Профиль активен и имеет роль teacher |

Назначение существует или отсутствует; userId=NULL и строки с active=false для «пустого» слота не нужны. История снятия отдельно, поэтому уникальность текущего слота не зависит от nullable active. Идентификаторы связи с Institution/Group сохраняются внутренними целыми, UUID разрешаются сервером до записи. Переименование и пустая область не требуют пересоздавать профиль.

`revision` профиля меняется при любом его редактировании; `accessRevision` — при роли/active/назначениях. Обычная смена имени не должна означать изменение полномочий. Name/email меняет Auth через контракт, версия карточки Access повышается в той же операции; пароль/ключ не входят в историю.

Техническая `mf_access_state`: ровно одна заранее созданная строка `id=1, assignments_revision BIGINT NOT NULL`. Она сериализует административные изменения первого среза и проверку последнего организатора, даёт assignmentSignature. Отсутствие строки — ошибка конфигурации, не версия 0. Изменение роли/active, влияющее на выбор назначения, тоже повышает assignments_revision. Подпись сравнивается сервером; криптографическая подпись не нужна для защиты права, которое проверяется отдельно.

Одна глобальная строка — осознанное упрощение для редких административных команд W06–W09. Оно даёт конфликты даже между разными учреждениями. Перед расширением на высокочастотные предметные команды оценить блокировки; не распространять глобальную сериализацию на каждый заказ/платёж автоматически.

## Organization: блоки и ограничения

| Блок / предлагаемая таблица | Поля сверх общих | Уникальность/индексы/инварианты |
| --- | --- | --- |
| MfInstitution / b_hlbd_mf_institution | UF_PUBLIC_ID string(36); UF_NAME string(255); UF_ADDRESS string(500); UF_REVISION int | UNIQUE(publicId); INDEX(createdAt,ID). Имя непустое после trim, адрес по согласованной валидации; имя не уникально |
| MfShoot / b_hlbd_mf_shoot | UF_PUBLIC_ID string(36); UF_INSTITUTION_ID int; UF_NAME string(255); UF_DATE date nullable; UF_REVISION int | UNIQUE(publicId); INDEX(institutionId,createdAt,ID); INDEX(institutionId,date,ID). Учреждение существует; календарный date не UTC timestamp |
| MfGroup / b_hlbd_mf_group | UF_PUBLIC_ID string(36); UF_SHOOT_ID int; UF_NAME string(255); UF_KIND string(16); UF_TIMEZONE string(64); UF_SENT_AT datetime nullable; UF_CLOSES_AT datetime nullable; UF_DELIVERY_DUE_AT datetime nullable; UF_REVISION int | UNIQUE(publicId); INDEX(shootId,createdAt,ID); INDEX(closesAt,ID). kind=regular/staff; непустая timezone; вся цепочка учреждения через Shoot |

Organization **не** хранит curatorId/headId/teacherId в своих блоках; это Access. Group не дублирует institutionId: учреждение определяется через Shoot. Gallery token, фото, orders, financial summary, готовность печати и личные ключи не входят в блок Group. Внешние расширенные ManagedGroup/OrganizationState из моков не копируются в одну JSON-колонку.

Для preparing все три календарных момента null. После передачи sentAt, closesAt и deliveryDueAt обязательны; обычное правило closesAt > sentAt, deliveryDueAt >= closesAt. Отдельные исключения D12 проектируются до разрешения соответствующей команды, не проходят обычной записью UF-полей. Тип группы, shootId и institutionId съёмки неизменяемы первым API. Изменение timezone после начала приёма тоже запрещено первым срезом.

Публичные UUID сравниваются канонически и однозначно; collation и длина индекса проверяются миграцией. Сортировка списков: createdAt DESC, ID DESC; выбирать только требуемые поля. API передаёт непрозрачный UUID, но внутренний ID допустим как стабильный tie-breaker. Пагинация по договорённому page/pageSize с пределом 100. Область и фильтры применяются в запросе до LIMIT, иначе возможны утечки и неверный total.

Точный поиск по UUID/id использует индекс. Поиск `q` по name/email требует отдельно определить семантику: простой B-tree не ускоряет `%term%`. До реализации списка W09 выбрать нормализованный префиксный поиск либо обосновать fulltext/ограниченную выборку по объёму; не заявлять индексирование произвольной подстроки. Все SQL-параметры связываются, динамические sort/filter выбираются из allowlist.

## Связи и история

Внешние связи InstitutionAssignment→Institution, GroupAssignment→Group, Shoot→Institution, Group→Shoot и assignment→StaffProfile обязательны. Если фактическая структура HL позволяет FK с совпадающими типами, использовать RESTRICT, без каскадного удаления. Если Bitrix-миграция не поддерживает нужный FK, проверка существования/role/цепочки идёт в транзакции под блокировками и отдельным integrity-check; нельзя обещать FK без проверки DDL. На b_user учитываются ограничения штатного механизма удаления: удаление сотрудника через обходной путь не должно оставлять разрешающий профиль.

Предлагается по одному журналу у владельца: MfAccessChange для роли/active/назначений, MfOrganizationChange для структуры/календаря. Поля: aggregateType, aggregateId, fromRevision, toRevision, actorUserId, reason, occurredAt UTC, operationId и минимальная before/after дельта без паролей/токенов/полных HTTP-тел. INDEX(aggregateType,aggregateId,toRevision), UNIQUE(aggregateType,aggregateId,toRevision). Для замены нескольких назначений операция объединяется operationId; владелец пишет одну запись на изменённый агрегат/версию. Все журнальные записи находятся в той же транзакции. Срок хранения согласовать до ввода персональных данных; W05 его не выдумывает.

## Конкуренция, повтор и Auth

Порядок блокировок и единая транзакция — в [модели](../02-domain/README.md#транзакционная-граница). SQL-обновление revision выполняется условно; ноль изменённых строк — конфликт, не успех. Для вставки пустого слота одного `SELECT FOR UPDATE` недостаточно: обязательны уникальный индекс слота и сериализация через AccessState. Из двух конкурентных замен одна проходит, другая получает конфликт; повторная загрузка показывает нового владельца.

Для команд с Idempotency-Key нужен scoped журнал завершённой операции у владельца сценария: UNIQUE(actorUserId,operation,idempotencyKey), хеш нормализованного payload, итоговый ID/revision и безопасный результат. Результат записывается в той же транзакции. Другой payload с тем же ключом — конфликт. Повтор после commit сначала проверяет текущий доступ и возвращает исходный результат, не пересоздаёт запись/историю и не отзывает сессии снова. Срок хранения/формат ключа сверить с каноническим HTTP-контрактом в волне реализации; универсальную инфраструктуру для всех будущих модулей заранее не строить.

Создание Institution с curator/head: общий transaction → блокировка AccessState → проверка инициатора/подписи/назначаемых профилей → Institution → записи Access → версии/отзыв/история/idempotency → commit. Если ошибка Access, учреждения не остаётся. Аналогично Group с teacher. Для редактирования сначала блокируются существующие родители и агрегат по единому порядку.

Смена роли/active/назначений: блокировки → повторная проверка → записи профиля/слотов → accessRevision всех затронутых пользователей → отзыв Auth → журналы/результат → commit. Ошибка отзыва приводит к rollback, а не успеху с ещё действующим токеном. Предлагаемые 24 часа приглашения не сохраняются в StaffProfile: одноразовую выдачу и секреты хранит Auth.

Проверить гонку login с отзывом: Auth должен блокировать ту же учётку и не сохранять token, рассчитанный до смены accessRevision, после успешного отзыва. Если существующий Auth не обеспечивает общий connection/lock и безопасные side effects CUser, нужен отдельный разбор его адаптера до W09. W02 исправляет срок/регистрацию, но не доказывает эту новую атомарную команду. Не заменять это окно гонки двумя несвязанными commit.

## Чтение и миграции

Репозиторий Domain возвращает `Bitrix\Main\ORM\Query\Result` или `Bitrix\Main\DB\Result`; потребитель читает массивные строки с phpDoc shape и преобразует в DTO. Один JOIN не требует ReadModel. Result/UF-поля/ReadModel не уходят в HTTP, межмодульный контракт или кеш. Не создавать Objectify-коллекции для списков. Служебный summary не записывается в HL как второй финансовый источник.

План миграций: W06 — StaffProfile и минимальная AccessState; W07 — Institution и InstitutionAssignment, необходимые журналы/повтор; W08 — Shoot, Group и GroupAssignment для teacher; W09 — только недостающие структуры управления учётками. Assignment-блоки принадлежат Access даже когда впервые понадобились в W07/W08. Не создавать все будущие агрегаты заранее.

Активный каталог: `api/public/local/php_interface/migrations.foundation/`; конфиг `migrations.cfg.php`; история `sprint_migration_versions`. Ранее применённые версии не редактируются. Каждый будущий PR содержит фактический migration ID, DDL до/после, проверку повторного запуска, индексов/коллизий, engine/таймзоны и откат кода без автоматического уничтожения данных.

Критерии БД: новая пустая тестовая установка; реальная работа Array Result; две съёмки/одно учреждение; коллизия UUID/слота; гонка двух назначений; rollback при ошибке Auth/журнала; отсутствие частично созданной сущности; конфликт устаревшей revision/signature; последний организатор под конкурентным запросом; login одновременно с отзывом. Это программа тестов будущих волн, не результат W05.
