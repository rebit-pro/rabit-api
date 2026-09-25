# Дизайн-система и UX кабинета MoreFoto — реализация

Статус: в работе с 2026-09-23. Ветка `codex/u-design-system` от `main` `bb35665` (merge PR #50 с утверждённым планом). Единый PR в `main` открывается после первой волны; merge — только после финального gate и ревью.

## 1. Цель и контекст

Реализовать утверждённый план [docs/plans/design-ux-plan/plan.md](../design-ux-plan/plan.md) — все волны U1–U8, B3, B4 — одним PR. Бриф, дизайн-решения, токены, логотип, аватарки, инфографика и UX доступа описаны в мастер-плане; этот документ фиксирует порядок исполнения, отклонения от мастер-плана, проверки и критерии готовности.

Решения DS-01…DS-20 приняты пользователем 2026-09-23 — все рекомендации мастер-плана (раздел 13): термины ролей по плану (DS-10), head читает «Списки сотрудников» (DS-14), одна сессия без продления (DS-15), капча выключена (DS-16), ранний нефинансовый срез U5/U6 выполняется (DS-20).

## 2. Порядок доставки

Решение пользователя 2026-09-23 — исключение из правила «одна волна — один PR» только для этой программы:

- Одна ветка и один PR. Волна — коммит или короткая серия коммитов в порядке U1 → U2 → U3 → U4 → B4 → B3 → U5 → U6 → U7 → U8. B4 идёт после U4, чтобы экраны доступа сразу строились на новых компонентах.
- На каждом коммите — только быстрые проверки: frontend `npm run check`, `npm run test:commerce` и unit-тесты волны, `npm run build`; backend — php-cs-fixer по изменённым файлам, PHPStan, PHPUnit. Каждый коммит зелёный и пригоден для `git bisect`.
- E2E-спеки пишутся в своей волне, запускаются один раз после всех волн вместе с полным `make test-e2e`, demo Cucumber и визуальной проверкой desktop/mobile. Стенды E2E по ходу волн не поднимаются.
- Самоконтроль вёрстки по ходу — скриншоты без backend (demo-режим, Vite с заглушками `page.route`); они не заменяют E2E.
- Ветка регулярно ребейзится на `main`; после rebase повторяются быстрые проверки затронутых волн.
- Перед каждой волной этот план дополняется деталями волны (фактами из кода, границами, тест-кейсами), затем пишется код.

## 3. Состав по волнам

| Волна | Содержание (мастер-план) | Коммиты | Проверки на коммите | В финальном gate |
| --- | --- | --- | --- | --- |
| U1 | Очистка наследия frontend (раздел 11), регистрация направления U и пакета доставки в графе (DS-13) | U1a frontend, U1b граф | check, commerce, build, размер бандла, diff CSS, валидатор графа | demo Cucumber, live E2E без изменений |
| U2 | Токены, шрифты, тема, гейты, playground (7.2–7.7) и замена глобальных стилей Berry | 1–2 | check, `tokens.test.mjs`, build, скриншоты без backend | computed-токены, шрифты, визуальная проверка |
| U3 | Логотип, каркас, навигация, `MfAvatar` на инициалах (8.1, 8.2, 10.3) | 1–2 | check, unit инициалов/тона, build | навигация 4 ролей на 390/768/1280/1440 |
| U4 | Поверхности, `MfStatus`, состояния, миграция hex, `@mdi/js` (10.4) | серия по модулям | check, unit `statusTone`, stylelint, build | экраны модулей, визуальная проверка |
| B4 | Приглашения, первый вход, пароли (10.1) | серия: backend, письма, frontend | php-cs-fixer, PHPStan, PHPUnit, check, build | сценарий приглашения, сброса и смены пароля |
| B3 | Аватары с фото (8.3) | backend, frontend | PHPStan, PHPUnit, check, build | `avatar.spec.ts`, 304, 403 |
| U5 | Серверные сводки (9.5) | по модулям | PHPStan, PHPUnit области видимости | HTTP-контракты сводок |
| U6 | Обзор по ролям и виджеты (9.2–9.4) | 1–2 | check, unit порогов и палитры, build | обзор по ролям, reduced motion |
| U7 | Учреждения, съёмки, фото, каталог, условия (10.5) | по экранам | check, build | live-спеки экранов |
| U8 | Ссылки, списки сотрудников, заказы, сотрудники (10.5) | по экранам | check, build | live-спеки экранов |

## 4. Отклонения от мастер-плана

- Граница U1/U2 (2026-09-23). Глобальные переопределения Berry, которые действуют на живые экраны, переходят из U1 в U2 и заменяются стилями на токенах: `scss/_override.scss` (`.v-row + .v-row`, `.v-divider`, `.bg-success`, `.v-selection-control`), `scss/layout/_container.scss` (`html { overflow-y: auto }`, поля `.v-main` на ≤1279px), `scss/components/_VButtons`, `_VCard`, `_VField`, `_VInput`, `_VTextField`, `_VTextarea`, `_VNavigationDrawer`. U1 удаляет только стили, селекторы которых не встречаются в живом DOM. Причина: U1 остаётся без визуальных изменений по построению, а сравнение «до/после» заменяется сравнением собранного CSS.
- DX-U1-02 (скриншоты «до/после») выполнен на стенде без backend в demo-режиме и дополнен DX-U1-04 (diff собранного CSS): из бандла уходят только правила с мёртвыми селекторами.
- В U1 дополнительно удаляются зависимости без импортов: `vee-validate` (только мёртвый `AuthRegister`), `date-fns`, `vite-plugin-vue-devtools`, `sass-loader`, `vue-cli-plugin-vuetify` — меньше `npm ci` и lockfile.
- Граф (DS-13): волны U1–U8, B3, B4 регистрируются в U1 с общим пакетом доставки `design-ux`; валидатор допускает зависимость внутри пакета для inProgress/review. Endpoint ID B3 (ACC-07…ACC-10) и B4 (AUTH-05…AUTH-09, ACC-11) назначаются коммитами B3/B4 вместе с контрактами в каноническом `endpoints.json`. Внешние изменения соседнего MoreFoto (без git) сохраняются патчем `docs/waves/design-ux/morefoto-contract.patch`.
- Слияние D3 (PR #46 `533c06b`, PR #49, PR #52) записывается в граф коммитом U1b по правилу «следующая волна фиксирует merge».

## 4.1. Детали U2

Факты из кода (2026-09-23):

- `scss/_variables.scss` настраивает только ядро Vuetify (`@use 'vuetify/styles' with (...)`: `$rounded`, `$typography`, `$body-font-family: Roboto`); стили компонентов Vuetify подключаются уже собранными (`vite-plugin-vuetify`, `styles: true`), их SASS-переменные не настраиваются, и мастер-план отверг `styles.configFile`. Классы `rounded-*`, `text-h*`, `text-*-emphasis` и `<v-row>`/`<v-divider>` в живом коде не используются.
- Vuetify 3.10 по умолчанию делает кнопки `text-transform: uppercase; letter-spacing: .089em`, `.v-card { overflow: hidden }`, `.v-card-text { padding: 1rem }`, контур поля `currentColor`; радиус контура поля наследуется от `.v-field`. Эти значения скрывали глобальные файлы Berry; в оверлеях вне `.morefoto-app` они действуют до сих пор.
- Размеры UI01–UI04 заданы на `.morefoto-app` в `styles/_morefoto-ui.scss`; радиус поля и кнопки 4px, рамка поля `opacity: .65` (2,21:1), шрифт Arial в `styles/morefoto.scss:4` и `.mf-ui-overlay`.
- Demo Cucumber проверяет радиус контура `4px` (`e2e/steps/ui/fields.steps.ts:40`).

Решения:

- Источник — `src/theme/tokens.ts`; генератор `scripts/tokens-build.mjs` (`npm run tokens:build`) пишет `src/styles/_tokens.scss`; `tests/tokens/tokens.test.mjs` (`npm run test:tokens`, входит в `npm run check`) проверяет актуальность файла, ссылки семантики на примитивы и контрасты раздела 7.6 мастер-плана.
- Каталог `src/scss/` удаляется целиком: настройка ядра Vuetify переезжает в `src/styles/vuetify.scss` (шрифт через `var(--mf-font-sans)`, `$rounded` xs 4 / sm 8 / md 12 / lg 16 / xl 24, без uppercase), глобальные переопределения Berry с живым эффектом заменяются `src/styles/_base.scss` на токенах: `html { overflow-y: auto }`, `.v-btn` без капса, `.v-card` с `overflow: visible` и отступами `--mf-panel-pad`, контур поля `border-strong`, `.v-divider` цвета `border`, трекинг Material обнулён, скрытие scrim при закрытии drawer. Правила Berry без живого эффекта (`.v-row + .v-row`, `.bg-success`, `.v-selection-control`, высоты 51/56, иконки кнопок +6px, `.v-label` 0.975rem) не переносятся. Поля `.v-main` 10px на ≤1279px не переносятся: отступы задаёт `.mf-main`.
- Шрифты: Golos Text (UI) и Manrope (заголовки, wordmark), variable, subset латиница + кириллица + ₽ + № + типографская пунктуация, woff2 48,8 и 30,8 КБ. Отклонение от мастер-плана: файлы в `src/assets/fonts/`, а не `public/fonts/` — Vite выдаёт хэшированные имена под `location /assets/` с `Cache-Control: public, immutable`, preload в `index.html` получает тот же URL. Запасные `Golos Text Fallback` (Arial, `size-adjust` 111,34 %) и `Manrope Fallback` против сдвига вёрстки. Лицензии OFL рядом со шрифтами.
- `MoreFotoTheme` из токенов; `variables`: `border-color` ink-150, `border-opacity` 1, `high-emphasis-opacity` 1, `hover-opacity` .06, `focus-opacity` .1. Отклонение: `medium-emphasis-opacity` остаётся по умолчанию до U4, где вторичный текст переводится на `text-secondary`; `info` темы остаётся sea-600 до `MfStatus`/`MfNotice` в U4.
- `_morefoto-ui.scss`: радиусы поля и кнопки — alias `--mf-radius-sm`, рамка поля `opacity: 1` (.65 только у disabled), подпись поля `text-secondary`, фокус `--mf-color-focus`, шрифт оверлеев `--mf-font-sans`. Строки размеров UI01–UI04 не меняются.
- Dark theme: только комментарий-дверь в сгенерированном файле; Vuetify-тема не регистрируется (без мёртвого кода). Stylelint переносится в U4, где hex-гейт включается как error вместе с миграцией hex.
- Playground `/demo/ui`: разделы «Токены» (цвета с контрастом, радиусы, тени) и «Типографика».

Тест-кейсы U2:

- DX-U2-01. `npm run check` (включая `test:tokens`) — актуальность `_tokens.scss`, контрасты AA/AAA по разделу 7.6.
- DX-U2-02 (финальный gate). Live-спека `e2e/live/design-tokens.spec.ts` на странице входа: `--mf-control-height` 48px, `--mf-control-compact` 40px, `--mf-touch-size` 44px, `--mf-focus-width` 2px, радиус контура поля 8px, `--v-field-border-opacity` 1, шрифт Golos Text загружен из `/assets/`.
- DX-U2-03. Скриншоты без backend до/после (10 экранов × 1440/390): видимые изменения — только шрифт, радиус 8, рамка поля, отступы карточек на mobile; проверка глазами и пиксельный diff.
- DX-U2-04. `npm run build`: шрифты в `dist/assets/` с хэшем, в `index.html` два preload с теми же URL.

## 4.2. Детали U3

Факты из кода (2026-09-23):

- `CabinetLayout.vue`: app-bar 72px с текстом «Море фото» (`.mf-brand`), подписью роли и отдельной кнопкой «Выйти»; drawer 248px с подписью «ЛИЧНЫЙ КАБИНЕТ», плоским списком (в live — до 7 пунктов, в demo — до 11) и блоком имени/email внизу. Навигация собирается в самом компоненте из роли, разрешений и режима.
- Вспомогательные страницы (`AccessPage`, `NotFoundPage`, `FeatureUnavailablePage`) живут в `BlankLayout` и сами рендерят `<main>`; `useRouteSeo` ставит заголовок «Раздел — MoreFoto»; вход — «Вход в MoreFoto» без знака; галерея — иконка v1 и «MoreFoto».
- От каркаса зависят селекторы live-спек и demo Cucumber: кнопка «Выйти» (8 мест), ссылка «Профиль» в drawer, «Заявки на списки сотрудников», заголовок «Вход в MoreFoto».

Решения:

- `src/components/brand/MfLogo.vue`: знак «волна сквозь кадр» (inline SVG по конструкции 8.1: рамка с разрывом справа, солнце, волна sea-600 с хвостами, толщина 2u до 32px и 1,5u от 48px) и wordmark «Море фото» HTML-текстом Manrope; варианты horizontal, compact (без левого хвоста и wordmark), stacked; `mono`; `role="img"`, `aria-label="Море фото"`. Favicon и app-icon остаются плашкой v1 (DS-03).
- `src/components/avatar/avatar.ts` — чистые функции инициалов и тона (fnv1a32 % 8) по 8.2; `MfAvatar.vue` (24/32/40/56/96, pending/expired/blocked, you, decorative) и `MfAvatarStack.vue`; unit-тест `tests/avatar/avatar.test.mjs` в `npm run check`.
- `src/modules/morefoto/layouts/navigation.ts` — чистая функция групп «Работа» и «Настройки» по роли, разрешениям и режиму, с unit-тестом; права и маршруты не меняются. Короткие подписи: «Списки сотрудников», «Сотрудники» (и в demo вместо «Пользователи»).
- Каркас: app-bar 64/56 с `MfLogo` (horizontal, на mobile compact + заголовок раздела), подпись роли на desktop, меню пользователя с `MfAvatar` 32 (имя, email, роль, «Профиль», «Выйти»); отдельная кнопка выхода убирается. Drawer: блок пользователя с `MfAvatar` 40 как ссылка на профиль, группы с подписями, пункт 44px radius-sm, активный — nav-active-bg и полоса 3px primary. Метки «Открыть меню» и «Основная навигация» сохраняются.
- Вспомогательные страницы для вошедшего сотрудника рендерятся в каркасе кабинета (`AuthAwareLayout`), для гостя и учётки без роли — в пустом layout с `<main>`.
- Вход: `MfLogo` stacked над панелью radius-lg на фоне sea-50 → surface, заголовок «Вход в кабинет». Ссылки «Забыли пароль?» и «Получили приглашение?» появятся в B4 вместе с маршрутами.
- Галерея: `MfLogo` horizontal 24 mono вместо иконки v1 и «MoreFoto». Заголовок вкладки — «Раздел — Море фото».
- Отклонения: хлебные крошки и вкладки контекста съёмки переносятся в U7 (живут в заголовках экранов, которые U7 переделывает); промежуточный `homePath` не вводится — U6 сразу переводит все роли на «Обзор», чтобы не править live-спеки дважды.

Тест-кейсы U3:

- DX-U3-01. `tests/avatar/avatar.test.mjs` и `tests/shell/navigation.test.mjs` (в `npm run check`): инициалы по 8.2 («Иванова Мария Сергеевна» → «ИМ», «Мария Иванова» → «МИ», «Анна-Мария Петрова» → «АП», email → «IV», пусто → «•»), стабильный тон; группы навигации по ролям.
- DX-U3-02 (финальный gate). `e2e/live/shell.spec.ts`: 4 роли на 1280, organizer на 390/768/1440 — логотип ведёт на стартовый экран, меню пользователя (профиль, выход), группы навигации, заголовок вкладки, 404 в каркасе.
- DX-U3-03. Скриншоты без backend: каркас desktop/mobile, вход, playground «Бренд».

## 4.3. Детали U4

Факты из кода (2026-09-23): 17 `v-chip` статусов с собственными цветами в 13 файлах (в том числе «заблокирован» чёрным `secondary`, статус заказа без цвета), статус-ячейка `UiDataTable` на своих hex; 87 `v-alert` (в основном tonal); `AdminDialog` в 42 местах, фиксированная ширина 880; ~180 hex в 46 файлах; `@mdi/font` целиком (woff2 403 КБ и CSS) при 57 используемых иконках, все имена статичные; `VDateInput` в Vuetify 3.10 ещё в labs; 9 нативных полей даты, E2E заполняет их ISO-строками.

Решения:

- U4a: `MfStatus` + словарь тонов `modules/morefoto/ui/statusTone.ts` (тон по домену, подписи остаются в модулях до U7/U8); `MfEmptyState`; тональные `v-alert` на токенах тонов глобально (без отдельного `MfNotice`); меню, `.mf-panel`, `AdminDialog` (sm/md/lg, лист снизу на ≤600px) и scrim на токенах. Поле даты и единый форматтер дат откладываются до экранов U7/U8.
- U4b: все hex вне `tokens.ts`/`_tokens.scss` заменяются семантическими токенами по соответствию 7.7 мастер-плана; stylelint (`color-no-hex`, запрет примитивов вне токенов, аватара и `components/viz`) и ESLint-правило против hex-строк в коде включаются как error и входят в `npm run check`.
- U4c: иконки — реестр путей `@mdi/js` для используемых имён `mdi-*` и собственный SVG-набор Vuetify; `@mdi/font` удаляется; тест сверяет, что каждое имя `mdi-*` в `src` есть в реестре.

Тест-кейсы U4: DX-U4-01 (тоны статусов, unit), DX-U4-02 (финальный gate: `make test-e2e` и визуальная проверка), DX-U4-03 (`npm run check` со stylelint/ESLint без hex), DX-U4-04 (реестр иконок полон, `materialdesignicons` нет в сборке).

## 4.4. Детали B4

Факты из кода (2026-09-23):

- Сессия одна на пользователя: токен и срок в `b_uts_user` (`UF_TOKEN`, `UF_TOKEN_EXPIRES_AT`), отзыв — `UserRepository::clearToken`; вход — `password_verify` по `b_user.PASSWORD`; пароль ставится через `CUser::Update`. `TokenResolver` отвечает 401 `Unauthorized` на неизвестный токен и `Token expired` на истёкший; фронтенд любое 401 показывает как «Сессия истекла». `LoginUseCase` отвечает `Invalid credentials` по-английски, pending-учётка — тем же 401.
- `StaffIdentityGateway::createPending` создаёт неактивного пользователя со случайным паролем, письма нет. Машинные коды в проекте — строка `HttpException` (`STAFF_NOT_FOUND`, `EMAIL_OCCUPIED`), тексты — на фронтенде.
- H1: `EmailNotificationInterface::queue()` пишет операцию в `b_rebit_notification_operation` и публикует в RabbitMQ; `BitrixEmailTransport` отправляет событие `REBIT_NOTIFICATION_OUTGOING_EMAIL` с `BODY = nl2br(htmlspecialchars(body))` — только текст.
- `EmailNotificationInputDto` валидирует поля в конструкторе — существующий долг против правила «DTO без валидации»; новое поле `bodyHtml` добавляется без валидации в DTO.

Решения:

- Таблица `rebit_auth_access_link` (`ID`, `USER_ID`, `PURPOSE` invite|reset, `TOKEN_HASH` sha256 hex, `ISSUED_AT`, `EXPIRES_AT`, `RESEND_AVAILABLE_AT`, `USED_AT`, `ISSUED_BY`), уникальность (`USER_ID`, `PURPOSE`) — новая ссылка заменяет прежнюю, уникальность `TOKEN_HASH`. Токен — 32 случайных байта base64url, в БД только sha256. TTL: приглашение 7 дней, сброс 60 минут, повторная отправка через 60 секунд (env `REBIT_AUTH_INVITE_TTL_HOURS`, `REBIT_AUTH_RESET_TTL_MINUTES`, `REBIT_AUTH_LINK_COOLDOWN_SECONDS`).
- `rebit.auth`, слой `Access`: доменная сущность ссылки и политика пароля (≥10 символов, не равен email); порты репозитория ссылок, писем и учётных данных; UseCase `IssueAccessInvitationUseCase`, `GetAccessInvitationUseCase`, `AcceptAccessInvitationUseCase`, `RequestPasswordResetUseCase`, `ConfirmPasswordResetUseCase`, `ChangePasswordUseCase` с русским phpDoc; инфраструктура — SQL-репозиторий, почтальон через H1 (текст + HTML с кнопкой, бренд и адрес кабинета из env `REBIT_AUTH_BRAND_NAME`, `REBIT_AUTH_APP_URL`).
- Маршруты: AUTH-05 `GET /api/v1/auth/invitations/{token}`, AUTH-06 `POST /api/v1/auth/invitations/{token}/accept` (вход сразу после установки пароля), AUTH-07 `POST /api/v1/auth/password-resets` (всегда 202 без тела; для pending-учётки уходит приглашение, для неизвестного адреса — ничего), AUTH-08 `POST /api/v1/auth/password-resets/{token}/confirm` (отзыв сессий и новый вход), AUTH-09 `PATCH /api/v1/me/password` (204, сессия сохраняется). Пустые ответы — общий `EmptyResponse` из `noContent()`/`created()`/`accepted()`: concrete controller не собирает массив ответа и не видит типов Bitrix.
- Коды: `INVALID_CREDENTIALS`, `TOKEN_EXPIRED`, `SESSION_REVOKED`, `LINK_NOT_FOUND`, `LINK_EXPIRED`, `LINK_USED`, `PASSWORD_WEAK`, `CURRENT_PASSWORD_INVALID`, `RATE_LIMITED`, `INVITATION_NOT_AVAILABLE`; тексты — словарь `frontend/src/api/authErrors.ts`. Перехватчик 401 ведёт на `/login?reason=expired|revoked`.
- H1: `EmailNotificationInputDto::bodyHtml`, колонка `BODY_HTML` в очереди, хэш полезной нагрузки учитывает HTML, транспорт отправляет HTML как есть, если он есть (HTML строит только наш код, данные экранируются).
- `morefoto.access`: `StaffIdentityGatewayInterface::issueInvitation()` и `invitations()`; `SaveStaffUseCase` выпускает приглашение новому pending-сотруднику и перевыпускает при смене email до активации; ACC-11 `POST /api/v1/users/{user_id}/invitations` (повтор с cooldown); `StaffOutputDto::invitation` (`sentAt`, `expiresAt`, `state` sent|expired|accepted); фильтр `accountStatus` (pending|active|blocked) в `GET /api/v1/users` (F11 раздела 6.2).
- Frontend: `/access/invite/:token`, `/access/recover`, `/access/reset/:token` в стиле входа; ссылки под формой входа; смена пароля в профиле; статус, фильтр и повтор приглашения у сотрудника; текст формы сотрудника про письмо. После принятия приглашения — экран `/cabinet/welcome` «Что дальше»: роль, разделы кабинета из той же `cabinetNavigation`, ссылки «Начать работу» (домашний раздел; после U6 — обзор) и «Профиль». В demo-режиме письма не отправляются — экраны показывают пояснение.
- Отличия от мастер-плана: таблица `rebit_auth_access_link` вместо `rebit_auth_invitation` (в ней и сброс); общие коды `LINK_*` вместо `INVITATION_*`/`RESET_EXPIRED` (экран различает назначение ссылки сам); `ACCOUNT_BLOCKED` не вводится — заблокированная учётка при входе получает `INVALID_CREDENTIALS`, чтобы не раскрывать состояние; счётчик попыток ссылки сброса не нужен — токен 256 бит, одноразовый, TTL 60 минут, перебор невозможен.
- Совместимость с регистрацией по коду (backend-возможность остаётся): устаревшая ссылка приглашения не действует для учётки, уже активированной кодом (`LINK_USED`), а код регистрации не активирует учётку после принятия приглашения — `RegistrationSafetyTest` расширяется этим сценарием.
- Не входит: письма на старый и новый адрес при смене email активного сотрудника (сессии уже отзываются), капча (DS-16), несколько сессий (DS-15), перебрендирование письма кода регистрации (UI регистрации по коду скрыт; остаётся follow-up), отзыв живой ссылки приглашения при отключении доступа pending-сотрудника (принятие такой ссылки даёт учётку без назначений и прав; повторная отправка для отключённого запрещена — follow-up).

Тест-кейсы B4:

- DX-B4-U1 (PHPUnit). UseCase: выпуск (хэш вместо токена, cooldown → `RATE_LIMITED`, не pending → `INVITATION_NOT_AVAILABLE`), просмотр (маска email, `LINK_EXPIRED`, `LINK_USED`), принятие (слабый пароль, активация, одноразовость, выдача сессии), сброс (202 для неизвестного, приглашение для pending, отзыв сессий), смена пароля (неверный текущий пароль); политика пароля; письмо с экранированием имени.
- DX-B4-U2 (PHPUnit). H1 с `bodyHtml`: хэш учитывает HTML, транспорт отправляет HTML без повторного экранирования.
- DX-B4-U3 (PHPUnit). `RegistrationSafetyTest`: устаревшая ссылка приглашения не меняет пароль и не выдаёт сессию учётке, активированной кодом (`LINK_USED`); обратный случай — существующий `already active`.
- DX-B4-U4 (PHPUnit). Архитектурная проверка чистых контроллеров `AccessLinkController`, `PasswordController`, `StaffInvitationController` (общий `CleanControllerSource` в тестах `rebit.share`) и канал логов.
- Выпуск приглашения из `SaveStaffUseCase`, фильтр `accountStatus` и экран «Что дальше» не имеют unit-обвязки (SQL-репозитории Bitrix, Vue-экран поверх уже покрытой `cabinetNavigation`) и проверяются в финальном gate.
- DX-B4-01…05 (финальный gate) — по разделу 17 мастер-плана, live-спека `zz-access` (приглашение → пароль → «Что дальше», вход pending, одинаковый ответ восстановления, сброс отзывает сессию, смена пароля, повтор приглашения, фильтр «Ожидает регистрации») и MySQL-верификатор `verify-access.php` (письмо сотрудника из B2 несёт живую ссылку, в БД только хэши).

## 4.5. Детали B3

Факты из кода (2026-09-23):

- Загрузка фото (`morefoto.media`): `MediaRequestFactory::upload` разбирает multipart поверх `HttpRequest` (legacy), `PhotoFileInspector` проверяет размер, MIME через `finfo`, размеры и sha256; `LocalPrivatePhotoStorage` пишет temp + rename с правами 0600/0700 в `MOREFOTO_PRIVATE_MEDIA_PATH` (по умолчанию `var/private/media`, на E2E-стенде `/runtime/private/media`); `GdPreviewRenderer` кодирует WebP через GD.
- `rebit.share`: `RequestFileToDtoMapper` (общая загрузка SHR-01) требует `moduleId` и не знает параметров маршрута; атрибуты `RouteParameter`/`RequestHeader` разбирает только `RequestToDtoMapper`. `PreviewResponse` отдаёт `no-store`, а `AuthenticatedApiJsonController::finalizeResponse` добавляет `no-store` каждому ответу.
- Профиль (`GetProfileUseCase` → `ProfileOutputDto`) и список сотрудников (`StaffManagementRepository::select()` → `StaffDirectoryUseCase`) не знают об аватарах; на frontend `MfAvatar` показывает только инициалы, в строках списка сотрудников аватара нет. `PhotoImage.vue` грузит защищённые превью через `api.get(..., blob)` и object URL.
- В PHP-образах cli/fpm (development и production) есть GD с WebP и `exif`; `upload_max_filesize` 200M.

Решения:

- Таблица `mf_staff_avatar` (`USER_ID` PK, `VERSION`, `FINGERPRINT` sha256 оригинала, `MIME`, `BYTES`, `WIDTH`, `HEIGHT`, `UPDATED_BY`, `UPDATED_AT`), миграция `Version20260923120003`, `down()` не удаляет непустую таблицу. Файлы `<MOREFOTO_AVATAR_PATH | MOREFOTO_PRIVATE_MEDIA_PATH/avatars>/<userId>/<version>-{256,64}.webp`, temp + rename, 0600/0700; nginx не меняется, отдаёт только API.
- `morefoto.access`, срез `Avatar`: доменная сущность `StaffAvatar` и `AvatarVariantEnum` (64, 256); порты репозитория, хранилища, инспектора и рендера; UseCase `SaveStaffAvatarUseCase`, `DeleteStaffAvatarUseCase`, `GetStaffAvatarUseCase` с русским phpDoc; stateless `AvatarOutputMapper` строит `thumbUrl`/`fullUrl` вида `/api/v1/users/{id}/avatar/64?v=N`; инфраструктура — SQL-репозиторий, локальное хранилище, инспектор (≤5 МиБ, ≤25 Мп, JPEG/PNG/WebP по содержимому, минимум 64×64) и GD-рендер (EXIF-ориентация 3/6/8, центральный квадрат, 256 и 64, WebP q82; перекодирование отбрасывает EXIF/GPS/ICC).
- Порядок записи: проверка и хэш → быстрый выход, если хэш совпал с текущим (версия не растёт) → рендер → транзакция с блокировкой строки профиля сотрудника (`FOR UPDATE`, сериализует загрузки одного сотрудника, не трогает глобальную блокировку доступа) → повторная сверка хэша → файлы новой версии → upsert строки → commit → удаление прежних версий. Сбой транзакции удаляет файлы новой версии. `UF_REVISION`/`UF_ACCESS_REVISION`, отзыв сессий и `mf_staff_operation` не затрагиваются.
- Права: свой аватар (`/me/avatar`) — любой сотрудник с включённым доступом; чужой (`/users/{user_id}/avatar`) — `staff.manage`, цель — любой сотрудник, включая pending и отключённых; чтение — любой сотрудник с включённым доступом, 404 `AVATAR_NOT_FOUND`, если аватара нет или `v` не совпадает с текущей версией. Ошибки файла — 422 `AVATAR_TOO_LARGE`, `AVATAR_TOO_SMALL` (меньше 64×64; в мастер-плане отдельного кода не было), `UNSUPPORTED_AVATAR_FORMAT`, `CORRUPTED_AVATAR`; ошибки multipart из общего маппера — 400 `MULTIPART_REQUIRED`, 422 `ONE_IMAGE_REQUIRED`, `IMAGE_UPLOAD_FAILED`, `UNKNOWN_FIELD`.
- Маршруты: ACC-07 `PUT /api/v1/me/avatar`, ACC-08 `DELETE /api/v1/me/avatar`, ACC-09 `PUT /api/v1/users/{user_id}/avatar`, ACC-10 `GET /api/v1/users/{user_id}/avatar/{variant}?v=N`, ACC-12 `DELETE /api/v1/users/{user_id}/avatar`. Отличие от мастер-плана: удаление чужого аватара получает свой ID ACC-12 — в реестре одна запись на метод и путь, а ACC-11 уже занят приглашением B4; `endpointCount` 105 → 110. PUT отвечает 200 `{userId, avatar: {version, thumbUrl, fullUrl}}`, DELETE — 204.
- Новый чистый `StaffAvatarController extends AuthenticatedApiJsonController`; `ProfileController`/`StaffController` не расширяются. `rebit.share`: `RequestImageDtoInterface` + `RequestImageToDtoMapper` (multipart, ровно один файл `file`, `UPLOAD_ERR_OK`, `is_uploaded_file`, параметры маршрута; PHP разбирает multipart только для POST, поэтому тело PUT читается `request_parse_body()` PHP 8.4 — проверено на встроенном сервере образа, ядро Bitrix тело не-JSON запросов не читает) в `MAPPER_CLASSES`, разбор атрибутов `RouteParameter`/`RequestHeader` выносится из `RequestToDtoMapper` в общий класс; `ImageContentOutputDto` и `ImageResponse` (ETag `"<userId>-<version>-<variant>"`, `Cache-Control: private, max-age=31536000, immutable`, `nosniff`, `no-referrer`, `If-None-Match` → 304 без тела); `finalizeResponse` не добавляет `no-store` к `ImageResponse`, `PreviewResponse` остаётся `no-store`.
- Поле `?avatar` в `ProfileOutputDto`, `StaffOutputDto`, `StaffDetailOutputDto` (`LEFT JOIN mf_staff_avatar`, столбец `AVATAR_VERSION`). Инициалы и тон по-прежнему считает клиент.
- Frontend: `useProtectedImage` выносится из `PhotoImage.vue` в `src/composables/`; `MfAvatar` получает `src` (фото поверх инициалов, при загрузке и ошибке — инициалы); фото в блоке пользователя, меню, строках списка сотрудников и профиле. Профиль: блок аватара 96 с действиями «Загрузить фото»/«Удалить» и проверкой размера до отправки; карточка сотрудника у организатора — те же действия для чужого аватара. `avatarApi.saveMine/removeMine/save/remove`; после смены своего аватара профиль перечитывается (`authApi.me()`, localStorage). В demo-режиме фото не загружаются — действия недоступны с пояснением.
- Не входит: обрезка на клиенте и выбор области кадра (квадрат по центру), аватары покупателей, чистка «осиротевших» файлов сверх удаления прежних версий.

Тест-кейсы B3:

- DX-B3-U1 (PHPUnit). Инспектор: размер, формат по содержимому, 64×64, 25 Мп; рендер: ориентация EXIF 6 поворачивает кадр, квадрат 256 и 64, WebP без чанка EXIF; UseCase: повтор того же файла не меняет версию, новый файл — версия +1 и удаление прежней, чужой аватар без `staff.manage` — 403, чтение с устаревшей `v` — 404.
- DX-B3-U2 (PHPUnit). `EntityTag` (сравнение `If-None-Match`: тег, список, `W/`, `*`, чужая версия и размер); `LocalAvatarStorage` (права 0600/0700, удаление прежних версий); граница `StaffAvatarController` (архитектурная проверка). Классы HTTP Bitrix в unit-тестах недоступны (заглушек `HttpResponse`/`HttpRequest` нет), поэтому `ImageResponse` и `RequestImageToDtoMapper` проверяются live-спекой: 304 без тела, заголовки кеша без `no-store`, 400 без multipart, 422 для текстового файла.
- DX-B3-01, DX-B3-02 (финальный gate) — live-спека `zz-avatar` (организатор загружает свой аватар в профиле → фото в блоке пользователя и списке, повтор без роста версии; аватар учителя из карточки; учитель получает 403 на чужой PUT; `If-None-Match` → 304; удаление → 204 и инициалы) и верификатор MySQL + FS `verify-avatar.php` (строка и файлы только текущей версии, WebP 256/64 без EXIF, после удаления нет ни строки, ни файлов).

## 4.6. Детали U5

Факты из кода (2026-09-23):

- Состояние группы считает `GroupCalendar::status()`: нет `sentAt` — preparing, `now >= closesAt` — closed, иначе open. `GroupDirectory::where()` фильтрует тем же правилом SQL (closed там без проверки `sentAt`). Страницы групп учреждения и съёмки (`StructureRepository`) уже считают `TOTAL` отдельным подзапросом `totals`.
- `ListGroupLinksUseCase` оценивает готовность только для групп текущей страницы (`GroupLinkReadiness` — пакетные запросы к медиа, продажам, назначениям и спискам); «подготовлено» = сохранённая подпись подготовки совпадает с текущей и проблем нет.
- Чистые контроллеры со `meta` из result-mapper: HND-01 `GroupLinkController`, HND-06 `StaffRequestController`, COM-12 `StaffOrderController`. Старые контроллеры с ручным разбором запроса: `StaffController` (ACC-02 собирает `meta` сам), `InstitutionController` (ORG-04 собирает `data` сам, `groups` передаёт страницей), `StructureController` и `MediaController` (отдают DTO целиком).
- Клиент загружает все страницы HND-01 и HND-06 (`handoff/service.ts`) и считает счётчики сам.

Решения:

- Общее SQL-правило состояния группы в `morefoto.organization` (`GroupStateSql`), совпадающее с `GroupCalendar::status()`; им пользуются `GroupDirectory` и все счётчики состояний. Закрытой считается только отправленная группа — как в PHP.
- HND-01 `meta.summary`: `referenceNow`, `byState {preparing, open, closed}`, `closingSoon` (открытые, закрываются в ближайшие 72 часа), `prepared` (готовящиеся группы с подтверждённой подготовкой: сохранённая подпись есть; актуальность подписи проверяет передача). Считается по всей области сотрудника с фильтрами учреждения и съёмки, без фильтра состояния — плитки сами служат фильтром. Справочник групп (`GroupDirectoryInterface`) получает `summary()` — один SELECT с условными суммами — и `ids()` готовящихся групп; handoff считает подтверждённые подготовки одним `COUNT` по своей таблице.
- ORG-04 и ORG-08: `groups.meta.summary.byState` — в том же подзапросе `totals`, что уже считает `TOTAL` страницы групп (отличие от мастер-плана: сводка лежит в `meta` страницы групп, поэтому старый `InstitutionController` не меняется).
- ORG-02: `shootCount`, `groupCount`, `openGroupCount` в `VisibleInstitutionOutputDto` — коррелированными подзапросами в том же SELECT страницы учреждений.
- MED-02: `stats {byStatus, unassigned}` в `PhotoPageOutputDto` — один `COUNT … GROUP BY` по съёмке и выбранной группе без фильтров «назначено» и «код ребёнка».
- COM-12: `meta.summary {total, byProductionStatus}` — один `COUNT … GROUP BY` с той же областью видимости и фильтрами поиска, кроме фильтра изготовления. `byPaymentStatus` не отдаётся до G1 (финансы не входят).
- ACC-02: `meta.summary {byAccountStatus, byRole}` — один SELECT с условными суммами по тем же фильтрам, кроме статуса и роли. Список сотрудников переезжает в новый чистый `StaffListController` (`StaffListRequestDto`, input- и result-mapper); `StaffController::listAction` и `StaffRequestFactory::listing` удаляются — правило чистых контроллеров не позволяет дописывать сводку в старый.
- HND-06: `meta.summary.byStatus` — один `COUNT … GROUP BY` с той же `visibility()`.
- Отличие от мастер-плана: клиентский пересчёт в `handoff/service.ts` удаляется в U8 вместе с экранами «Ссылки и сроки» и «Списки сотрудников», которые переходят на `meta.summary` и постраничную загрузку; U6 берёт сводки для обзора. INF-04 (`processingPhotos`, `unassignedPhotos` в HND-01) не делается — в мастер-плане он необязательный.
- Изменяемые UseCase без class-level phpDoc получают его в этой волне.

Тест-кейсы U5:

- DX-U5-U1 (PHPUnit). Для каждой сводки UseCase передаёт в хранилище ту же область видимости, что и для списка (curator/head — свои учреждения, teacher — свои группы, organizer — всё), и игнорирует фильтр, который сводка раскладывает на плитки; `GroupStateSql` согласован с `GroupCalendar::status()` на граничных моментах.
- DX-U5-01 (финальный gate). Live-спека `zzzzzzz-summaries`: у каждого эндпоинта сумма разбивки равна `total` без фильтра; у curator счётчики не больше, чем у organizer, и совпадают с его отфильтрованными списками.

## 4.7. Детали U6

Факты из кода (2026-09-23):

- `/cabinet/overview` в live показывает `ScopeOverview` поверх `useCabinetScope` (ORG-01 не реализован — в live экран сообщает, что кабинет не подключён), поэтому `homePath` ведёт организатора в каталог, остальных — в профиль.
- Токены диаграмм готовы (U2): `--mf-chart-{0…7}`/`-strong`, `chart-accent`, `chart-accent-2`, `chart-track`, `chart-grid`, пастели; при `prefers-reduced-motion` длительности `--mf-duration-*` равны 0.
- Экраны списков: заказы — `StaffOrdersLiveScreen` (COM-12 через `orders/live/api.ts`), сотрудники — `StaffManagementScreen` (ACC-02), списки сотрудников — `StaffRequestsScreen` поверх `loadHandoff()` (все страницы HND-06 в памяти).

Решения:

- `src/components/viz/`: `MfStatTile` (категория, число с единицей, подсказка в тоне, ссылка-фильтр, `variant="unavailable"` для финансов), `MfDistribution` (SVG-полоса с разделителями, легенда «● Подпись N · P %», sr-only таблица, `forced-colors` → штриховка), `MfTimeline` (передана → приём до → доставка до, маркер «сегодня», вертикальный на mobile), `MfQueue` (первые 5 строк и «Все»); `format.ts` (склонение числительных, проценты) и `countdown.ts` (правило 9.3.5). `MfProgress` и `MfRing` строятся в U7 вместе с экраном фотографий — единственным их live-потребителем; `MfMiniBar` не строится: временных рядов в live нет (отличие от мастер-плана).
- `modules/morefoto/ui/chartPalette.ts` — единственное соответствие категорий пастелям (индексы как у `MfAvatar`).
- Live «Обзор» (`modules/morefoto/overview/`): organizer/curator/head — 4 плитки INF-01 из `meta.summary` HND-01, очередь «Требует проверки» (первые 5 готовящихся групп из того же ответа), распределение групп по состояниям, три финансовые плитки «недоступно» (INF-07); teacher — карточки своих групп (INF-11: сроки на `MfTimeline` с countdown, состояние ссылки, «кадров N · детей M») и статусы своих списков сотрудников из `meta.summary` HND-06. Контакт куратора в карточке — после имён в ORG-04 (U7, DS-11). Demo-режим оставляет прежний обзор.
- `homePath` в live — `/cabinet/overview` для всех ролей сотрудников.
- Плитки над списками: INF-08 — статусы учётки (клик = фильтр) и распределение по ролям; INF-06 — «Заказов N» и распределение по изготовлению (оплата — после G1); INF-09 — плитки статусов списков (клик = фильтр), для teacher — плашки статусов.
- Счётчики в меню (organizer/curator): «Ссылки и сроки» — группы, ждущие проверки; «Списки сотрудников» — отправленные на проверку. Загружаются в каркасе при входе и не чаще раза в минуту при переходах.

Тест-кейсы U6:

- DX-U6-U1 (unit). `countdown`: >7 дн. neutral, 3–7 info, <3 warning, просрочено danger, не передана — «—» neutral; склонения; палитра категорий — уникальные индексы 0–7.
- DX-U6-01 (финальный gate). Live-спека `zzzzzzz-overview`: organizer — 4 плитки INF-01 совпадают с `meta.summary` HND-01, очередь и распределение с sr-only таблицей, финансовые плитки без цифр; teacher — карточки групп с countdown; при `reducedMotion: 'reduce'` переходы виджетов нулевые; плитки над списками сотрудников и заказов совпадают со сводками и фильтруют список.

## 4.8. Детали U7

Факты из кода (2026-09-23, после слияния `main` `3987390`):

- Живые экраны структуры: список учреждений и группы съёмки — `StructureScreen` + `StructureList` (карточки `structure-row`), карточка учреждения — `InstitutionScreen` (`InstitutionOverview`, `InstitutionCollection`). Навигация — ссылки «Навигация по структуре» и `← Съёмка` на экранах фото и условий; поиск учреждений только по кнопке «Найти»; пустой список — «Пока нет записей.» без действия; ответственные — «Сотрудник №12», «Заведующая», «Воспитатель назначен». Словарь ролей (`types.ts`) уже в терминах DS-10.
- Данные готовы: ORG-02 — `shootCount`/`groupCount`/`openGroupCount` (U5) и `curatorName`/`headName` (DS-11); ORG-04/ORG-08 — `groups.meta.summary.byState`; MED-02 — `stats` (U5) и `summary` группы (#60).
- Фотографии после #47/#60: страница группы, строка готовности `photo-readiness`, очередь с ручным стартом, паузой, продолжением и повтором (шесть сценариев `zz-media` и замер `zz-media-bench` нажимают «Загрузить на сервер»); «Предпросмотр» выключен без объяснения; `stats` не используется.
- Каталог: общие условия стоят над продукцией на одной странице; состояние продукции — текст. COM-01 не фильтрует и не ищет (только `page`, `pageSize`). Условия групп — выпадающий список групп и флажок «Наследовать общий прайс и предложения» в редакторе.

Решения:

- Структура. `MfBreadcrumbs` (`nav` «Хлебные крошки», `aria-current="page"`, усечение средних звеньев на 390px) заменяет ссылки «Навигация по структуре» и «← Съёмка». Вкладки контекста съёмки «Группы · Фотографии · Условия» (ссылки с `aria-current`) — на страницах съёмки, фото и условий; кнопка «Фотографии» в шапке съёмки уходит во вкладку. Карточка учреждения: имена и инициалы (`MfAvatar` 24) куратора и руководителя учреждения по DS-11, «не назначен» — если назначения нет; INF-02 — `MfDistribution` групп по состоянию приёма на карточке учреждения и на странице съёмки. Список учреждений: INF-12 «Съёмок N · Групп M», `MfStatus` «Приём открыт: K», имя куратора. Группы: «Ответственный группы назначен / не назначен» (DS-10), сроки — `MfTimeline` с countdown (INF-03) вместо списка дат. Пустые списки — `MfEmptyState` с действием для организатора («Новое учреждение», «Новая съёмка», «Новая группа») и пояснением для куратора и руководителя. Поиск учреждений запускается сам через 300 мс после ввода; кнопка «Найти» остаётся.
- Фотографии. INF-05 — панель «Готовность подборки» по `stats`: новый `MfProgress` «Обработано X из N» и плитки «Готово», «Обрабатывается», «Ошибка», «Повтор», «Без ребёнка»; строка `photo-readiness` сохраняется. Зона перетаскивания файлов добавляет их в ту же очередь, что и поле «Выбрать фотографии»; общий прогресс очереди «Загружено X из N»; список файлов сворачивается, когда все файлы прошли, и остаётся открытым, пока есть ошибка или ожидание (строкам с ошибкой нужно действие). У выключенного «Предпросмотра» — объяснение «Назначьте кадры ребёнку, чтобы включить предпросмотр».
- Каталог и условия. Вкладки «Продукция | Общие условия» (`?tab=conditions`, по умолчанию продукция), состояние продукции — `MfStatus` («В продаже» success, «Отключено» neutral). Условия группы в редакторе — сегмент «Общие условия / Собственные условия» (радиокнопки) вместо флажка; пустая съёмка — `MfEmptyState` со ссылкой на съёмку.
- DS-12. `support: {name, email, phone}` в профиле (ACC-01) из переменных `MOREFOTO_SUPPORT_NAME`, `MOREFOTO_SUPPORT_EMAIL`, `MOREFOTO_SUPPORT_PHONE` модуля `morefoto.access` (порт `SupportContactProviderInterface`, реализация из конфигурации); `null`, если все пусты. Профиль получает раздел «Помощь», экран «Недостаточно прав» — тот же контакт.
- Отличия от мастер-плана:
  - автостарт загрузки не делается — ручной старт, пауза и продолжение входят в контракт очереди #47 и в её сценарии;
  - sticky-панель назначения и бейдж кода остаются в виде #60: экран фото только что переработан, и повторная переделка раскладки не входит в срез;
  - поиск и фильтр «в продаже» в каталоге требуют фильтра в COM-01 (старый `CatalogController`) — follow-up;
  - число позиций и chip условий в списке групп требуют сводки условий по группам — follow-up;
  - фото ответственных не показываются: ORG-04 не отдаёт версию аватара, остаются инициалы;
  - контакт куратора в обзоре учителя (INF-11) не делается: учитель не читает ORG-02/04, нужен контакт в HND-01 — решение в U8;
  - учётка без роли получает на `/me` 403 и контакта не видит: экрану «Доступ к кабинету не назначен» нужен публичный источник контакта — follow-up.

Тест-кейсы U7:

- DX-U7-U1 (PHPUnit). Контакт поддержки: переменные окружения → `support` в профиле; все пустые → `null`; имена ответственных в назначениях и граница `InstitutionDetailController` (`InstitutionDetailControllerTest`).
- DX-U7-U2 (unit). Доля обработанных кадров для `MfProgress` (0 из 0, все в обработке, ошибки и повторы считаются обработанными).
- DX-U7-01 (финальный gate). Live-спеки: `z-institution-detail` — имена ответственных вместо номеров, распределение групп; `organization` — хлебные крошки и вкладки съёмки; `zz-media` — панель готовности по `stats`, переход по хлебным крошкам; `conditions` — вкладки каталога и сегмент условий; профиль — раздел «Помощь». Визуальная проверка 1440/390 учреждений, съёмки, фото, каталога, условий и профиля.

## 4.9. Детали U8

Факты из кода (2026-09-23): «Ссылки и сроки» и «Списки сотрудников» — общие для demo и live компоненты, которые в live загружают все страницы HND-01/HND-06 и фильтруют их на клиенте; счётчики над ними с U6 серверные. Руководитель учреждения получал на HND-06 403, пункта меню у него не было. Заказы уже показывают `MfStatus` оплаты и изготовления, но live-словарь писал «Оплачено · демонстрация». Контакт куратора учителю недоступен: ORG-02/04 учитель не читает.

Решения (2026-09-23, пользователь ушёл до вечера и поручил довести PR, слить и выкатить; объём U8 сужен под сегодняшнюю выкатку):

- «Ссылки и сроки»: сроки на `MfTimeline`, чек-лист проблем (INF-04) со ссылками на вкладки съёмки и списки сотрудников для организатора, раздельные сообщения успеха и ошибки, «Только просмотр» у руководителя.
- DS-14: руководитель учреждения читает списки и карточки списков своих учреждений (HND-06, HND-08, сводка U5 — та же область), изменения ему по-прежнему закрыты; пункт меню и маршруты в live открыты руководителю. Карточка списка — шаги «Передан куратору → Проверка → Наборы перенесены», история свёрнута.
- INF-11: `curatorName` в элементах HND-01 — одно чтение назначений на страницу через контракт `InstitutionAccessInterface`; карточка группы в обзоре учителя называет куратора. Только имя, без email и телефона.
- Заказы: в live «Оплачено» без пометки демонстрации (demo-словарь не меняется), быстрый период «7 дней / 30 дней» по московской дате.
- Отличия от мастер-плана — follow-up: серверная постраничная загрузка экранов ссылок и списков (клиентская загрузка всех страниц остаётся), автодополнение кодов из галереи (MED-02 куратору недоступен), аватары участников истории, подтверждение отключения доступа и смены роли в «Сотрудниках», мобильная карточка сотрудника.

Тест-кейсы U8:

- DX-U8-U1 (PHPUnit). Руководитель читает списки только своего учреждения, чужая карточка — 404, изменение — 403; куратор учреждения в элементах HND-01 и в контракте результата.
- DX-U8-01 (финальный gate). `zzz-handoff` — руководитель входит в списки через меню, видит «Только просмотр», API отвечает 200 с ролью head; `zzzz-links` — «Только просмотр» у руководителя; `zzzzzzz-overview` — куратор в карточках учителя; `shell` — меню руководителя со «Списками сотрудников».

## 5. Не входит

По разделу 4 мастер-плана: покупательские экраны (кроме автоматического наследования токенов и логотипа), чаты, тёмная тема (только «дверь»), платежи и финансовые дашборды N1, несколько сессий, капча.

## 6. Зависимости

- Слитые волны: A3/A4, B1/B2, C3/C4, D1/D2/D3, E2/E3/E5, F1/F2, H1.
- Открытые PR с пересечениями: [#47](https://github.com/rebit-pro/rabit-api/pull/47) массовая загрузка фото — U7 строит экран фотографий поверх него после merge; если к U7 он не слит, экран фотографий в U7 ограничивается токенами и состояниями без переделки загрузки. [#36](https://github.com/rebit-pro/rabit-api/pull/36) N1 наследует компоненты U6. [#48](https://github.com/rebit-pro/rabit-api/pull/48) чат — потребитель токенов.
- Соседний `../MoreFoto` без git: канонический граф `docs/04-bitrix-modules/backend-waves.json`, контракты `docs/05-rest-api/endpoints.json`, реестр решений `docs/frontend/business-review/decisions.md` (D01/D08 по DS-19).

## 7. Риски

- Размер единого PR (≈35–40 тыс. строк, из них ≈13 тыс. удаления) — коммит на волну с «что/зачем/проверки», таблица коммитов с фокусом ревью в описании PR, B4 отдельной серией с фокусом безопасности.
- Регрессии находятся поздно — зелёные коммиты-волны и `git bisect`; E2E-спеки пишутся в волнах.
- Долгоживущая ветка конфликтует с параллельными PR — регулярный rebase, повтор быстрых проверок.
- Существующие live-спеки опираются на тексты и роли элементов (выход в шапке, «Сотрудник №12» и т. п.) — спеки обновляются в волне, которая меняет интерфейс, и прогоняются в финальном gate.
- Demo Cucumber проверяет радиус поля `4px` (`e2e/steps/ui/fields.steps.ts:40`) — шаг обновляется в U2 вместе с радиусом.

## 8. Чек-лист

- [x] Прочитать инструкции, мастер-план, состояние `main` и открытых PR.
- [x] Создать ветку и worktree `/home/user/rabit-api-worktrees/u-design-system` от `main` `bb35665`.
- [x] U1a — очистка наследия frontend.
- [x] U1b — граф: направление U, пакет `design-ux`, запись D3, валидатор, канонический патч.
- [x] Открыть PR (draft) после U1 — [#53](https://github.com/rebit-pro/rabit-api/pull/53).
- [x] U2 — токены, шрифты, тема, замена глобальных стилей Berry.
- [x] U3 — бренд, каркас, аватар-инициалы.
- [x] U4 — поверхности, статусы, состояния, миграция hex, иконки.
- [x] B4 — приглашения и пароли.
- [x] B3 — аватары с фото.
- [x] U5 — серверные сводки.
- [x] U6 — обзор и инфографика.
- [x] U7 — рабочие экраны учреждений, съёмок, фото, каталога.
- [x] U8 — ссылки, списки, заказы (сотрудники — follow-up по разделу 4.9).
- [x] Финальный gate: `make test-e2e` (full gate PASS на `30abfb8`); demo Cucumber и визуальная проверка — после выкатки по решению пользователя.
- [x] Описание PR с таблицей коммитов и фокусом ревью; PR слит `b20423f`, выкачен 2026-09-25 (запись в журнале).

## 9. Критерии приёмки

1. Выполнены критерии раздела 16 мастер-плана для всех волн.
2. Каждый коммит-волна проходит свои быстрые проверки; результаты записаны в `progress.md` с командой и доказательством.
3. Финальный gate зелёный: полный `make test-e2e`, demo Cucumber, визуальная проверка desktop/mobile затронутых экранов.
4. Граф валиден: направление U, волны U1–U8/B3/B4, endpoint ID B3/B4, канонический MoreFoto синхронизирован патчем.
5. В описании PR — таблица коммитов, фокус ревью, ограничения и условия подключения.

## 10. Тест-кейсы

U1 (на коммите):

1. DX-U1-01. Действие: `npm run build` до и после. Ожидание: главный чанк меньше не менее чем на 3 МБ raw; `vue-tabler-icons`, `perfect-scrollbar`, `vue-i18n` отсутствуют в бандле. Команда: сравнение `du -b dist/assets/*.js` с сохранённой сборкой `main`.
2. DX-U1-03. Действие: `python3 tools/verify-wave-graph.py docs/waves/graph.json`. Ожидание: DAG валиден; направление U, волны U1–U8, B3, B4 в пакете `design-ux`; D3 merged; негативные fixtures, включая «зависимость вне пакета», отклоняются.
3. DX-U1-04. Действие: сравнить собранный CSS до и после. Ожидание: удалены только правила, селекторы которых опираются на классы, отсутствующие в живом коде (темы Berry, layout Berry, `.single-line-alert`, `.theme-tab`, `.elevation-10`, `.primary-shadow`, `.ps*`); правила Vuetify и MoreFoto не изменились. Команда: скрипт сравнения правил CSS в scratchpad, результат в `progress.md`.
4. DX-U1-05. Действие: `npm run check` и `npm run test:commerce`. Ожидание: зелёные.
5. DX-U1-06. Действие: `npm ci` на новом lockfile и поиск удалённых пакетов. Ожидание: установка успешна; `grep` удалённых пакетов по `src`, `package.json`, `package-lock.json` пуст.

Следующие волны: DX-U2-01…DX-U8-01, DX-B3-01/02, DX-B4-01…05, DX-U5-01, DX-U6-01 — по разделу 17 мастер-плана; перед стартом волны детализируются здесь.

Финальный gate:

6. DX-FIN-01. Действие: полный `make test-e2e`. Ожидание: все live-сценарии и интеграционные проверки зелёные, 0 skipped.
7. DX-FIN-02. Действие: demo Cucumber `npm run test:e2e:run`. Ожидание: зелёный.
8. DX-FIN-03. Действие: визуальная проверка desktop 1440 и mobile 390 затронутых экранов (вход, каркас, обзор, учреждения, съёмка, фото, каталог, сотрудники, ссылки, списки, заказы, профиль, экраны доступа). Ожидание: соответствие разделам 7–10 мастер-плана, без горизонтальной прокрутки на 390.

## 11. Команды

- Быстрые frontend-проверки: `docker run --rm --network none -v /home/user/rabit-api-worktrees/u-design-system/frontend:/app -v rabit-u-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce && npm run build'`.
- Граф: `python3 tools/verify-wave-graph.py docs/waves/graph.json`.
- Финальный gate: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.
