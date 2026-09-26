# Issue #92 (срез 1) — журнал

## Точка продолжения

- Ветка `codex/issues-92-compact-tables`, worktree `/home/user/rabit-api-worktrees/issues-92-compact-tables`,
  base `origin/main` `41b1146e`. Issue [#92](https://github.com/rebit-pro/rabit-api/issues/92). PR [#152](https://github.com/rebit-pro/rabit-api/pull/152).
- Завершено: backend, frontend, unit/архитектурные тесты, live E2E группами a (79/79) и b (53/53), визуальная проверка.
- Сейчас: PR передан на review.
- Следующий шаг: review; после review без блокеров — полный `make test-e2e` (гейт), затем merge и выкатка отдельными действиями.
- Блокеров нет. Открытых решений нет. Непроверенный риск: шаги mock-Cucumber (демо-режим) не прогонялись — демо-вход
  падает до изменённых шагов.
- Рабочее дерево чистое после коммита.
- Backend-проверки из `api/`: `docker run --rm --network none -e XDEBUG_MODE=off -v "$PWD":/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro -w /app rabit-api-php-cli:d3-webp sh -c 'php vendor/bin/phpunit --testsuite=unit; php -d memory_limit=4G vendor/bin/phpstan analyse --configuration=phpstan.neon'`.
- Frontend-проверки из `frontend/`: `docker run --rm --network none -v "$PWD":/app -v rabit-issues92-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`.
- Полный гейт: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Статус тест-кейсов

| ID | Статус | Дата | Комментарий |
|---|---|---|---|
| T01 | PASS (live) | 2026-09-26 | `cabinet-tables` T10: группы с кадрами удаляются, группа b 53/53 |
| T02 | PASS (unit) | 2026-09-26 | `testOrdersRefuseBeforeAnythingIsRemoved`; live-сценария с заказом нет |
| T03 | PASS (live) | 2026-09-26 | `cabinet-tables` T10 — удаление съёмки |
| T04 | PASS (live) | 2026-09-26 | `cabinet-tables` T10 и DEC-05 — учреждение со страницы и из списка |
| T05 | PENDING | 2026-09-26 | `PHOTO_PROCESSING` при удалении структуры — только в SQL участника Media, отдельной проверки нет |
| T06 | PASS (unit) | 2026-09-26 | `testOnlyOrganizerRemoves`, `testAccessDenialBecomesHttpRefusal` |
| T07 | PASS (live+unit) | 2026-09-26 | `cabinet-tables` T07; `DeleteProductUseCaseTest` |
| T08 | PASS (unit) | 2026-09-26 | `testPurchasedProductStays`; live-сценария с заказом нет |
| T09 | PASS (live) | 2026-09-26 | `cabinet-tables` T09 |
| T10 | PASS (live) | 2026-09-26 | `cabinet-tables` T10 |
| T11, T12 | PASS (live) | 2026-09-26 | `cabinet-tables` T11 T12 |
| T13 | PASS | 2026-09-26 | `StructureRemovalControllerTest`, `CatalogRemovalControllerArchitectureTest` |
| T14 | PASS (live) | 2026-09-26 | группы a 79/79 и b 53/53, включая спеки сотрудников и учреждений |
| T15 | PASS | 2026-09-26 | `screens/impl/` против `screens/after/` |

## Журнал

### 2026-09-26

- Запрос пользователя: каталог, страница учреждения и «Ссылки и сроки» сделать компактными, в табличном виде с CRUD
  по образцу «Сотрудников»; заказы и платежи — вторым срезом. Сначала PR с планом и скриншотами, реализация — после
  одобрения скриншотов.
- Проверено, что #92 никто не взял: открытых PR и веток по #92 нет (есть только слитые `issues-91-staff-table`,
  `issues-103-table-toolbar`).
- Разбор схемы: все FK на продукцию/учреждение/съёмку/группу — `ON DELETE RESTRICT`; заказы хранят снимки названий,
  но `mf_order.GROUP_PUBLIC_ID` ссылается на группу → группу с заказами удалить нельзя без архива (DEC-01).
  Контракта «группа с заказами» нет, событий удаления нет → новые контракты в `rebit.share/lib/Contracts/`.
  Удаление кадров (#105/#106) стирает файлы после commit, без очереди — тот же приём для каскада.
  Чтение учреждения не отдаёт у съёмок число групп, у групп — название съёмки → добавить.
- Прототип на реальных компонентах в копии `src` (scratchpad), API — заглушки Playwright. Найдено по ходу:
  - в наборе иконок нет 10 нужных `mdi-*` (список в плане);
  - `UiDataTable` делит ширину колонок поровну, колонка действий фиксирована 176 px → в прототипе добавлены
    `width`, `actionsWidth`, `autoPager`.
- Скриншоты: `screens/before` (3 desktop) и `screens/after` (11: desktop, mobile, выбор, диалоги удаления).
- Попутно (не по теме, не блокер): `stores/auth.ts` ставит `setTimeout` на весь срок сессии; при сроке больше
  ~24,8 суток (предел `setTimeout`) таймер срабатывает сразу и сессия сбрасывается. Сейчас срок токена задаётся
  в часах и так далеко не заходит, поэтому issue не заводится; учтено в заглушках стенда (срок +2 часа).
- Проверки кода не запускались: в ветке нет изменений кода, только документы и скриншоты.
- Открыт draft PR [#152](https://github.com/rebit-pro/rabit-api/pull/152) с планом и скриншотами (`1889ca5`).
- 2026-09-26, после одобрения: план уточнён (контракты по модулям-поставщикам, единый код `STRUCTURE_HAS_ORDERS`,
  вопросы родителей удаляются вместе с группой — CHECK не даёт обнулить группу). Frontend отдан отдельному агенту в
  том же worktree (только `frontend/`), backend — в основной сессии.
- Backend: общий сервис `PhotoFileCleaner` вынесен из `DeleteGroupPhotosUseCase` и используется удалением структуры.
  `composer install` в новый том не уложился в таймаут Composer (300 с) — проверки идут на `vendor` основного
  checkout (lock совпадает с `origin/main`).
- Проверки backend (из `api/`, образ `rabit-api-php-cli:d3-webp`, `XDEBUG_MODE=off`):
  - `php vendor/bin/phpunit --testsuite=unit` — OK (800 тестов, 45527 утверждений);
  - `php -d memory_limit=4G vendor/bin/phpstan analyse --configuration=phpstan.neon` — No errors (после правки типов моков в двух тестах);
  - `php-cs-fixer fix` по 43 своим файлам — поправлено 4, повторный dry-run чистый; `phplint` — OK.
- Frontend принят (отдельный агент, только `frontend/`): каталог, страница учреждения (`StructureWidget`,
  `StructureRemoveDialog`), список учреждений (удаление), «Ссылки и сроки» таблицей; общие `ui/removal.ts`,
  `UiRemoveDialog`, `UiBulkNotice`; `UiDataTable` получил `width`/`actionsWidth`/`autoPager`/`actions`; новая live-спека
  `cabinet-tables.spec.ts` (группа b), обновлены затронутые live-спеки и шаги mock-Cucumber.
- Проверки frontend (повтор в основной сессии, том `rabit-issues92-node`, `--network none`):
  `npm run check` — exit 0 (в т.ч. `test:ui` 65/65); `npm run test:commerce` — 218/218.
- Mock-Cucumber (демо-режим) агент прогнать не смог: вход падает на «Не удалось загрузить данные» ещё до изменённых
  шагов; сравнение с чистым `main` не выполнено. Обновлённые шаги Cucumber — непроверенный риск.
- Live E2E, частичный прогон группы b (не гейт):
  `E2E_GROUPS=b make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`
  - прогон 1 (`rabit-e2e-afe40b4880ee`): 50 passed, 3 failed. Прошли T10 (две группы, съёмка и учреждение со страницы
    учреждения, реальная MySQL) и удаление учреждения из списка; остальные спеки группы b зелёные. Упали T09, T07, T11/T12:
    у полей поиска каталога и ссылок доступное имя «Название Название» / «Группа или съёмка …» (Vuetify дублирует подпись),
    спека ищет точное имя. Исправлено: `aria-label` у обоих полей (как в `OrganizationTable`).
  - прогон 2 (`rabit-e2e-8a86b7135fa1`): группа b — 53 passed, 0 failed (все сценарии #92 T07, T09, T10, DEC-05, T11/T12).
  - группа a, прогон 1 (`rabit-e2e-49ee53de4983`): 76 passed, 3 failed:
    - `catalog.spec` ×2 — цена в таблице писалась «125,50 ₽» вместо прежнего `money()` «125,5 ₽»; вернул `money()` в ячейку цены;
    - `z-institution-detail` C4 #92 — неверное ожидание спеки (у «C4 Съёмка 01» 13 групп, а не одна) и UX-недочёт:
      смена фильтра по съёмке оставляла виджет на 3-й странице. Виджет получил проп `filter` (новый фильтр — первая
      страница), спека ждёт «1–10 из 13».
  - быстрые проверки после правок: `npm run check` exit 0, `npm run test:commerce` 218/218.
  - группа a, прогон 2 (`rabit-e2e-ca5d7e88743a`): 79 passed, 0 failed. Группа b после последних правок (ячейка цены,
    проп `filter`) не перезапускалась: правки её спек не затрагивают; полный гейт после review перепроверит обе.
- Визуальная проверка desktop 1440 / mobile 390 на стенде заглушек по итоговому коду: `screens/impl/` (11 снимков),
  совпадает с одобренными `screens/after/`; отличие — номер шага подготовки в строке ссылки (текущий шаг, а не число
  выполненных) и сноска про «Фотографии ещё готовятся» под таблицей.
