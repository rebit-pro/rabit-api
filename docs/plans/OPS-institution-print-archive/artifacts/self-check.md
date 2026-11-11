# Авторская самопроверка этапа 5

Статус: авторские проходы Б/А завершены по решению 1.1; полный gate 3 PASS (62559a39d294). **Это не независимое review.**
Исполнитель: Codex; точный идентификатор модели и reasoning недоступны, не заявляются.

- Checkout: `/home/user/rabit-api/.worktrees/ops-institution-print-archive`, `codex/ops-institution-print-archive`.
- Base/HEAD/merge-base: `7b9de6ed82b7786e4b5e957a3d44a004fcb92935`. Проверяется dirty diff и новые файлы, commit отсутствует. Удалённая ветка задачи/PR не создавались.
- Последняя gh-проверка main: тот же SHA. Источники точной версии: `source-hashes-gate-3.json` (66 runtime/test файлов); `source-hashes-final.json` добавляет после gate только уточнение комментариев ArchiveLayout, исполняемые токены идентичны.
- Правила: AGENTS/CLAUDE этого дерева; OS code-review от 07.11.2026, review-opposition, architecture-review и карточки layers/http/storage/processes/frontend/delivery. Сначала Б, затем А. Все методы новых классов и изменённые методы существующих файлов прочитаны целиком; проверки в течение реализации тоже относятся к авторской самопроверке.

## Проход Б

Таблицы используют профили ниже. «Ок» означает авторскую проверку в указанном контексте, не формальное доказательство отсутствия дефектов. Все строки также проверены по CHANGE-01/02/03, TEST-03. Глобальные неприменимости перечислены отдельно; применимость риска проверяется поперёк файлов в А.

- **PHP-core** — ок: LAYER-01 LAYER-02 LAYER-03 LAYER-04 REUSE-01 REUSE-02 DTO-01 DTO-02 DTO-03 DOC-01 DOC-02 DI-01 DI-02 EXC-01 EXC-02 STYLE-01 STYLE-02 TEST-01. Неприменимо/граница: DI-03 DI-04: регистрация проверяется в DI; REPO/CACHE/HTTP/FE: нет собственного хранения, кеша или HTTP-обвязки; EVT/MSG/CLI: не точка входа; LOCK/PROC: по отдельным сценариям А; MIG/NOTE: нет миграции/публикации.
- **PHP-data** — ок: LAYER-01 LAYER-02 LAYER-03 REUSE-01 REUSE-02 DTO-01 DTO-02 DTO-03 DOC-01 DOC-02 STYLE-01 STYLE-02. Неприменимо/граница: DI/EXC/REPO/CACHE/HTTP/FE/EVT/MSG/LOCK/CLI/PROC/MIG: только типизированные immutable данные/сигнатуры; поведение проверяется у потребителя.
- **PHP-DI** — ок: LAYER-01 LAYER-02 LAYER-03 REUSE-01 REUSE-02 DI-01 DI-02 DI-03 DI-04 STYLE-01 STYLE-02 TEST-01. Неприменимо/граница: DTO/DOC/REPO/CACHE/HTTP/FE/EVT/MSG/LOCK/CLI/PROC/MIG: конфиг собирает готовые сервисы; runtime-цепочка в А.
- **PHP-storage** — ок: LAYER-01 LAYER-02 LAYER-03 REUSE-01 REUSE-02 DTO-01 DTO-02 DOC-01 DOC-02 DI-02 EXC-01 EXC-02 REPO-01 REPO-02 REPO-03 REPO-04 REPO-05 PERF-01 PERF-02 LOCK-01 LOCK-02 STYLE-01 STYLE-02 TEST-01. Неприменимо/граница: CACHE-01 CACHE-02: кеш не добавлен; DI-01 DI-03 DI-04: регистрация отдельно; HTTP/FE/EVT/MSG/CLI/PROC/MIG: не их точка входа.
- **PHP-http** — ок: LAYER-01 LAYER-02 LAYER-03 REUSE-01 REUSE-02 DTO-01 DTO-02 DTO-03 DOC-01 DOC-02 DI-01 DI-02 EXC-01 EXC-02 HTTP-01 HTTP-02 HTTP-03 HTTP-04 HTTP-05 HTTP-06 CMP-02 CMP-03 STYLE-01 STYLE-02 TEST-01. Неприменимо/граница: CMP-01: Bitrix-компоненты не менялись; REPO/CACHE/FE/EVT/MSG/LOCK/CLI/PROC/MIG: нет собственной реализации, делегировано UseCase/обвязке.
- **PHP-cron** — ок: LAYER-01 LAYER-02 LAYER-03 REUSE-01 REUSE-02 DI-01 DI-02 EXC-01 EXC-02 LOCK-01 LOCK-02 CLI-01 PROC-01 STYLE-01 STYLE-02 TEST-01. Неприменимо/граница: HTTP/FE/EVT/MSG/CACHE/MIG: не добавлены; DTO/REPO: команды используют существующие контракты, SQL отсутствует.
- **PHP-migration** — ок: LAYER-01 LAYER-02 REUSE-01 REUSE-02 REPO-02 REPO-03 REPO-04 REPO-05 MIG-01 MIG-02 STYLE-01 STYLE-02 TEST-01. Неприменимо/граница: DTO/DI/CACHE/HTTP/FE/EVT/MSG/LOCK/CLI/PROC: только DDL; MIG-02: проверен порядок поставки, production deployment не выполнялся.
- **Test-PHP** — ок: LAYER-01 LAYER-02 REUSE-01 REUSE-02 DTO-01 DTO-02 DOC-01 DOC-02 STYLE-01 STYLE-02 TEST-01 TEST-02 TEST-03. Неприменимо/граница: HTTP/REPO/LOCK/CLI/PROC: применены к проверяемым сценариям, не к тестовым двойникам как production реализации; FE/CACHE/EVT/MSG/MIG/DI: не добавлены; прямой SQL в изолированном verifier допустим как независимая сверка владельцев.
- **Frontend** — ок: REUSE-01 REUSE-02 DTO-02 DTO-03 HTTP-01 HTTP-02 HTTP-03 HTTP-04 HTTP-05 HTTP-06 CMP-02 CMP-03 FE-01 FE-02 FE-03 FE-05 FE-06 FE-07 FE-08 STYLE-01 TEST-01. Неприменимо/граница: FE-04: клиентское SPA; PHP-specific LAYER/DOC/DI/EXC/REPO/CACHE/EVT/MSG/LOCK/CLI/PROC/MIG: нет PHP/хранения; backend остаётся единственным разрешением прав.
- **Test-browser** — ок: REUSE-01 REUSE-02 DTO-02 HTTP-01 HTTP-02 HTTP-03 HTTP-04 HTTP-05 HTTP-06 FE-01 FE-02 FE-03 FE-05 FE-06 FE-07 FE-08 STYLE-01 TEST-01 TEST-02 TEST-03. Неприменимо/граница: FE-04: SPA; остальные backend implementation ID: тест вызывает настоящие HTTP, не реализует runtime; Reviewer не запускался, E2E выполнял автор до самопроверки.
- **Guide** — ок: CHANGE-01 CHANGE-02 CHANGE-03 DOC-01 DOC-02 CMP-03 FE-05 FE-06 FE-07 TEST-03. Неприменимо/граница: Остальные ID: статический текст/якоря без runtime/бизнес-логики; инструкция сопоставлена с API/UI.
- **Tool** — ок: CHANGE-01 CHANGE-02 CHANGE-03 REUSE-01 REUSE-02 CLI-01 TEST-01 TEST-02 TEST-03. Неприменимо/граница: Остальные ID: добавлена строка запуска verifier в существующий runner, без изменения механизма стенда.

Глобально: EVT-01/02, MSG-01/02/03 — нет новых событий/сообщений/transport; CACHE-01/02 — нет нового кеша/инвалидации; CMP-01 — нет IncludeComponent/AJAX/frame; NOTE-01 — внешняя публикация не поручена. CACHE-03 проверен для изолированного файлового state: owner-specific путь и CAS, expiry не удаляет новую генерацию. MIG-02 проверен как порядок поставки, фактическая production-выкладка вне поручения. TEST-02 неприменим в runtime-файлах, проверен рядом с unit/native. FE-04 — SPA. STYLE-02/PHPDoc неприменимы к TS/HTML/Python; профильные типы/комментарии проверены.

### Часть 1 — Domain/Application/Share и unit (28 файлов)

| Файл | Профиль | Результат |
| --- | --- | --- |
| `api/public/local/modules/morefoto.organization/lib/Application/Institution/Service/PrintInstitutionDirectory.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/Dto/InstitutionDownloadOutputDto.php` | PHP-data | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/Dto/InstitutionProductionOutputDto.php` | PHP-data | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/Dto/InstitutionVersionOutputDto.php` | PHP-data | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/Service/InstitutionArchiveAccess.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/Service/InstitutionArchiveBuilder.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/Service/InstitutionArchiveView.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/Service/InstitutionGroups.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/Service/InstitutionSnapshot.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/UseCase/CreateInstitutionSupplementUseCase.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/UseCase/DownloadInstitutionArchiveUseCase.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/UseCase/ExportInstitutionArchiveUseCase.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/UseCase/GetInstitutionProductionUseCase.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/UseCase/RenewInstitutionArchiveUseCase.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Application/Institution/UseCase/RunInstitutionProductionUseCase.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Domain/Archive/Service/ArchiveLayout.php` | PHP-core | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Domain/Institution/Exception/InstitutionArchiveBusyException.php` | PHP-data | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Domain/Institution/Repository/InstitutionArchiveRepositoryInterface.php` | PHP-data | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Domain/Institution/ValueObject/InstitutionArchive.php` | PHP-data | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Domain/Institution/ValueObject/InstitutionArchiveGroup.php` | PHP-data | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Domain/Institution/ValueObject/InstitutionArchiveRequest.php` | PHP-data | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Domain/Institution/ValueObject/NewInstitutionArchive.php` | PHP-data | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/tests/Unit/InstitutionPrintArchiveTest.php` | Test-PHP | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/tests/Unit/ProductionArchitectureTest.php` | Test-PHP | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/tests/Unit/Support/InMemoryInstitutionArchives.php` | Test-PHP | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/tests/Unit/Support/InstitutionFixture.php` | Test-PHP | Ок; рамки профиля выше |
| `api/public/local/modules/rebit.share/lib/Contracts/Organization/Dto/PrintInstitutionOutputDto.php` | PHP-data | Ок; рамки профиля выше |
| `api/public/local/modules/rebit.share/lib/Contracts/Organization/PrintInstitutionDirectoryInterface.php` | PHP-data | Ок; рамки профиля выше |

### Часть 2 — Infrastructure/Presentation/DI/схема (20 файлов)

| Файл | Профиль | Результат |
| --- | --- | --- |
| `api/public/local/modules/morefoto.organization/di/institution.php` | PHP-DI | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.organization/lib/Domain/Institution/Repository/InstitutionRepository.php` | PHP-storage | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/.settings.php` | PHP-DI | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/di/http.php` | PHP-DI | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/di/institution.php` | PHP-DI | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Infrastructure/Database/BitrixInstitutionArchiveRepository.php` | PHP-storage | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Command/RunProductionCommand.php` | PHP-cron | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Controller/InstitutionArchiveContentController.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Controller/InstitutionProductionController.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Institution/InstitutionResultMapper.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Institution/Request/Dto/InstitutionArchiveRenewalRequestDto.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Institution/Request/Dto/InstitutionArchiveRequestDto.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Institution/Request/Dto/InstitutionArchiveTicketRequestDto.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Institution/Request/Dto/InstitutionProductionRequestDto.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Institution/Request/Dto/InstitutionSupplementRequestDto.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Institution/Result/Dto/InstitutionDownloadResultDto.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Institution/Result/Dto/InstitutionProductionResultDto.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/lib/Presentation/Institution/Result/Dto/InstitutionVersionResultDto.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/modules/morefoto.production/routes.php` | PHP-http | Ок; рамки профиля выше |
| `api/public/local/php_interface/migrations.foundation/Version20261110180001.php` | PHP-migration | Ок; рамки профиля выше |

### Часть 3 — Frontend/инструкции/E2E tooling (18 файлов)

| Файл | Профиль | Результат |
| --- | --- | --- |
| `api/tools/e2e/institution-production-fixture.php` | Test-PHP | Ок; рамки профиля выше |
| `api/tools/e2e/prepare.php` | Test-PHP | Ок; рамки профиля выше |
| `api/tools/e2e/production-fixture.php` | Test-PHP | Ок; рамки профиля выше |
| `api/tools/e2e/verify-institution-production.php` | Test-PHP | Ок; рамки профиля выше |
| `frontend/e2e/live/zzzzzzzzzzzz-print-archive.spec.ts` | Test-browser | Ок; рамки профиля выше |
| `frontend/public/guide/curator/index.html` | Guide | Ок; рамки профиля выше |
| `frontend/public/guide/organizer/index.html` | Guide | Ок; рамки профиля выше |
| `frontend/src/modules/morefoto/production/live/InstitutionArchiveVersionCard.vue` | Frontend | Ок; рамки профиля выше |
| `frontend/src/modules/morefoto/production/live/InstitutionPrintArchiveDialog.vue` | Frontend | Ок; рамки профиля выше |
| `frontend/src/modules/morefoto/production/live/institution-archive-api.ts` | Frontend | Ок; рамки профиля выше |
| `frontend/src/modules/morefoto/production/live/institution-archive-rules.ts` | Frontend | Ок; рамки профиля выше |
| `frontend/src/modules/morefoto/production/live/institution-archive-types.ts` | Frontend | Ок; рамки профиля выше |
| `frontend/src/modules/morefoto/production/live/useInstitutionArchive.ts` | Frontend | Ок; рамки профиля выше |
| `frontend/src/modules/morefoto/structure/components/InstitutionScreen.vue` | Frontend | Ок; рамки профиля выше |
| `frontend/src/modules/morefoto/structure/components/InstitutionTable.vue` | Frontend | Ок; рамки профиля выше |
| `frontend/src/modules/morefoto/structure/components/StructureScreen.vue` | Frontend | Ок; рамки профиля выше |
| `frontend/tests/ui/institution-print-archive.test.mjs` | Test-browser | Ок; рамки профиля выше |
| `tools/run-browser-e2e.py` | Tool | Ок; рамки профиля выше |

| Диапазон целиком | Результат |
| --- | --- |
| CHANGE-01/02/03; REUSE-01/02; TEST-01/02/03; MIG-01/02; CACHE-03; LOCK-01/02; PROC-01; PERF-01/02 | Проверен состав и связь с этапом 5, fixture/test только на disposable stand; части ≤40 файлов; готовые отчёты сверяются с manifest. История, повтор печати и будущая ротация следуют явным решениям пользователя. |

## Проход А

1. Organization → публичный каталог → Production: чужой SQL в runtime Production отсутствует, cron keyset идёт по всем учреждениям. GroupDirectory читается страницами; 101 группа проверена unit. ReadModel/Result не попадает в HTTP/межмодульную границу.
2. Все существующие группы, актуальный срок и +1 час → immutable L1 источники → сохранённые paths. Открытая новая съёмка не забирает старый ZIP. Builder проверяет scope/deadline до и после записи. Восстановление generation>1 сохраняет старый состав и не требует закрытия новых групп.
3. quantity×printCount, общий кадр, отдельная продукция, коллизии имён/регистра, BOM/semicolon/CRLF/formula guard и trace IDs используют layout/manifest L1 v2. Старый v1 не переписывается. CSV восстанавливается по источникам, а не по текущим заказам/каталогу.
4. Mutex учреждения и request, уникальные ключи, owner/CAS и отдельный файл аренды: устаревший worker не публикует и не удаляет файл победителя. Момент между эффектом команды и ключом найден при самопроверке и исправлен v1.1: create/renew+remember атомарны без вложенной транзакции L1; native failure injection подтвердил rollback/replay на реальном MySQL.
5. Actor scope проверяется у статуса/команд/CSV/выдачи ticket и повторно на bytes. Ticket 2 min + generation + expiry; creator scope не переносится в singleton. Organizer manage, curator read own, foreign/прочие/anonymous denied. Nginx internal path и исключение токена из access log переиспользованы.
6. Missing metadata/файл, unsafe name, write failure, expiry/rebuild: нет неполного ready, временный файл удаляется; fixed snapshot не создаёт новую печать. Изображения идут через файловый writer, >160 MiB ZIP уложился в 128 MiB unit. Метаданные остаются O(строк); без ротации рост истории не ограничен, это принятое пользователем решение.
7. Vue Composition API/TS: props/emits, scoped styles/tokens; session/request identity отсекает устаревшие ответы, cleanup прекращает poll; mutation key сохраняется после ошибки, общий busy ограничивает двойной клик. Большой ZIP скачивается браузером по краткому ticket без Blob изображений; CSV Blob малый.
8. Маршруты/DI/cron/input/output/frontend consumer проверены одной цепочкой. Supplement — только новые своевременные строки, следующий full накопительный. Pending/digital не блокируют. PDF/этапы 6–8/ротация/реестр передачи отсутствуют.

## Самооппонирование и замечания

- REPO-04/HTTP-06: crash window между эффектом и remember подтверждён, исправлен узкой транзакцией доставки (v1.1); unit 69/1735 и PHPStan PASS, native failure injection PASS в финальном gate. Общую транзакцию над L1 отвергли по реальной семантике nested rollback ядра.
- DOC-01: старый комментарий приватного ArchiveLayout::folder описывает только продукцию, хотя метод теперь используется и для учреждения/групп. Закрыто после замороженного gate только правкой двух комментариев. Равенство исполняемых токенов подтверждено PHP 8.4; поведение не менялось.
- Не включать всю историю было исходным ограничением, но пользователь прямо заменил его на «все существующие»; это не обход требования авторским решением. Полная новая версия повторяет прежнее по тому же прямому поручению; UI/guide предупреждают.
- Прямые чужие SQL в native verifier/fixture — независимая проверка disposable БД, не runtime-порт. Исходные браузерные данные не изменяются, fixture trail проходит старый verify-orders.
- Предупреждения кеша DtoClassMetadata/DtoParameterMetadata относятся к неизменённой основе; записаны отдельно в progress. Нового дефекта за пределами задачи не исправляли.

Ограничения: независимое review отдельной свежей сессией не выполнялось; production deployment не проводился; будущая ротация потребует отдельной политики источников для восстановления. Данная самопроверка не разрешает commit/push/PR/merge/deploy.

## Итог авторских проверок

Полный gate a+b 162/162 и все native PASS, backend 1345/52413, frontend 118 UI/253 Commerce, адресный unit 69/1735 под 128 MiB, functional 219/874. Desktop/mobile финального run просмотрены. CS Fixer dry-run 0/52; whitespace и final manifests проверены. Фактические команды — commands.json и progress.md. Открытых авторских замечаний по реализации нет; независимое review не выполнено.

## Документы и доказательные артефакты

Ок: CHANGE-01/02/03, DOC-01/02, TEST-03; остальные runtime ID неприменимы к сохранённым отчётам/изображениям. Проверены отсутствие секретов/дампов/сборок, принадлежность run, UTF-8/JSON/hashes и ссылки. Старые manifests намеренно сохраняют историю неуспешного/предыдущего прогона и не доказывают финальный результат. PNG получены на тестовых данных и просмотрены; report не содержит сырого trace или credentials.

| Файл | Результат |
| --- | --- |
| `docs/plans/OPS-institution-print-archive/artifacts/backend-unit.log` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/commands.json` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/gate-3.log` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/institution-desktop.png` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/institution-mobile.png` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/native-institution.log` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/self-check.md` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/source-hashes-final.json` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/source-hashes-gate-1.json` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/source-hashes-gate-2-native.json` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/source-hashes-gate-2.json` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/source-hashes-gate-3.json` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/artifacts/verification.json` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/plan.md` | Ок, границы выше |
| `docs/plans/OPS-institution-print-archive/progress.md` | Ок, границы выше |
