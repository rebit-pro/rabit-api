# Дизайн-система и UX кабинета MoreFoto — журнал

## Точка продолжения

- Дата: 2026-09-23.
- Ветка: `codex/u-design-system` от `main` `bb35665` (merge PR #50); worktree `/home/user/rabit-api-worktrees/u-design-system`.
- PR: [#53](https://github.com/rebit-pro/rabit-api/pull/53) draft, base `main`; head — коммиты U1a `a144f1e`, U1b `de5a5f0` и этот журнал.
- Документация: [план](plan.md), мастер-план [design-ux-plan](../design-ux-plan/plan.md), отчёт пакета [docs/waves/design-ux](../../waves/design-ux/README.md).
- Завершено: U1, U2 (`d7c3661`), U3 (`1a944fa`), draft PR #53; U4a — статусы, уведомления, пустые состояния, панели, меню, диалог.
- Сейчас: U4b — перевод hex на токены и гейт hex в линтерах.
- Следующий шаг: U4c — иконки через `@mdi/js` вместо `@mdi/font`, затем журнал и коммит U4.
- Блокеры: нет.
- Рабочее дерево: чистое после коммита журнала; node_modules — volume `rabit-u-node` (после `npm uninstall`) и чистый `rabit-u-node-ci` (`npm ci` на новом lockfile).
- Команды следующей проверки: быстрые frontend-проверки из раздела 11 плана; `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

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

