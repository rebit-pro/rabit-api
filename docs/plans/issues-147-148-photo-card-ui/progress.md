# Issues #147 и #148 — журнал

## Точка продолжения

- Ветка `codex/issues-147-148-photo-card-ui`, worktree `/home/user/rabit-api-worktrees/issues-photo-card-ui`,
  base `origin/main` `e847e87`. Issues #147, #148.
- Завершено: реализация, быстрые проверки, визуальная проверка на заглушках.
- PR [#149](https://github.com/rebit-pro/rabit-api/pull/149); самопроверка без блокеров; полный gate PASS.
- Сейчас: ожидание команды пользователя на merge и деплой.
- Блокеров и открытых решений нет.

## Статус тест-кейсов

| ID | Статус | Дата | Доказательство |
|---|---|---|---|
| T01 | PASS | 2026-09-26 | stub: высоты 247 (1440) и 293 (390), кнопок за краем 0 |
| T02 | PASS | 2026-09-26 | stub: 60 → 0; live `#106`: 3 → 0 |
| T03 | PASS | 2026-09-26 | скриншоты `card-*` |
| T04 | PASS | 2026-09-26 | stub: активная «2» на подложке 0.12, скриншот mobile |
| T05 | PASS | 2026-09-26 | замеры до/при диалоге: шапка 1425/1425, контент 1097/1097, карточка 247/247 (до исправления 1440/1112/251) |
| T06 | PASS | 2026-09-26 | `make test-e2e` `rabit-e2e-79ab9ea1f474`: a 79/79, b 48/48, 11 верификаторов |

## Журнал

### 2026-09-26

- Замечания пользователя (скриншот stage): карточка кадра разъезжается, нужен «Выбрать все», чекбокс в углу, код в
  полосе снизу, убрать «Без ребёнка» и выделить такие кадры; чёрный квадрат пагинации; сдвиг фона у попапов.
- Сдвиг: Vuetify скрывает полосу прокрутки (`html.v-overlay-scroll-blocked`), компенсация не работает на раскладке
  — ширина росла на 15 px. Исправлено `scrollbar-gutter: stable` + отключение `padding-inline-end`.
- Пагинация: правило Vuetify `.v-pagination .v-pagination__item--is-active .v-btn__overlay { opacity: var(--v-border-opacity) }`
  при `border-opacity: 1` темы давало сплошной квадрат цвета текста; переопределено с большей специфичностью.
- Иконки добавлены в реестр `plugins/icons.ts` (`mdi-star`, `mdi-star-outline`, `mdi-checkbox-multiple-marked-outline`,
  `mdi-selection-remove`, `mdi-account-question-outline`).
- `npm run check` PASS (65 node-тестов). Ошибка JS в stub от незаглушённого `/legal/consents/pending` — артефакт заглушек.
- Самопроверка: блокеров нет; имя чекбокса для диктора у кадра с несколькими кодами теперь содержит все коды.
- Полный gate `rabit-e2e-79ab9ea1f474` (~12 мин): PASS — a 79/79, b 48/48, 11 верификаторов.

### 2026-09-26, выкатка J1 (main 591d698) на app.morefoto36.ru

- Перед этим в тот же день выкачен main `0ef947b` (#149, код #144 без его серверных шагов): релиз
  `main-20260926161546-0ef947b`, миграций нет, DI-smoke 11/11, живая проверка PASS.
- **Релиз** `/srv/morefoto/releases/main-20260926163307-591d698` из main `591d698` (J1 #143 поверх `0ef947b`), по команде пользователя.
  `backup.sh` (85 050 байт), `restore-check.sh` — 173 таблицы: PASS.
- **Подготовка J1** (`j1-prepare.sh`): `runtime/var/private/files` 1000:1000 0700; симлинк
  `runtime/public/local/modules/morefoto.files`; образ `rabit-api-nginx:20260911-074507-uid1000` — прежний stage-образ,
  где пользователь `nginx` переназначен на 1000:1000 (как `api/docker/production/nginx/Dockerfile` J1).
- **Миграции**: только `migrate.sh up Version20260926180001` — success (Installed 31 → 32); `install-module.sh` —
  `morefoto.files installed`.
- **Backend nginx**: `backend.conf` поставляется с релизом — `default.conf` main с поправками stage; внутренние
  `/_protected/media/` и `/_protected/files/` указывают на `/runtime/var/private/…` (backend видит хранилище через
  `/runtime`); URL скачивания исключён из access-лога. Образ backend — `…-uid1000`; `nginx -t` OK, воркеры uid 1000,
  временные каталоги 1000, хранилища читаются.
- FPM первым; DI-smoke 16/16 (в т. ч. `PublicFileController`, Request/Open/Consume/Purge use case), блокировка
  оригинала OK. Затем backend и семь воркеров/диспетчеров.
- **Новые сервисы** (`files-services.sh`, клоны `morefoto_stage_media_consumer` + секрет `rebit_encryption_key`):
  `morefoto_stage_files_consumer` (`app:files:consume --limit=100 --time-limit=300`, от root, как медиа-воркер —
  архивы наследуют владельца корня хранилища) и `morefoto_stage_files_dispatcher` (`app:files:dispatch-pending` раз в
  минуту, `app:files:purge` раз в час). Очередь `filesArchive` создана в vhost `morefoto_stage`. Диспетчер: 0/0 без ошибок.
- Frontend 2/2 `morefoto-frontend:main-20260926163307-591d698` (прежний `morefoto-frontend:main-20260926161546-0ef947b`).
- Живая проверка: страницы — 200; `GET /api/v1/public/orders/current/files` без ключа — 404 `ORDER_NOT_FOUND`;
  `/_protected/files/…` на backend — 404 (internal), на домене frontend — SPA; удаление кадров без токена — 401;
  Playwright desktop/mobile — без ошибок. Скачивание по оплаченному заказу (файл и ZIP) — проверка пользователя.
- **Для следующих релизов**: `switch-backend.sh` должен включать `morefoto_stage_files_consumer` и
  `morefoto_stage_files_dispatcher` (всего 11 сервисов), backend — образ `…-uid1000` и `backend.conf` с J1.
- **Поправка**: ранее записано, что `rebit.leadhunter` «не слинкован в runtime» — неверно: симлинк есть (проверка
  `test -e` на хосте смотрела на путь внутри контейнера). Модуль по-прежнему не подключается в `init.php` и не имеет
  маршрутов.
- Серверные шаги #144 (смена владельца превью, медиа-воркер от www-data) **не выполнялись** — ждут отдельного согласия.
- Откат: `docker service rm morefoto_stage_files_consumer morefoto_stage_files_dispatcher`; backend/воркеры —
  `docker service rollback` (прежний `/app` — `main-20260926161546-0ef947b`, образ backend `rabit-api-nginx:20260911-074507`);
  FPM — по `services-before.json`. Модуль и таблицы J1 можно оставить.
