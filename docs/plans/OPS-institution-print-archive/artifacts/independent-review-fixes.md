# Независимое повторное review исправлений этапа 5

Reviewer: свежий субагент /root/review_archive_fixes, fork_turns=none, без истории реализации. Точная модель и reasoning средой не раскрыты. Base/merge-base 7b9de6ed82b7786e4b5e957a3d44a004fcb92935; проверенный head 1421ec00563463315093e0652d6312009f04ba87.

Вердикт: блокирующих замечаний по REV-01/02/03 и их интеграции не найдено. Все три исправления подтверждены исходниками и готовыми регрессионными отчётами.

- REV-01 закрыто: guard использует firstReadyAt, ready записывает его через COALESCE, renewal сохраняет. До первой публикации проверяется полный текущий состав; ранее опубликованный архив восстанавливается при новой открытой съёмке.
- REV-02 закрыто: истёкшую служебную аренду разрешено перехватить, счётчик насыщается на 4; builder завершает её в failed без ZIP-сборки. Owner/CAS и действующая аренда защищены.
- REV-03 закрыто: obsoletePath атомарно сохраняется вместе с renewal/request, переживает ошибки удаления и повтор команды; builder удаляет файл до сборки, ready очищает ссылку. Первая публикация, состав и CSV сохраняются.

## Покрытие Б

Пути относительно api/public/local/modules/morefoto.production, кроме явно полных путей. Во всех девяти файлах прочитаны классы/изменённые методы целиком, проверены STYLE-01/02 и DOC-01/02.

| Файл | Проверенные правила |
| --- | --- |
| Application/Institution/Service/InstitutionArchiveBuilder.php | LAYER-01–04, REUSE-01/02, DTO-02/03, DI-01/02, EXC-01/02, LOCK-01/02, PERF-01/02 |
| Application/Institution/Service/InstitutionGroups.php | LAYER-01–04, REUSE-01/02, DTO-02/03, DI-01/02, EXC-01/02, PERF-01/02 |
| Domain/Institution/ValueObject/InstitutionArchive.php | LAYER-03, DTO-01–03, REUSE-01/02 |
| Infrastructure/Database/BitrixInstitutionArchiveRepository.php | LAYER-01–04, DTO-02/03, EXC-01/02, REPO-01–05, LOCK-01/02, PERF-01/02 |
| tests/Unit/InstitutionPrintArchiveTest.php | TEST-01–03, CHANGE-03 |
| tests/Unit/Support/InMemoryInstitutionArchives.php | TEST-01/02, DTO-02/03, соответствие SQL/CAS/rollback |
| tests/Unit/Support/InstitutionFixture.php | TEST-01/02, DI-01, сборка зависимостей |
| api/public/local/php_interface/migrations.foundation/Version20261110180001.php | MIG-01/02, REPO-02, TEST-01/03 |
| api/tools/e2e/verify-institution-production.php | TEST-01–03, REPO-02–04, LOCK-01/02, CLI-01 |
| Диапазон исправлений | CHANGE-01–03, HTTP-03/05/06, PROC-01, TEST-03, NOTE-01 |

Кеши, события/очереди, компоненты, DI-регистрации и HTTP-входные контракты исправлениями не меняются: CACHE-*, EVT-*, MSG-*, CMP-*, DI-03/04, HTTP-01/02/04 неприменимы.

Проход А после Б: публикация, crash/retry, renewal/replay, очистка, объектные права и совместимость внутреннего VO/схемы/потребителей. 66/66 SHA-256 совпадают; девять файлов побайтово соответствуют проверенному ref, pre-gate/final manifests равны. git diff --check и свежий AST PASS, usages 53/53 без усечения.

## Неблокирующий follow-up

MEDIUM, InstitutionArchiveBuilder.php:50 → BitrixInstitutionArchiveRepository.php:138, REPO-04/CHANGE-03. Если после перехвата lease удаление прежнего due.workPath временно падает, claim уже заменил WORK_PATH новым. Последующий failed обнуляет WORK_PATH; старый остаток ZIP/временного файла больше не находится для штатной очистки. Готовый архив и печатные строки не повреждаются; возможны остатки прерванных сборок. Это прежнее поведение ветки, вне причин трёх исправлений, не регрессия FIRST_READY_AT/OBSOLETE_PATH. Требует отдельной задачи; issue не публиковалась без поручения.

## Доказательства и ограничения

Прочитаны готовые gate 56eab0e1d064: browser162/162, backend1348/52478, targeted72/1800, PHPStan PASS, CS0/52, native REV-01/02/03 PASS. Reviewer самостоятельно E2E, visual, стенды не запускал, код не менял и публикаций не делал. Остальной исходный diff повторно целиком не ревьюировался: это повторное независимое review девяти исправленных файлов и их интеграции после исходного полного review. GitHub проверял родитель.

Родитель после отчёта сверяет неизменность 66 runtime/test файлов; дальнейшие изменения допускаются только в документации поставки. Review не является разрешением deployment.
