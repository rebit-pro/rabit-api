# Issue #91 — журнал

## Точка продолжения

- Ветка `codex/issues-91-staff-table`, worktree `/home/user/rabit-api-worktrees/issues-staff-table`,
  base `origin/main` `4ca7e9c`. PR открыт (ссылка в описании ветки на GitHub). Issues: #91, #92.
- Завершено: backend (архив, сортировка), frontend (`UiDataTable`, выбор, удаление), unit, live E2E-сценарии написаны,
  быстрые проверки зелёные, визуальная проверка на заглушках.
- Сейчас: ожидание review.
- Следующий шаг: после review без блокеров — полный `make test-e2e` (T01, T06, T09–T11).
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

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS (unit) / PENDING (live) | 2026-09-25 | `vendor/bin/phpunit` | `ArchiveStaffUseCaseTest::testArchiveRemovesAccessButKeepsHistory`; live — сценарий #91 в `staff.spec.ts` |
| T02 | PASS (unit) / PENDING (live) | 2026-09-25 | `vendor/bin/phpunit` | `testActorCannotRemoveThemselves`; live — `CANNOT_ARCHIVE_SELF` в сценарии #91 |
| T03 | PASS | 2026-09-25 | `vendor/bin/phpunit` | `testDisabledOrganizerIsRemovedWithoutTheLastOrganizerCheck` |
| T04 | PASS | 2026-09-25 | `vendor/bin/phpunit` | `testLastActiveOrganizerStays` |
| T05 | PASS | 2026-09-25 | `vendor/bin/phpunit` | `testMissingStaffIsNotFound`, `testOnlyOrganizerRemovesStaff` ×3 |
| T06 | PENDING | — | `make test-e2e` | сценарий #91: повторное добавление → тот же id, pending, revision 3 |
| T07 | PASS (unit) / PENDING (live) | 2026-09-25 | `vendor/bin/phpunit` | `testSortDefaultsToNameAndReadsTheDirection`, `INVALID_SORT` ×2 |
| T08 | PASS | 2026-09-25 | `vendor/bin/phpunit` | `StaffArchiveControllerArchitectureTest` |
| T09 | PENDING | — | `make test-e2e` | сценарий #91 (заглушки: PASS, не доказательство) |
| T10 | PENDING | — | `make test-e2e` | сценарий #91 и mobile B2 |
| T11 | PENDING | — | `make test-e2e` | скриншоты `i91-desktop-*`, `b2-mobile-staff.png` |
| T12 | PASS | 2026-09-25 | PHPUnit 753/753; PHPStan OK; php-cs-fixer 0; `npm run check` (lint, stylelint, typecheck, typecheck:e2e, test:ui 27/27) | вывод команд в сессии |
