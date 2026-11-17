# Авторская самопроверка этапа 7

Версия: base/HEAD/merge-base `b826a157d608c63cbf9e50aab5ebc0b54de228bb` + собственный dirty diff; новые файлы включены в source-manifest.json. Удалённый main повторно сверён. PR нет, внешних публикаций не было. Это самопроверка автора, независимое review не выполнено; точная модель и reasoning недоступны в контексте.

Проход Б выполнен по исходникам целых изменённых методов/классов; затем проход А по сценариям. Таблицы ниже фиксируют применённые карточки, не заменяют результаты запуска. В строках перечислены проверенные правила; особые runtime-вопросы явно оставлены. Общие STYLE-01/02 относятся ко всем PHP/TS/Vue-файлам: точечный formatter, PHPStan и проектные lint/typecheck прошли. Генератор этой таблицы только оформляет уже прочитанный набор.

## Диапазон целиком

| Правила | Результат |
| --- | --- |
| CHANGE-01/02/03 | Покрыты все 83 source/config/test файла, plan/progress и собственные артефакты; SHA-256 включает untracked. Чужой исходный checkout не изменялся. Секреты/дампы/сборки в diff отсутствуют. |
| TEST-01 | Unit 1406 / 53683 assertions, frontend UI 129 / commerce 253; новые реальные T14/T12/T15 assertions подготовлены, full a+b PASS (164 browser, 22 native verifiers). |
| TEST-02/03 | Unit fake назван однопоточным. GET_LOCK/CAS/Result/байты/cleanup не объявлены доказанными unit; native PASS (69 assertions этапа 7). Предыдущие FAIL и версии записаны в progress. |
| MIG-01/02 | Foundation-схема и безопасный down прочитаны; rollout: migration до backend/worker, действующие cron обновляются вместе; native PASS. |
| NOTE-01 | — Публичный набор замечаний и approve не поручены. |
| CACHE-01/02 | — Новые cache keys/инвалидация не вводятся. |
| CACHE-03 | Область ProtectedStorage и удаление собственных attempt/tmp проверены по коду; native cleanup PASS. |
| EVT-01/02, CMP-01 | — Новых Bitrix handlers/domain events/IncludeComponent нет; сообщения очереди проверены по MSG. |

## Files: сценарий и предметные правила (23 файлов)

| Файл | Вид | Проверенные ID | Ограничение/применимость |
| --- | --- | --- | --- |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Contract/DigitalArchivePublisherInterface.php` | порт/контракт | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, REUSE-01/02 | Публичная граница принадлежит поставщику; состояние/реализация не вынесены в Share. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Contract/DigitalArchiveWriterInterface.php` | порт/контракт | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, REUSE-01/02 | Публичная граница принадлежит поставщику; состояние/реализация не вынесены в Share. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Dto/DigitalArchiveLinkOutputDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Dto/DigitalArchiveListOutputDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Dto/DigitalArchiveOutputDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Message/BuildInstitutionDigitalArchiveMessage.php` | очередь/CLI | MSG-01/02/03, LOCK-01/02, CLI-01, PROC-01, DI-01/02 | Действующая очередь Files, dispatch/purge; producer dedup отдельно от CAS. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Message/Handler/BuildInstitutionDigitalArchiveMessageHandler.php` | очередь/CLI | MSG-01/02/03, LOCK-01/02, CLI-01, PROC-01, DI-01/02 | Действующая очередь Files, dispatch/purge; producer dedup отдельно от CAS. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Service/DigitalArchiveAccess.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Service/DigitalArchiveBuild.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Service/DigitalArchiveSnapshot.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/Service/DigitalArchiveView.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/UseCase/CreateInstitutionDigitalArchiveUseCase.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/UseCase/DownloadInstitutionDigitalArchiveUseCase.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/UseCase/ExportInstitutionDigitalArchiveUseCase.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/UseCase/GetInstitutionDigitalArchiveUseCase.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/UseCase/ListInstitutionDigitalArchivesUseCase.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Application/Institution/UseCase/MaintainInstitutionDigitalArchivesUseCase.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Domain/Institution/Exception/DigitalArchiveException.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Domain/Institution/Repository/DigitalArchiveRepositoryInterface.php` | порт/контракт | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, REUSE-01/02 | Публичная граница принадлежит поставщику; состояние/реализация не вынесены в Share. |
| `api/public/local/modules/morefoto.files/lib/Domain/Institution/Service/DigitalArchiveLayout.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Domain/Institution/Service/DigitalArchivePolicy.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Domain/Institution/ValueObject/DigitalArchive.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/lib/Domain/Institution/ValueObject/DigitalArchiveFile.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |

## Files: границы, хранение и проверки (28 файлов)

| Файл | Вид | Проверенные ID | Ограничение/применимость |
| --- | --- | --- | --- |
| `api/public/local/modules/morefoto.files/.settings.php` | bootstrap/DI | DI-01/02/03/04, LAYER-01/02/03, HTTP-01, MIG-02 | Singleton без запроса в состоянии; interface-key использует constructor; env не добавлен. |
| `api/public/local/modules/morefoto.files/di/files.php` | bootstrap/DI | DI-01/02/03/04, LAYER-01/02/03, HTTP-01, MIG-02 | Singleton без запроса в состоянии; interface-key использует constructor; env не добавлен. |
| `api/public/local/modules/morefoto.files/di/institution.php` | bootstrap/DI | DI-01/02/03/04, LAYER-01/02/03, HTTP-01, MIG-02 | Singleton без запроса в состоянии; interface-key использует constructor; env не добавлен. |
| `api/public/local/modules/morefoto.files/include.php` | bootstrap/DI | DI-01/02/03/04, LAYER-01/02/03, HTTP-01, MIG-02 | Singleton без запроса в состоянии; interface-key использует constructor; env не добавлен. |
| `api/public/local/modules/morefoto.files/install/index.php` | bootstrap/DI | DI-01/02/03/04, LAYER-01/02/03, HTTP-01, MIG-02 | Singleton без запроса в состоянии; interface-key использует constructor; env не добавлен. |
| `api/public/local/modules/morefoto.files/lib/Infrastructure/Database/BitrixDigitalArchiveRepository.php` | SQL/хранилище | LAYER-01/02/03, REPO-01/02/03/04/05, PERF-01/02, EXC-01/02, LOCK-01/02, DOC-01/02 | Только собственные таблицы; Result остаётся у поставщика, CAS/GET_LOCK требует native. |
| `api/public/local/modules/morefoto.files/lib/Infrastructure/Database/DigitalArchiveCodec.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Infrastructure/File/DigitalArchiveWriter.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Infrastructure/Messenger/DigitalArchivePublisher.php` | очередь/CLI | MSG-01/02/03, LOCK-01/02, CLI-01, PROC-01, DI-01/02 | Действующая очередь Files, dispatch/purge; producer dedup отдельно от CAS. |
| `api/public/local/modules/morefoto.files/lib/Infrastructure/Messenger/FilesMessengerFactory.php` | очередь/CLI | MSG-01/02/03, LOCK-01/02, CLI-01, PROC-01, DI-01/02 | Действующая очередь Files, dispatch/purge; producer dedup отдельно от CAS. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Command/DispatchPendingDownloadsCommand.php` | очередь/CLI | MSG-01/02/03, LOCK-01/02, CLI-01, PROC-01, DI-01/02 | Действующая очередь Files, dispatch/purge; producer dedup отдельно от CAS. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Command/PurgeDownloadsCommand.php` | очередь/CLI | MSG-01/02/03, LOCK-01/02, CLI-01, PROC-01, DI-01/02 | Действующая очередь Files, dispatch/purge; producer dedup отдельно от CAS. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Controller/InstitutionDigitalArchiveContentController.php` | HTTP | HTTP-01/02/03/04/05/06, LAYER-01/02, DTO-02/03, DI-01, CMP-02/03 | Один strict request DTO, shared base, UseCase/mapper; CMP-01: Bitrix component отсутствует. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Controller/InstitutionDigitalArchiveController.php` | HTTP | HTTP-01/02/03/04/05/06, LAYER-01/02, DTO-02/03, DI-01, CMP-02/03 | Один strict request DTO, shared base, UseCase/mapper; CMP-01: Bitrix component отсутствует. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Institution/DigitalArchiveResultMapper.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Institution/Request/Dto/CreateInstitutionDigitalArchiveRequestDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Institution/Request/Dto/InstitutionDigitalArchiveRequestDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Institution/Request/Dto/InstitutionDigitalArchiveTicketRequestDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Institution/Request/Dto/InstitutionDigitalArchivesRequestDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Institution/Result/Dto/DigitalArchiveLinkResultDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Institution/Result/Dto/DigitalArchiveListResultDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/lib/Presentation/Institution/Result/Dto/DigitalArchiveResultDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/morefoto.files/routes.php` | HTTP | HTTP-01/02/03/04/05/06, LAYER-01/02, DTO-02/03, DI-01, CMP-02/03 | Один strict request DTO, shared base, UseCase/mapper; CMP-01: Bitrix component отсутствует. |
| `api/public/local/modules/morefoto.files/tests/Unit/DownloadsMaintenanceCommandTest.php` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `api/public/local/modules/morefoto.files/tests/Unit/InstitutionDigitalArchiveArchitectureTest.php` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `api/public/local/modules/morefoto.files/tests/Unit/InstitutionDigitalArchiveTest.php` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `api/public/local/modules/morefoto.files/tests/Unit/Support/InMemoryDigitalArchives.php` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `api/public/local/php_interface/migrations.foundation/Version20261117180001.php` | схема | MIG-01/02, REPO-02/03/04, STYLE-01/02 | Foundation, up повторяем, down защищает состав; runtime replay PASS. |

## Поставщики, UI и стенд (32 файлов)

| Файл | Вид | Проверенные ID | Ограничение/применимость |
| --- | --- | --- | --- |
| `api/docker/common/nginx/conf.d/default.conf` | nginx | HTTP-03/05, FE-06, CHANGE-02 | Только исключение ticket URI из access log; internal storage остаётся закрытым. |
| `api/public/local/modules/morefoto.commerce/di/orders.php` | bootstrap/DI | DI-01/02/03/04, LAYER-01/02/03, HTTP-01, MIG-02 | Singleton без запроса в состоянии; interface-key использует constructor; env не добавлен. |
| `api/public/local/modules/morefoto.commerce/lib/Domain/Order/Repository/OrderRepository.php` | SQL/хранилище | LAYER-01/02/03, REPO-01/02/03/04/05, PERF-01/02, EXC-01/02, LOCK-01/02, DOC-01/02 | Только собственные таблицы; Result остаётся у поставщика, CAS/GET_LOCK требует native. |
| `api/public/local/modules/morefoto.commerce/lib/Infrastructure/Files/PurchasedDigitalLines.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.commerce/tests/Unit/PurchasedDigitalLinesTest.php` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `api/public/local/modules/morefoto.media/di/gallery.php` | bootstrap/DI | DI-01/02/03/04, LAYER-01/02/03, HTTP-01, MIG-02 | Singleton без запроса в состоянии; interface-key использует constructor; env не добавлен. |
| `api/public/local/modules/morefoto.media/lib/Application/Gallery/Service/ChildPhotos.php` | сервис | LAYER-01/02/03/04, REUSE-01/02, DI-01/02, EXC-01/02, DOC-01/02, PERF-01/02 | Проверены ответственность, typed вход/выход и отсутствие изменяемого request state. |
| `api/public/local/modules/morefoto.media/lib/Domain/Gallery/Repository/GalleryPhotoRepository.php` | SQL/хранилище | LAYER-01/02/03, REPO-01/02/03/04/05, PERF-01/02, EXC-01/02, LOCK-01/02, DOC-01/02 | Только собственные таблицы; Result остаётся у поставщика, CAS/GET_LOCK требует native. |
| `api/public/local/modules/morefoto.media/tests/Unit/ChildPhotosTest.php` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `api/public/local/modules/rebit.share/lib/Contracts/Commerce/Dto/PurchasedDigitalLineOutputDto.php` | данные | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, STYLE-01/02 | Только типизированные данные; I/O, auth и бизнес-ветвей здесь нет. |
| `api/public/local/modules/rebit.share/lib/Contracts/Commerce/PurchasedDigitalLinesInterface.php` | порт/контракт | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, REUSE-01/02 | Публичная граница принадлежит поставщику; состояние/реализация не вынесены в Share. |
| `api/public/local/modules/rebit.share/lib/Contracts/Media/BoundedChildPhotosInterface.php` | порт/контракт | LAYER-01/02/03, DTO-01/02/03, DOC-01/02, REUSE-01/02 | Публичная граница принадлежит поставщику; состояние/реализация не вынесены в Share. |
| `api/tools/e2e/institution-digital-fixture.php` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `api/tools/e2e/prepare.php` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `api/tools/e2e/verify-institution-digital.php` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `frontend/docker/development/nginx/conf.d/default.conf` | nginx | HTTP-03/05, FE-06, CHANGE-02 | Только исключение ticket URI из access log; internal storage остаётся закрытым. |
| `frontend/docker/production/nginx/conf.d/default.conf` | nginx | HTTP-03/05, FE-06, CHANGE-02 | Только исключение ticket URI из access log; internal storage остаётся закрытым. |
| `frontend/e2e/live/groups.json` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `frontend/e2e/live/zzzzzzzzzz-institution-digital.spec.ts` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `frontend/public/guide/curator/index.html` | инструкция | CHANGE-02/03, FE-05/06/07 | Статический текст, без формы, script или новых внешних запросов. |
| `frontend/public/guide/organizer/index.html` | инструкция | CHANGE-02/03, FE-05/06/07 | Статический текст, без формы, script или новых внешних запросов. |
| `frontend/src/modules/morefoto/files/DigitalArchiveCard.vue` | UI/API caller | FE-01/02/03/05/06/07/08, HTTP-01/03/04/05/06 | Composition API/TS и текущие токены; FE-04: SPA; visual PASS. |
| `frontend/src/modules/morefoto/files/InstitutionDigitalArchiveDialog.vue` | UI/API caller | FE-01/02/03/05/06/07/08, HTTP-01/03/04/05/06 | Composition API/TS и текущие токены; FE-04: SPA; visual PASS. |
| `frontend/src/modules/morefoto/files/institution-digital-api.ts` | UI/API caller | FE-01/02/03/05/06/07/08, HTTP-01/03/04/05/06 | Composition API/TS и текущие токены; FE-04: SPA; visual PASS. |
| `frontend/src/modules/morefoto/files/institution-digital-rules.ts` | UI/API caller | FE-01/02/03/05/06/07/08, HTTP-01/03/04/05/06 | Composition API/TS и текущие токены; FE-04: SPA; visual PASS. |
| `frontend/src/modules/morefoto/files/institution-digital-types.ts` | UI/API caller | FE-01/02/03/05/06/07/08, HTTP-01/03/04/05/06 | Composition API/TS и текущие токены; FE-04: SPA; visual PASS. |
| `frontend/src/modules/morefoto/files/useInstitutionDigitalArchive.ts` | UI/API caller | FE-01/02/03/05/06/07/08, HTTP-01/03/04/05/06 | Composition API/TS и текущие токены; FE-04: SPA; visual PASS. |
| `frontend/src/modules/morefoto/structure/components/InstitutionScreen.vue` | UI/API caller | FE-01/02/03/05/06/07/08, HTTP-01/03/04/05/06 | Composition API/TS и текущие токены; FE-04: SPA; visual PASS. |
| `frontend/src/modules/morefoto/structure/components/InstitutionTable.vue` | UI/API caller | FE-01/02/03/05/06/07/08, HTTP-01/03/04/05/06 | Composition API/TS и текущие токены; FE-04: SPA; visual PASS. |
| `frontend/src/modules/morefoto/structure/components/StructureScreen.vue` | UI/API caller | FE-01/02/03/05/06/07/08, HTTP-01/03/04/05/06 | Composition API/TS и текущие токены; FE-04: SPA; visual PASS. |
| `frontend/tests/ui/institution-digital-archive.test.mjs` | проверка | STYLE-01/02, TEST-01/02/03, DOC-01/02 | Runtime-доказательства отдельно от unit; full a+b PASS, результаты в verification.json. |
| `tools/run-browser-e2e.py` | runner | TEST-01/02/03, CLI-01, CHANGE-02/03 | Подключены fixture/spec/verifier; существующее владение disposable-ресурсами сохранено. |

## Проход А

1. Оплата/история: Commerce отбирает paid digital/bundle без coveredByGift и неразрешённой late; истёкший buyer key не участвует. Cutoff UTC, MAX_LINES+1. Media раскрывает только выбранных детей/съёмку, bounded limit+1; Files дедуплицирует photo внутри группы, хранит все основания.
2. Фиксация/повторы: scoped actor/key и institution lock до одного INSERT состава; publish после сохранения, потерю брокера восстанавливает dispatch. Сборка читает сохранённые имена/пути/размеры, а не новые покупки/кадры. Attempts/owner/CAS исключают stale readiness.
3. Отказы/срок: отсутствующий оригинал — failed, не ready; технический 503 повторяется, 401/403/404 терминальны. Готовность +7 суток; ticket не продлевает ZIP, manifest/состав и оригиналы сохраняются после purge. Имена/коллизии/CSV формулы проверены по всем ветвям.
4. Доступ/HTTP: organizer create, curator только назначенное учреждение, прочие 403; каждый content проверяет текущий actor scope. Отдельный короткий HMAC-контекст, пути не выходят наружу, ticket URI исключён из трёх nginx log. Buyer routes/rights/mail не заменялись.
5. DI/доставка: поставщики уже в main, interface-key constructor; Files consumer/dispatch/purge переиспользованы. Обнаруженный безусловный CLI SUCCESS исправлен и проверен через CommandTester по обоим batch.
6. UI: action на учреждении и списке, существующие Vuetify/tokens; composable отменяет запросы/таймеры, epoch блокирует устаревший DOM-download, key сохраняется для неопределённого повтора. Статусы/дата/срок/CSV разделены. Реальная визуальная проверка PASS (desktop, mobile и footer).
7. Scope: печатный lifecycle/облако/чат/права покупателя/полный этап 8 не включались. Прежний LIMIT 5001 у покупателей описан как отдельное наблюдение; новая bounded проекция его не наследует.

## Визуальный критерий

Сотрудник учреждения вручную передаёт купленные кадры, сверяет состав и срок. Доминанта карточки — состояние и Скачать ZIP; дата состава/срок и CSV вторичны. Действующая система «Кадр и мазок v2»: морской акцент sea-600, белая поверхность, холодные graphite/sea фон и текст, семантические error/success; Golos Text/Manrope, сетка 4px, 16/24px padding, действующие mf-panel и Vuetify focus/overlay. Нового направления/палитры нет. Просмотрены настоящие desktop/mobile/footer screenshots финального прогона; все три PASS, без мокового макета.

## Итог

17.11.2026: полный `rabit-e2e-7c941de7cfb1` PASS за 602.6 s, 164 browser (81+83), без skipped/failed/flaky, 22 native verifier. Этап 7 — 69 native assertions, 64 МиБ ZIP: дополнительный PHP peak 0 bytes, RSS 0 KiB на данном процессе. Последняя исправленная фикстура сохраняет реальные assignment связи; D3 глобальный verifier PASS. Все 83 SHA-256 runtime/config/test файла совпали после gate. Desktop/mobile/footer реально просмотрены и сохранены; финальные метрики/хеши — verification.json. Только авторская самопроверка; независимое review не подменяется.
