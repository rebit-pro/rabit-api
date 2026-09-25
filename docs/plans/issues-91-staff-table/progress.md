# Issue #91 — журнал

## Точка продолжения

- Ветка `codex/issues-91-staff-table`, worktree `/home/user/rabit-api-worktrees/issues-staff-table`,
  base `origin/main` `4ca7e9c`. PR [#96](https://github.com/rebit-pro/rabit-api/pull/96). Issues: #91, #92.
- Завершено: backend (архив, сортировка), frontend (`UiDataTable`, выбор, удаление), unit, live E2E-сценарии написаны,
  быстрые проверки зелёные, визуальная проверка на заглушках.
- Review 1: два блокера (P1 перепроверка актора под блокировкой, P2 pending-организатор) исправлены в `0a0d169`,
  ответы в тредах; второго круга review нет (решение пользователя). Слит `origin/main` `94502a1` (`dbdfa60`).
- PR #96 влит в `main` как `d8caca4` (gate PASS, 111 сценариев); issue #91 закрыт.
- Выкачено на https://app.morefoto36.ru 2026-09-25, релиз `/srv/morefoto/releases/issues91-20260925165859-d8caca4`:
  шесть backend-сервисов и frontend `morefoto-frontend:issues91-20260925165859-d8caca4`; вместе ушёл слитый #86. Миграций нет.
- Откат: `docker service rollback` для шести backend-сервисов (прежний `/app` — релиз `g1-20260925182710-fc0cdb9`)
  и `morefoto_frontend` (прежний образ в `frontend-before.txt` — `morefoto-frontend:g1-20260925182710-fc0cdb9`);
  спецификации — `services-before.json`, резервная копия БД — `database-before.sql.gz` релиза.
- Открыто: пользовательская проверка удаления в своём кабинете; остальные таблицы — #92.
- Блокеров нет. Открытых решений нет.
- Рабочее дерево чистое после коммита; `api/vendor` и `frontend/node_modules` — пустые точки монтирования (в `.gitignore`).
- Следующая проверка: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Журнал

### 2026-09-25

- Замечание пользователя: таблицы кабинета без сортировки/мультивыбора/удаления; минимум — нельзя удалить сотрудников.
- Найдено: `StaffManagementScreen.vue` на самописных строках; в API нет `DELETE /api/v1/users/{id}`; список
  всегда `ORDER BY p.UF_USER_ID`; `UiDataTable` уже умеет сортировку, выбор и удаление.
- Решения пользователя: удаление = архив; объём — сотрудники сейчас, остальные таблицы отдельным issue (#92).
- Созданы issues #91 и #92, worktree и ветка, план.
- Backend: `ArchiveStaffUseCase` + чистый `StaffArchiveController` (`DELETE /api/v1/users/{user_id}` → 204),
  `StaffIdentityGatewayInterface::archive()` (учётка pending без токена, ссылки доступа удалены).
- Найден риск: уникальный ключ `(UF_AGGREGATE_ID, UF_TO_REVISION)` журнала доступа ломал бы повторное добавление
  удалённого сотрудника (503). Исправлено: `SaveStaffUseCase` продолжает ревизию из журнала (`lastRevision()`).
- Сортировка `sort=name|role|assignments|status`, `direction`; роли и статусы — по смыслу, добивка по ID.
- Frontend: экран на `UiDataTable`, `StaffPerson` в ячейке имени (кнопка «Редактировать сотрудника X» сохранена
  для прежних E2E), панель «Выбрано: N · Удалить выбранных», диалог с именами, итог «Удалено N из M»; своя учётка
  пропускается. `UiDataTable` получил слоты ячеек и `labelKey` без изменения поведения по умолчанию.
- Визуальная проверка на заглушках API (Vite + Playwright, desktop 1440 и mobile 390): сортировка, выбор, диалог,
  итог, нет горизонтальной прокрутки. Поправлен вес шрифта имени. Это не заменяет live E2E.

### 2026-09-25, review 1

- P1: bearer через `ArchiveStaffRequestDto` (`RequestHeader`), в `state->run()` сначала `lockParticipants()` и
  `assertCan()`; регрессии на отключение актора и отзыв сессии во время ожидания.
- P2: `LAST_ORGANIZER` только для цели из множества счётчика; регрессия invited/blocked при count=1.
- После merge `origin/main` 94502a1: PHPUnit 874/874 (45437 assertions), PHPStan OK, php-cs-fixer 0/113,
  `npm run check` (27/27), `git diff --check` — PASS.
- Полный gate ожидал завершения чужого прогона на общем Docker (стенд `5209ede1233f`), чтобы не делить память.
- Прогон 1 на `dbdfa60` (`rabit-e2e-c7ce157498f4`, ~283 с) — FAIL: группа b 100% и все verifier PASS, группа a 64/65.
  Упал только новый сценарий #91 на повторном добавлении: 503, `RepositoryException` в
  `UserRepository::updateStaffContact()` — архив и повторное добавление в одну секунду, `TIMESTAMP_X` не менялся,
  MySQL вернул 0 изменённых строк. Та же ловушка в `resetToPending()` для приглашённого (уже `ACTIVE=N`).
  Исправлено `18824d3`: ошибка только если учётки нет (`assertUpdated()`). PHPUnit 874/874, PHPStan OK.
- Прогон 2 на `18824d3` (`rabit-e2e-242656c88cda`, ~324 с) — PASS: 111 браузерных сценариев (a 65, b 46),
  verifier storefront, handoff, orders, links, transfers, avatar, payment-costs, payments, access. Скриншоты
  `i91-desktop-remove-dialog.png`, `i91-desktop-removed.png`, `b2-mobile-staff.png` просмотрены.

### 2026-09-25 — merge и выкатка

- Пользователь: второго круга review нет; после gate — merge и деплой, нужен кабинет с удалением.
- Merge: `gh pr merge 96 --merge --match-head-commit d913aa0…` → `d8caca4`.
- На проде до выкатки: релиз G1 `fc0cdb9` (PR #80, выкачен другой сессией). После него в `main` не выкачены
  #86 и #87 (#87 — инструменты). Миграций и изменений `composer.lock` после `fc0cdb9` нет.
- Артефакты: `git archive d8caca4 api` (2084 файла), образ frontend `VITE_API_MOCKS_ENABLED=false`, чанк
  `StaffManagementScreen-B1mvZv0T.js` содержит «Удалить выбранных». Скрипты — по образцу G1, маркер
  `StaffArchiveController.php`; `sha256sum --check` и `bash -n` на сервере — PASS.
- Сверка: все шесть backend-сервисов монтировали `/app` релиза G1, реплики 1/1, свободно 25 ГБ.
- `prepare-release.sh` (vendor из G1, `composer.lock` и список миграций совпадают), `backup.sh` — 64 981 байт.
- FPM первым: DI-smoke через `prolog_before.php` 10/10 (`StaffArchiveController`, `ArchiveStaffUseCase`, список,
  сохранение, `StaffIdentityGatewayInterface`, три контроллера media из #86, платежи); `DELETE /api/v1/users/{id}` без
  токена — 401 `UNAUTHORIZED`. Затем nginx, media consumer/dispatcher, notification consumer/dispatcher — код виден.
- Frontend 2/2 на новом образе; `index.html` на проде совпадает с образом по SHA-256.
- Smoke: `/health`, `/login`, `/cabinet/{users,overview,institutions,orders}` — 200; `GET /api/v1/users?sort=…`
  без токена — 401; логи FPM, nginx и воркеров за 5 минут — без фатальных ошибок.
- Не проверено на проде: авторизованное удаление в кабинете организатора — пользовательская проверка.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS (unit + live) | 2026-09-25 | `vendor/bin/phpunit` | `ArchiveStaffUseCaseTest::testArchiveRemovesAccessButKeepsHistory`; live — сценарий #91 в `staff.spec.ts` |
| T02 | PASS (unit + live) | 2026-09-25 | `vendor/bin/phpunit` | `testActorCannotRemoveThemselves`; live — `CANNOT_ARCHIVE_SELF` в сценарии #91 |
| T03 | PASS | 2026-09-25 | `vendor/bin/phpunit` | `testDisabledOrganizerIsRemovedWithoutTheLastOrganizerCheck` |
| T04 | PASS | 2026-09-25 | `vendor/bin/phpunit` | `testLastActiveOrganizerStays` |
| T05 | PASS | 2026-09-25 | `vendor/bin/phpunit` | `testMissingStaffIsNotFound`, `testOnlyOrganizerRemovesStaff` ×3 |
| T06 | PASS | 2026-09-25 | `make test-e2e` (`rabit-e2e-242656c88cda`) | сценарий #91: повторное добавление → тот же id, pending, revision 3 |
| T07 | PASS (unit + live) | 2026-09-25 | `vendor/bin/phpunit` | `testSortDefaultsToNameAndReadsTheDirection`, `INVALID_SORT` ×2 |
| T08 | PASS | 2026-09-25 | `vendor/bin/phpunit` | `StaffArchiveControllerArchitectureTest` |
| T09 | PASS | 2026-09-25 | `make test-e2e` (`rabit-e2e-242656c88cda`) | сценарий #91 (заглушки: PASS, не доказательство) |
| T10 | PASS | 2026-09-25 | `make test-e2e` (`rabit-e2e-242656c88cda`) | сценарий #91 и mobile B2 |
| T11 | PASS | 2026-09-25 | `make test-e2e` (`rabit-e2e-242656c88cda`) | скриншоты `i91-desktop-*`, `b2-mobile-staff.png` |
| T12 | PASS | 2026-09-25 | после review и merge main: PHPUnit 874/874; PHPStan OK; php-cs-fixer 0; `npm run check` (lint, stylelint, typecheck, typecheck:e2e, test:ui 27/27) | вывод команд в сессии |
