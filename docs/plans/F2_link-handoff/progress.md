# F2 — прогресс

## Точка продолжения

- Ветка `codex/f2-link-handoff` в worktree `/home/user/rabit-api-worktrees/f2-link-handoff`. Base `origin/main` 8cba22c7655b5886d5fe663523214bcd059e674b. Draft PR https://github.com/rebit-pro/rabit-api/pull/40 (план; реализация добавляется в ту же ветку).
- Основной checkout `/home/user/rabit-api` занят параллельной сессией E5 (PR #37); его не трогать.
- Завершено:
  - перестановка F2 перед D3 в `docs/waves/graph.json` и каноническом плане MoreFoto;
  - сбор спецификации и кода;
  - согласование четырёх решений (см. plan.md);
  - plan/progress.
- Сейчас: backend F2 реализован и покрыт unit-тестами; следующий этап — live frontend экрана ссылок.
- Следующий шаг: frontend `/cabinet/links` в live-режиме (HND-01…05), затем E2E-спецификация и verifier.
- Блокеров нет. Полный `make test-e2e` — только после review без блокеров (указание пользователя от 22.09.2026 по E5).
- Рабочее дерево: backend F2 закоммичен отдельным коммитом (см. хронологию). Внешний MoreFoto уже изменён (без Git), резервная копия исходных файлов в scratchpad сессии.
- Команды следующей проверки:
  - `python3 tools/verify-wave-graph.py docs/waves/graph.json`
  - `python3 /home/user/MoreFoto/docs/05-rest-api/validate.py`
  - `cd /home/user/MoreFoto && node docs/05-rest-api/validate-postman.cjs`

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

## Результаты тест-кейсов

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| F2-GRAPH | PASS | 2026-09-22 | `verify-wave-graph.py` (40/99/10 negative, ready E5+F2), MoreFoto `validate.py` и `validate-postman.cjs` exit 0 |
| F2-CALENDAR | PASS (unit) | 2026-09-22 | `GroupCalendarDeliveryTest` 8/24: +7/+7 МСК, год/29 февраля/UTC, будущее, повтор, исправление, продление, граница `now == closesAt`. Сервисный слой — PENDING verifier MySQL |
| F2-READINESS | PASS (unit) | 2026-09-22 | `LinkPolicyTest`: коды проблем и изменение подписи от названия/воспитателя/материалов/условий/заявок |
| F2-PERMISSIONS | PASS (unit), E2E PENDING | 2026-09-22 | `LinkPolicyTest` матрица D08; `GroupLinkWorkflowTest` 403/404 для куратора, руководителя, воспитателя, чужого куратора |
| F2-PREPARE | PENDING | — | — |
| F2-TRANSMIT | PENDING | — | — |
| F2-REPEAT | PENDING | — | — |
| F2-CORRECT | PENDING | — | — |
| F2-EXTENSION | PENDING | — | — |
| F2-CLOSE-BOUNDARY | PENDING | — | — |
| F2-INVALIDATION | PENDING | — | — |
| F2-RACE-MEDIA | PASS (unit) | 2026-09-22 | `MediaLockRecheckTest` 2/8: разметка и обложка после ожидания блокировки → GROUP_LOCKED, запись не выполняется |
| F2-GALLERY-QUOTE | PENDING | — | — |
| F2-TOKEN | PENDING | — | — |
| F2-ARCH | PASS (частично) | 2026-09-22 | architecture-тест контроллера, DTO-архитектура F1 покрывает новые DTO, PHPStan 0, php-cs-fixer; финальный прогон — перед PR |
| F2-CONTRACT | PASS (unit) | 2026-09-22 | `GroupLinkContractTest` 11/66: строгий JSON, INVALID_SENT_AT, REVIEW/CONFIRMATION_REQUIRED, INVALID_REASON, фильтры, форма ответов |
| F2-UI | PENDING | — | — |
| F2-VISUAL | PENDING | — | — |
| F2-PUBLISH | PENDING | — | — |
