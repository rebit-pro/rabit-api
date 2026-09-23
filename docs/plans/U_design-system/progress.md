# Дизайн-система и UX кабинета MoreFoto — журнал

## Точка продолжения

- Дата: 2026-09-23.
- Ветка: `codex/u-design-system` от `main` `bb35665` (merge PR #50); worktree `/home/user/rabit-api-worktrees/u-design-system`.
- PR: [#53](https://github.com/rebit-pro/rabit-api/pull/53) draft, base `main`; head — коммиты U1–U4, серия B4 и этот журнал.
- Документация: [план](plan.md), мастер-план [design-ux-plan](../design-ux-plan/plan.md), отчёт пакета [docs/waves/design-ux](../../waves/design-ux/README.md).
- Завершено: U1, U2 (`d7c3661`), U3 (`1a944fa`), U4 (`c001fc5`, `eedc437`, `6962cf9`), B4 (`3295ad1`, `92ae90d`, `717136b`, `74011c8`, `f5f2f68`), B3 (`604d79c` backend, `42b7ec7` frontend и E2E, коммит реестра и журнала), draft PR #53.
- Сейчас: push серии B3 и обновление описания PR.
- Следующий шаг: U5 — серверные сводки (раздел 9.5 мастер-плана, DS-20 ранний нефинансовый срез); сначала детали волны в плане (раздел 4.6) и сверка фактов модулей-владельцев сводок.
- Блокеры: нет. Открытые решения: нет; follow-up B4 записаны в плане (письмо кода регистрации, отзыв ссылки при отключении pending-сотрудника, письма при смене email активного сотрудника).
- Рабочее дерево: чистое после коммита журнала; node_modules — volume `rabit-u-node`, vendor — `rabit-u-vendor`; канонический `../MoreFoto` изменён на месте, копия до пакета — в scratchpad сессии (`morefoto-before`), патч пересоздаётся diff'ом.
- Команды следующей проверки: быстрые frontend-проверки из раздела 11 плана; backend `vendor/bin/phpunit`, `phpstan analyse`, php-cs-fixer по изменённым файлам (образ `rabit-api-php-cli:d3-webp`, volume `rabit-u-vendor`); `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
| --- | --- | --- | --- | --- |
| DX-U1-01 | PASS | 2026-09-23 | `npx vite build` до/после, `du -cb dist/assets/*.js` | главный чанк 3 878 500 → 284 160 байт (−3,59 МБ, gzip 431 → 105 КБ); JS всего 4 717 272 → 1 122 927; `dist` 15,1 → 11,5 МБ |
| DX-U1-02 | PASS | 2026-09-23 | стенд скриншотов без backend (demo-режим, Vite + Playwright, 10 экранов × 1440/390), `main` против U1, пиксельное сравнение PIL | 19 из 20 снимков идентичны; `overview-1440` — 7 пикселей с разницей ±1 в RGB (сглаживание) |
| DX-U1-03 | PASS | 2026-09-23 | `python3 tools/verify-wave-graph.py docs/waves/graph.json` | 50 волн, 99 ID, ready U1/U5; 13 негативных fixtures, включая 3 новых для пакета; канонический MoreFoto — 51 волна, `build.py`, `validate.py`, `validate-postman.cjs` зелёные |
| DX-U1-04 | PASS | 2026-09-23 | скрипт сравнения правил CSS `dist/assets/*.css` до/после | 13 804 → 13 690 правил: удалено 114 (темы Dark 16, horizontal 23, sidebar 37, topbar 11, perfect-scrollbar 13, VAlert 9, VShadow 3, VTabs 2), добавлено 0, прочих изменений 0 |
| DX-U1-05 | PASS | 2026-09-23 | `npm run check && npm run test:commerce` (docker, `rabit-u-node`) | lint, vue-tsc, tsc e2e — 0 ошибок; commerce 172/172 |
| DX-U1-06 | PASS | 2026-09-23 | `npm ci` в чистый volume `rabit-u-node-ci` | 20 с, 299 пакетов верхнего уровня; lockfile: −176 пакетов, 0 добавлено, 0 смен версий; удалённых пакетов нет |
| DX-U2-01 | PASS | 2026-09-23 | `npm run check` (lint, vue-tsc, tsc e2e, `test:tokens`) и `npm run test:commerce` | 0 ошибок; tokens 5/5 (актуальность `_tokens.scss`, ссылки семантики, контрасты 7.6); commerce 172/172 |
| DX-U2-02 | PENDING | 2026-09-23 | `e2e/live/design-tokens.spec.ts` против `vite preview` live-сборки | спека проверена на статической сборке — 1 passed; зачёт — в финальном gate на стенде |
| DX-U2-03 | PASS | 2026-09-23 | стенд скриншотов U1 → U2, просмотр login, institutions, orders-390, links, playground | изменились шрифт (Golos Text/Manrope), радиус 8, рамка поля #738596 opacity 1, трекинг normal, отступы `.v-main` на ≤1279px; поломок нет; зонд computed-стилей подтвердил значения |
| DX-U2-04 | PASS | 2026-09-23 | `npx vite build`, `dist/index.html`, CSS | `GolosText-wght-*.woff2` 48,8 КБ и `Manrope-wght-*.woff2` 30,8 КБ в `dist/assets/`; оба preload указывают на те же хэшированные URL, что и `@font-face` |
| DX-U3-01 | PASS | 2026-09-23 | `npm run check` (`test:ui`: tokens, avatar, navigation) | 14/14: инициалы по 8.2 («ИМ», «МИ», «АП», «IV», «•», Ё/Й без нормализации, скобки игнорируются), стабильный тон fnv1a32, группы навигации по 4 ролям live и demo |
| DX-U3-02 | PENDING | 2026-09-23 | `e2e/live/shell.spec.ts` | спека написана, `typecheck:e2e` зелёный; запуск — в финальном gate (нужен live-вход) |
| DX-U3-03 | PASS | 2026-09-23 | стенд скриншотов: каркас 1440/390, открытое меню пользователя, drawer, вход, playground «Бренд» | логотип во всех лок-апах, группы «Работа»/«Настройки», блок пользователя, активный пункт с полосой, меню с именем/email/ролью, вход с stacked-логотипом; горизонтальной прокрутки нет |
| DX-U4-01 | PASS | 2026-09-23 | `npm run test:ui` (`tests/ui/status-tone.test.mjs`) | 18/18: у каждого статуса с подписью (оплата, производство, списки, доступ, состояние группы) есть тон, «заблокирован» — danger, «ожидает регистрации» — pending, неизвестное — neutral, карточка ссылки по закрытию/передаче/подготовке |
| DX-U4-03 | PASS | 2026-09-23 | `npm run check` (+ `lint:styles`), негативная проверка stylelint/ESLint через stdin | hex вне токенов — 0 (151 замена в 42 файлах, 4 `white`); stylelint 0 замечаний; на входе с hex, `white` и `var(--mf-sea-600)` stylelint даёт 3 ошибки, ESLint — ошибку на hex в строке и шаблоне |
| DX-U4-02 | PENDING | 2026-09-23 | финальный gate | `make test-e2e` и визуальная проверка экранов модулей |
| DX-U4-04 | PASS | 2026-09-23 | `npm run test:ui` (`tests/ui/icons.test.mjs`), `npx vite build` | 57 имён `mdi-*` из `src` есть в реестре, значения — SVG-пути; `materialdesignicons` в `dist` нет; CSS 743 301 → 442 642 байт, `dist` 11,5 → 7,7 МБ, главный чанк +25 КБ (пути иконок); скриншоты U4b → U4c — 0,07–0,15 % пикселей (сглаживание) |
| DX-B4-U1 | PASS | 2026-09-23 | `vendor/bin/phpunit --filter 'AccessUseCasesTest\|H1AccessLinkMailerTest\|LoginUseCaseTest\|TokenResolverTest'` | 32 теста, 149 проверок: хэш вместо токена, cooldown `RATE_LIMITED`, `INVITATION_NOT_AVAILABLE`, маска email, `LINK_EXPIRED`/`LINK_USED`, слабый пароль, активация и одноразовость, одинаковый ответ сброса, приглашение для pending, замена сессии, неверный текущий пароль, экранирование имени в HTML, коды `INVALID_CREDENTIALS`/`SESSION_REVOKED`/`TOKEN_EXPIRED` |
| DX-B4-U2 | PASS | 2026-09-23 | `vendor/bin/phpunit --filter 'BitrixEmailTransportTest\|DeliveryUseCasesTest'` | 10 тестов, 35 проверок: HTML уходит без повторного экранирования, хэш полезной нагрузки учитывает `bodyHtml`, текстовые операции сохраняют прежний хэш |
| DX-B4-U3 | PASS | 2026-09-23 | `vendor/bin/phpunit --filter RegistrationSafetyTest` | 14 тестов, 63 проверки; новый: устаревшая ссылка приглашения для учётки, активированной кодом, — `LINK_USED` 410, пароль и сессия не меняются |
| DX-B4-U4 | PASS | 2026-09-23 | `vendor/bin/phpunit` (архитектурные тесты `AccessControllerArchitectureTest`, `StaffInvitationControllerArchitectureTest`) | 4 теста: зависимости трёх контроллеров B4 — только UseCase, RequestDto, input-mapper и общий API контроллера, без `json([...])` и типов Bitrix; каналы логов `auth` и `access`; полный прогон 582/582 |
| DX-B4-01…05 | PENDING | 2026-09-23 | финальный gate: `zz-access.spec.ts`, `verify-access.php` | спека (6 сценариев) и верификатор написаны, `typecheck:e2e` и `php -l` зелёные; запуск — после всех волн |
| DX-B3-U1 | PASS | 2026-09-23 | `vendor/bin/phpunit` (`AvatarFileInspectorTest`, `GdAvatarRendererTest`, `AvatarUseCasesTest`) | инспектор: формат по содержимому, 64×64, 5 МиБ, заголовок PNG 6000×6000 отклонён до декодирования; рендер: центральный квадрат, 256/64 WebP, EXIF 6 поворачивает кадр, в WebP нет EXIF; UseCase: тот же файл — версия 1, новый — версия 2 и удаление прежних файлов, учитель получает 403 на чужой аватар, неизвестный сотрудник — 404, устаревшая версия — 404, удаление идемпотентно |
| DX-B3-U2 | PASS | 2026-09-23 | `vendor/bin/phpunit` (`EntityTagTest`, `LocalAvatarStorageTest`, `StaffAvatarControllerArchitectureTest`) | If-None-Match: тег, `W/`, список, `*`, чужая версия и размер; файлы 0600, каталог 0700, только текущая версия; граница контроллера чистая; полный прогон 603/603, PHPStan без ошибок |
| DX-B3-01, DX-B3-02 | PENDING | 2026-09-23 | финальный gate: `zz-avatar.spec.ts`, `verify-avatar.php` | спека (3 сценария) и верификатор написаны, `typecheck:e2e`, ESLint и `php -l` зелёные |
| DX-FIN-01 | PENDING | — | — | финальный gate |
| DX-FIN-02 | PENDING | — | — | финальный gate |
| DX-FIN-03 | PENDING | — | — | финальный gate |

## Хронология

### 2026-09-23 — старт программы

- Пользователь взял план в работу и выбрал доставку единым PR: волна — коммит, быстрые проверки на коммите, E2E и визуальная проверка — один раз после всех волн. B4 — в общем PR отдельной серией. Решения DS-01…DS-20 — все рекомендации. PR #50 слит агентом (`bb35665`) после коммита с решениями и порядком доставки.
- По логам прогонов D3 полный gate — 10–15 минут (`npm ci` 2–4 мин, браузер 5–8 мин).
- Ветка `codex/u-design-system` и worktree созданы от `main` `bb35665`. Volume `rabit-u-node` — копия `rabit-e5-node` (lockfile не менялся с A8, sha256 `d44649ae…1961`).
- Разведка U1:
  - граф импортов от `src/main.ts`: 341 достижимый файл из 416, 75 мёртвых — совпадает с мастер-планом;
  - живой код не использует `vue-i18n` (`useI18n` только в мёртвых `layouts/full/*`), `vee-validate` (только мёртвый `AuthRegister`), `date-fns`, `vite-plugin-vue-devtools`, `sass-loader`, `vue-cli-plugin-vuetify`; `public/favicon.svg` — иконка MoreFoto v1, остаётся;
  - глобальные стили Berry: `layout/_dark`, `_horizontal`, `_sidebar`, `_topbar`, `components/_VAlert`, `_VShadow`, `_VTabs` — селекторы не встречаются в живом коде; `_override`, `layout/_container` (`html { overflow-y: auto }`, поля `.v-main` на ≤1279px), `components/_VButtons`, `_VCard`, `_VField`, `_VInput`, `_VTextField`, `_VTextarea`, `_VNavigationDrawer` меняют вид живых экранов — перенесены в U2 (раздел 4 плана).
- Базовая сборка `main` (`npx vite build`, 16 с): главный чанк `index-*.js` 3 878 500 байт, JS всего 4 717 272, CSS всего 760 497 (59 файлов), `dist` 15 121 328 байт; сохранена в scratchpad сессии для DX-U1-01/04.

### 2026-09-23 — U1a: очистка наследия frontend

- Удалены 75 недостижимых файлов (граф импортов от `src/main.ts`), темы `theme/LightTheme.ts`/`DarkTheme.ts`, словарь `utils/locales/*`, стили Berry с мёртвыми селекторами (`layout/_dark`, `_horizontal`, `_sidebar`, `_topbar`, `components/_VAlert`, `_VShadow`, `_VTabs`), `public/sounds/new-trade.wav`, `public/assets/svg/sprite.svg`.
- `main.ts` без `PerfectScrollbarPlugin`, `VueTablerIcons`, `i18n`; `plugins/vuetify.ts` — одна тема `MoreFotoTheme` и встроенная локаль Vuetify `ru` (те же сообщения `vuetify/locale`, что давал адаптер vue-i18n); `scss/style.scss` без удалённых файлов и CSS perfect-scrollbar; из `env.d.ts` убраны глобальные типы GeeTest-виджета (живой код их не использует; `GeeTestCaptchaPayload` контракта входа в `api/auth.ts` сохранён).
- `npm uninstall` (с доступом к registry): `vue-tabler-icons`, `vue3-perfect-scrollbar`, `vue-i18n`, `vee-validate`, `date-fns`, `vite-plugin-vue-devtools`, `sass-loader`, `vue-cli-plugin-vuetify`. Верхнеуровневый `@vue/compiler-sfc` 3.5.30 ушёл вместе с devtools; Vue использует вложенный 3.5.24.
- Проверки — таблица выше (DX-U1-01, 04, 05, 06).

### 2026-09-23 — U1b: направление U и пакет design-ux в графе

- `docs/waves/graph.json`: дата 2026-09-23, baseline `bb35665`, D3 merged (PR #46, `533c06b`, follow-up #49/#52 в `decisionEvidence.D3-FOLLOW-UP`), D01 принят (`decisionEvidence.DESIGN-UX`), направление U, `mergePolicy.deliveryBundleNote`, `deliveryBundles.design-ux`, 10 волн пакета со статусом inProgress и пересчитанными `unlocks`. U5 и U7 перепроверяют существующие операции (`verifiesEndpointIds`); новых endpoint ID пока нет.
- Отклонения зависимостей от таблицы мастер-плана: U1 зависит от A8 (frontend, который очищается); B4 — от U3/U4 (экраны доступа на новых компонентах); U8 — от B4 (действия приглашения в списке сотрудников).
- `tools/verify-wave-graph.py` и канонический `wave_graph.py` (до изменения были идентичны): ID `[A-NU]`, правила пакета и три негативных fixtures.
- Канонический MoreFoto: `render-waves.py` выводит раздел «Пакеты доставки» и пакет у волны; `build.py` и `validate.py` принимают ID U; пересобраны `backend-waves.md`, `endpoints.json`, обе Postman-коллекции, `verification.json`; добавлены ссылка на v2 в `frontend/design-plan.md` и запись о D01/D08 в `frontend/business-review/decisions.md`. Патч — `docs/waves/design-ux/morefoto-contract.patch` (12 файлов, 1 866 строк, `patch -p1 --dry-run` на копии до изменений — без ошибок).

### 2026-09-23 — стенд скриншотов и DX-U1-02

- Для самоконтроля вёрстки собран стенд без backend в scratchpad сессии: Vite dev в demo-режиме в контейнере Playwright без сети, вход demo-ролями, 10 экранов × 1440/390 за ~40 с. Demo-моки считают `navigator.onLine=false` сетевой ошибкой — стенд подменяет его через `addInitScript`.
- `main` (экспорт `bb35665`) против U1: 19 из 20 снимков совпали попиксельно, в `overview-1440` 7 пикселей отличаются на ±1 в RGB (сглаживание). DX-U1-02 выполнен дополнительно к DX-U1-04.

### 2026-09-23 — U2: токены, шрифты, тема

- Факты и решения — раздел 4.1 плана. `src/theme/tokens.ts` → `scripts/tokens-build.mjs` → `src/styles/_tokens.scss`; `tests/tokens/tokens.test.mjs` в `npm run check`; расчёт контраста вынесен в `src/theme/contrast.ts` (тест и playground).
- Шрифты: Golos Text и Manrope из google/fonts (OFL), subset `pyftsubset` (латиница, кириллица, ₽, №, пунктуация, `layout-features=*`), вариативность сохранена (fvar/gvar/HVAR проверены), woff2 48,8/30,8 КБ в `src/assets/fonts/` с лицензиями. Запасные лица с метриками: Golos `size-adjust` 111,34 %, ascent 88,02 %, descent 19,76 %; Manrope 106 %.
- Каталог `src/scss/` удалён: ядро Vuetify настраивается в `src/styles/vuetify.scss`, глобальная база — `src/styles/_base.scss`. CSS компонентов Vuetify грузится лениво с чанками маршрутов после основных стилей, поэтому базовые переопределения подняты через `:root`. Трекинг Material (`letter-spacing` у 13 селекторов Vuetify) обнулён: с Golos Text он выглядел разреженным. Контур поля в покое — `border-strong`, в фокусе наследует primary (раньше Berry держал его серым), ошибки — цвет Vuetify.
- `MoreFotoTheme` из токенов; `_morefoto-ui.scss`: радиусы через `--mf-radius-sm`, рамка поля opacity 1, подпись поля `text-secondary`, фокус и шрифт оверлеев на токенах; `morefoto.scss`: шрифт кабинета, Manrope у `.mf-brand`, заголовков страниц и панелей.
- Demo Cucumber: `e2e/steps/ui/fields.steps.ts:40` ожидает радиус контура `8px`. Live-спека `e2e/live/design-tokens.spec.ts` (DX-U2-02) проверена на `vite preview` live-сборки: первая версия упала на XPath-предке (нашёлся `v-field__input`), локатор исправлен на `.v-input` с полем Email — 1 passed.
- Playground `/demo/ui`: разделы «Токены» (семантические цвета с контрастом, статусы, пастель, радиусы, тени) и «Типографика».
- Бандл: главный чанк 286 940 байт (+2,8 КБ токены и тема), CSS всего 743 301 байт.
- Наблюдение для U4: у экрана «Ссылки и сроки» собственный заголовок (Golos Bold из `handoff.css`), заголовки страниц унифицируются компонентами U4. Manrope 600 визуально легче Golos 600 — оставлено по плану, оценка на финальной визуальной проверке.

### 2026-09-23 — U3: бренд, каркас, аватарки

- Детали и отклонения — раздел 4.2 плана: хлебные крошки и вкладки съёмки перенесены в U7, промежуточный `homePath` не вводится (U6 сразу переводит роли на «Обзор»).
- `MfLogo`: геометрия знака пересчитана — волна пересекает правую грань рамки на y ≈ 13,4 (в мастер-плане 13,7–16,3 — приблизительно); разрыв рамки центрирован по точке пересечения и равен толщине штриха плюс 0,5u воздуха с каждой стороны, концы рамки `butt`. Compact обрезает левый хвост `clipPath` с `useId()`.
- `avatar.ts` (инициалы, seed, тон fnv1a32) и `MfAvatar`/`MfAvatarStack`; `navigation.ts` — чистая функция групп меню с тестом; видимость пунктов повторяет прежнюю логику (роль/разрешения), профиль переехал в блок пользователя и меню.
- `CabinetLayout`: app-bar 64/56 с логотипом, заголовком раздела на mobile, подписью роли и меню пользователя; тень появляется после прокрутки; drawer с блоком пользователя, подписями групп и активным пунктом (nav-active-bg + полоса 3px). Метки «Открыть меню» и «Основная навигация» сохранены.
- Служебные страницы в `ServiceRoutes` с `AuthAwareLayout`: сотрудник видит их в каркасе, гость и учётка без роли — отдельно, `<main>` один. Тексты без «MoreFoto»; «Раздел пока недоступен» говорит об онлайн-оплате.
- Вход: stacked-логотип, панель radius-lg с тенью sm на фоне primary-soft → surface, заголовок «Вход в кабинет», декоративная волна accent. Галерея: mono-логотип в шапке и подвале. Заголовки вкладок «Раздел — Море фото», мета-теги `index.html` на русском бренде; подписи маршрутов «Списки сотрудников», «Сотрудники».
- E2E: хелперы `logout()` (live) и `signOut()`/`openUserMenu()` (Cucumber) через меню пользователя; «Вход в MoreFoto» → «Вход в кабинет»; ссылка «Заявки на списки сотрудников» → «Списки сотрудников»; профиль в drawer — ссылка «Профиль: имя». Для учётки без роли выход остаётся кнопкой страницы доступа (без каркаса) — это поймал `typecheck:e2e` вместе с затенением `logout` в спеке выхода. Новая спека `e2e/live/shell.spec.ts`.
- `npm run check` включает `test:ui` (tokens + avatar + navigation) вместо `test:tokens`.
- Замечание для U4/U6: в `MfAvatarStack` правая буква частично закрыта соседним аватаром при нахлёсте −8px — оценить на обзоре U6.

### 2026-09-23 — U4a: статусы, уведомления, пустые состояния, диалог

- Детали и решения — раздел 4.3 плана.
- `MfStatus` (тон, точка или иконка) и `components/status/tones.ts`; словарь тонов доменных статусов `modules/morefoto/ui/statusTone.ts` с тестом. 17 ad-hoc `v-chip` со своими цветами (включая «заблокирован» чёрным `secondary`) и статус-ячейка `UiDataTable` переведены на `MfStatus`. Подписи статусов пока остаются в модулях (в разных местах различаются, их сверяют E2E) — сводятся в U7/U8 вместе с экранами.
- `MfEmptyState` (иконка в пастельном круге категории, заголовок, текст, действия) — в пустом состоянии `UiDataTable`.
- Тональные `v-alert` (87 мест) получили токены тонов глобально в `_base.scss`, без правки вызовов; отдельный `MfNotice` не вводится. Меню — radius md и тень md; `.mf-panel` — токены и тень xs.
- `AdminDialog`: размеры sm/md/lg (по умолчанию lg = прежние 880), radius lg, тень lg, заголовок Manrope, на ≤600px — лист снизу; scrim диалогов — sea-900 @ .48 через defaults.
- Отложено осознанно: поле даты дд.мм.гггг (нативные поля в русской локали браузера уже показывают дд.мм.гггг, E2E заполняют их ISO-строками), единый форматтер дат и `MfPagination` — вместе с экранами U7/U8; `MfSkeleton` — не нужен, пока скелетоны Vuetify в токенах.

### 2026-09-23 — U4b: hex → токены и гейт

- Все hex в CSS-объявлениях `.vue`/`.scss` (вне `_tokens.scss`) заменены скриптом по таблице «тип свойства + цвет → семантический токен» (соответствие 7.7): серые тексты → `text-secondary`/`text-tertiary`, графит → `text`, синий текст → `link`, контур фокуса → `focus`, серые границы → `border`/`border-hover`/`border-strong`, голубые фоны → `selected`, светлые → `bg`/`surface-2`, тёплые/зелёные фоны и тексты → тона warning/success/danger. Бирюзовые акценты demo-экранов (#278579, #277c78, #1c7b78, #4f7771, #166864, #315b53, #346d66) сведены к единственному акценту sea (`primary`/`link`/`focus`). Остатков нет; hex в TS и атрибутах шаблонов не было; 4 `background: white` → `surface`.
- Гейт: `stylelint.config.mjs` (`color-no-hex`, `color-named: never`, запрет `--mf-sea|ink|pastel-*` вне токенов), `npm run lint:styles` в `npm run check`; ESLint `no-restricted-syntax` на hex в строках и шаблонах `src/**/*.{ts,vue}` кроме `theme/tokens.ts`. Stylelint 16.26.1, postcss-html 1.8.1, postcss-scss 4.0.9 — точные версии; lockfile +79 пакетов, без смен версий.
- Скриншоты U4a → U4b: изменились только оттенки шапок таблиц (#f3f7fa → bg #f5f8fa) и отдельные блоки пользователей/каталога/playground (0,1–3,5 % пикселей), поломок нет.

### 2026-09-23 — U4c: иконки через @mdi/js

- Реестр `src/plugins/icons.ts` (57 используемых имён `mdi-*` → пути `@mdi/js`, генерируется скриптом с проверкой, что экспорт существует) и набор `src/plugins/iconset.ts` — обёртка над `VSvgIcon` Vuetify (роль и `aria-hidden` сохраняются); внутренние иконки Vuetify — алиасы `vuetify/iconsets/mdi-svg`. `@mdi/font` удалён.
- Найдено попутно: `mdi-image-clock-outline` (пустое состояние галереи «Фотографии ещё готовятся») нет ни в `@mdi/js`, ни в CSS шрифта 7.4.47 — на проде там пустое место. Заменено на `mdi-timer-sand`.
- Тест `tests/ui/icons.test.mjs`: каждое имя `mdi-*` в `src` есть в реестре, значения — SVG-пути. E2E и стили на классы `.mdi-*` не опираются.

### 2026-09-23 — B4: приглашения, первый вход и пароли

- Детали, решения и отличия от мастер-плана — раздел 4.4 плана.
- `3295ad1` backend: таблица `rebit_auth_access_link` (миграция `Version20260923120002`, down удаляет только пустую), `BODY_HTML` в очереди H1 (`Version20260923120001`); слой `Access` в `rebit.auth` — 6 UseCase с русским phpDoc, SQL-репозиторий, генератор токена 32 байта base64url, письма через H1 (consumer `auth-invite`/`auth-reset`, дедупликация по `issuedAt`, HTML только из нашего шаблона с экранированием); маршруты AUTH-05…AUTH-09 в `PrivateApiJsonController` (no-store, no-referrer) и чистых контроллерах; машинные коды вместо английских сообщений входа и токена; `morefoto.access` — выпуск приглашения при создании pending-сотрудника и смене его email, ACC-11 и состояние приглашения в списке и карточке.
- `92ae90d` экраны: `/access/invite`, `/access/recover`, `/access/reset` в общем `AccessShell`, словарь кодов `authErrors.ts`, `reason=revoked|session-expired` в перехватчике 401 и guard, ссылки помощи под формой входа, смена пароля в профиле, статус и повтор приглашения у сотрудника; E2E-фикстуры (в БД только SHA-256 тестовых токенов), верификатор `verify-access.php` в раннере, спека `zz-access`.
- Сверка с разделом 10.1 мастер-плана нашла три пропуска, закрытых `717136b`: экран «Что дальше» после принятия приглашения (разделы из `cabinetNavigation`, «Начать работу», «Профиль»), фильтр `accountStatus` в списке сотрудников (F11) и расширение `RegistrationSafetyTest`. `v-form.reset()` в форме смены пароля заменён явной очисткой полей — reset ставит `null`, а правила ждут строку.
- Реестр: B4 получил AUTH-05…AUTH-09 и ACC-11, `endpointCount` 99 → 105 в `docs/waves/graph.json` и каноническом `backend-waves.json`, N2 перепроверяет и их; в каноническом `build.py` — 6 контрактов (`idem=False`: ключ идемпотентности не поддерживается), фильтр `accountStatus` и `invitation` в ACC-02, секретные переменные Postman `invitation_token`, `reset_token`, `new_password`; счётчики «99» в `render-waves.py`, README и описании коллекции берутся из реестра. Проверки: `wave_graph.py` (51 волна, 105 ID), `render-waves.py`, `build.py`, `validate.py`, `validate-postman.cjs` — зелёные; локальный валидатор — 50 волн, 105 ID, 13 негативных fixtures. Патч пересоздан (16 файлов, 3 849 строк) и воспроизводит канонический каталог из копии до пакета (`patch -p1` + `diff -rq` без расхождений).
- Быстрые проверки на `717136b`: PHPUnit 578/578, PHPStan без ошибок, php-cs-fixer по изменённым файлам; `npm run check` (lint, stylelint, vue-tsc, tsc e2e, unit 20/20), `test:commerce` 172/172, `vite build`.
- Скриншоты без backend (demo): «Что дальше» для organizer и teacher на 1440/390, профиль с блоком «Безопасность» на 390 — вёрстка ровная; подсветка одной строки на снимке — наведение курсора, оставшегося после входа. Экран сотрудников в demo — отдельный компонент, фильтр статуса проверяется live-спекой.
- Покрытие и границы: браузер не читает письма, поэтому цепочка «организатор создал сотрудника → письмо» проверяется верификатором MySQL (последнее письмо `auth-invite` сотрудника из B2 несёт ссылку, хэш которой хранится), а «ссылка → пароль → вход» — спекой на фикстурном токене. Выпуск приглашения в `SaveStaffUseCase` и фильтр статуса не имеют unit-обвязки (SQL Bitrix) и проверяются там же.
- Самопроверка перед B3 нашла два нарушения правила чистых контроллеров в B4: нет архитектурных тестов у `AccessLinkController`, `PasswordController`, `StaffInvitationController`, а два action собирали ответ массивом (`['accepted' => true]`, `['changed' => true]`). Исправлено отдельным коммитом: общий `EmptyResponse` (201/202/204) из `noContent()`/`created()`/`accepted()` базового контроллера, AUTH-07 отвечает 202, AUTH-09 — 204 без тела, `AcceptedJsonTrait` удалён; проверка границы вынесена в `Rebit\Share\Tests\Support\CleanControllerSource` для B4 и будущего контроллера B3. Канонический реестр: формат `empty` в `build.py` и офлайн-валидаторе Postman (успешный ответ без тела), контракты AUTH-07/09 обновлены, патч — 17 файлов.
- Замечание: публикация в RabbitMQ идёт внутри транзакции сохранения сотрудника; если consumer прочтёт операцию до commit, он её пропустит, и письмо отправит `app:notification:dispatch-pending` из cron (раз в минуту, `api/docker/common/cron/crontab`) — задержка до минуты, не потеря. Это существующее свойство H1.

### 2026-09-23 — B3: аватары с фото

- Детали, решения и отличия от мастер-плана — раздел 4.5 плана.
- Проверка до кода: PHP разбирает multipart только для POST, а контракт требует `PUT /me/avatar`. `request_parse_body()` PHP 8.4.25 на встроенном сервере образа разобрал PUT с файлом (`is_uploaded_file` = true); ядро Bitrix читает `php://input` только для JSON, поэтому маппер изображений читает тело PUT сам. В образах cli/fpm есть GD с WebP и `exif`.
- `604d79c` backend: срез `Avatar` в `morefoto.access` (таблица `mf_staff_avatar`, три UseCase и `AvatarAccess` с русским phpDoc, инспектор, GD-рендер, приватное хранилище, SQL-репозиторий с блокировкой строки профиля сотрудника), чистый `StaffAvatarController`, в `rebit.share` — `RequestImageToDtoMapper`, `RequestTechnicalValues` (разбор атрибутов маршрута и заголовков вынесен из `RequestToDtoMapper`), `ImageResponse` + `EntityTag`, `finalizeResponse` без `no-store` для изображений. Поле `avatar` в профиле, списке и карточке (`LEFT JOIN mf_staff_avatar`). Попутно добавлен обязательный phpDoc у затронутых `GetProfileUseCase`, `StaffDirectoryUseCase`, `SaveStaffUseCase`. Миграция добавлена в список E2E-стенда в том же коммите: выборка сотрудников теперь ссылается на новую таблицу.
- Отличия: код `AVATAR_TOO_SMALL` для снимков меньше 64×64; удаление чужого аватара — отдельный ID ACC-12 (в реестре одна запись на метод и путь); `ImageResponse` и multipart-маппер проверяются live-спекой, потому что классов HTTP Bitrix в unit-окружении нет.
- `42b7ec7` frontend: `useProtectedImage` вынесен из `PhotoImage`, `MfAvatar` показывает фото поверх инициалов, `AvatarEditor` в профиле и карточке сотрудника, `auth.reloadProfile()` после смены своего фото, иконки `mdi-camera-outline`/`mdi-delete-outline` в реестре. Скриншоты live-режима с заглушками API (1440/390): фото в шапке, боковой панели, профиле, строках и карточке; найден и исправлен сдвиг колонок строк сотрудников из-за подписи о приглашении (дефект B4) — фиксированная колонка статуса.
- Реестр: ACC-07…ACC-10 и ACC-12 у B3, `endpointCount` 105 → 110, N2 перепроверяет их; канонический генератор получил формат `image` (WebP, ETag, `immutable`), поля multipart ACC-07/09 в `validate.py`, переменные `avatar_version`/`avatar_variant`. Проверки: `wave_graph.py` (51 волна, 110 ID), `render-waves.py`, `build.py`, `validate.py`, `validate-postman.cjs` (110 положительных fixtures); локальный валидатор — 50 волн, 110 ID; патч (17 файлов, 4 718 строк) воспроизводит канонический каталог.
- Быстрые проверки: PHPUnit 603/603, PHPStan без ошибок, php-cs-fixer; `npm run check` (unit 20/20), `test:commerce` 172/172, `vite build`.
- Риск для финального gate: если PHP-сессия Bitrix добавит свой `Cache-Control: no-store`, браузер не закеширует аватар — спека проверяет отсутствие `no-store` в ответе картинки.

