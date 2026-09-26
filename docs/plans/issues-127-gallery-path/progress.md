# Issue #127 — журнал

## Точка продолжения

- Ветка `codex/issues-127-gallery-path`, worktree `/home/user/rabit-api-worktrees/issues-127-gallery-path`,
  base `origin/main` `71cd0f4`. Issue #127. PR — см. ниже.
- Завершено: `galleryPath()` + node-тесты, `GalleryPath.vue`, «Ссылки и сроки», блок на странице фотографий,
  тексты галереи родителя, live E2E `#127` в `z-links-preparation.spec.ts` (группа b).
- PR [#130](https://github.com/rebit-pro/rabit-api/pull/130). Самопроверка без блокеров; полный gate PASS на `f113d1e`.
- Слито `3545cb8`; выкачено на stage 26.09.2026 вместе с #107 релизом `issues107-130-20260926133312-3545cb8` (см. журнал).
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

### 2026-09-26, выкатка на stage

- **Выкачено** на https://app.morefoto36.ru 26.09.2026 по команде пользователя: релиз `/srv/morefoto/releases/issues107-130-20260926133312-3545cb8`
  из main `3545cb8` (#107 и слитые после legal #118, #119, #121, #130). Vendor из `legal-20260926110349-23642d4`
  (`composer.lock` не менялся).
- `prepare-release.sh`, `backup.sh` (78 226 байт), `restore-check.sh` — одноразовая MySQL, 169 таблиц: PASS.
- Миграции: `migrate.sh up` без аргументов пытается применить старую неотмеченную `Version20260911220001` (E1,
  схема на stage уже есть) и падает; сверка с копией: структура `b_hlbd_mf_product` идентична, 10 строк, как было.
  Применена только адресная `migrate.sh up Version20260926120001` (#121, таблица `mf_support_guest_address`) — success.
- FPM переключён первым; DI-smoke внутри FPM — 11/11, именованная блокировка оригинала на реальной БД работает.
  Затем backend, media ×2, notification ×2, payment_reconciler, support ×2 — код виден; frontend 2/2
  `morefoto-frontend:issues107-130-20260926133312-3545cb8` (прежний `morefoto-frontend:legal-20260926110349-23642d4`).
- Живая проверка: `/health`, `/login`, `/cabinet/institutions`, `/guide/`, `/legal`, `/g/<token>` — 200;
  `POST /api/v1/groups/<id>/photo-deletions` без токена — 401 (маршрут есть); `index-UxWlU_jp.js` совпадает с образом;
  Playwright desktop 1280 / mobile 390 — без ошибок JS и горизонтальной прокрутки.
- Не проверено мной: наличие `REBIT_ENCRYPTION_KEY` (≥ 32 символа) у `morefoto_stage_fpm` для #121 — чтение секрета на
  сервере запрещено режимом auto; без ключа гостевая форма «Написать нам» отвечает 503. Проверку выполняет пользователь.
- Откат: `docker service rollback` девяти backend-сервисов (прежний `/app` — `legal-20260926110349-23642d4`) и
  `morefoto_frontend`; спецификации — `services-before.json`, копия БД — `database-before.sql.gz` релиза. Миграция
  #121 только добавляет таблицу.
