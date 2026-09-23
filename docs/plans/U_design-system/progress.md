# Дизайн-система и UX кабинета MoreFoto — журнал

## Точка продолжения

- Дата: 2026-09-23.
- Ветка: `codex/u-design-system` от `main` `bb35665` (merge PR #50); worktree `/home/user/rabit-api-worktrees/u-design-system`.
- PR: открывается draft после коммитов U1.
- Документация: [план](plan.md), мастер-план [design-ux-plan](../design-ux-plan/plan.md), отчёт пакета [docs/waves/design-ux](../../waves/design-ux/README.md).
- Завершено: U1a (очистка frontend) и U1b (граф, пакет design-ux, D3 merged, канонический патч MoreFoto); проверки DX-U1-01/03/04/05/06 — PASS.
- Сейчас: коммиты U1, push, draft PR.
- Следующий шаг: U2 — детализировать план волны (токены, шрифты, замена глобальных стилей Berry), затем код.
- Блокеры: нет.
- Рабочее дерево: изменения U1a/U1b до коммита.
- Команды следующей проверки: быстрые frontend-проверки из раздела 11 плана; `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Тест-кейсы

| ID | Статус | Дата | Команда | Доказательство |
| --- | --- | --- | --- | --- |
| DX-U1-01 | PASS | 2026-09-23 | `npx vite build` до/после, `du -cb dist/assets/*.js` | главный чанк 3 878 500 → 284 160 байт (−3,59 МБ, gzip 431 → 105 КБ); JS всего 4 717 272 → 1 122 927; `dist` 15,1 → 11,5 МБ |
| DX-U1-03 | PASS | 2026-09-23 | `python3 tools/verify-wave-graph.py docs/waves/graph.json` | 50 волн, 99 ID, ready U1/U5; 13 негативных fixtures, включая 3 новых для пакета; канонический MoreFoto — 51 волна, `build.py`, `validate.py`, `validate-postman.cjs` зелёные |
| DX-U1-04 | PASS | 2026-09-23 | скрипт сравнения правил CSS `dist/assets/*.css` до/после | 13 804 → 13 690 правил: удалено 114 (темы Dark 16, horizontal 23, sidebar 37, topbar 11, perfect-scrollbar 13, VAlert 9, VShadow 3, VTabs 2), добавлено 0, прочих изменений 0 |
| DX-U1-05 | PASS | 2026-09-23 | `npm run check && npm run test:commerce` (docker, `rabit-u-node`) | lint, vue-tsc, tsc e2e — 0 ошибок; commerce 172/172 |
| DX-U1-06 | PASS | 2026-09-23 | `npm ci` в чистый volume `rabit-u-node-ci` | 20 с, 299 пакетов верхнего уровня; lockfile: −176 пакетов, 0 добавлено, 0 смен версий; удалённых пакетов нет |
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

