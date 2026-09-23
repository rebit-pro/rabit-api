# Дизайн и UX кабинета MoreFoto — план

Статус: утверждён пользователем 2026-09-23 — решения DS-01…DS-20 приняты по рекомендациям (раздел 13). Реализация — единым PR из ветки `codex/u-design-system` по порядку раздела 12; журнал реализации — `docs/plans/U_design-system/progress.md`.

## 1. Цель и контекст

Перестроить дизайн и UX личного кабинета `app.morefoto36.ru` так, чтобы он выглядел современно и «дорого», опирался на единую токен-систему (пастельные цвета плюс «синий моря», типографика, радиусы, тени, motion), получил логотип кабинета, аватарки сотрудников с буквенным fallback, инфографику по ролям и понятный сквозной сценарий доступа (приглашение, первый вход, восстановление пароля).

Бриф пользователя от 2026-09-22: больше инфографики; аватарки сотрудников, без фото — буквы; более современный дизайн; пастельные цвета и «синий цвет моря»; дорогой логотип кабинета; весь кабинет должен выглядеть дорого; проработать UX (пример: сотруднику выдают логин, а пароль не выдают, и непонятно, как войти); токен-система; сейчас только план отдельным PR, реализация позже. Дополнение: тени использовать как выразительный инструмент; чаты с клиентами (сообщения с сайта) — будущее направление, не сейчас.

Основание: дизайн-план v1 соседнего проекта (`../MoreFoto/docs/frontend/design-plan.md`), UI01–UI04 (`design-system-implementation-plan.md`, `ui-components.md`), иконка v1 (`icon-v1.md`), реестр решений бизнес-проверки (`business-review/decisions.md`: D01 «принимается ли визуал» и D08 «выдача/восстановление паролей — механизм до rebit.auth» не закрыты). Референс заказчика: morefoto36.ru (белый фон, графит #1E1E1E, голубой мазок в логотипе).

Ветка `codex/design-ux-plan` от `main` `5b750c07e964e279e3517292dae6517643f3e7be`. Граф волн, код и соседний проект в этом PR не меняются.

## 2. Факты из кода, определяющие план

| Область | Факт | Где |
| --- | --- | --- |
| Цвет | Токенов цвета нет: тема Vuetify из 14 значений; в live-компонентах 189 hex в 47 файлах, 7 оттенков «серого» текста, 8 оттенков границ, 3 схемы статусов; `rgb(var(--v-theme-primary))` встречается 6 раз | `frontend/src/theme/MoreFotoTheme.ts`, `frontend/src/styles/morefoto.scss`, `staff/components/StaffManagementScreen.vue`, `handoff/handoff.css`, `ui/components/UiTableCell.vue` |
| Шрифт | В кабинете Arial; Roboto объявлен, но не подключён; web-шрифтов нет; uppercase кнопок из наследия гасится только внутри `.morefoto-app` | `frontend/src/styles/morefoto.scss:4`, `frontend/src/scss/_variables.scss:8,107-125`, `index.html` |
| Бренд | В шапке текст «Море фото», в галерее «MoreFoto», на входе бренда нет; знак существует только как иконка v1 | `layouts/CabinetLayout.vue:187`, `frontend/public/icons/morefoto-v1.svg` |
| Каркас | Vuetify-drawer без аватара и группировки, app-bar 72px, роль скрыта на мобильном, title вкладки статичен, вспомогательные страницы вне каркаса | `layouts/CabinetLayout.vue`, `views/AccessPage.vue` |
| Глубина | Ноль теней; 9 значений радиусов от 4 до 24px; 5 реализаций «карточки»; рамка поля с opacity .65 даёт 2,21:1 (нарушение WCAG 1.4.11) | `styles/_morefoto-ui.scss:69`, экраны модулей |
| Аватарки | Отсутствуют в `ProfileOutputDto`, `StaffOutputDto` и UI; `PERSONAL_PHOTO` не используется; `FileServiceInterface` без реализации | `morefoto.access/lib/Application/Profile/Dto/ProfileOutputDto.php`, `Staff/Dto/StaffOutputDto.php` |
| Онбординг | `POST /api/v1/users` создаёт CUser со случайным паролем и `UF_AUTH_REGISTRATION_PENDING=1`; письмо не отправляется; `/register` → `/login`; на входе нет «Забыли пароль?»; pending-сотрудник получает 401 «Invalid credentials» по-английски; регистрация по коду работает на backend, но UI недостижим и брендирован «Rebit P2P Trader» | `SaveStaffUseCase.php:103`, `rebit.auth/lib/Infrastructure/Adapter/StaffIdentityGateway.php:28`, `frontend/src/router/PublicRoutes.ts:59`, `LoginUseCase.php:52`, `views/authentication/RegisterPage.vue` |
| Пароль | Сброса и смены пароля нет ни у сотрудника, ни у организатора; одна сессия на пользователя, срок 24 ч без продления | `rebit.auth/routes.php`, `UserRepository.php:252-261`, `di/auth.php` |
| Письма | `EmailNotificationInterface::queue()` (H1) — надёжная доставка, но только plain text (`BitrixEmailTransport` экранирует HTML); письмо кода регистрации идёт отдельным синхронным `CEvent` с брендом «RaBit API» | `rebit.share/lib/Application/Contract/Notification/Dto/EmailNotificationInputDto.php`, `rebit.notification/.../BitrixEmailTransport.php:26`, `migrations.foundation/Version20260326120009.php:12` |
| Сессии | Отзыв сессии организатором (смена email/роли) показывается сотруднику как «Сессия истекла» | `api/http.ts:49-57`, `AuthLogin.vue:67-69` |
| Стартовый экран | В live curator/head/teacher после входа попадают на «Профиль», организатор — в каталог; обзора нет | `stores/auth.ts:180-184`, `router/index.ts:39-57` |
| Инфографика | Live API отдаёт только списки с `meta.total`; готовые агрегаты — `group-links` (state, prepared, problems, сроки) и условия продаж; финансы в live «unavailable» до волн G/I; клиентский пересчёт по всем страницам уже есть и является антипаттерном | `handoff/service.ts:57-59,102-104`, `PhotoWorkspace.vue:49,104`, `services/cabinet.ts:5-6` |
| Наследие | 75 мёртвых файлов (13 408 строк) шаблона Berry/P2P; `vue-tabler-icons` (4217 иконок, ~3,4 МБ raw в главном чанке при нулевом использовании) и `@mdi/font` целиком (347 КБ CSS + 3,6 МБ шрифтов) при 43 используемых иконках; 14 чужих тем; 1 336 строк глобального scss, на который `.morefoto-app` наслаивает переопределения; Cucumber-регресс зависит от demo-экранов модуля morefoto | `frontend/src/main.ts`, `plugins/vuetify.ts`, `scss/style.scss` |
| Граф волн | Валидатор принимает ID только `[A-N][1-9]\d*`; направления дизайна нет; `endpointCount` 99; свободны B3, B4 и буква U (проверено в `docs/waves/graph.json` и `../MoreFoto/docs/04-bitrix-modules/backend-waves.json`) | `tools/verify-wave-graph.py:14` |

## 3. Scope

- Токен-система: примитивы и семантические токены цвета, типографика, радиусы, тени, spacing, z-index, motion; соответствие Vuetify; миграция hardcoded значений и lint-гейты.
- Логотип кабинета и правила применения (шапка, sidebar, вход, письма, favicon/app icon, монохром).
- Каркас: шапка, боковое меню, меню пользователя, хлебные крошки, вкладки контекста съёмки, мобильная навигация, вспомогательные страницы, стартовый экран.
- Аватарки сотрудников: компонент с буквенным fallback, хранение, загрузка, защищённая выдача, места показа.
- Сценарий доступа: приглашение сотрудника, первый вход и установка пароля, восстановление и смена пароля, различение отозванной и истёкшей сессии, русские коды ошибок.
- Единая система состояний: загрузка, пустые состояния, ошибки, успех, подтверждения, «недоступно».
- Инфографика по ролям на live-данных с серверными сводками; финансы — состояние «недоступно» до волн G/I.
- Очистка наследия шаблона как предпосылка чистой базы токенов.
- Разбиение на независимые волны с зависимостями, проверками и критериями приёмки; предложение по нумерации.

## 4. Не входит

- Реализация: код, миграции, шрифты и SVG в этом PR не добавляются.
- Покупательские экраны (галерея, корзина, оформление, заказ): получают токены и логотип автоматически через общие классы; перепроектирование их UX — отдельный план после запуска оплат (G1).
- Чаты с клиентами: будущее направление; токен-система резервирует примитивы (раздел 9.6), экраны не проектируются.
- Тёмная тема: закладывается только структура токенов и пустой блок значений.
- Платежи, чеки, возвраты, производство, доставка (волны G–M) и лента активности (INF-10) без подтверждённого управленческого вопроса.
- Финансовые дашборды организатора и куратора (оплачено, ожидает оплаты, сравнение организаций по деньгам): остаются в N1 по плану [PR #36](https://github.com/rebit-pro/rabit-api/pull/36); здесь проектируется только ранний нефинансовый срез (раздел 9) и компонентная база, которую N1 переиспользует.
- Чат клиента с куратором через MAX: отдельный план [PR #48](https://github.com/rebit-pro/rabit-api/pull/48); токен-система резервирует для него примитивы (раздел 9.6).
- Несколько сессий на пользователя и продление токена: зафиксировано как отдельное решение (DS-15), в волны не включено.
- Изменение графа `docs/waves/graph.json`, валидатора и канонического плана MoreFoto: фиксируется как решение DS-13, выполняется в первой волне реализации.

## 5. Зависимости

- Слитые волны, на которые опираются волны плана: A3/A4 (сессии, multipart), B1/B2 (профиль, сотрудники), C3/C4 (структура), D1/D2 (фото), E2/E3/E5 (каталог, условия, заказы), F1/F2 (заявки, ссылки), H1 (надёжная email-доставка).
- Решения бизнес-проверки: D01 (визуал) закрыт утверждением раздела 7 этого плана, D08 (выдача/восстановление паролей) — утверждением раздела 10.1 (оба — 2026-09-23).
- Соседний проект `../MoreFoto`: после утверждения плана `docs/frontend/design-plan.md` получает ссылку на v2 (этот документ), `decisions.md` — записи по D01/D08; выполняется отдельным коммитом в соседнем репозитории вместе с первой волной.
- Открытые PR с пересечениями (не stacked, код не переносится): [#36](https://github.com/rebit-pro/rabit-api/pull/36) план дашбордов N1 — источник требований к финансовым виджетам, drill-down и семантике денег/времени, ему передаётся компонентная база U6; [#48](https://github.com/rebit-pro/rabit-api/pull/48) план чата через MAX — потребитель токенов и `MfAvatar`; [#47](https://github.com/rebit-pro/rabit-api/pull/47) массовая загрузка фото (#31/#33/#34) — U7 строится поверх него после merge; [#35](https://github.com/rebit-pro/rabit-api/pull/35) ЮKassa и E6 — зависимость финансовых плиток INF-07 (в каноническом плане уже есть E6, поэтому там 41 волна).

## 6. Итоги аудита

Шесть независимых аудитов (визуал, онбординг, экраны, данные инфографики, аватарки, наследие) и панель из трёх дизайн-направлений с тремя судьями и синтезом. Полные тексты — в журнале workflow сессии; в план перенесены выводы с доказательствами.

### 6.1. Визуал

- F01 Нет системы цветовых токенов (см. раздел 2). F02 Типографика без web-шрифта и с тремя слоями `text-transform`. F03 Бренд текстовый, три написания. F04 Sidebar и каркас дефолтные. F06 Аватарок нет. F07 Радиусы без правила. F08 Нет глубины: ноль теней, всё на 1px-границах, а наследие даёт кнопкам чужой hover-подъём. F09 Пять реализаций панели. F10 Три схемы статусов, «заблокирован» показан чёрным `secondary`. F11 Загрузка и уведомления реализованы тремя способами. F12 Инфографики нет. F13 Ряды из 4–6 outlined-кнопок в шапках без иерархии действий. F14 Десять размеров иконок. F16 Мобильная шапка 72px и длинные пункты меню. F21 Нативные date-инпуты показывают `mm/dd/yyyy`.
- Сохраняется: токены размеров UI01–UI04 (`_morefoto-ui.scss:2-20`: контролы 48/40, touch 44, spacing 4–32, focus 2px), ядро палитры (#24658A, #1E1E1E, #F5F8FA, #5E6872, #DCE4EA, #738596), знак v1, паттерн `.mf-page-heading`, `UiDataTable`, `AdminDialog`, `ui/defaults.ts`, a11y-решения (skip-link, `aria-*`, `role=status/alert`, `<dl>`, `<details>`).

### 6.2. Онбординг и вход

- F01 Сотрудник после создания не получает ни письма, ни инструкции; организатор должен объяснять путь устно. F02 Экран регистрации недостижим и устарел, хотя backend (`RequestRegistrationCodeUseCase.php:67-72,101-107`, `ConfirmRegistrationUseCase.php:82-95`) принимает pending-пользователя. F03 Pending-сотрудник при входе видит «Invalid credentials». F04 Нет смены и восстановления пароля. F05 После смены email/роли организатором сотрудник видит ложное «Сессия истекла». F06 Письмо кода брендировано «RaBit API» и идёт синхронным путём мимо H1. F07 Тексты ошибок смешаны (английские технические и русские). F09 Первый экран curator/head/teacher — «Профиль», пустая область не объяснена. F10 «Доступ не назначен» без контакта организатора. F11 В списке сотрудников нет фильтра pending и действий онбординга. F12 Открытая регистрация по email pending-сотрудника позволяет чужому запросу сбить его код. F13 Капча предусмотрена backend, но UI её не рендерит.
- Сохраняется: безопасность pending-учётки (случайный пароль, `ACTIVE=N`, флаг pending), механика кодов (cooldown, TTL, лимит попыток, тесты `RegistrationSafetyTest`), форма входа с фокусом на ошибке, guard с `returnUrl`, серверный отзыв сессий при смене роли, идемпотентная доставка `rebit.notification`.

### 6.3. Экраны кабинета

- SCR-AUTH-01 (см. 6.2). SCR-HOME-01 Нет стартового экрана и инфографики. SCR-REQUESTS-01 Заявки на списки в live нельзя довести до конца (перенос отключён), head не видит раздел. SCR-SHELL-01/02 Нет аватара и меню пользователя; «Условия групп» доступны только по URL; страница съёмки без переходов к ссылкам и заявкам. SCR-INSTITUTION-01 «Сотрудник №12» вместо имён, термины «Заведующая/Руководитель». SCR-STRUCTURE-01 «Воспитатель» и «Ответственный группы» одновременно, пустые состояния без CTA. SCR-STAFF-01/02 Роль и назначения не видны на 390px; отключение доступа без подтверждения. SCR-LINKS-01 Три состояния в одном сером chip, ошибки в зелёном notice. SCR-ORDERS-01 en-US даты, нет статуса изготовления в списке. SCR-PHOTOS-01 Двухшаговая загрузка без drag-and-drop, немые disabled-кнопки. SCR-DIALOG-01 Одна ширина 880 для всех форм. SCR-STATES-01/02 Четыре вида загрузки, шесть пустых состояний, два вида success, три пагинации, три формата даты. SCR-STATUS-01 Пять словарей статусов. SCR-PROFILE-01 Профиль без аватара, назначений, безопасности и контактов. SCR-LOGIN-01 Вход без восстановления доступа.
- Сохраняется: `AdminDialog` с черновиками и конфликтами 409, идемпотентность и восстановление потерянного ответа, предметные тексты ошибок по кодам (`staff/api.ts`, `structure/api.ts`, `links-api.ts`), подтверждение замены ответственных с причиной, фильтры заказов в URL.

### 6.4. Данные для инфографики

Готовые агрегаты в live: `group-links` (state, prepared, problems, photoCount, childCount, сроки) и условия продаж. Всё остальное — пагинированные списки с `meta.total`; счётчики нужно считать на сервере (`COUNT … GROUP BY` в существующих репозиториях с той же областью видимости), а не по всем страницам на клиенте. Предложены элементы INF-01…INF-12 (раздел 9.5). Финансовые плитки — только форма и состояние «недоступно» до G1 → I1 → N1.

### 6.5. Аватарки

Реализуемы одной волной без RabbitMQ и без изменений `rebit.auth` (раздел 8.3). Публичный `/upload/` Bitrix не подходит: nginx-статика с годовым публичным кешем, а на production basic auth по Origin ломает `<img>`; нужна защищённая выдача с версией в URL и private-кешем.

### 6.6. Наследие шаблона

LEG-01 `vue-tabler-icons` глобально при нулевом использовании. LEG-02 `@mdi/font` целиком. LEG-03 14 тем Berry и ссылки глобального scss на несуществующие токены `borderLight`/`containerBg` (дивайдеры уходят в `currentColor`). LEG-04 Глобальный Berry-scss (1 336 строк) — фундамент, на который MoreFoto наслаивает переопределения (высоты полей 51/56 против 48/40, uppercase, apexcharts, несуществующий `auth-pattern-dark.svg`). LEG-05 75 мёртвых файлов. LEG-06 `vue3-perfect-scrollbar`. LEG-07 `vue-i18n` со словарём P2P только ради адаптера Vuetify. LEG-08 `AppEmptyState`/`Ui*Card` с чужой палитрой. LEG-11 Статика шаблона и звук P2P в `dist`. Сохраняются: `main.ts`, `App.vue`, `router/*`, `layouts/blank`, весь `modules/morefoto/**` включая demo-экраны и mocks (Cucumber-регресс), `mocks/config.ts`, `MoreFotoTheme.ts`, `styles/*`, `ui/defaults.ts`, живой вход, `stores/auth.ts`, `api/*`, `useSeoMeta`.

### 6.7. Панель дизайн-направлений

Три направления: «Море и свет» (спа-премиум: тени вместо границ, радиусы 20/24, смена primary), «Галерея» (editorial: фотография — главное, почти монохром с одним синим, пастель только для данных), «Тихая вода» (современный dashboard: тонированный фон, слоистые карточки, stat tiles). Судьи по трём линзам (бренд, доступность и реализуемость, целостность продукта) дали «Галерее» 42/40/41 балла, «Тихой воде» 37/41/40, «Морю и свету» 38/33/36. Отвергнуто: смена primary и цвета текста (ломает иконку v1, палитру design-plan и референс, требует переоткрытия D01), панели только на тенях (1,07:1 — теряются на слабых экранах и в печати), пересборка Vuetify elevation через `styles.configFile`, радиусы 20/24 «спа/маркетинг» для рабочего инструмента. Взято из проигравших: единый источник токенов с генерацией scss, холодный scrim, пурпурный tone `pending`, pending-аватар с прозрачностью только фона, словарь категорий инфографики, `MoreFotoTheme.variables` для рамок VCard, subset шрифтов с ₽ и №, `@media print`. Итог — раздел 7.

## 7. Дизайн-направление «Кадр и мазок v2» и токен-система

### 7.1. Концепция

Кабинет — издательская вёрстка вокруг фотографии: белая поверхность на прохладном фоне, графитовый текст, одна «краска моря» для действий и один пастельный набор только для данных (аватарки, категории инфографики, теги). «Дорого» достигается точностью, а не декором: одна 4px-сетка радиусов, одна холодная шкала теней поверх 1px-границ, гротеск с родной кириллицей и геометрический display-шрифт в заголовках и wordmark, табличные цифры везде, где есть числа. Ядро текущей палитры и размеры UI01–UI04 сохраняются, поэтому diff минимален при незакрытом D01. Голубой #C3DFF3 иконки v1 становится `sea-200` и пастелью «небо»; голубой мазок референса превращается в единственный жест бренда — волну, проходящую сквозь рамку кадра. Тёмная тема не входит в v1, но компоненты пишутся только на семантических `--mf-color-*`, поэтому вторая тема — это второй блок значений и второй `ThemeDefinition`.

### 7.2. Примитивы

Источник — `frontend/src/theme/tokens.ts`; hex разрешён только там и в сгенерированном `styles/_tokens.scss`.

Sea-blue «синий моря» (якорь `sea-600` = текущий primary, `sea-200` = иконка v1):

| Токен | Hex | Роль |
| --- | --- | --- |
| `--mf-sea-50` | `#EEF5FA` | primary-soft, selected, фон активного пункта меню |
| `--mf-sea-100` | `#DCEBF4` | selected-strong, hover на голубом |
| `--mf-sea-200` | `#C3DFF3` | волна логотипа, пастель «небо», info-border |
| `--mf-sea-300` | `#8FBFDD` | второй ряд серии графика (только с подписью) |
| `--mf-sea-400` | `#5A9AC2` | non-text графика ≥3:1 (3,07) |
| `--mf-sea-500` | `#3A7FA8` | крупный текст ≥24px, иконки графиков (4,39) |
| `--mf-sea-600` | `#24658A` | primary, link, focus, chart-accent, eyebrow |
| `--mf-sea-700` | `#1C5372` | primary-hover, активный пункт меню, info-fg |
| `--mf-sea-800` | `#16405A` | primary-active |
| `--mf-sea-900` | `#0F2C3E` | база холодных теней и scrim, инверсия логотипа |

Пастель — только данные (пары bg/fg ≥5,8:1; ступень `-mid` — заливка сегментов графиков); индекс задаёт массив `MF_PASTELS`:

| # | Имя | `-bg` | `-mid` | `-fg` |
| --- | --- | --- | --- | --- |
| 0 | sand «песок» | `#F3E7D3` | `#D9B874` | `#6B4E16` |
| 1 | coral «коралл» | `#F6D9D3` | `#E39A8A` | `#8A3A2C` |
| 2 | shell «ракушка» | `#F2DCE6` | `#DDA0BC` | `#7E3557` |
| 3 | lavender «лаванда» | `#E3DDF3` | `#B3A5DE` | `#4E3E8C` |
| 4 | sky «небо» | `#C3DFF3` | `#8FBFDD` | `#1C5372` |
| 5 | mint «мята» | `#D3EEE4` | `#93D1B4` | `#1F5F49` |
| 6 | olive «олива» | `#E4EDD0` | `#BFCB86` | `#4B5F1B` |
| 7 | pebble «галька» | `#E3E7E9` | `#B7C1C9` | `#3E4A54` |

Нейтрали ink (графит с холодным подтоном; текущие значения сохранены):

| Токен | Hex | Роль |
| --- | --- | --- |
| `--mf-ink-900` | `#1E1E1E` | text |
| `--mf-ink-800` | `#2E3338` | secondary-hover, инверсные поверхности |
| `--mf-ink-700` | `#444B52` | текст и иконки меню |
| `--mf-ink-600` | `#5E6872` | text-secondary |
| `--mf-ink-500` | `#66737F` | text-tertiary (4,86 на белом) |
| `--mf-ink-400` | `#738596` | border-strong (контур полей, 3,80) |
| `--mf-ink-300` | `#9AA7B2` | disabled (2,46, исключение 1.4.3) |
| `--mf-ink-200` | `#C4CED6` | border-hover, сетка графиков |
| `--mf-ink-150` | `#DCE4EA` | border, divider |
| `--mf-ink-100` | `#EDF1F4` | surface-2, трек графиков, skeleton |
| `--mf-ink-50` | `#F5F8FA` | bg |
| `--mf-white` | `#FFFFFF` | surface |

Статусные tone (fg — текущие цвета темы; `pending` — пурпур, чтобы не сливаться с warning):

| tone | fg | bg | border | Смысл |
| --- | --- | --- | --- | --- |
| success | `#246C4F` | `#DFF2EA` | `#A9DCC7` | active, оплачен, доставлен, приём открыт |
| warning | `#8A5A10` | `#FBF0DA` | `#E8CF9A` | срок <3 дн., требует внимания, приглашение истекло |
| danger | `#B53A3A` | `#FBE4E4` | `#EDB4B4` | blocked, просрочено, ошибка |
| info | `#1C5372` | `#E3EFF7` | `#B9D6E8` | передано, в работе, срок 3–7 дн. |
| neutral | `#5E6872` | `#EDF1F4` | `#DCE4EA` | draft, архив, срок >7 дн. |
| pending | `#5A4796` | `#ECE8F7` | `#CBC2EA` | ожидает регистрации, приглашение отправлено |

### 7.3. Семантические токены

Объявляются в `styles/_tokens.scss` на `:root, .v-theme--MoreFotoTheme`; компоненты и `.mf-*` ссылаются только на семантику, примитивы вне `_tokens.scss` запрещены lint-правилом.

| Группа | Токены → примитив |
| --- | --- |
| Поверхности | `bg` → ink-50; `surface` → white; `surface-2` → ink-100 (вложенные блоки, thead, треки); `surface-inverse` → ink-900 (toast, полоса логина) |
| Текст | `text` → ink-900; `text-secondary` → ink-600; `text-tertiary` → ink-500 (запрещён на surface-2 и selected, минимум 13px); `text-disabled` → ink-300; `text-inverse` → white; `link` → sea-600 |
| Действие | `primary` / `primary-hover` / `primary-active` / `primary-soft` / `on-primary` → sea-600 / 700 / 800 / sea-50 / white; `secondary` / `secondary-hover` → ink-900 / ink-800 («чёрная» кнопка референса — только вход и галерея); `accent` → sea-200 (мазок логотипа, декоративная линия, не для текста) |
| Границы | `border` → ink-150; `border-strong` → ink-400 (контур полей); `border-hover` → ink-200; `divider` → ink-100 |
| Состояния | `selected` → sea-50; `selected-strong` → sea-100; `hover` → ink-50; `focus` → sea-600; `overlay` → `rgba(15,44,62,.48)`; `skeleton` / `skeleton-hi` → ink-100 / ink-50 |
| Tone | `tone-{success,warning,danger,info,neutral,pending}-{fg,bg,border}` |
| Навигация | `nav-bg` → surface; `nav-fg` → ink-700; `nav-hover` → ink-50; `nav-active-bg` → sea-50; `nav-active-fg` → sea-700; `nav-divider` → ink-100 |
| Графики | `chart-accent` → sea-600; `chart-accent-2` → sea-300; `chart-track` → ink-100; `chart-grid` → ink-200; `chart-{0..7}` → pastel-mid; `chart-{0..7}-strong` → pastel-fg |
| Аватар | `avatar-{0..7}-bg/-fg` → pastel-bg/-fg |

Радиусы `--mf-radius-*` (4px-сетка, правило «одна поверхность — один радиус, вложенное −4px»): xs 4 (статусы, теги, миниатюры), sm 8 (поля, кнопки, stat tile), md 12 (панели, карточки, меню), lg 16 (диалоги, hero входа), xl 24 (только фото-превью), full 9999 (аватар, точки, прогресс). Существующие `--mf-radius-field/button` становятся alias на sm: единственное видимое изменение полей и кнопок — радиус 4 → 8.

Spacing: `--mf-space-1..8` без изменений; добавляются `space-5: 20`, `space-10: 40`, `space-12: 48`, `space-16: 64`, `--mf-gutter` 16 (<md) / 40 (≥md), `--mf-content-max: 1320px`, `--mf-panel-pad` 24 desktop / 16 mobile, `--mf-section-gap: 32`.

Z-index `--mf-z-*`: base 0, raised 1, sticky 10, fab 20, app 1000 (Vuetify layout, не переопределять), overlay 2000 (VOverlay по умолчанию), toast 2100, skip-link 2200.

Motion: `duration-fast` 120 мс (hover, focus), `duration-base` 200 мс (меню, чипы, раскрытие), `duration-slow` 320 мс (диалог, drawer, skeleton → контент, заливка прогресса), `ease-standard cubic-bezier(.2,0,0,1)`, `ease-exit cubic-bezier(.4,0,1,1)`, shimmer 1600 мс; `prefers-reduced-motion` обнуляет длительности и останавливает shimmer; `@media print` убирает тени и фон.

Дверь тёмной темы: блок `.v-theme--MoreFotoDarkTheme { … }` пустой в v1, `MoreFotoDarkTheme` зарегистрирован в `vuetify.ts`, но не включён.

### 7.4. Тени как выразительный инструмент

Холодный подтон `sea-900` `rgb(15,44,62)`; тень всегда дополняет 1px-границу, а не заменяет её (вариант «только тени» отвергнут судьями: 1,07:1 между surface и bg теряется на слабых матрицах и в печати).

| Токен | Значение | Где |
| --- | --- | --- |
| `--mf-shadow-xs` | `0 1px 2px rgba(15,44,62,.06), 0 0 0 1px rgba(15,44,62,.04)` | панель, stat tile в покое |
| `--mf-shadow-sm` | `0 2px 6px rgba(15,44,62,.08), 0 1px 2px rgba(15,44,62,.04)` | hover интерактивной карточки, sticky thead, app-bar при прокрутке |
| `--mf-shadow-md` | `0 8px 24px rgba(15,44,62,.12), 0 2px 6px rgba(15,44,62,.06)` | меню, popover, user-menu, bottom sheet фильтров |
| `--mf-shadow-lg` | `0 20px 48px rgba(15,44,62,.18), 0 4px 12px rgba(15,44,62,.08)` | диалоги, drawer на mobile, просмотр фото |
| `--mf-shadow-ring-surface` | `0 0 0 2px var(--mf-color-surface)` | аватар в стопке, маркер «сегодня» на таймлайне |

Правила выразительности: (1) подъём по наведению — только сменой тени xs → sm за 120 мс, без `transform` (hover-подъём кнопок из наследия удаляется); (2) sticky-элементы получают тень только после прокрутки (класс по `IntersectionObserver`), в покое — граница; (3) слои читаются по возрастанию: панель < карточка при наведении < меню < диалог; (4) фотографии в просмотре и обложки групп — единственные элементы с `lg` на светлом фоне; (5) кнопки в покое и при наведении без тени; (6) на surface-2 и пастельных подложках вместо тени используется `ring-surface`.

### 7.5. Типографика

| Роль | Шрифт | Файл | Почему |
| --- | --- | --- | --- |
| `--mf-font-sans` — весь UI, таблицы, формы, KPI-цифры | Golos Text (Paratype), variable 400–900, OFL 1.1 | `public/fonts/GolosText[wght].woff2` ≈60 КБ | родная кириллица, плотный в 13–16px, `tnum`/`lnum` |
| `--mf-font-display` — h1, h2, display входа/галереи, wordmark | Manrope, variable, OFL 1.1 (используем 500–700) | `public/fonts/Manrope[wght].woff2` ≈45 КБ | геометрия, «издательский» тон |

Self-hosting, subset cyrillic+latin с ₽ (U+20BD) и № (U+2116), `font-display: swap`, два `<link rel="preload">` в `index.html`, лицензии в `public/fonts/LICENSE`. Fallback `'Golos Text', 'Segoe UI', Arial, sans-serif` с `size-adjust`. Отклонение от design-plan v1 (Ubuntu/Arsenal): Ubuntu — лицензия UFL, Arsenal — только static 400/700; запасной вариант — Ubuntu Sans variable (решение DS-02).

Токены: `--mf-text-xs 12`, `sm 13`, `md 14`, `base 16`, `lg 18`, `xl 21`, `2xl 28`, `3xl 32`, `4xl 40`; `--mf-leading-tight 1.25 / snug 1.35 / normal 1.5 / relaxed 1.6`; `--mf-tracking-tight -0.01em / display -0.02em / caps 0.08em`; веса 400/500/600/700.

| Роль | Desktop | Mobile | Вес, семейство |
| --- | --- | --- | --- |
| display (hero входа, заголовок галереи) | 40/44 | 30/36 | 700 −0.02em, Manrope |
| h1 страницы | 32/40 | 26/32 | 600 −0.01em, Manrope |
| h2 панели | 21/28 | 20/28 | 600, Manrope |
| h3, заголовок диалога | 18/26 | 17/24 | 600, Golos |
| body, lead (мера 720px) | 16/24 | 16/24 | 400, Golos |
| body-sm (ячейки, строки списков) | 14/20 | 14/20 | 400 (ключевая колонка 500), tnum |
| caption, meta | 13/18 | 13/18 | 400, text-tertiary только на surface |
| eyebrow | 12/16 | 12/16 | 600, 0.08em, единственный uppercase |
| control (поля, кнопки, вкладки) | 16/24 | 16/24 | 500, без uppercase |
| kpi (stat tile) | 28/32 | 24/28 | 600 tnum, Golos |
| kpi-sm (таймлайн, кольцо) | 20/26 | 18/24 | 600 tnum |
| countdown | 14/20 | 14/20 | 600 tnum |

Display-шрифт только ≥18px; `font-variant-numeric: tabular-nums lining-nums` на `td`, KPI, `time` и суммах; из `$typography` убираются uppercase для button/overline, `$btn-font-weight: 500`; `hyphens: auto` при `lang="ru"`.

### 7.6. Контраст (WCAG 2.x)

| Пара | Контраст | Вердикт |
| --- | --- | --- |
| text / surface, bg, surface-2 | 16,67 / 15,63 / 14,68 | AAA |
| text-secondary / surface, bg, surface-2, selected | 5,68 / 5,32 / 5,00 / 5,16 | AA |
| text-tertiary / surface, bg | 4,86 / 4,55 | AA (только ≥13px) |
| text-tertiary / surface-2, selected | 4,28 / 4,41 | запрещено правилом |
| white / primary, primary-hover, primary-active | 6,36 / 8,30 / 10,97 | AA / AAA |
| link sea-600 / surface, bg, sea-50 | 6,36 / 5,96 / 5,77 | AA |
| nav-active-fg / sea-50; nav-fg / surface | 7,54; 8,85 | AAA |
| focus sea-600 / surface, bg | 6,36 / 5,96 | UI ≥3 |
| border-strong / surface, bg (opacity 1) | 3,80 / 3,56 | UI ≥3 (текущая рамка @ .65 — 2,21, нарушение исправляется) |
| tone success, warning, danger, info, neutral, pending fg / bg | 5,41 / 5,23 / 4,77 / 7,10 / 5,00 / 6,29 | AA |
| аватар sand…pebble fg / bg | 6,30 / 5,80 / 6,38 / 6,65 / 6,00 / 6,13 / 5,87 / 7,30 | AA |
| pending-аватар (фон @ .7, буквы fg) | 6,70 (sand) / 6,65 (sky) | AA |
| заливки chart-mid / surface | 1,74–2,27 | <3 → обязательны контур fg, 2px разделитель и легенда |
| логотип: волна sea-600 / surface; графит / surface | 6,36; 16,67 | PASS |
| app-icon: белая рамка и волна #C3DFF3 на #24658A | 6,36; 4,59 | PASS |
| disabled #9AA7B2 / surface | 2,46 | намеренно (исключение 1.4.3) |

### 7.7. Соответствие Vuetify и миграция

- Единый источник `src/theme/tokens.ts` (`MF_SEA`, `MF_INK`, `MF_PASTELS`, `MF_TONES`, `MF_SEMANTIC`, `MF_RADIUS`, `MF_SHADOW`, `MF_MOTION`, `MF_Z`) → `npm run tokens:build` (`tools/tokens-build.mjs`) генерирует `src/styles/_tokens.scss`; тест `tests/tokens/tokens.test.mjs` (`node --test`, как `test:commerce`) сверяет актуальность файла и входит в `npm run check`.
- `MoreFotoTheme.ts` собирается из `tokens.ts`: `background`, `surface`, `surface-variant` (ink-100), `on-*`, `primary`, `primary-darken-1`, `secondary`, `accent` (sea-200), `lightText` (совместимость на одну волну), `inputBorder`, `error/warning/success/info`, новый `pending`; `variables: { 'border-color': '#DCE4EA', 'border-opacity': 1, 'high-emphasis-opacity': 1, 'medium-emphasis-opacity': 1, 'hover-opacity': .06, 'focus-opacity': .1 }`. Vuetify `variables` для `--mf-*` не используются — одна система имён.
- `scss/_variables.scss`: `$body-font-family` Golos Text, `$heading-font-family` Manrope, `$border-radius-root: 8px`, явная карта `$rounded` (xs 4, sm 8, md 12, lg 16, xl 24, pill, circle), без uppercase, `$btn-font-weight: 500`.
- `styles/_morefoto-ui.scss`: строки 17-18 → alias на `--mf-radius-sm`; 69 → `--v-field-border-opacity: 1` (.65 только для disabled); 42 → `--mf-color-text-secondary`; 148 → `var(--mf-color-focus)`; 153 — убрать Arial; строки 2-16 и 19-20 не трогать.
- `ui/defaults.ts`: `VBtn { rounded:'sm' }`, `VCard { rounded:'md', flat:true, border:true }`, `VChip { variant:'tonal', size:'small', rounded:'xs', label:true }`, `VAvatar { rounded:'circle' }`, `VDialog { scrim:'rgb(15,44,62)', opacity:.48 }`, `VMenu { elevation:0 }` + `.mf-ui-overlay { box-shadow: var(--mf-shadow-md) }`, `VNavigationDrawer { border:0, elevation:0 }`, `VAppBar { flat:true }`, `VProgressLinear { rounded, height:6, bgColor:'surface-variant' }`, `VSkeletonLoader { color:'surface-variant' }`.
- Миграция hex по инвентарю аудита: `#5e6872` ×42 → `text-secondary`; `#24658a` ×27 → `link`/`primary` по контексту; `#dce4ea` ×18, `#dce3e8` ×16, `#e2e8ec`, `#dde2e5` → `border`; `#eaf3f9` ×9, `#f4f8fa`, `#f3f7fa` → `selected`/`surface-2`; `#f5f8fa` → `bg`; `#1e1e1e`, `#253638`, `#0f172a` → `text`; `#738596` → `border-strong`; красные/охра/зелёные статусов → `tone-*` через `MfStatus`; `#687781`, `#61717d`, `#5a6a7c`, `#526a73` → `text-secondary`; палитра `AppEmptyState.vue` — компонент заменяется; `CurrencyIcon.vue` — удаляется в волне наследия.
- Гейты: stylelint (`stylelint-config-standard-scss` + `-recommended-vue`) `color-no-hex` сначала как warning с allowlist по файлам, снимается по модулям, затем error; запрет `--mf-sea-*|--mf-ink-*|--mf-pastel-*` вне `_tokens.scss`, `MfAvatar` и `components/viz/*`; ESLint `no-restricted-syntax` на hex-строки в `<script>` кроме `theme/tokens.ts`.
- Playground `/demo/ui` получает вкладки «Токены» (цвета с авто-контрастом, радиусы, тени), «Типографика», «Бренд» (логотип в лок-апах), «Инфографика» (все состояния); demo Cucumber остаётся зелёным.

## 8. Логотип и аватарки

### 8.1. Логотип «Кадр и мазок»

Знак = рамка фотокадра + солнце + одна волна цвета моря, которая входит в кадр слева и выходит за правую грань, разрывая рамку: «мазок» референса morefoto36.ru и те же три элемента, что в иконке v1. Дорого через точность: одна толщина линии, ноль градиентов и теней, воздух, wordmark с ручным кернингом.

Конструкция (`viewBox 0 0 24 24`, u = 1/24): рамка `rect x=2 y=4.5 w=20 h=16 rx=2.5` без заливки, `stroke=currentColor`; солнце `circle cx=16.5 cy=9 r=1.6`; волна `path M0.5 15.2 C 5 10.4, 8.5 18.6, 13 14 S 20.5 10.6, 23.5 15.4` цветом `--mf-sea-600`, хвосты 1,5u с обеих сторон, правый обязателен; правая грань рамки прерывается на y≈13,7–16,3, внутри кадра волна под `clipPath` с отступом 1u; толщина штриха 2u при рендере ≤32px, 1,5u при ≥48px; охранное поле 0,5 высоты знака; минимум 16px (только рамка и правый хвост, без солнца ≤20px).

Wordmark: «Море фото» Manrope 600, `letter-spacing -0.01em`, кегль 0,75 высоты знака; «Море» цветом text, «фото» — `sea-600` вес 500 (сохраняет логику текущего `.mf-brand span`); латиница `MoreFoto` тем же построением только для служебных мест (manifest, aria, письма на английском).

| Лок-ап | Состав | Где |
| --- | --- | --- |
| horizontal | знак 28 + wordmark 21px, ≈150×28 | app-bar 64 desktop / 56 mobile |
| compact | знак 24 без левого хвоста | rail, вкладки, favicon 32 |
| stacked | знак 48 над wordmark 32px, под формой линия волны `sea-200` 2px | вход, «Задать пароль», «Забыли пароль», пустые состояния |
| gallery | horizontal 24 монохром графит | `GalleryHeader` вместо надписи «MoreFoto» |
| email | PNG @2x 320×64 на белом, подпись «Море фото · кабинет» 13px | письма |
| mono | один цвет: графит на белом (16,67) или белый на sea-900 (14,48) | инверсия, печать, тёмная тема |
| app-icon | плашка v1 `rx=14` #24658A без градиента, рамка белая, волна #C3DFF3 stroke 2,5 (4,59) | manifest 192/512 maskable, apple-touch 180 |

Компонент `src/components/brand/MfLogo.vue` (props `variant`, `size 16–96`, `mono`, `lang`), inline SVG, `role="img"`, `aria-label="Море фото"`; статичные `public/icons/morefoto-mark.svg`, `favicon.svg`. Hero входа — единственная декоративная деталь экрана: фон `linear-gradient(180deg, var(--mf-sea-50), var(--mf-color-surface))`.

### 8.2. Компонент `MfAvatar`

Props: `name`, `src?`, `seed`, `size 24|32|40|56|96`, `status? pending|expired|blocked`, `you?`, `ring?`; круг `radius-full`, `VAvatar rounded="circle"`.

| Размер | Где | Инициалы | Бейдж статуса |
| --- | --- | --- | --- |
| 24 | ячейки таблиц, «Ответственные», история | 1 буква / 10px / 600 | нет |
| 32 | app-bar user-menu, строки списков | 1–2 / 12px | 8px |
| 40 | sidebar-профиль, карточка сотрудника | 2 / 14px | 10px |
| 56 | карточка сотрудника, диалог | 2 / 20px | 12px |
| 96 | профиль, загрузка | 2 / 34px | 16px |

- Инициалы: trim, схлопнуть пробелы, отбросить скобочные токены; ≥2 слов — первые буквы первого и второго слова в порядке ввода («Иванова Мария Сергеевна» → «ИМ», «Мария Иванова» → «МИ», не переставлять: фамилию нельзя распознать достоверно, а порядок совпадает с тем, что человек видит рядом с аватаром); дефисная фамилия — первая часть; одно слово — одна буква; пусто — две буквы local-part email; нет email — «•» тоном pebble; `toLocaleUpperCase('ru-RU')`, Ё/Й не нормализовать; в 24px — только первая буква. Golos Text 600, `letter-spacing .02em`.
- Цвет: `seed = 'staff:' + id` (для приглашённых без id — email в нижнем регистре; имя не участвует, переименование не меняет цвет), `index = fnv1a32(seed) % 8` → пара `avatar-{index}-bg/-fg`. Sea-blue для аватара не используется, чтобы не конкурировать с кнопками.
- Состояния: `active` без декора; `pending` — прозрачность .7 только у фона, пунктирное кольцо `1.5px dashed tone-pending-fg`, от 40px бейдж «часы», `aria-label="…, ожидает регистрации"`; `expired` — как pending с бейджем warning; `blocked` — тон принудительно pebble, фото `grayscale(1)`, бейдж «замок» danger, никогда не чёрный `secondary`; `you` — кольцо 2px sea-600; `loading` — круг skeleton с shimmer. Точки «онлайн» нет: presence в домене отсутствует.
- Фото: `object-fit: cover` 1:1, WebP 64/256, `?v=version`, lazy, `@error` → инициалы без мигания. Одиночный аватар рядом с именем — `aria-hidden`; без текста — `aria-label=name`.
- `MfAvatarStack`: до 4 × 32px, overlap −8px, `ring-surface`, далее чип «+N».

### 8.3. Backend аватарок (волна B3)

- Владелец `morefoto.access`, namespace `Morefoto\Access\{Application,Domain,Infrastructure,Presentation}\Avatar`. Таблица `mf_staff_avatar` (USER_ID PK, VERSION, FINGERPRINT sha256, MIME, BYTES, WIDTH, HEIGHT, UPDATED_BY, UPDATED_AT), миграция в `migrations.foundation/`, `down()` не удаляет непустую таблицу. Файлы `<MOREFOTO_AVATAR_PATH | var/private/media/avatars>/<userId>/<version>-{256,64}.webp` (temp+rename как в `LocalPrivatePhotoStorage`); production уже монтирует `/app/var/private/media`, E2E — `/runtime/private/media`; nginx не меняется. `PERSONAL_PHOTO`/`b_file` и `morefoto.media` отклонены: публичный `/upload/` и общий конвейер фото не подходят.
- Endpoint'ы: `PUT/DELETE /api/v1/me/avatar` (ACC-07/08, сам сотрудник), `PUT/DELETE /api/v1/users/{user_id}/avatar` (ACC-09, `staff.manage`; pending/blocked допускаются), `GET /api/v1/users/{user_id}/avatar/{variant}?v=N` (ACC-10, variant 64|256, любой enabled staff, 404 при несовпадении версии). Новый чистый `StaffAvatarController extends AuthenticatedApiJsonController`; `ProfileController`/`StaffController` не расширяются.
- Общие правки `rebit.share`: `RequestImageDtoInterface` + `RequestImageToDtoMapper` (ровно один файл `file`, `UPLOAD_ERR_OK`, `is_uploaded_file`) в `MAPPER_CLASSES`; `Responses/ImageResponse` (ETag `"<userId>-<version>-<variant>"`, `Cache-Control: private, max-age=31536000, immutable`, `nosniff`, `If-None-Match` → 304); `finalizeResponse` не переписывает `Cache-Control` у `ImageResponse`; `PreviewResponse` фото остаётся `no-store`.
- Обработка синхронно: инспектор (≤5 МиБ, ≤25 МП, MIME jpeg/png/webp, минимум 64×64), GD-рендер (EXIF-orientation 3/6/8, центрированный квадратный crop, 256 и 64, `imagewebp` q≈82; перекодирование отбрасывает EXIF/GPS/ICC). Ошибки 422 `AVATAR_TOO_LARGE` / `UNSUPPORTED_AVATAR_FORMAT` / `CORRUPTED_AVATAR`. Идемпотентность по содержимому: тот же sha256 — версия не растёт; иначе VERSION+1, порядок файл → БД → удаление старых версий. Три UseCase с русским phpDoc; `UF_REVISION`/`UF_ACCESS_REVISION`, `revokeSessions` и `mf_staff_operation` не затрагиваются.
- DTO: `AvatarOutputDto(version, thumbUrl, fullUrl)`, поле `?avatar` в `ProfileOutputDto`, `StaffOutputDto`, `StaffDetailOutputDto` (`LEFT JOIN mf_staff_avatar`, столбец AVATAR_VERSION); URL формирует stateless mapper. Инициалы и цвет считает клиент.
- Frontend: `useProtectedImage(src)` выносится из `PhotoImage.vue`; `avatarApi.saveMine/removeMine/save/remove`; после смены своего аватара — `authApi.me()` и обновление localStorage; demo-адаптер отдаёт `avatar: null`.
- Новые endpoint ID: `endpointCount` 99 → 103, синхронизация с `../MoreFoto/docs/05-rest-api/endpoints.json` и `backend-waves.json`.

## 9. Инфографика

### 9.1. Язык «печатные данные»

Цифры крупнее текста в той же шкале (Golos tnum), подписи 13/500 text-secondary, линии 1,5px, ноль 3D/градиентов/теней у графиков, каждая величина со словом и единицей («12 групп», «3 дн.»), у каждого графика sr-only `<table>`/`<dl>` и `<title>/<desc>`. Компоненты — чистый SVG/CSS в `src/components/viz/`, без внешней chart-библиотеки (временных рядов в live нет; apexcharts удаляется с наследием).

### 9.2. Виджеты

| Виджет | Конструкция | Live-данные |
| --- | --- | --- |
| `MfStatTile` | панель radius-sm, border + shadow-xs (hover sm), padding 20/24; круг 40px `pastel-{k}` с иконкой категории; eyebrow 13/500; число 28/600 tnum; hint 13 в tone; сетка `auto-fit minmax(180px,1fr)`; клик = фильтр списка | учреждения/съёмки/группы, заявки, заказы, сотрудники |
| `MfProgress` | трек chart-track 6px radius-xs, заливка chart-accent, «N из M», анимация 320 мс | готовность фото съёмки, заполнение списка |
| `MfRing` | 56/96, stroke 6/8, дуга chart-accent, число 20/600 в центре; ≥2 сегмента — chart-{k} | статусы обработки фото |
| `MfTimeline` | линия 2px chart-grid, вехи «передана → приём до → доставка до» точками 8px, пройденный отрезок chart-accent, маркер «сегодня» 10px с ring-surface, даты 13/500, countdown 14/600 в tone; на mobile вертикальный | `group-links`, `/groups/{id}/link` |
| `MfDistribution` | stacked-полоса 12px radius-xs, сегменты с 2px белым зазором и контуром `chart-{k}-strong`, легенда «● Оплачен 12 · 40 %» | заказы/группы/сотрудники по статусу |
| `MfMiniBar` | 5–14 столбиков ≤24px, только с числовой подписью рядом, последний — chart-accent | заявки за 7/30 дн., загрузки по дням |
| `MfQueue` | list-row: аватар 24 + название + `MfStatus` + countdown; первые 5 и ссылка «Все» | заявки, ссылки «требует проверки» |

Словарь категорий (`ui/chartPalette.ts`, единственное место маппинга, тот же индекс у `MfAvatar`): учреждения=sky(4), съёмки=olive(6), группы=mint(5), сотрудники=lavender(3), заявки=sand(0), заказы=coral(1), фото=shell(2), прочее=pebble(7).

### 9.3. Цветовые правила

1. Заливка сегмента — `-mid`, контур — `-fg`, 2px белый разделитель, легенда обязательна; соседние `-mid` различаются 1,01–1,29, поэтому цвет никогда не единственный носитель смысла (подпись, точка, `forced-colors` → `stroke-dasharray`).
2. Акцент один — chart-accent sea-600; второй ряд той же серии — sea-300; больше двух оттенков синего в графике нельзя.
3. Статусные tone — только когда сегмент означает статус; «успех» никогда не кодируется зелёным как «хорошо», красный никогда как «много».
4. Пастель — только категории и аватары; sea-заливки и пастель в одной диаграмме не сочетать; >6 серий → «прочее» pebble.
5. Countdown: neutral >7 дн., info 3–7 дн., warning <3 дн., danger просрочено; ссылка не передана — вся линия neutral, countdown «—».

### 9.4. Пустые и недоступные значения

0 объектов — плитка остаётся, «0» text-tertiary и действие («Добавьте учреждение»); поле отсутствует — «—» с подсказкой «нет данных»; финансы в live до волн G/I — `MfStatTile variant="unavailable"`: surface-2, `1px dashed ink-200`, иконка замка, «Недоступно до подключения оплаты», `aria-label="Финансовые итоги недоступны"`, без нулей и demo-цифр; загрузка — skeleton-пресет тех же размеров; ошибка — `MfNotice danger` и «Повторить».

### 9.5. Карта элементов и серверные сводки

| ID | Экран, роли | Управленческий вопрос | Элемент | Backend |
| --- | --- | --- | --- | --- |
| INF-01 | Обзор, organizer/curator | Сколько групп ждут проверки ссылки, готовы к передаче, в приёме, закрываются в 3 дня | 4 `MfStatTile` + `MfQueue` ссылок | `meta.summary` в HND-01 (`GET /api/v1/group-links`): счётчики state×prepared, closingSoon, `referenceNow` |
| INF-02 | Учреждение, съёмка; organizer/curator/head | Доля групп ещё не открытых родителям и уже закрытых | `MfDistribution` preparing/open/closed «из N групп» | `groupStatusCounts` в `InstitutionDetailOutputDto`, `ShootDetailOutputDto` (один COUNT GROUP BY по правилам `GroupDirectory.php:70-75`) |
| INF-03 | Карточка группы в «Ссылки», строка группы на съёмке; все роли | Сколько дней до закрытия приёма и когда обещана доставка | `MfTimeline` с маркером «сегодня» и countdown | нет (желательно `referenceNow` в meta HND-01) |
| INF-04 | «Ссылки», organizer (действие), остальные (чтение) | Что мешает открыть галерею и сколько шагов осталось | чек-лист 5 пунктов по `problems[]`, «Кадров N · Детей M» | опционально `processingPhotos`, `unassignedPhotos` в `GroupLinkSummaryOutputDto` из контракта `GroupMaterialsInterface` |
| INF-05 | Фотографии съёмки, organizer | Готова ли подборка группы к публикации | `MfProgress` «обработано X из N», плитки ready/processing/failed/duplicate, «без ребёнка» | `stats` в `PhotoPageOutputDto` для выбранной группы (COUNT GROUP BY UF_STATUS) |
| INF-06 | Заказы, organizer/curator | Сколько заказов оформлено и сколько не ушло в печать | плитка «Заказов N» и `MfDistribution` по изготовлению; распределение по оплате и суммы — только после G1 в N1 ([PR #36](https://github.com/rebit-pro/rabit-api/pull/36)): до этого оно вырождается в «100 % не оплачено» | `meta.summary{byProductionStatus}` в `SearchStaffOrdersUseCase`; поле `byPaymentStatus` резервируется, но не показывается до G1 |
| INF-07 | Учреждение, съёмка, группа, обзор | Сколько оплачено и останется после возвратов | три плитки в форме `SettlementTotals` в состоянии «недоступно» | ничего до G1 → I1 → N1 |
| INF-08 | Сотрудники, organizer | Сколько сотрудников не активировали доступ | 3 кликабельные плитки pending/active/blocked, распределение по ролям | `meta.summary{byAccountStatus, byRole}` в `StaffDirectoryUseCase::list`, фильтр `accountStatus` |
| INF-09 | Заявки на списки; curator/organizer, teacher | Сколько списков ждут проверки, сколько на уточнении | 3 плитки-фильтра; для teacher — status pills | `meta.summary.byStatus` в `StaffRequestListOutputDto` с той же `visibility()` |
| INF-11 | Обзор teacher | Что сделать по моей группе сегодня | карточка группы: дни до закрытия, pill ссылки, pill моего списка, контакт куратора | нет; контакт куратора требует `curatorName` в ORG-04 (DS-11) |
| INF-12 | Список учреждений; organizer/curator/head | Где идёт приём и где нет съёмок | «Съёмок N · Групп M», pill «Приём открыт: K» | `shootCount/groupCount/openGroupCount` в `VisibleInstitutionOutputDto` подзапросами COUNT, без N+1 |

INF-10 (лента активности) отложена до подтверждения управленческого вопроса. Правило: виджеты не считаются из пагинированных списков на клиенте; существующий клиентский пересчёт в `handoff/service.ts` переводится на серверные сводки.

### 9.6. Задел под чаты (будущее)

Токены и компоненты, которые чаты возьмут без переделки: `MfAvatar` 32 и `MfAvatarStack`, пузыри `--mf-color-bubble-in` → surface-2 / `--mf-color-bubble-out` → sea-50 с radius-md (уголок xs), бейдж непрочитанного tone-danger на `MfStatTile` и пункте меню, `MfNotice` toast на surface-inverse и `--mf-z-toast`, состояние «новое сообщение» в `MfQueue`. Экраны, транспорт (MAX) и модуль чата проектируются планом [PR #48](https://github.com/rebit-pro/rabit-api/pull/48); очередь куратора на сайте из того плана рисуется на `MfQueue` и `MfStatus`.

## 10. UX-сценарии

### 10.1. Доступ: приглашение, первый вход, пароли (волна B4)

Модель: персональное приглашение по ссылке с одноразовым токеном и сроком. Открытая регистрация по email и коду остаётся backend-возможностью, в UI кабинета не показывается (закрывает F12 из 6.2: чужой запрос не может сбить код pending-сотрудника).

Сценарий приглашения:

1. Организатор создаёт сотрудника (`POST /api/v1/users`). `SaveStaffUseCase` после `createPending` вызывает `SendStaffInvitationUseCase` (`morefoto.access`): через `StaffIdentityGatewayInterface::issueInvitation(userId)` (`rebit.auth`) выпускается токен (32 случайных байта, в БД только SHA-256, TTL 7 дней, `resendAvailableAt` +60 с), письмо ставится в очередь `EmailNotificationInterface::queue()` (consumer `access-invite`, ключ дедупликации `invite:<userId>:<version>`). В форме сотрудника вместо алерта «сам задаст пароль через обычную регистрацию» — «Сотрудник получит письмо со ссылкой для установки пароля; ссылка действует 7 дней».
2. В списке и карточке сотрудника: `MfStatus pending` «Ожидает регистрации · приглашение отправлено 22.09», при истечении — tone warning «приглашение истекло»; действие «Отправить приглашение повторно» (`POST /api/v1/users/{user_id}/invitations`, ACC-11; cooldown 60 с; прежний токен аннулируется). `StaffOutputDto` получает `invitation: {sentAt, expiresAt, state: sent|expired|accepted} | null`, `ListStaffInputDto` — фильтр `accountStatus`.
3. Сотрудник открывает `/access/invite/:token`: `GET /api/v1/auth/invitations/{token}` (AUTH-05) возвращает замаскированный email, имя и срок; 404/410 → экран «Ссылка недействительна» с подсказкой обратиться к организатору. Форма: пароль + подтверждение (≥10 символов, не равен email, показ пароля, `autocomplete="new-password"`), логотип stacked, hero sea-50.
4. `POST /api/v1/auth/invitations/{token}/accept` (AUTH-06): активация (`ACTIVE=Y`, `UF_AUTH_REGISTRATION_PENDING=0`, пароль), пометка токена использованным, выдача login-токена → автоматический вход → экран приветствия «Что дальше» (назначения, ссылка на профиль и обзор).
5. Смена email до активации автоматически перевыпускает приглашение на новый адрес; после активации — письмо на старый и новый адрес и отзыв сессии с кодом `SESSION_REVOKED`.

Пароли:

- «Забыли пароль?» на входе → `/access/recover`: email → `POST /api/v1/auth/password-resets` (AUTH-07) всегда отвечает 202 «Если адрес зарегистрирован, мы отправили письмо»; для pending-учётки вместо сброса повторно уходит приглашение; cooldown 60 с на адрес. Ссылка `/access/reset/:token` (TTL 60 мин, одноразовый, лимит попыток) → новый пароль → `POST /api/v1/auth/password-resets/{token}/confirm` (AUTH-08) → отзыв всех сессий → вход.
- Смена пароля в профиле: `PATCH /api/v1/me/password` (AUTH-09) с текущим и новым паролем; успех — `MfNotice success`, сессия сохраняется.
- Ошибки auth переводятся на машинные коды по образцу `morefoto.access` (`INVALID_CREDENTIALS`, `ACCOUNT_BLOCKED`, `TOKEN_EXPIRED`, `SESSION_REVOKED`, `INVITATION_EXPIRED`, `INVITATION_USED`, `RESET_EXPIRED`, `PASSWORD_WEAK`, `RATE_LIMITED`); словарь русских текстов на frontend `auth/errors.ts`. Существование адреса не раскрывается: pending-сотрудник при входе получает тот же `INVALID_CREDENTIALS`, а под формой всегда есть статические ссылки «Получили приглашение? Задайте пароль по ссылке из письма» и «Забыли пароль?».
- Перехватчик 401 различает `TOKEN_EXPIRED` и `SESSION_REVOKED`: `/login?reason=expired|revoked`, тексты «Сессия истекла» и «Организатор изменил данные вашей учётной записи, войдите заново с актуальным email».

Backend `rebit.auth`: таблица `rebit_auth_invitation` (ID, USER_ID, PURPOSE invite|reset, TOKEN_HASH CHAR(64), EXPIRES_AT, USED_AT, ISSUED_BY, ISSUED_AT, RESEND_AVAILABLE_AT, ATTEMPTS) по образцу `rebit_auth_registration_confirmation`; UseCase `IssueInvitationUseCase`, `GetInvitationUseCase`, `AcceptInvitationUseCase`, `RequestPasswordResetUseCase`, `ConfirmPasswordResetUseCase`, `ChangePasswordUseCase` с русским phpDoc; контракт `StaffIdentityGatewayInterface` расширяется методом `issueInvitation`. Письма: `EmailNotificationInputDto` получает необязательное поле `bodyHtml`, `BitrixEmailTransport` отправляет HTML при его наличии; три шаблона (приглашение, сброс, код регистрации) с логотипом email-варианта, именем получателя и кнопкой; письмо кода регистрации перебрендируется с «RaBit API» на «Море фото». Капча GeeTest остаётся выключенной для кабинета (DS-16).

### 10.2. Профиль

Секции: «Аккаунт» (`MfAvatar` 96 с действиями «Загрузить фото»/«Удалить», имя, email), «Роль и назначения» (учреждения из `GET /api/v1/institutions` для curator/head, группы из `GET /api/v1/group-links` для teacher, «все учреждения» для organizer), «Безопасность» (смена пароля; статус приглашения для pending), «Помощь» (контакт организатора — `support: {name, email, phone}` в `ProfileOutputDto` из конфигурации `morefoto.access`, DS-12). Те же контакты — на `/access-unavailable` вместо безадресного «обратитесь к организатору».

### 10.3. Каркас и навигация (волна U3)

- `MfAppBar` 64 desktop / 56 mobile: слева `MfLogo` horizontal (compact на mobile), на mobile — заголовок раздела из `meta.title`; справа роль 14/500 text-secondary и кнопка user-menu с `MfAvatar` 32 (имя, email, роль, «Профиль», «Сменить пароль», «Выйти»); отдельная кнопка выхода из шапки убирается. `router.afterEach` → `document.title = «Раздел — Море фото»`.
- `MfSidebar` 248: вверху `MfAvatar` 40 + имя + `MfStatus` роли как ссылка на профиль; группы «Работа» (Обзор, Учреждения, Ссылки и сроки, Списки сотрудников, Заказы) и «Настройки» (Каталог и цены, Сотрудники); пункт 44px radius-sm, активный — nav-active-bg + полоса 3px sea-600; короткие подписи («Списки сотрудников» вместо «Заявки на списки сотрудников»); счётчики «требует внимания» появляются только после U5.
- `MfBreadcrumbs` (`aria-current`, усечение на 390px) и вкладки контекста съёмки «Группы · Фотографии · Условия · Ссылки и сроки · Списки» на странице съёмки и учреждения — закрывают SCR-SHELL-02 (условия групп доступны только по URL).
- Mobile: drawer сохраняется (до 7 пунктов — нижняя панель не подходит), фильтры списков — `MfFilterSheet` (bottom sheet) с чипами активных фильтров, липкая панель действий внизу форм.
- Вспомогательные страницы (`AccessPage`, `NotFoundPage`, `FeatureUnavailablePage`) для авторизованных рендерятся внутри `CabinetLayout`; текст `FeatureUnavailable` актуализируется («Оплата появится после подключения»).
- Стартовый экран: до U6 `homePath` для curator/head/teacher ведёт на «Ссылки и сроки» (у них там есть данные), organizer — в «Учреждения»; после U6 — «Обзор» для всех ролей.
- Вход: `LoginPage` с `MfLogo` stacked на hero-градиенте, панель radius-lg, ссылки «Забыли пароль?» и «Получили приглашение?» (маршруты открываются в B4), русские тексты ошибок.

### 10.4. Единая система состояний (волна U4)

| Ситуация | Правило |
| --- | --- |
| Загрузка страницы/списка | `MfSkeleton` пресеты page / list / card / table / tile / chart тех же размеров, что контент; `aria-busy` |
| Загрузка действия | линейный прогресс в кнопке или диалоге; повторное нажатие заблокировано |
| Пустое состояние | `MfEmptyState`: иконка 40 в пастельном круге категории, заголовок 18/600, текст text-secondary, одно действие (CTA) или пояснение «кто и как выдаст доступ» |
| Ошибка запроса | `MfNotice danger` inline с «Повторить»; в диалоге — над формой, фокус в сообщении |
| Успех | результат действия внутри списка/диалога — inline `MfNotice success` с авто-скрытием 6 с; действие «вне экрана» (отправка письма) — toast на surface-inverse с `aria-live=polite` |
| Опасные действия | `AdminDialog variant="confirm"` (иконка, danger-кнопка, чекбокс подтверждения и причина, как у замены ответственных) для отключения доступа, понижения роли, удаления аватара организатором |
| Недоступные значения | «недоступно» без нулей (раздел 9.4) |
| Формы | ошибки у полей через `error-messages`, фокус на первое ошибочное; restored/draft/conflict остаются в `AdminDialog`; размеры диалога sm/md/lg; на ≤600px — полноширинный лист снизу |
| Даты | один formatter: дата / дата и время / относительный срок («через 3 дня»), суффикс «МСК» один; `MfDateField` с маской дд.мм.гггг и `v-date-picker` (SCR-STATES-02, F21) |
| Статусы | один `MfStatus` и словарь `statusTone.ts`: accountStatus, group.state, payment/production, request.status, link state → tone/иконка/текст; «заблокирован» = danger |
| Пагинация | одна `MfPagination` (диапазон + страницы) |
| Действия в шапке | 1 primary + ≤1 secondary, остальное в `MfActionMenu`; «Обновить» — иконка 40px |

### 10.5. Экраны по аудиту (волны U7/U8)

- Учреждения и структура: имена и аватары ответственных вместо «Сотрудник №12» (данные — `assignment-options` для organizer, поле `curatorName/headName` в ORG-04 для остальных, DS-11); единый словарь ролей («Ответственный группы», «Руководитель учреждения» — DS-10); `MfStatus` состояний групп; empty-state с CTA «Новая съёмка/группа»; поиск с debounce.
- Фотографии: поверх [PR #47](https://github.com/rebit-pro/rabit-api/pull/47) (очередь до 2000 файлов, наблюдаемая обработка) — dropzone с автостартом и общим прогрессом, сворачиваемая панель загрузки после первого успеха, sticky-панель назначения кода при выделении, объяснение disabled («Назначьте кадры ребёнку, чтобы включить предпросмотр»), бейдж кода поверх превью, INF-05.
- Каталог и условия: вкладки «Продукция | Общие условия», `MfStatus` продукции, поиск/фильтр «в продаже»; условия групп — сегмент-контрол «Общие / Собственные», chip статуса и число позиций в списке групп, CTA в пустом состоянии.
- Сотрудники: `UiDataTable` с `MfAvatar` 40 первым столбцом, мобильная карточка (имя, роль, статус, назначения), фильтры в query, INF-08, действия приглашения (10.1), подтверждение отключения и смены роли (10.4).
- Ссылки и сроки: `MfTimeline` + чек-лист проблем со ссылками на экран-источник, раздельные success/error, подпись «только просмотр» для head (INF-03/04).
- Заявки на списки: шаговый статус «Передан → Проверка → Перенесён» с явным «перенос наборов подключат позже», INF-09, autocomplete кодов из галереи группы, свёрнутая история, аватары акторов; режим чтения для head — DS-14.
- Заказы: период с ru-форматом и пресетами 7/30 дней, `MfStatus` оплаты и изготовления в списке и карточке, skeleton-строки, INF-06, убрать «Оплачено · демонстрация» из live-словаря.

## 11. Очистка наследия (волна U1)

Слои удаления без изменения внешнего вида: (1) bootstrap — `vue-tabler-icons`, `vue3-perfect-scrollbar`, `app.use(i18n)` с заменой на встроенную локаль Vuetify `ru`; (2) мёртвый код — `views/{exchange,wallet,home,dashboard,docs,profile,pages}`, `RegisterPage/AuthRegister` (заменяются экранами B4), `layouts/full/*`, `components/shared/*`, `components/public/PublicTopBar`, `theme/LightTheme.ts`, `DarkTheme.ts`, `types/themeTypes`, `stores/{customizer,advertisements,chatScripts,exchange,identity,trades,wallet}`, `api/{exchange,identity,wallet}`, неиспользуемые composables, `src/mocks/{adapter,database,runtime}` P2P, `config.ts`, `utils/utils.ts`, `morefoto/components/ScopeGroups.vue`, `public/assets`, `public/sounds`, `src/assets/images/favicon.svg`, GeeTest-типы; (3) глобальный scss — `scss/_override.scss`, `scss/components/*` (кроме радиуса outline из `_VField.scss`, уже завязанного на `--mf-radius-field`), `scss/layout/*`; `_variables.scss` остаётся до U2. `@mdi/font` остаётся до перехода на `@mdi/js` через `aliases` (U2/U3). Сохраняются `mocks/config.ts` (49 импортёров) и весь `modules/morefoto/**`. Проверки: `npm run check`, `npm run build` с сравнением размеров чанков (ожидание: главный чанк −3,4 МБ raw), `npm run test:commerce`, demo `npm run test:e2e:run`, live E2E, скриншоты e5/e4/d3/f2 без diff.

## 12. Волны реализации

Порядок доставки — решение пользователя 2026-09-23, исключение из правила «одна волна — один PR» только для этой программы:

- Все волны U1–U8, B3, B4 выполняются в одной ветке `codex/u-design-system` от актуального `main` и сдаются одним PR в `main`. Одна волна — один коммит или короткая серия коммитов в порядке U1 → U2 → U3 → U4 → B4 → B3 → U5 → U6 → U7 → U8; B4 идёт после U4, чтобы экраны доступа сразу строились на новых компонентах.
- На каждом коммите — только быстрые проверки: frontend lint, typecheck, unit, build; backend php-cs-fixer по изменённым файлам, PHPStan, PHPUnit. Каждый коммит зелёный и пригоден для `git bisect`.
- E2E-спеки пишутся внутри своей волны, но запускаются один раз после всех волн вместе с полным `make test-e2e` и визуальной проверкой desktop/mobile. Для самоконтроля вёрстки по ходу — быстрые скриншоты без backend (demo-режим или Vite с заглушками), они не заменяют E2E.
- Merge — только после финального gate и ревью; до этого PR держится открытым и регулярно ребейзится на `main`. Ревью: коммит на волну с описанием «что/зачем/проверки», в описании PR — таблица коммитов с фокусом ревью; B4 — отдельная серия коммитов с фокусом безопасности.
- Столбцы dependsOn и «Разблокирует» ниже задают порядок коммитов и границы ревью внутри PR. В графе волны регистрируются общим пакетом доставки (DS-13): зависимость внутри одного пакета допустима для inProgress/review.

| ID | Название | Тип | dependsOn | Объём | Разблокирует |
| --- | --- | --- | --- | --- | --- |
| U1 | Очистка наследия frontend и регистрация направления U | frontend, docs | — | −13k / +0,5k | U2 |
| B4 | Приглашения, первый вход и пароли | backend + frontend | A3, B2, H1 (merged) | ≈4,5k | B3-независима; U3 берёт готовые маршруты |
| U2 | Токен-система, шрифты, гейты, playground | frontend | U1 | ≈3k | U3, U4 |
| U3 | Бренд, каркас, навигация, аватар-инициалы | frontend | U2 | ≈4k | B3, U6 |
| U4 | Поверхности, статусы, состояния, миграция hex | frontend | U2 | ≈5,5k | U6, U7, U8 |
| B3 | Аватары сотрудников с фото | backend + frontend | B2, A4 (merged), U3 | ≈2,3k | — |
| U5 | Серверные сводки для инфографики | backend | C4, D2, E5, F1, F2 (merged) | ≈3k | U6 |
| U6 | Обзор по ролям и виджеты инфографики | frontend | U3, U4, U5 | ≈4,5k | — |
| U7 | Рабочие экраны: учреждения, съёмки, фотографии, каталог, условия | frontend (+ORG-04 поля) | U4 | ≈5k | — |
| U8 | Ссылки, списки сотрудников, заказы, сотрудники | frontend | U4 | ≈5k | — |

Связь с N1 и [PR #36](https://github.com/rebit-pro/rabit-api/pull/36): U5/U6 — тот самый «более ранний ограниченный срез», который план дашбордов допускает при отдельном решении о границах и изменении DAG (DS-20). Он не содержит денег и не подменяет unavailable нулями; N1 после I1–I3/M1 добавляет финансовые виджеты, сравнение организаций по суммам и drill-down по оплатам на тех же компонентах `components/viz/*`, фильтрах и семантике `calculatedAt`.

Правило нумерации — DS-13: буква U (UI/UX кабинета) добавляется в `directions` графа, regex валидатора расширяется до `[A-NU][1-9]\d*`, канонический план MoreFoto и генераторы синхронизируются в U1; backend-волны аватарок и паролей остаются в направлении B (Access). Альтернатива без регистрации в графе — серия `codex/design-<slug>` по прецеденту issues-веток; тогда B3/B4 и U5 всё равно регистрируются, потому что добавляют endpoint ID.

Детали волн:

- U1. Вход: `main`. Выход: frontend без Berry/P2P, bundle без tabler и perfect-scrollbar, тема одна, `docs/waves/graph.json` с направлением U и волнами U1–U8/B3/B4, валидатор и карты обновлены, `python3 tools/verify-wave-graph.py docs/waves/graph.json` зелёный, `endpointCount` не меняется (endpoint'ы регистрируют B3/B4/U5 при своём merge). Не входит: любые визуальные изменения, `@mdi/font`. Приёмка на коммите: быстрые проверки, размер бандла и скриншоты контрольных экранов до/после без backend (demo-режим и заглушки) — равны или различаются только антиалиасингом, потому что после U2 сравнить «до/после» уже нельзя; demo Cucumber и live E2E — в финальном gate.
- B4. Вход: H1, B2. Выход: сценарий 10.1 целиком (таблица, 6 UseCase, 5 маршрутов AUTH-05…09 и ACC-11, `bodyHtml` в контракте, HTML-шаблоны, экраны `/access/invite`, `/access/recover`, `/access/reset`, смена пароля в профиле, коды ошибок и словарь, `reason=revoked`). Не входит: капча, несколько сессий, аватары. Приёмка: E2E «организатор создал сотрудника → письмо в disposable-почте/таблице очереди → ссылка → пароль → вход → обзор»; повтор приглашения с cooldown; истёкший токен → 410; сброс пароля отзывает сессии; pending при входе получает `INVALID_CREDENTIALS`; `RegistrationSafetyTest` расширен.
- U2. Выход: `tokens.ts`, `_tokens.scss`, генератор и тест, шрифты с subset и preload, `MoreFotoTheme` из токенов, `$rounded`, `_morefoto-ui.scss` по 7.7, stylelint warning, playground «Токены/Типографика». Видимые изменения только три: шрифт, радиус полей и кнопок 4 → 8, рамка поля opacity 1 (обновить `e2e/steps/ui/fields.steps.ts:40` с `4px` на `8px`). Приёмка: live E2E проверяет computed `--mf-control-height: 48px`, `--mf-control-compact: 40px`, `--mf-touch-size: 44px`, `--mf-focus-width: 2px`; demo `visual.steps` контраст подписей ≥4,5; визуальная проверка входа, списка сотрудников, чек-аута 390px.
- U3. Выход: `MfLogo` (лок-апы 8.1), favicon/app-icon из знака, `MfAppBar`, `MfSidebar`, user-menu, `MfAvatar`/`MfAvatarStack` на инициалах (`avatar` пока null), `MfBreadcrumbs`, вкладки съёмки, `document.title`, вспомогательные страницы в каркасе, `LoginPage` (ссылки ведут на маршруты B4, если B4 слита, иначе скрыты), интерим `homePath`. Не входит: рестайл экранов, фото аватара. Приёмка: unit `node --test` для `initials`/`avatarTone`; live E2E навигации по 4 ролям на 390/768/1280/1440; playground «Бренд».
- U4. Выход: `MfPanel`, `MfStatus` + `statusTone.ts`, `MfNotice`, `MfSkeleton`, `MfEmptyState`, `MfPagination`, `MfDateField`, formatter дат, рестайл `UiDataTable` и `AdminDialog` (размеры, confirm, bottom sheet), `StaffManagementScreen` на `UiDataTable`, миграция hex по модулям staff, orders, photos, handoff, curator, gallery, commerce, stylelint error по завершённым модулям, `@mdi/js` через `aliases` вместо `@mdi/font`. Приёмка: тест-кейсы на каждый доменный статус → tone; `npm run test:commerce`; `make test-e2e`; визуальная desktop/mobile 6 экранов.
- B3. По разделу 8.3. Приёмка: unit (рендер/инспектор/UseCase/граница контроллера/304), интеграция MySQL+FS, live E2E `avatar.spec.ts` (PUT multipart → `<img>` в списке и sidebar, teacher 403 на чужой PUT, DELETE → буквы, ETag → 304), скриншоты профиля и списка.
- U5. Выход: серверные поля 9.5 (`meta.summary` HND-01 с `referenceNow`, `groupStatusCounts` ORG, `stats` MED, `meta.summary` COM-12, `meta.summary` ACC и фильтр `accountStatus`, `meta.summary.byStatus` HND-06, счётчики `VisibleInstitutionOutputDto`) — один COUNT GROUP BY в существующих репозиториях с той же областью видимости, без N+1; клиентский пересчёт в `handoff/service.ts` удаляется. Не входит: финансы, лента активности. Приёмка: PHPUnit на область видимости каждой сводки (curator не видит чужие учреждения в счётчиках), HTTP-контракты, PHPStan.
- U6. Выход: `components/viz/*`, `chartPalette.ts`, live «Обзор» для 4 ролей (INF-01, INF-11, финансы «недоступно»), плитки над списками (INF-06, INF-08, INF-09), `homePath` → обзор, счётчики в меню. Приёмка: unit на пороги countdown и палитру, live E2E обзора по ролям, sr-only таблицы, `prefers-reduced-motion`, визуальная проверка.
- U7/U8. По 10.5; каждая ≤6k строк, при превышении делится по экранам. ORG-04 поля `curatorName/headName` — часть U7 как backend-срез с тестами.

## 13. Решения

Приняты пользователем 2026-09-23: все рекомендации плана. Для решений, требовавших подтверждения, выбраны рекомендованные варианты: DS-10 — термины ролей по плану (текст, уточняется с заказчиком без переделки), DS-14 — head видит «Списки сотрудников» в режиме чтения, DS-15 — одна сессия без продления (отдельный issue), DS-16 — капча выключена, DS-20 — ранний нефинансовый срез U5/U6 выполняется.

- DS-01. Направление «Кадр и мазок v2» (раздел 7): пастель только для данных, ядро палитры сохраняется, primary #24658A остаётся. Рекомендуется. Альтернатива: усилить пастель в поверхностях (hero входа, фон stat tile) — потребует пересчёта контрастов и переоткрытия D01.
- DS-02. Шрифты Golos Text + Manrope (OFL, variable, self-hosted) вместо Ubuntu/Arsenal из design-plan v1. Рекомендуется. Альтернатива: Ubuntu Sans variable.
- DS-03. Логотип: линейный знак «волна сквозь кадр» для шапки, входа и писем; плашка v1 остаётся favicon/app-icon. Рекомендуется. Альтернатива: 2–3 варианта знака в playground перед U3.
- DS-04. Wordmark «Море фото» (кириллица, «фото» синим) в кабинете и галерее, «MoreFoto» только в служебных местах. Рекомендуется.
- DS-05. Тёмная тема — только «дверь» (пустой блок значений). Рекомендуется.
- DS-06. Радиус полей и кнопок 4 → 8 и рамка поля opacity 1 как единственные видимые изменения U2. Рекомендуется (устраняет нарушение 1.4.11).
- DS-07. App-bar 72 → 64/56, выход переезжает в user-menu с аватаром. Рекомендуется.
- DS-08. Онбординг по персональной ссылке-приглашению (10.1); открытая регистрация в UI не показывается; существование email не раскрывается. Рекомендуется. Альтернативы: код по email (UI над существующим backend) или временный пароль, показанный организатору один раз.
- DS-09. Аватары: `mf_staff_avatar` + приватные файлы + защищённый endpoint; загружают и сотрудник, и организатор; чтение — любой enabled staff; лимиты 5 МиБ / 25 МП / минимум 64×64; варианты 64 и 256. Рекомендуется.
- DS-10. Канонические термины ролей: «Ответственный группы» (teacher) и «Руководитель учреждения» (head). Требует подтверждения с заказчиком.
- DS-11. Имена и контакты ответственных в ORG-04 (`curatorName`, `headName`, контакт куратора для teacher) — backend-срез внутри U7. Рекомендуется.
- DS-12. Контакт организатора для профиля и «Доступ не назначен» — из конфигурации `morefoto.access` (`support: {name, email, phone}`), не из справочника сотрудников. Рекомендуется.
- DS-13. Нумерация: направление U и расширение валидатора; B3/B4 в направлении B. Рекомендуется. Альтернатива: серия `codex/design-*` без графа.
- DS-14. Head получает раздел «Списки сотрудников» в режиме чтения. Требует подтверждения.
- DS-15. Несколько сессий на пользователя и продление токена (сейчас один токен, 24 ч): не входит в план, отдельный issue. Требует подтверждения.
- DS-16. Капча GeeTest для кабинета остаётся выключенной; UI её не рендерит. Требует подтверждения.
- DS-17. Серверные сводки размещаются как `meta.summary`/поля в существующих списках у модулей-владельцев (U5), а не единый `GET /api/v1/cabinet/summary`. Рекомендуется.
- DS-18. Письма: расширение `EmailNotificationInputDto` полем `bodyHtml` и перевод письма кода регистрации на бренд «Море фото» в B4. Рекомендуется.
- DS-19. D01 закрывается утверждением раздела 7, D08 — раздела 10.1; записи вносятся в `../MoreFoto/docs/frontend/business-review/decisions.md` вместе с U1.
- DS-20. Ранний нефинансовый срез инфографики (U5/U6) выполняется до N1 и регистрируется в графе как отдельные волны; N1 из PR #36 сохраняет финансовые виджеты и наследует компоненты U6. Требует подтверждения.

## 14. Риски и ограничения

- Визуальная регрессия UI01–UI04 при замене базового scss — снимается скриншотной регрессией контрольных экранов (U1 — без diff; U2 — только три ожидаемых отличия) и live E2E computed-проверками токенов размеров.
- Demo-режим и Cucumber-регресс зависят от demo-экранов и классов `.mf-skip`, `.mf-back`, `.mf-ui-overlay`; удаление наследия и переименования сохраняют demo зелёным.
- Письма-приглашения зависят от H1 и расширения контракта на HTML; при отказе от `bodyHtml` v1 отправляется plain text со ссылкой без потери сценария.
- Утечка существования email — единые ответы 202 и `INVALID_CREDENTIALS`, статические подсказки.
- Загрузка аватарок — новый multipart-путь: лимиты, EXIF-strip, приватные файлы, защищённая выдача; публичный `/upload/` не используется.
- Шрифты: два семейства, preload только основного начертания, `size-adjust` fallback против CLS; лицензии в репозитории.
- Объём дизайн-долга (189 hex в 47 файлах, 75 мёртвых файлов): волны нарезаны ≤6k строк, каждая со своей E2E и визуальной проверкой; при превышении волна делится, а не растёт.
- Серверные сводки затрагивают пять модулей: U5 держится на одном шаблоне COUNT GROUP BY с областью видимости, без нового SQL-слоя; при росте объёма делится по направлениям (B/C/D/E/F).
- Соседний проект обновляется отдельными коммитами; расхождение design-plan v1 и этого плана до U1 отмечено ссылкой в разделе 5.
- Полный `make test-e2e` и визуальная проверка — один раз после всех волн, до merge единого PR; до этого только быстрые проверки на каждом коммите. Поздно найденная регрессия локализуется `git bisect` по зелёным коммитам-волнам; E2E-спеки пишутся в своих волнах, а не в конце.
- Размер единого PR (≈35–40 тыс. строк, из них ≈13 тыс. удаления наследия) усложняет ревью — коммит на волну, таблица коммитов с фокусом ревью, B4 отдельной серией.
- Долгоживущая ветка конфликтует с параллельными PR (#47 и др.) — регулярный rebase на `main`, повтор быстрых проверок после rebase.
- Доставка пользователям, включая сценарий паролей B4, — только после финального gate всей программы.

## 15. Чек-лист

- [x] Прочитать инструкции, ветку, дизайн-документы соседнего проекта и код кабинета.
- [x] Провести аудиты: визуал, онбординг, экраны, данные инфографики, аватарки, наследие.
- [x] Панель дизайн-направлений, судьи, синтез токен-системы с контрастами.
- [x] Спроектировать UX доступа, профиль, каркас, систему состояний, инфографику.
- [x] Разбить реализацию на волны с зависимостями, объёмами и проверками.
- [x] Записать `plan.md` и `progress.md`.
- [x] Прогнать проверки PR (тест-кейсы DP-01…DP-05) и зафиксировать результат в `progress.md`.
- [x] Commit, push, PR в `main` без merge.
- [x] Получить решения DS-01…DS-20 от пользователя (2026-09-23: все рекомендации плана).
- [x] Выбрать порядок доставки (2026-09-23: единый PR, волна — коммит, быстрые проверки на коммите, E2E в конце).
- [ ] Реализация в ветке `codex/u-design-system`; журнал — `docs/plans/U_design-system/progress.md`.

## 16. Критерии приёмки

1. План содержит цель, факты из кода с путями, scope и не-scope, зависимости, решения, риски, checklist, критерии приёмки и нумерованные тест-кейсы.
2. Токен-система задана конкретными значениями (hex, px, ms, веса) с рассчитанными контрастами и правилами применения; указано соответствие Vuetify и порядок миграции hardcoded значений.
3. Логотип описан конструктивно (геометрия, лок-апы, минимальные размеры, цвета) так, что его можно отрисовать в SVG без дополнительных решений.
4. Аватарки: компонент, правило инициалов и цвета, backend-архитектура с endpoint'ами, DTO и хранением.
5. Сценарий доступа закрывает разрыв «логин есть — пароля нет»: приглашение, установка пароля, восстановление, смена, коды ошибок, отзыв сессии.
6. Инфографика привязана к управленческим вопросам и live-данным; для каждого элемента указана серверная сводка или её отсутствие; финансы — «недоступно».
7. Волны независимы, каждая с dependsOn на слитые волны, объёмом ≤6k строк, входом/выходом и проверками; предложена и обоснована нумерация.
8. PR содержит только `docs/plans/design-ux-plan/plan.md` и `progress.md`; граф, код и соседний проект не изменены.

## 17. Тест-кейсы

Тест-кейсы этого PR (документы):

1. DP-01. Предусловие: ветка `codex/design-ux-plan`. Действие: `git diff --stat main` . Ожидание: изменены только `docs/plans/design-ux-plan/plan.md` и `progress.md`. Команда: `git diff --stat main --`.
2. DP-02. Действие: проверить, что ссылки на существующие файлы репозитория и соседнего проекта в плане верны. Ожидание: все пути из разделов 2, 5, 6 присутствуют в `main` или `../MoreFoto`; будущие файлы волн (`tokens.ts`, `_tokens.scss`, `MfLogo.vue`, `statusTone.ts` и т. п.) в проверку не входят. Команда: скрипт по путям в обратных кавычках с `os.path.exists` (описан в `progress.md`).
3. DP-03. Действие: проверить отсутствие секретов и персональных данных. Ожидание: нет токенов, паролей, реальных email кроме тестовых `example.invalid`. Команда: `grep -nE "password=|token=|@(gmail|mail|yandex)\." docs/plans/design-ux-plan/*.md | grep -v DP-03` пусто.
4. DP-04. Действие: валидатор графа на неизменённом графе. Ожидание: зелёный, `endpointCount` 99. Команда: `python3 tools/verify-wave-graph.py docs/waves/graph.json`.
5. DP-05. Действие: markdown-таблицы и заголовки корректны. Ожидание: каждый `|`-ряд таблиц имеет одинаковое число колонок в пределах таблицы. Команда: скрипт проверки таблиц в `progress.md`.

Ожидаемые проверки волн (стабильные ID; результаты фиксируются в `docs/plans/U_design-system/progress.md`; unit и статические проверки — на коммите волны, браузерные и визуальные — в финальном gate после всех волн):

6. DX-U1-01. Предусловие: U1 собрана. Действие: `npm run build` до/после. Ожидание: главный чанк уменьшился не менее чем на 3 МБ raw, в `dist` нет `materialdesignicons-*` изменений, tabler отсутствует. Команда: `du -b frontend/dist/assets/*.js`.
7. DX-U1-02. Действие: скриншоты входа, каталога, сотрудников, заказов, ссылок на 1440 и 390. Ожидание: pixel-diff ≤0,1 %. Команда: `npm run test:e2e:live` (визуальные шаги) и сравнение с эталонами e4/e5/d3/f2.
8. DX-U1-03. Действие: `python3 tools/verify-wave-graph.py docs/waves/graph.json` после регистрации направления U. Ожидание: DAG валиден, 99 ID сохранены, U1–U8/B3/B4 присутствуют.
9. DX-U2-01. Действие: `npm run check`. Ожидание: `tokens.test.mjs` подтверждает актуальность `_tokens.scss`, stylelint без ошибок (warning допустимы).
10. DX-U2-02. Действие: live E2E читает computed-переменные. Ожидание: `--mf-control-height 48px`, `--mf-control-compact 40px`, `--mf-touch-size 44px`, `--mf-focus-width 2px`, рамка поля `4px` → `8px`, `--v-field-border-opacity 1`.
11. DX-U2-03. Действие: Lighthouse/визуальная проверка входа на 390. Ожидание: шрифты Golos/Manrope загружены из `public/fonts`, CLS <0,1, внешних запросов шрифтов нет.
12. DX-U3-01. Действие: `node --test frontend/tests/avatar/initials.test.mjs`. Ожидание: «Иванова Мария Сергеевна» → «ИМ», «Мария Иванова» → «МИ», «Анна-Мария Петрова» → «АП», «ivan@example.invalid» → «IV», пусто → «•»; `avatarTone(id)` стабилен.
13. DX-U3-02. Действие: live E2E по 4 ролям на 390/768/1280/1440. Ожидание: логотип в шапке, user-menu с инициалами открывает профиль и выход, хлебные крошки на странице съёмки, вкладки контекста, `document.title` меняется, страницы 404/доступ внутри каркаса.
14. DX-U4-01. Действие: unit `statusTone`. Ожидание: каждый доменный статус (accountStatus, group.state, payment, production, request.status, link state) имеет tone и текст; неизвестный статус → neutral.
15. DX-U4-02. Действие: `make test-e2e`. Ожидание: 73/73 текущих сценариев плюс новые; ни одного hex в модулях staff/orders/photos/handoff/curator (stylelint error).
16. DX-B4-01. Действие: организатор создаёт сотрудника в disposable-стенде. Ожидание: запись в очереди H1 с consumer `access-invite`, письмо содержит ссылку `/access/invite/<token>`, в списке статус «приглашение отправлено».
17. DX-B4-02. Действие: открыть ссылку, задать пароль. Ожидание: `ACTIVE=Y`, pending 0, автоматический вход, экран приветствия; повторное открытие ссылки → 410 `INVITATION_USED`.
18. DX-B4-03. Действие: вход pending-сотрудника с любым паролем. Ожидание: 401 `INVALID_CREDENTIALS`, русский текст, статические ссылки под формой.
19. DX-B4-04. Действие: «Забыли пароль» для существующего и несуществующего адреса. Ожидание: одинаковый 202 и текст; письмо только существующему; ссылка одноразовая, TTL 60 мин; после смены — прежняя сессия получает `SESSION_REVOKED`.
20. DX-B4-05. Действие: смена email организатором у активного сотрудника. Ожидание: `/login?reason=revoked` с текстом об изменении данных; письма на старый и новый адрес.
21. DX-B3-01. Действие: organizer `PUT /api/v1/users/{id}/avatar` multipart PNG 1200×800. Ожидание: 200 с `avatar.version=1`, файлы 256/64 WebP без EXIF, `<img>` в списке и sidebar; повтор того же файла — версия не растёт.
22. DX-B3-02. Действие: teacher `PUT` чужого аватара; `GET` с `If-None-Match`. Ожидание: 403; 304 без тела; `DELETE` → 204 и буквы в UI.
23. DX-U5-01. Действие: PHPUnit сводок для curator с одним учреждением. Ожидание: `meta.summary` учитывает только его область; organizer видит всё; запросов COUNT — по одному на сводку.
24. DX-U6-01. Действие: live E2E обзора по ролям. Ожидание: organizer/curator — 4 плитки INF-01 и очередь; teacher — карточки групп с countdown; финансовые плитки в состоянии «недоступно» без нулей; sr-only таблицы присутствуют; при `prefers-reduced-motion` анимаций нет.
25. DX-U7-01 / DX-U8-01. Действие: live E2E затронутых экранов и визуальная проверка desktop/mobile. Ожидание: сценарии из `frontend/e2e/live/*.spec.ts` зелёные, новые состояния и статусы соответствуют разделам 10.4–10.5.

Команды быстрых проверок (на каждом коммите волны): `docker run --rm --network none -v /home/user/rabit-api/frontend:/app -v rabit-e5-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`; backend — `make check` и `make test-unit`. Полный gate — один раз после всех волн: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.
