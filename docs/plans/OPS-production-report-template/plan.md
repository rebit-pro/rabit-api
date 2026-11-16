# OPS-production-report-template — этап 6 MoreFoto

План v1.5, 16.11.2026. **Все запрошенные тестовые наборы выполнены. Основной live a+b и Stage6 native/PDF/desktop-mobile PASS; дополнительный a с бенчем PASS после увеличения ёмкости disposable worker; полный demo: 21 PASS / 430 FAIL. Общий результат FAIL, независимое review — PENDING.** Новое явное поручение пользователя «Делай мерж в маин» разрешает commit/push/PR/merge этого среза с известными результатами. Deployment не поручен. Фактические результаты и история решений — [progress.md](progress.md).

## Цель и границы

Добавить рабочую ведомость по структуре DOCX: №, ФИО заказа с явной неоднозначностью соответствия детям, динамическая продукция/форматы, исходные имена файлов и количества, купленные электронные кадры/комплекты и денежный итог. Компактная сводка остаётся отдельным видом по умолчанию. Печатная часть окончательной ведомости соответствует конкретной сохранённой версии Production и её ZIP/CSV.

Источники: поручение 16.11.2026, исходный implementation-prompt §4 и implementation-stages (этап 6) из `OPS-telemos-20261107-prompt` основного checkout; AGENTS/CLAUDE этого дерева, `docs/architecture.md`, `docs/waves/a8/README.md`; план этапа 5 с BOUNDARY-5; [замечания R1–R7](artifacts/review-2026-11-12.md). DOCX-образец содержит одну таблицу / 42 строки / семь колонок, A4 landscape. ФИО образца не используются в фикстурах и Git; реальные форматы не ограничены образцом.

В scope: snapshot-агрегация Commerce, read-контракт владельца Production, явный выбор версии, Vue-экран, серверный PDF, руководство, проверки и подготовка E2E/native сценариев.

Вне scope: этапы 7–8, электронный архив учреждения, ротация, DOCX/XLSX, смена прав/оплаты, новый учёт передачи лаборатории, переписывание исторических покупок/ZIP/CSV, несвязанные дефекты и внешние публикации. Новое поручение пользователя «запускай все тесты» разрешает агенту полный E2E, включая a+b и demo-regress. Режим — авторская проверка реализации, не Reviewer.

## Checkout и зависимости

- Worktree: `/home/user/rabit-api/.worktrees/ops-production-report-template`.
- Ветка: `codex/ops-production-report-template`.
- Base/HEAD/merge-base и удалённый main: `2e4174eb8e558bf1712d257bb10ed3e0fc400555`, gh подтверждён 16.11.2026. Незакоммиченная реализация продолжается без сброса файлов; PR этой ветки отсутствует (`gh pr list` → `[]`).
- DEP-5 PASS: [PR #239](https://github.com/rebit-pro/rabit-api/pull/239), MERGED 11.11.2026, merge `079b19245ef70d2ec999f34e952cd9bdc6388b49`.
- DEP-3 PASS: [PR #240](https://github.com/rebit-pro/rabit-api/pull/240), MERGED 12.11.2026, merge совпадает с HEAD. Оба merge являются предками HEAD.

## Состав решения — реализованная версия 1.3

| Файлы / область | Обязанность |
| --- | --- |
| Share `Contracts/Production/PrintCompositionInterface` и четыре `Dto/Print*OutputDto` | Узкий публичный контракт версий и сохранённых строк; чистые immutable DTO, без ORM/Result/внутренних ReadModel |
| Production `Application/Worksheet/Service/PrintComposition`, `di/worksheet.php`, `.settings.php` | Проверка собственных прав/версии/готовности/срока/файла/layout; чтение собственного сохранённого состава и точных путей без пересборки |
| Commerce `SalesReportRepository::worksheet`, `WorksheetPurchaseMapper` | Один scoped JOIN только Commerce-таблиц и преобразование снимков заказа/строк в DTO |
| Commerce `ProductionWorksheet`, `WorksheetFiles`, `WorksheetAssembler`, `Worksheet*OutputDto` | Оркестрация, пакетное раскрытие Media/J1, сверка выбранных lineId и immutable покупок, деньги/неоднозначность/отдельные состояния |
| Commerce Request/Input/Result DTO, `SalesReportResultMapper`, `WorksheetResultMapper`, DI | Типизированный новый выбор; совместимый ответ сводки, дополнительное nullable `worksheet`; controller и routes не меняются |
| Commerce `WorksheetHtml`, `SalesReportHtml`, `MpdfSalesReportWriter` | Только разметка готовой проекции, A4 landscape, короткие строки/детализация больших списков и блоки динамических колонок |
| Frontend `sales-report/api.ts`, `worksheet.ts`, `useSalesReport.ts`, `SalesReportScreen`, `WorksheetSection`, `WorksheetTable` | Типы, query-выбор, одинаковые параметры JSON/PDF, отклонение устаревшего ответа, отдельный экран и таблица/карточки |
| `frontend/public/guide/reports/index.html` | Сводка/preview/final/выбор версии, накопительный full/дополнение, права, деньги и текущая электронная часть |
| Backend ProductionWorksheet/contract/architecture tests; frontend UI test | Регрессии R1–R5, лимиты и единый набор покупок → ведомость → CSV/ZIP |
| E10 live spec; `api/tools/e2e/verify-production-worksheet.php`; runner VERIFIERS | Подготовленные реальные JSON/PDF/роли/desktop/mobile и native-сверка, группа b; до expiry/rebuild старых Production verifiers |

### HTTP и публичные сигнатуры

Маршруты прежние: COM-15 `GET /api/v1/sales-report`, COM-16 `GET /api/v1/sales-report/pdf`.

- Optional поля Request/Input: `view=summary|worksheet` (summary), `worksheetMode=preview|final` (preview), `archiveType=null|group|institution`, `archiveId=null|UUID`. Действуют прежние фильтры области и `childrenView=all|ordered`.
- Старый запрос остаётся сводкой; summary возвращает `worksheet=null`. Поля сводки и компактный режим этапа 3 сохраняются.
- Final требует type+ID; preview с archive-параметрами отклоняется. Ошибки не заменяются preview или другой версией.
- `PrintCompositionInterface::available(int $actorId, ?string $groupId, ?string $institutionId): PrintVersionsOutputDto` возвращает доступные версии и `allowed`; права teacher/head на архивы не расширяются.
- `read(int $actorId, string $type, string $id): PrintCompositionOutputDto` возвращает выбранную версию и сохранённые строки. Версия: тип/ID/учреждение/группы/номер/full|supplement/статус/layout/даты/число файлов и отпечатков. Строка: исходные orderId/lineId/photoId и snapshot-продукция/quantity/printCount/prints/filename/path/printsInFile. photoId остаётся внутренним shared/Application полем.
- HTTP `WorksheetFileResultDto`: filename, относительный сохранённый path, printsInFile, nullable code/childCode. Для electronic path=null; raw photoId и серверный путь не публикуются. code/childCode взяты из существующего Media GalleryAssignmentOutputDto и различают одноимённые кадры; исходное имя не меняется.
- Ошибки 422: INVALID_WORKSHEET_SELECTION, WORKSHEET_AREA_MISMATCH, WORKSHEET_TOO_LARGE. Ошибки 409: WORKSHEET_ARCHIVE_NOT_READY, WORKSHEET_LEGACY_LAYOUT, WORKSHEET_SOURCE_MISSING, WORKSHEET_SNAPSHOT_MISMATCH. Прежние 401/403/404 Production/Commerce сохраняются.
- Конкретный GetSalesReportUseCase получает ProductionWorksheet через constructor DI; interface-key Production собирается через `constructor`. ServiceLocator остаётся в DI/bootstrap. Схема БД и миграции не меняются.

### Данные, деньги и версии

- Commerce читает неизменяемые mf_order/mf_order_line: имена/продукцию/форматы/UNIT_PRICE/DISCOUNT/TOTAL. Каталог не переоценивает историю; TOTAL уже net, скидка повторно не вычитается.
- Идентичность по orderId/lineId. ФИО и полная сумма относятся ко всему заказу; одинаковые имена не сливаются, отсутствующее имя явно отсутствует. Все дети снимка участвуют в ambiguous.
- Preview: оплаченные покупки отдельно от pending/unpaid/late, без выдуманных путей будущего ZIP.
- Final: печать и денежный итог только lineId выбранной версии; полная сумма заказа — справочно, остальные текущие покупки/неоплаченные заказы отдельно. Вклад позиции не складывается повторно с printsInFile общего файла.
- Купленные digital/bundle отделены от coveredByGift/J4/подарков. Bundle раскрывается по сохранённым shootId/childId через существующее J1 ready; его file list не заморожен в покупке. Электронная часть final явно текущая и не объявляется содержимым печатного архива.
- Full учреждения накопительный по BOUNDARY-5, supplement содержит только собственный состав. Повторное скачивание не создаёт новое задание; дополнение/новая full/новая съёмка не подменяют выбранный старый ID.
- Финальный архив требует ready, неистёкшего срока, существующего ZIP и подходящего layout. Group layout 1 отклоняется: сохранённого исходного имени нет. Старые ZIP/CSV не меняются.
- Ограничения: 10000 строк, 50000 ссылок на файлы, включая fixed печать и повторные комплекты. Превышение явно 422 без частичной выдачи.

### Экран и PDF

Composition API + TypeScript, текущие UI-kit/tokens Manrope/Golos, без новой палитры. `SalesReportScreen` компонует вид; `WorksheetSection` получает report/selectedSource и emits source; `WorksheetTable` получает group/columns/final/items/reference, запросов не выполняет. `useSalesReport` ведёт query/загрузки/ошибки и идентичность запроса по всей области и версии; JSON/PDF используют один выбор.

В PDF не более трёх физических колонок на блок; деньги и electronic-ячейка заказа только в первом блоке. В каждой строке не более одной покупки на колонку; списки >3 файлов вынесены в обычный поток детализации с номером заказа/позиции. Исходные имена/пути переносятся с zero-width break без потери символов. Основной текст/имена 8 pt, path/meta 7 pt. Все дополнительные заказы показывают prints и quantity × printCount, bundle — комплекты. Многостраничные synthetic PDF проверяются без HTTP/браузера.

## Checklist и критерии приёмки

- [x] Прочитать правила/план/прогресс/R1–R7, подтвердить main и merge этапов 3/5 через gh.
- [x] Продолжить существующий dirty diff; обновить состав решения до кода.
- [x] Исправить R1–R3: большой PDF, различимость electronic, единицы вспомогательных заказов.
- [x] Исправить R4/R5/R7: полный PHPStan/unit и точечный formatter без расширения baseline.
- [x] Исправить R6: руководство и публичные ограничения.
- [x] Проверить snapshot-данные, версии, деньги/ФИО, общий файл и границы 10001/50001 unit-тестами; проверить PDF.
- [x] Подготовить live/native сценарии и команды отдельного полного a+b/desktop/mobile прогона.
- [x] Выполнить локальные backend/frontend проверки, quality gate и авторскую самопроверку; сохранить доказательства.
- [x] GATE-AB: полный свежий реальный a+b с native-сверкой выполнен автором по новому поручению; 163 browser / все 46 стадий PASS, exec-обёртка 143 зафиксирована отдельно.
- [x] Просмотрены четыре реальные desktop/mobile PNG, HTTP PDF и native CSV/ZIP; ограничения ручного покрытия указаны в visual-live.json.
- [ ] Независимое review проверенной версии. PENDING.

Приёмка перед merge: два вида доступны, история не зависит от каталога, нет повторного учёта денег/заказов, неопределённость ФИО видна, выбранные lineId/имена/пути/отпечатки совпадают с сохранёнными CSV/ZIP, PDF читаем, права действуют через реальный HTTP. Локальные проверки не закрывают PENDING real gate.

## Стабильные тест-кейсы

UNIT — полный backend unit плюс адресный `ProductionWorksheet|SalesReport|GroupSalesArchitecture`; STATIC — PHPStan/phplint/точечный formatter; FE — check/test:commerce/build-only. Точные выполненные команды в progress, результаты и логи в `artifacts/verification-20261116/`.

| ID | Предусловия → действие | Ожидаемый результат / планируемая проверка |
| --- | --- | --- |
| DEP-3/5 | Проверить gh и ancestry merge #239/#240 | Обе зависимости в base; gh/merge-base |
| T05 | 15x21, разные товары, нули/расширения имён, 1/2/3/>3, скидка/digital/bundle; изменить каталог | Snapshot-имена/деньги не меняются; UNIT/FE + отдельный GATE-AB/native |
| T06 | Нет ФИО / одинаковые ФИО / несколько детей в заказе | Заказы не объединяются, ambiguous явно, деньги один раз; UNIT + GATE-AB |
| T07 | Пустая/обычная/большая группа, длинные имена, 300 файлов, много колонок | PDF A4-L с заголовками/переносами, readable; UNIT + PyMuPDF + отдельный visual desktop/mobile |
| T10 | Явно выбрать group/institution version; скачать JSON/PDF/CSV/ZIP | Сохранённые lineId/path/filename/prints соответствуют; UNIT/native/GATE-AB |
| T11 | Повтор общего кадра, несколько источников, quantity=2/printCount=3 | Вклад 6 отп. (2 × 3), printsInFile отдельно, итог заказа один; UNIT/native/GATE-AB |
| T12 | Коллизии имён, утрата/expiry файла, большой состав | Нет подмены готовности/версии, basename различим, память ограничена; UNIT + native/GATE-AB |
| T13 | Pending/late, затем supplement/new full/open shoot | Старый ID сохраняет состав, full накопительный, late вне задания; UNIT + native/GATE-AB |
| T15 | Свой/чужой куратор, organizer, teacher/head; прямые JSON/PDF/ZIP | Auth/область/Production права не расширены; UNIT/architecture + GATE-AB |
| DOC-6 | Прочитать /guide/reports/, пройти все виды | Текст соответствует контракту/экрану, ссылки рабочие; FE + отдельный visual |
| R1-PDF | Render 200 заказов preview/final и bundle 300 + long filename + 10 колонок | Все имена/заказы/пути, 8/7 pt, деньги в одной ячейке; UNIT и check-pdf.py |
| R2-FILES | Два готовых кадра комплекта с IMG_0007.JPG | code/childCode различимы в публичных DTO/UI/PDF, raw photoId отсутствует; UNIT/FE |
| R3-PRINTS | quantity=2/printCount=3 в outside/deferred, bundle quantity=2 | PDF/UI: 6 отп. (2 × 3), 2 компл.; UNIT |
| LIMIT-LINES | Commerce и сохранённая Production version: 10001 строка | 422 WORKSHEET_TOO_LARGE без частичного ответа; UNIT |
| LIMIT-FILES | Повторные комплекты / fixed ссылки: 50000 и 50001 | Граница 50000 допустима, 50001 отклонена; UNIT |
| SNAPSHOT-ZIP | Единый набор source purchases → реальный in-memory ZIP/CSV → worksheet | Совпадают lineId/path/filename/prints/printsInFile, net деньги; UNIT |
| GATE-AB | Свежие отдельные БД a+b, все live specs/native checks | Ноль failed/flaky/skipped; manifest/log/desktop/mobile/PDF по проверенным хешам |

## Полный прогон по поручению пользователя — IN PROGRESS

Запускать из этого worktree, не использовать рабочую БД/.env. Нужные образы существуют локально (наличие проверено 16.11.2026). Путь старого имени используется **только как внешний источник лицензированного ядра**, его marker prolog_before.php существует; checkout остаётся указанным выше.

```bash
python3 tools/run-browser-e2e.py run --groups a,b \
  --kernel /home/user/rebit-p2p/api/public/bitrix \
  --vendor /home/user/rabit-api/api/vendor \
  --php-cli rabit-api-e2e-php-cli:local \
  --php-fpm rabit-api-e2e-php-fpm:local \
  --nginx rabit-api-e2e-nginx:local --mysql mysql:8.0
```

Подготовленный Stage6 test находится в существующем `zzzzzzzzzzzzzz-sales-report.spec.ts`, ровно в группе b; groups.json не менялся. Он записывает production-worksheet.json и PDF для native-проверки. `verify-production-worksheet.php` запускается перед Production-verifiers, истекающими/пересобирающими версии.

Дополнительные обязательные ручные сценарии (через реальный API, без mock):

1. Desktop 1440×1000 / mobile 390×844: summary → preview → конкретные group/institution full/supplement; прочитать длинные имена/пути/коллизии/неопределённые ФИО. Сверить отсутствие горизонтального скролла страницы, подписи/клавиатуру, ошибки/пустую/загрузку и PDF.
2. Исходный Stage5 firstId и latestId, дополнение и новая открытая съёмка: старый ID не заменяется, выбранный PDF совпадает с его CSV/ZIP. Сверить байты ZIP/CSV, lineId, имена и количество; текущая electronic-часть обозначена отдельно.
3. Организатор/свой и чужой куратор, teacher/head: прямые JSON/PDF/ZIP; teacher/head получают preview, final запрещён. Отсутствующий/чужой/expired/failed/pending/layout1 не подменяется другой версией.
4. Во время настоящего JSON/PDF-запроса сменить вид/область/версию; устаревший результат не появляется и не скачивается. Вернуть исходный выбор, повторить после ошибки.
5. В тестовой БД изменить только текущий каталог после покупки: ФИО, net суммы/скидки, старые имена/форматы и выбранная печать остаются прежними. Смешанный заказ, 2×3 и общие файлы не удваивают деньги/отпечатки. Большой bundle / пределы / крайний допустимый объём проверять с измерением времени/памяти.

Результаты автоматических HTTP/MySQL/native/browser/desktop-mobile проверок сохранены ниже. Ручные условия вне проведённого покрытия остаются PENDING. Самопроверка автора не заменяет независимое review.


## План прогона всех тестов — 16.11.2026

Новое явное поручение пользователя отменяет прежнее ограничение не запускать E2E. Внешние действия с Git/публикацией по-прежнему не разрешены.

- [x] Зафиксировать SHA-256 исходников и убедиться в неизменности worktree/base.
- [x] Полный runner `tools/run-browser-e2e.py run --groups a,b` по команде выше: backend/frontend/static/unit/functional/build, свежие реальные MySQL/Bitrix/native/browser.
- [x] Opt-in медиабенч: `E2E_MEDIA_BENCH=1`, 50 фото по умолчанию, свежая группа a; отдельное дополнение к полному gate.
- [x] Полный Cucumber demo-regress `npm run test:e2e:run` в изолированном frontend; после частичного однопоточного прохода — полный набор с 4 worker / 6 ГБ, без изменения сценариев/таймаутов; не подменяет live gate.
- [x] Проверить фактические desktop/mobile PNG и HTTP PDF этапа 6, выбранные ZIP/CSV через native verifier.
- [x] Падения разобраны по категориям и сопоставлены со scope; несовместимость demo-profile подтверждена кодом, остальные UI-падения перечислены с ограничениями диагностики. Продуктовый код не менялся; ёмкость тестового media-worker проверена отдельным свежим повтором.
- [x] Обновить plan/progress/verification, сохранить только обезличенные артефакты. Непроведённое или неуспешное не считать PASS.


Дополнительные стабильные кейсы нового поручения:

| ID | Действие | Ожидаемый результат |
| --- | --- | --- |
| DEMO-ALL | Полный Cucumber без фильтра: 451 сценарий, 4 worker / 6 ГБ | Все сценарии попытались выполниться; точные passed/failed/skipped/undefined и причины по реальным JSON/HTML, без маскировки старых дефектов |
| BENCH-ALL | Все 82 spec-сценария группы a с E2E_MEDIA_BENCH=1 / 50 фото | Бенч и следующие media-тесты PASS; capacity disposable worker достаточна. Исходный FAIL и параметры временного повтора сохранены отдельно |


## Фактический итог полного прогона

[Общий verification](artifacts/full-tests-20261116/verification.json), [подробности падений](artifacts/full-tests-20261116/failures.md), [все 451 demo-результат](artifacts/full-tests-20261116/demo-summary.json).

- GATE-AB PASS по фактическим стадиям: 163 browser / 46 стадий, основной штатный runner; exec-wrapper 143 после полного результата зафиксирован отдельно.
- BENCH-ALL PASS при runtime-override worker (1000 сообщений / 3600 s): 82/82, native и exit 0. Первый default-запуск: 78/82, exit 1, сохранён. Уникальных live-сценариев с бенчем 164.
- Backend: 1377 tests / 52833 assertions; frontend check/UI/commerce/build PASS. По исходникам предыдущий точечный formatter 43/43 актуален (SHA-256 не менялись).
- DEMO-ALL FAIL: 21 PASS / 430 FAIL, 451 сценарий; 284 PASS / 430 FAIL / 818 SKIPPED шагов; exit 1. Это фактические падения, не скрытые пропуски: после failed step остальные шаги сценария Cucumber штатно пропускает.
- Продуктовый код и тесты репозитория не менялись в ходе поручения «запускай все тесты». Временные ресурсы остановлены/удалены. Git-публикаций нет.

Для повторения успешного opt-in прогона: `E2E_MEDIA_BENCH=1 python3 docs/plans/OPS-production-report-template/artifacts/full-tests-20261116/bench-runtime-wrapper.py` из указанного worktree. Wrapper раскрывает только runtime capacity disposable media-worker; изменение дефолтов штатного runner не выполнялось.

Открытые условия: отдельное исправление demo mocks/UI, независимое review, ручные сценарии вне автоматического покрытия, крайний объём 50000 ссылок. Список исполненных автоматических тестов не заменяет эти непроведённые проверки. Не все конкретные предусловия T05–T15 были проверены через live HTTP: например, live 15x21/printCount>1, supplement-ведомость и ручные pending/expired/layout1 остаются PENDING, хотя соответствующие unit-тесты PASS.


## Публикация и merge по прямому поручению

Пользователь после отчёта о 430 demo FAIL поручил «Делай мерж в маин». Агент трактует это как явное исключение для текущего merge из обычного условия общей зелёной готовности и как разрешение необходимых commit/push/PR. Общие правила проекта/AGENTS не меняются; deployment не разрешён.

- [x] Актуальный удалённый main проверен через gh: `2e4174eb8e558bf1712d257bb10ed3e0fc400555`, совпадает с base проверок; PR ветки пока отсутствует.
- [x] Все 53 проверенных исходника совпадают с source-before.json; git diff --check PASS, high-confidence secret scan документации/доказательств PASS.
- [ ] Сохранить commit всей реализации и относящихся plan/progress/артефактов, выполнить обычный push ветки.
- [ ] Создать один PR в main с честными PASS/FAIL/PENDING; отметить прямое поручение пользователя и отсутствие независимого review.
- [ ] Выполнить обычный merge с проверкой head SHA, без admin/force и без обхода серверной защиты; проверить итоговый main/PR.

Demo FAIL, отсутствие независимого review и оставшиеся ручные проверки не переименовываются в PASS. Исходный запуск opt-in имел четыре FAIL; успешный повтор требует ёмкости тестового worker и описан в доказательствах. Слияние выполняется по отдельному поручению пользователя, а не на основании выдуманного независимого одобрения.
