# F2 — прогресс

## Точка продолжения

- Ветка `codex/f2-link-handoff` в worktree `/home/user/rabit-api-worktrees/f2-link-handoff`. Исходный base 8cba22c; актуальный `origin/main` 4b507b3 (merge E5 #37) влит в ветку. PR https://github.com/rebit-pro/rabit-api/pull/40.
- Основной checkout `/home/user/rabit-api` использует параллельная сессия; его не трогать. E5 (PR #37) слита в main 22.09.2026.
- Завершено: граф F2 → D3, backend HND-01…05 с контрактами Organization/Access/Media/Commerce, live frontend, E2E-спецификация и MySQL verifier (написаны, не запускались), уточнения контракта MoreFoto, отчёт волны; быстрый gate зелёный.
- Сейчас: PR #40 открыт для review (не draft), head 68082e1 опубликован, GitHub: MERGEABLE.
- Следующий шаг: code review PR #40. После review без блокеров — полный `make test-e2e` (команда ниже), просмотр `f2-desktop-prepared.png`/`f2-mobile-open.png`, перенос результатов в progress/verification.
- Блокеров нет. Открытых решений нет. Конфликты с E5 разрешены, быстрый gate на main 4b507b3 + diff F2 повторён.
- Рабочее дерево: всё закоммичено. Внешний MoreFoto изменён без Git (граф и `build.py`), воспроизводимый patch — `docs/waves/f2/morefoto-contract.patch`.
- Команды следующей проверки:
  - `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`
  - `python3 tools/verify-wave-graph.py docs/waves/graph.json`

## Хронология

### 2026-09-22 — старт и перестановка графа

- Пользователь поручил F2 параллельно с E5, считая её независимой. По графу F2 зависела от D3 («Атомарные переносы»), а D3 — от E5 (PR #37 не слит). Слитый PR #32 «D3» был исправлением stage и занимал тот же ID, отсюда путаница.
- D3 также зависит от E5 и открытых D11/D12. Пользователь выбрал вариант «F2 перед D3».
  - F2 теперь зависит от E3/F1/E4 без D11.
  - D3 зависит от D2/F1/E5/F2.
  - В D3 перенесены проверки «→ заказ», «запрет разметки после заказа» и «перенос сбрасывает подготовку»: MED-05/06, HND-02/03/04, COM-10.
- Скрипт перестановки сначала прогнан на копии MoreFoto.
  - `render-waves.py` и `build.py` на копии без изменений входа — идемпотентны (пустой diff).
  - После перестановки на копии: `validate.py` exit 0, `node docs/05-rest-api/validate-postman.cjs` из корня копии exit 0, `wave_graph.validate_graph` готовит E5 и F2, 10 негативных fixtures.
  - Первый запуск `validate.py` на копии упал на отсутствующих `source` (AUTH-01, SHR-01): копия не содержала `frontend` и `../rebit-p2p`. После symlink — PASS. Проблема среды копии, не изменения.
- Применено к `/home/user/MoreFoto` после резервной копии: `validate.py` exit 0, `validate-postman.cjs` exit 0, `diff -rq` с отрепетированной копией пуст.
- `docs/waves/graph.json` в ветке: та же перестановка плюс отметка E4 merged (байт в байт как в ветке E5). `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 40 волн, 99 API, 35 legacy, `readyFromMain` = E5, F2, 10 негативных fixtures — PASS.
- Patch исходного `backend-waves.json` сохранён в `docs/waves/f2/morefoto-graph.patch`.
- Пользователь выбрал решения:
  - ключ галереи хранить открытым текстом (моя рекомендация — шифрованная копия — отклонена);
  - `sentAt` не раньше выдачи ключа;
  - заявки F1 блокируют подготовку до D3;
  - собственная revision ссылки.

### 2026-09-22 — публикация плана

- Первый commit 29fd8eb «docs(f2): reorder F2 before D3 and plan link handoff»; `git diff --check` PASS.
- `git fetch origin`: main не изменился (8cba22c). `git push -u origin codex/f2-link-handoff` — PASS.
- По указанию пользователя создан PR с планом: `gh pr create --draft` → https://github.com/rebit-pro/rabit-api/pull/40. Draft выбран, так как реализация добавляется в ту же ветку и PR до неё не сливается.
- Следующий шаг — реализация без паузы (указание пользователя).

### 2026-09-22 — backend F2

- Миграция `Version20260922150001`: `mf_gallery_capability.TOKEN` (сырой ключ по решению пользователя) + индекс активных ключей группы; таблицы `mf_group_link`, `mf_group_link_history`, `mf_group_link_idempotency` с CHECK-ограничениями; откат запрещён при наличии данных.
- Контракты rebit.share:
  - `GroupCalendarInterface::recordLinkSent/correctLinkSent`, `GroupDirectoryInterface` (Organization);
  - `GroupLinkAccessInterface` (Access);
  - `GalleryLinkInterface`, `GroupMaterialsInterface` (Media);
  - `Contracts/Commerce/GroupSalesReadinessInterface`.
- Поставщики:
  - календарь с правилами D10 и журналом Organization; `CalendarRuleViolation` → 422/409;
  - каталог групп со scope/state/пагинацией;
  - `GroupLinkAccess` с порядком блокировок;
  - `GalleryLinks` и `GroupMaterials`; `GroupSalesReadiness` через те же share-блокировки, что E3.
- Гонка MED-05/06: после `lockRevision` повторно проверяется `groupEditable`.
- Handoff:
  - доменные политики готовности (подпись и коды проблем в camelCase по правилу enum проекта), прав D08 и даты передачи;
  - репозиторий в Infrastructure;
  - сессия команды: порядок блокировок, идемпотентность после блокировки группы;
  - 5 UseCase, чистый контроллер, маршруты, DI, установщик.
- Встречное подключение Commerce ↔ Handoff в Bitrix даёт `E_USER_WARNING` («Module is in loading progress»), поэтому Handoff получает контракт Commerce лениво через ServiceLocator (`init.php` загружает оба модуля).
- Проверки в образе `rabit-api-php-cli:d1-local` с vendor основного checkout (read-only):
  - `php -l` 85 файлов — PASS;
  - первый PHPStan — FAIL: 1 ошибка «left side of ?? is not nullable» (повторный `find()` после блокировок). Добавлен `@phpstan-impure` контракту каталога, повтор — PASS;
  - PHPUnit unit до тестов F2 — 281/1266 PASS.
- Тесты F2:
  - `GroupCalendarDeliveryTest` 8;
  - `LinkPolicyTest` 9;
  - `GroupLinkControllerArchitectureTest` 2 — первый прогон FAIL: ложное срабатывание подстроки `Bitrix\` на разрешённом `Rebit\Share\Infrastructure\Bitrix\ControllerJson`; подстрока убрана, проверка по токенам осталась;
  - `GroupLinkContractTest` 11;
  - `GroupLinkWorkflowTest` 5;
  - `MediaLockRecheckTest` 2.
- Полный unit-набор после тестов — `phpunit --testsuite=unit` 318 tests / 1459 assertions PASS; PHPStan без ошибок; php-cs-fixer по 89 изменённым файлам исправил форматирование 3 тестов.
- В `api/tools/e2e/prepare.php` добавлена миграция `20260922150001`.

### 2026-09-22 — frontend, E2E-спецификация, контракт и отчёт

- Frontend live `/cabinet/links`:
  - `links-api.ts` (HND-01…05, маппинг кодов ошибок);
  - `loadLinks` по HND-01 (все страницы, без ключей);
  - live-ветка `saveLink` с клиентскими проверками `liveLinkErrors`;
  - ключ и история по запросу HND-02; тексты проблем по кодам;
  - пункт меню для всех ролей персонала, маршрут в live-whitelist;
  - «Ссылка передана» в карточке съёмки.
- `npm ci` в образе playwright v1.52.0 с томом `rabit-f2-node`; первый `npm run check` — FAIL: 6 ошибок prettier (форматирование); `eslint --fix` по 4 файлам, повтор `npm run check` — PASS. `npm run test:commerce` — 160/160 PASS (2 новых теста F2).
- E2E `frontend/e2e/live/zzzz-links.spec.ts`:
  - UI-подготовка организатором; инвалидация переименованием;
  - передача воспитателем на 390 px; галерея и quote;
  - повтор и идемпотентность;
  - исправление куратором с нижней границей по минуте первого события `prepared`;
  - запреты руководителю и воспитателю; 404 чужому воспитателю;
  - скриншоты desktop/mobile.

  Проверяется типизацией и линтом (`npm run check` PASS); не запускалась.
- `api/tools/e2e/verify-links.php`:
  - след в БД: история, revision 5, единственный ключ и его хеш, журнал Organization, 5 записей идемпотентности;
  - календарь на MySQL с управляемыми часами: +7/+7, повтор, внутреннее продление сохраняется при исправлении, LINK_NOT_SENT, SENT_AT_IN_FUTURE;
  - граница `now == closesAt` в каталоге групп и галерее.

  Подключён в `tools/run-browser-e2e.py`. `php -l` PASS, php-cs-fixer override — 0 правок. Не запускался.
- Контракт MoreFoto: пометки F2 в `build.py` для HND-01…05 (формы, коды, правила дат и повтора). `build.py`/`render-waves.py` — PASS, `validate.py` exit 0, `validate-postman.cjs` exit 0. Единый patch источников — `docs/waves/f2/morefoto-contract.patch` (граф + build.py).
- Отчёт `docs/waves/f2/README.md`, `verification.json` (status review-pending), раздел F2 в `docs/testing/manual-wave-checklist.md`.
- Объём рукописных изменений к main — около 5,7 тыс. строк (backend ~3,1, тесты ~1,2, frontend ~0,6, документы ~0,6), в пределах согласованного лимита 6 тыс.

### 2026-09-22 — обновление base: E5 слита в main

- `git fetch`: `origin/main` = 4b507b3 «Merge pull request #37» (E5). `gh pr view 37` — MERGED 2026-09-22T11:56:33Z, merge commit 4b507b3b3c27719888e38f582b48e6a56c0c5946.
- `git merge origin/main`: конфликты в `api/tools/e2e/prepare.php` (миграции E5 и F2), `frontend/src/router/index.ts` (маршруты E5 и Links), `CabinetLayout.vue` (пункты «Заказы» и «Ссылки и сроки»), `tools/run-browser-e2e.py` (verifier E5 и F2). Во всех случаях сохранены обе стороны. `graph.json` слился автоматически.
- По merge receipt E5 отмечена merged в `docs/waves/graph.json` и в каноническом MoreFoto (baseline 4b507b3, PR #37). `verify-wave-graph.py` — PASS: 40 волн, 99 API, readyFromMain = F2, 10 негативных fixtures. MoreFoto `render-waves.py`/`build.py` PASS, `validate.py` exit 0, `validate-postman.cjs` exit 0; графы совпадают по E4/E5/D3/F2. Patch `morefoto-contract.patch` перегенерирован от исходной копии.
- Повторный gate на main 4b507b3 + diff F2:
  - `phplint` 800 файлов — OK;
  - PHPStan — 0 ошибок;
  - `phpunit --testsuite=unit` — 372 tests / 1731 assertions PASS;
  - frontend `npm run check` — PASS; `npm run test:commerce` — 169/169 PASS.
- Спецификация E5 `zzzzz-orders` берёт товары из условий группы-фикстуры E4, где активны только товары E4. Товар F2 на неё не влияет.

### 2026-09-22 — PR готов к review

- Merge-коммит 68082e1 опубликован. `git diff --cached --check` по своим файлам — PASS; хвостовые пробелы есть только в контекстных строках `docs/waves/e5/morefoto-contract.patch`, пришедшего из main.
- `gh pr edit 40 --body-file …` — описание обновлено: реализация, проверки, отложенный E2E, подключение.
- `gh pr ready 40` — PR переведён из draft в review.
- `gh pr view 40` — OPEN, MERGEABLE, head 68082e1.
- Merge и deployment не выполнялись.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| F2-GRAPH | PASS | 2026-09-22 | `verify-wave-graph.py` (40/99/10 negative, ready E5+F2), MoreFoto `validate.py` и `validate-postman.cjs` exit 0 |
| F2-CALENDAR | PASS (unit) | 2026-09-22 | `GroupCalendarDeliveryTest` 8/24: +7/+7 МСК, год/29 февраля/UTC, будущее, повтор, исправление, продление, граница `now == closesAt`. Сервисный слой — PENDING verifier MySQL |
| F2-READINESS | PASS (unit) | 2026-09-22 | `LinkPolicyTest`: коды проблем и изменение подписи от названия/воспитателя/материалов/условий/заявок |
| F2-PERMISSIONS | PASS (unit), E2E PENDING | 2026-09-22 | `LinkPolicyTest` матрица D08; `GroupLinkWorkflowTest` 403/404 для куратора, руководителя, воспитателя, чужого куратора |
| F2-PREPARE | PASS (unit), E2E PENDING | 2026-09-22 | `GroupLinkWorkflowTest`: успех, LINK_NOT_READY, REVISION/SIGNATURE_CONFLICT, 403/404, LINK_ALREADY_SENT; `GroupLinkContractTest` REVIEW_REQUIRED |
| F2-TRANSMIT | PASS (unit), E2E PENDING | 2026-09-22 | `GroupLinkWorkflowTest`: передача воспитателем, LINK_NOT_PREPARED, SENT_AT_BEFORE_LINK, 403 руководителю; будущая дата — `GroupCalendarDeliveryTest` |
| F2-REPEAT | PASS (unit), E2E PENDING | 2026-09-22 | `GroupLinkWorkflowTest`: повтор устаревшей формой без изменения сроков; replay и IDEMPOTENCY_CONFLICT |
| F2-CORRECT | PASS (unit), E2E PENDING | 2026-09-22 | `GroupLinkWorkflowTest`: LINK_NOT_SENT, 403 воспитателю, история с прежними датами и причиной; `GroupLinkContractTest` INVALID_REASON; SENT_AT_UNCHANGED — `GroupCalendarDeliveryTest` |
| F2-EXTENSION | PASS (unit), verifier PENDING | 2026-09-22 | `GroupCalendarDeliveryTest` продление сохраняется/перекрывается; MySQL — `verify-links.php` |
| F2-CLOSE-BOUNDARY | PASS (unit), verifier PENDING | 2026-09-22 | `GroupCalendarDeliveryTest` граница `now == closesAt`; каталог групп и галерея — `verify-links.php` |
| F2-INVALIDATION | PASS (unit), E2E PENDING | 2026-09-22 | `LinkPolicyTest` и `GroupLinkWorkflowTest`: смена материалов сбрасывает `prepared`, передача отклоняется |
| F2-RACE-MEDIA | PASS (unit) | 2026-09-22 | `MediaLockRecheckTest` 2/8: разметка и обложка после ожидания блокировки → GROUP_LOCKED, запись не выполняется |
| F2-GALLERY-QUOTE | PENDING | — | `zzzz-links.spec.ts`, после review |
| F2-TOKEN | PASS (unit), E2E PENDING | 2026-09-22 | `GroupLinkWorkflowTest` ключ после подготовки; `GroupLinkContractTest` список без ключа; хеш в БД — `verify-links.php` |
| F2-ARCH | PASS (частично) | 2026-09-22 | architecture-тест контроллера, DTO-архитектура F1 покрывает новые DTO, PHPStan 0, php-cs-fixer; финальный прогон — перед PR |
| F2-CONTRACT | PASS (unit) | 2026-09-22 | `GroupLinkContractTest` 11/66: строгий JSON, INVALID_SENT_AT, REVIEW/CONFIRMATION_REQUIRED, INVALID_REASON, фильтры, форма ответов |
| F2-UI | PASS (check/unit), E2E PENDING | 2026-09-22 | `npm run check` PASS, `npm run test:commerce` 160/160; браузер — после review |
| F2-VISUAL | PENDING | — | после review, `make test-e2e` + просмотр PNG |
| F2-PUBLISH | PASS (review) | 2026-09-22 | PR #40 ready for review, MERGEABLE; merge — после review и полного E2E |
