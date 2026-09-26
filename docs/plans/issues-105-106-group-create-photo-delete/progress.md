# Issues #105 и #106 — журнал

## Точка продолжения

- Ветка `codex/issues-105-106-group-create-photo-delete`, worktree
  `/home/user/rabit-api-worktrees/issues-105-106-group-create-photo-delete`, base `origin/main` `00c507f`
  (слит `a1f4747`). PR [#107](https://github.com/rebit-pro/rabit-api/pull/107). Issues: #105, #106; follow-up #122.
- Завершено: реализация; review 1 (P1 блокировка пути оригинала, P2 восстановление черновика съёмки) исправлен в
  `8df4ad9`, ответы в тредах; полный gate PASS на `48f62a6`.
- Сейчас: merge и выкатка на stage (команда пользователя 2026-09-26).
- Следующий шаг: `gh pr merge 107 --merge --match-head-commit <head>`, затем релиз по образцу legal.
- Блокеров нет. Открытых решений нет.
- Рабочее дерево чистое после коммита.
- Команды:
  - PHPUnit: `docker run --rm --network none --memory 2g --cpus 2 --env XDEBUG_MODE=off --mount type=bind,source=$PWD/api,target=/app,readonly --mount type=bind,source=/home/user/rabit-api/api/vendor,target=/app/vendor,readonly --tmpfs /app/var:rw,size=256m --workdir /app --entrypoint php rabit-api-php-cli:d1-local vendor/bin/phpunit --colors=never`
  - PHPStan: та же команда с `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`.
  - Frontend: `docker run --rm --network none -v $PWD/frontend:/app -v rabit-issues6263-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy npm run check`
  - Полный gate: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`

## Статус тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS (live) | 2026-09-26 | `npm run check`; gate | `structure-group-shoot.test.mjs`: путь POST в выбранную съёмку; live — `#105` в `z-institution-detail.spec.ts` |
| T02 | PASS (live) | 2026-09-26 | `npm run check`; stub | `defaultShootId`: поздняя по дате; заглушки: по умолчанию «Новогодняя съёмка · 20.12.2026» |
| T03 | PASS (live) | 2026-09-26 | stub | скриншот без съёмок: пояснение, кнопки «Новая группа» нет |
| T04 | PASS (live) | 2026-09-26 | stub | диалог «Редактирование группы»; live проверяет PATCH и disabled поля |
| T05 | PASS (live) | 2026-09-26 | gate | C4 куратор: добавлена проверка «Новая группа» = 0 |
| T06 | PASS (live) | 2026-09-26 | PHPUnit | `DeleteGroupPhotosUseCaseTest::testDeletesPhotosAndRemovesTheirFilesOnlyAfterTheCommit` |
| T07 | PASS (live) | 2026-09-26 | gate | live: обложка переходит на оставшийся назначенный кадр |
| T08 | PENDING | 2026-09-26 | gate | SQL `deletePhotos()` удаляет дубликаты до строк кадров; gate PASS, но сценария с дубликатом нет |
| T09 | PASS (unit) | 2026-09-26 | PHPUnit | `testSentGroupIsRejectedBeforeTheLock`, `testDeletionWaitingForTheLinkDeliveryIsRejectedAfterTheLock` |
| T10 | PENDING | 2026-09-26 | — | `deletablePhotos()` отклоняет `processing`; ни unit, ни live не воспроизводят обработку — открытый риск покрытия |
| T11 | PASS (live) | 2026-09-26 | PHPUnit | `testStaleRevisionDeletesNothing` |
| T12 | PASS (live) | 2026-09-26 | PHPUnit | `testRepeatedKeyReturnsTheStoredResultWithoutDeletingAgain`, `testRepeatedKeyWithAnotherSetIsAConflict` |
| T13 | PASS (live) | 2026-09-26 | gate | live: куратор → 403 `FORBIDDEN` |
| T14 | PASS (live) | 2026-09-26 | gate | live: удалённый кадр → 409 `PHOTO_NOT_DELETABLE` |
| T15 | PASS (unit) | 2026-09-26 | PHPUnit | общий оригинал (`originalPathInUse` = true) не удаляется |
| T16 | PASS | 2026-09-26 | PHPUnit | `PhotoMediaContractTest::testControllersKeepTheCleanBoundary` с `DeleteGroupPhotosUseCase`; mapper/strict body |
| T17 | PASS (live) | 2026-09-26 | stub | выбор 2 → диалог «Удалить кадры: 2?» → «Удалено кадров» |
| T18 | PASS (live, заглушки) | 2026-09-26 | stub | desktop/mobile скриншоты, `scrollWidth <= innerWidth` |
| T19 | PASS | 2026-09-26 | см. «Команды» | после слияния main: PHPUnit 962/962, PHPStan OK, php-cs-fixer, `npm run check` (50 node-тестов) |
| T20 | PASS | 2026-09-26 | `make test-e2e` №2 (`rabit-e2e-58019d5311de`) | группа a 78/78, группа b 47/47, 10 верификаторов MySQL; `#105` и `#106` — PASS |

## Журнал

### 2026-09-25

- Замечание пользователя (скриншоты): на странице учреждения не создать группу; в кадрах группы не удалить лишние фото.
- Найдено: кнопка «Новая …» в `InstitutionCollection.vue` только для съёмок, `can-manage` у групп принудительно
  `false`; редактор экрана учреждения создан только для съёмок. API групп достаточен.
- Найдено: в `morefoto.media` нет удаления кадров; FK `RESTRICT` от назначений, обложки и дубликатов; превью без
  метода удаления; оригиналы удаляются через существующий `PrivatePhotoStorageInterface::delete()`.
- Проверено: заказы создаются только при открытой галерее, то есть после отправки ссылки; в «Подготовке» кадры в
  заказах быть не могут. Роль с `media.manage` — только организатор.
- Созданы issues #105, #106 (назначены на себя), ветка и worktree от `caa37b6`, план и журнал.

### 2026-09-26

- Пользователь взял PR #107 в реализацию («Возьми в реализацию, пожалуйста, PR-107») — DEC-1…DEC-6 приняты без правок.
- Слит `origin/main` `57c00c1`: контроллеры медиа уже разделены (`GroupMediaController`), удаление встроено туда же.
- Backend: `PreviewRendererInterface::remove()` + `GdPreviewRenderer`; `MediaMutationRepository::deletablePhotos()`
  (FOR UPDATE, 409 `PHOTO_NOT_DELETABLE`/`PHOTO_PROCESSING`) и `deletePhotos()` (дубликаты → назначения → обложка на
  следующий кадр или снятие → строки); `DeleteGroupPhotosUseCase` по шаблону обложки, файлы удаляются после коммита,
  сбой удаления файла только логируется; `DeleteGroupPhotosRequestDto`, `PhotoInputMapper::deletion()` (общая проверка
  ID вынесена в `photoIds()`), `PhotoDeletionResultDto`, маршрут, DI.
- Frontend #106: `photosApi.remove`, тексты ошибок, `removePhotos` в workspace, «Удалить выбранные» и иконка на
  карточке, `PhotoDeleteDialog`, mock `deletePhotos`.
- Frontend #105: `defaultShootId`, `structureApi.shoots`, `useStructureEditor.open(kind, item, parent)` и `setParent`
  (ключ черновика меняется до записи — синхронный watcher пишет уже под новым ключом), поле «Съёмка» в
  `StructureFields`, кнопки и редактирование групп в `InstitutionCollection`/`InstitutionScreen`.
- Ошибка процесса: `npx prettier --write` по папкам применил чужой конфиг (двойные кавычки) к 22 файлам. Откатил
  непричастные файлы `git checkout`, свои отформатировал `eslint --fix`; итоговый diff проверен.
- PHPStan нашёл 3 замечания в новом тесте (intersection с final-классом, spread с ключами, write-only свойство) —
  исправлено.
- Проверки: PHPUnit медиа 102/102, весь PHPUnit 935/935, PHPStan OK, php-cs-fixer 0/16, `npm run check` PASS.
- Live E2E: `#105` в `z-institution-detail.spec.ts`, `#106` в `zz-media.spec.ts` (группа `a`); не запускались —
  полный gate после review.
- Визуальная проверка на заглушках API (Vite + Playwright, desktop 1440 и mobile 390): пустое состояние групп,
  список с кнопками, диалоги новой/редактируемой группы, выбор и диалог удаления кадров, результат. Ошибок консоли нет,
  горизонтальной прокрутки нет. Не заменяет live E2E.

### 2026-09-26, review 1 и gate

- Review (COMMENT, два блокера в тредах):
  - P1 — удаление могло стереть оригинал новой загрузки того же содержимого между `store()` и `register()`.
    Исправлено: порт `OriginalFileLockInterface` + `MysqlOriginalFileLock` (`GET_LOCK` по sha1 пути, 30 с);
    `store→register` в загрузке и `originalPathInUse→delete` в удалении под одной блокировкой; путь заранее из
    `PrivatePhotoStorageInterface::path()`. Попутно закрыта та же гонка двух одновременных загрузок одного файла.
  - P2 — смена съёмки затирала сохранённый черновик целевой съёмки, включая pending и `Idempotency-Key`.
    Исправлено: `restorableDraft()` в `model.ts`; `setParent()` восстанавливает черновик выбранной съёмки, текущий
    остаётся под своим ключом.
- Коммит `8df4ad9`, слит `origin/main` `00c507f` (`a1f4747`). PHPUnit 962/962, PHPStan OK, `npm run check` (50) PASS.
  Ответы в тредах: discussion_r4110711894, discussion_r4110711946.
- Gate №1 (`rabit-e2e-4947f02619b5`, стартовал после чужого прогона): FAIL — группа a 77/78: в `#106` утверждение
  `covers` = `{}`, а PHP отдаёт пустую карту как `[]` (поведение API прежнее). Все проверки UI и API до этой строки
  прошли. Исправлено утверждение (`48f62a6`).
- Gate №2 (`rabit-e2e-58019d5311de`, 358 с): PASS — группа a 78/78, группа b 47/47, 10 верификаторов.
- Неблокирующее: пустой черновик при повторном открытии помечается восстановленным (было и до PR) — issue #122.
