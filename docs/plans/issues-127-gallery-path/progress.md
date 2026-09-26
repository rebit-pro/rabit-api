# Issue #127 — журнал

## Точка продолжения

- Ветка `codex/issues-127-gallery-path`, worktree `/home/user/rabit-api-worktrees/issues-127-gallery-path`,
  base `origin/main` `71cd0f4`. Issue #127. PR — см. ниже.
- Завершено: `galleryPath()` + node-тесты, `GalleryPath.vue`, «Ссылки и сроки», блок на странице фотографий,
  тексты галереи родителя, live E2E `#127` в `z-links-preparation.spec.ts` (группа b).
- PR [#130](https://github.com/rebit-pro/rabit-api/pull/130). Самопроверка без блокеров; полный gate PASS на `f113d1e`.
- Сейчас: merge в main (разрешён пользователем). Деплой — отдельной командой, вместе с #107.
- Блокеров нет. Открытых решений нет.
- Команды: frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-issues6263-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy npm run check`;
  gate — `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Статус тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01–T06 | PASS | 2026-09-26 | `npm run check` | `tests/ui/gallery-path.test.mjs`, 5 тестов (61 node-тест всего) |
| T07–T10 | PASS | 2026-09-26 | gate №2 `rabit-e2e-868844f18f56` | live `#127`: путь на карточке и странице фотографий, «Посмотреть страницу родителей», заглушка родителя |
| T11 | PASS | 2026-09-26 | `make test-e2e` №2 | группа a 78/78, группа b 48/48, 10 верификаторов |
| T12 | PASS | 2026-09-26 | gate | скриншоты `i127-*` desktop/mobile просмотрены: переполнения нет |

## Журнал

### 2026-09-26

- Замечание пользователя (скриншот галереи «Фотографии ещё готовятся»): непонятно, как кадры попадают в галерею.
  Причина: кадры видны только после «Отметить передачу», а «Открыть галерею» доступна уже после «Проверить ссылку».
  Issue #127; пользователь взял в реализацию и разрешил merge в main (деплой отдельной командой).
- Реализовано по плану. Во время работы пользователь прислал ещё два замечания — оформлены отдельными issues:
  #128 (перевыпуск ссылки покупателя на заказ: ключ хранится только как SHA-256, сервис `OrderAccessKeys`
  уже умеет `recovery`/`replaced`, нужен HTTP и UI) и #129 (ссылка на базу знаний `/guide/` со страницы входа и из кабинета).
- `npm run check` PASS (lint, stylelint, typecheck, typecheck:e2e, 61 node-тест).
- Самопроверка: блокеров нет. Неизвестные коды проблем в путь не попадают — сервер отдаёт ровно пять известных.
- Gate №1 (`rabit-e2e-89d855d66af2`): FAIL — b 47/48, `zzzz-links`: мой сценарий создавал активную продукцию в общем
  каталоге, это меняло подпись продаж у всех групп и сбрасывало «проверено» у группы F2. Создание убрано (`f113d1e`),
  сценарий опирается на продукцию из F2-теста того же файла.
- Gate №2 (`rabit-e2e-868844f18f56`, ~10 мин вместе с ожиданием): PASS — a 78/78, b 48/48, 10 верификаторов.
