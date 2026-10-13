# Доработка интерфейса — прогресс

## Точка продолжения

- Дата: 13.10.2026. Ветка `codex/issues-92-129-ui-refresh` от main b1b547a; рабочая копия `/home/user/rabit-api-worktrees/issues-92-129-ui-refresh`. Прототип e89af3b согласован пользователем.
- Связанное: #92, #129, #157, #159, #188; план `plan.md`; скриншоты `screenshots/`; страница сравнения https://claude.ai/artifact/HyZvj8sVvbAgeHEekxzrNB.
- Завершено: S0–S7, S8.1 (FB-1…4), S8.2 (`GET /api/v1/shoots`, серверная сортировка «Съёмок»), S8.3 (правки live E2E и новый сценарий «Съёмки»); быстрые проверки зелёные.
- Сейчас: S8.4 — полный `make test-e2e` (прогон 2 после исправления восстановления в корзине).
- Следующий шаг: открыть PR; после ревью без блокеров — полный гейт (тестовый магазин ЮKassa выключен, #174).
- Блокеры: нет. Live E2E по изменённым спекам ещё не запускались.
- Проверки: PHP — scratchpad `php-ui.sh` (php-cli d1-local, volumes `rabit-e7-vendor`, `rabit-e7-phpstan`); frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy sh -c 'npm run check && npm run test:commerce'`; гейт — `E2E_YOOKASSA_ENV=/nonexistent/yookassa-test.env E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor make test-e2e`.
- Стенд скриншотов: см. `screenshots/tools/` и запуск в журнале «прототип».

## Журнал

### 13.10.2026 — старт

- Замечания пользователя 1–6 (см. план). Пункты 1 и 6 уже есть в #92 и #129, остальные — новые.
- Инвентаризация: учреждения и группы съёмки — карточки `StructureList`; общего API съёмок нет; корзина и оформление делят состояние витрины; в живом оформлении три галочки.
- Скриншоты «до» (демо): вход, галерея, диалог кадра, корзина, оформление — desktop 1440×1000 и mobile 390×844.

### 13.10.2026 — прототип

- S1: семантические токены `workspace`, `workspace-border`, `workspace-row-hover` в `src/theme/tokens.ts`, `_tokens.scss` пересобран `npm run tokens:build`; `UiDataTable` — поднос рабочей области, строки на белом, карточки mobile на белом.
- S2: `InstitutionTable`, `GroupTable` на `UiDataTable`; `StructureScreen` использует их и общую панель поиска `StructureSearch`; `useStructurePage` меняет размер страницы; `StructureList` удалён. Сортировка — в пределах страницы до серверной (S8).
- S3: пункт меню «Съёмки» (`navigation.ts`, тест навигации), маршрут `/cabinet/shoots` (`ShootList`, разрешён в живом режиме), `ShootListScreen` + `ShootTable`; `structureApi.list` для области `shoot` без учреждения ходит в `GET /api/v1/shoots` — backend в S8.
- S4: выбор продукции списком с ценами (`ProductSelector`), фото на телефоне ниже (42svh), нижняя панель корзины в галерее (`CartBar`); корзина и оформление на одной странице — `CheckoutPanel` (бывший `CheckoutForm`: итог, email, комментарий по ссылке, согласия, «Оформить заказ · сумма»), компактные строки корзины, сворачиваемые условия, `/g/:token/checkout` перенаправляет на корзину; `CheckoutForm`, `CheckoutScreen`, `CheckoutPage` удалены. UI-DEC-05: `reviewed` ставится при оформлении.
- S5: футер `LegalFooter` (бренд, «Покупателям», «Документы», реквизиты, разработчик), строка бренда галереи убрана; вход — «База знаний» в шапке и карточкой; кабинет — «База знаний» внизу меню; иконки добавлены в реестр.
- Попутно #157: ссылки согласий — цвет ссылок продукта.
- Стенд скриншотов: живой режим кабинета с подменой API (`/api/v1/me`, учреждения, съёмки, заказы), живая витрина с подменой (галерея, каталог, расчёт, документы), демо-режим для галереи и диалога. Найдены и исправлены при съёмке: маршрут `ShootList` не был в списке живого режима; узкие колонки групп; зазор в сетке корзины; порядок блоков корзины на телефоне; тесная шапка входа на телефоне.

### 13.10.2026 — согласование и реализация

- Пользователь посмотрел страницу сравнения: «Со всем остальным я согласен… бери в реализацию». Приняты UI-DEC-01…09, в том числе UI-DEC-05. Замечания FB-1…4 записаны в план.
- FB-1: шкала «Группы по состоянию приёма» снята со страницы съёмки. FB-2: вкладки съёмки — сегменты с иконками на подложке, текущая белая. FB-3: класс `mf-filters` на панелях фильтров (заказы, платежи, ссылки, сотрудники, каталог, поиск структуры) — заливка, светлая граница, 14 px; поля форм не тронуты (контраст 3:1). FB-4: согласия компактнее (36 px), ссылки без подчёркивания.
- Backend (BE-DEC-01): `GET /api/v1/shoots` — `ShootListController` (чистый: RequestDto → `ShootListInputMapper` → `ListAllShootsUseCase` → `ShootListResultMapper`), `ShootSortEnum`, `StructureRepository::shootList` (область по ролям, поиск по началу названия, сортировка всего фильтра, съёмка без даты — последней), маршрут и DI. Frontend «Съёмок» передаёт `sort`/`direction` серверу.
- Unit: `ShootListTest` — граница контроллера, умолчания и 422 маппера, область организатора и куратора, 403 воспитателя, 409 при смене доступа, маппинг строк.
- E2E: `productRadio` в helpers; `zzzz-storefront`, `zzzz-storefront-windows`, `zzzzzzzzzz-files` — выбор продукции списком; `zzzzz-orders` — оформление в корзине, без галочки «проверено», старый `/checkout` ведёт в корзину, изменение цены подтверждается кнопкой с новым итогом; `organization`, `cabinet-tables`, `z-institution-detail`, `zz-u7-screens` — строки таблиц вместо карточек, нет шкалы на странице съёмки; новый сценарий «UI-T03 UI-T09» в `cabinet-tables` (API: порядок, поля, 422, 403 воспитателя; UI: меню, поиск, сортировка, переход в съёмку).
- Проверки 13.10.2026: PHPUnit OK 1145; PHPStan без ошибок; php-cs-fixer 0 по 15 файлам; `npm run check` (lint, stylelint, typecheck, typecheck:e2e, test:ui 88/88), `test:commerce` 239/239.

### 13.10.2026 — ревью и полный гейт

- PR #189 открыт; самопроверка — 1 блокер (панель оформления без ключа галереи) исправлен в ad2baeb; неблокирующее — #190 (серверная сортировка учреждений и групп). Итог ревью опубликован в PR.
- Пользователь: «Если тесты зелёные, мержи и выкладывай».
- Прогон 1 `rabit-e2e-d25de27a4186` (head ad2baeb) — FAIL: группа b 66 passed, 1 failed — `zzzzz-orders` «E5: lost response survives group closure and reload, then recovers the same order»: после закрытия группы корзина не получает расчёт и не показывает панель оформления, а вместе с ней пропадал экран восстановления неподтверждённой отправки (на прежней странице оформления он был всегда). Группа a остановлена раннером.
- Исправление: состояние оформления принадлежит корзине (`useCheckout` в `CartContents`, тип `Checkout`), экран восстановления — отдельный `CheckoutRecovery` над корзиной при любой корзине; обычная форма скрыта на время восстановления; `CartContents` пересоздаётся по токену галереи. `npm run check`, `test:commerce` 239/239 — зелёные.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| UI-T01 | PASS (скриншот) | 13.10.2026 | `screenshots/after/*-cab-institutions.webp`; без горизонтальной прокрутки на 390 px; live E2E — S8 |
| UI-T02 | PASS (скриншот) | 13.10.2026 | `screenshots/after/*-cab-shoot.webp`; live E2E — S8 |
| UI-T03 | PENDING | — | backend готов, сценарий `cabinet-tables` «UI-T03 UI-T09» — в полном гейте |
| UI-T04 | PASS (скриншот) | 13.10.2026 | `*-cab-orders.webp`, `*-cab-institutions.webp`, `*-cab-institution.webp` |
| UI-T05 | PASS (скриншот, unit) | 13.10.2026 | `*-live-photo.webp`, `*-live-cart.webp`, `mobile-gallery-with-cart.webp`; `/checkout` → `/cart`; `npm run test:commerce` 239/239; live E2E — S8 |
| UI-T06 | PASS (скриншот, unit) | 13.10.2026 | `*-login.webp`; `tests/shell/navigation.test.mjs` в `test:ui` |
| UI-T07 | PASS (скриншот) | 13.10.2026 | футер на `*-login.webp`, `*-live-cart.webp`; `data-testid` `legal-footer`, `footer-developer` и ссылка «Оферта» сохранены |
| UI-T09 | PASS (unit), PENDING (E2E) | 13.10.2026 | `ShootListTest` 11 тестов; live — гейт |
| UI-T10 | PASS (скриншот) | 13.10.2026 | стенд: страница съёмки без шкалы, вкладки, фильтры заказов, согласия в корзине |
| UI-T08 | PASS | 13.10.2026 | `npm run check` (lint, stylelint, typecheck, typecheck:e2e, `test:ui` 88/88) и `npm run test:commerce` 239/239 |
