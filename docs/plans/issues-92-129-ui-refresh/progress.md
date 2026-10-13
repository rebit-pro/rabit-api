# Доработка интерфейса — прогресс

## Точка продолжения

- Дата: 13.10.2026. Ветка `codex/issues-92-129-ui-refresh` от main b1b547a; рабочая копия `/home/user/rabit-api-worktrees/issues-92-129-ui-refresh`. PR нет — сначала пользователь смотрит скриншоты.
- Связанное: #92, #129, #157, #159, #188; план `plan.md`; скриншоты `screenshots/before/`, `screenshots/after/`; скрипты стенда `screenshots/tools/`.
- Завершено: S0–S6 — прототип frontend всех шести пунктов, быстрые проверки зелёные, скриншоты «до» и «после» desktop 1440 и mobile 390.
- Сейчас: S7 — пользователь смотрит скриншоты.
- Следующий шаг: учесть замечания; затем S8 — `GET /api/v1/shoots` (UseCase, SQL, DI, контракт), серверная сортировка учреждений и групп, правки live E2E, полный `make test-e2e`, PR.
- Блокеры: нет. Открытые решения: UI-DEC-01…08 по скриншотам; UI-DEC-05 (без галочки «Состав и условия проверены») — юридически значимое, нужна явная отметка пользователя.
- Рабочее дерево: всё в коммите прототипа.
- Стенд скриншотов: контейнер Playwright 1.52 без сети, node_modules `rabit-e7-node`, frontend смонтирован в `/app`, Vite demo на 4173 и live на 4174, скрипты из `screenshots/tools/` в `/shots`:
  `docker run -d --name mf-ui-shots --network none -v <worktree>/frontend:/app -v rabit-e7-node:/app/node_modules -v <tools-dir>:/shots -w /app mcr.microsoft.com/playwright:v1.52.0-jammy sleep infinity`, затем два `vite` (`VITE_API_MOCKS_ENABLED=true` на 4173 и `false` на 4174) и `node demo-flow.mjs after`, `node live-cabinet.mjs after`, `node live-cart.mjs after` (выход в `/shots/out`).
- Проверки: `docker run --rm --network none -v $PWD/frontend:/app -v rabit-e7-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy sh -c 'npm run check && npm run test:commerce'`. Форматирование — только `npx eslint --fix <файлы>`.

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

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| UI-T01 | PASS (скриншот) | 13.10.2026 | `screenshots/after/*-cab-institutions.webp`; без горизонтальной прокрутки на 390 px; live E2E — S8 |
| UI-T02 | PASS (скриншот) | 13.10.2026 | `screenshots/after/*-cab-shoot.webp`; live E2E — S8 |
| UI-T03 | PENDING | — | экран и подмена API готовы (`*-cab-shoots.webp`); backend `GET /api/v1/shoots` — S8 |
| UI-T04 | PASS (скриншот) | 13.10.2026 | `*-cab-orders.webp`, `*-cab-institutions.webp`, `*-cab-institution.webp` |
| UI-T05 | PASS (скриншот, unit) | 13.10.2026 | `*-live-photo.webp`, `*-live-cart.webp`, `mobile-gallery-with-cart.webp`; `/checkout` → `/cart`; `npm run test:commerce` 239/239; live E2E — S8 |
| UI-T06 | PASS (скриншот, unit) | 13.10.2026 | `*-login.webp`; `tests/shell/navigation.test.mjs` в `test:ui` |
| UI-T07 | PASS (скриншот) | 13.10.2026 | футер на `*-login.webp`, `*-live-cart.webp`; `data-testid` `legal-footer`, `footer-developer` и ссылка «Оферта» сохранены |
| UI-T08 | PASS | 13.10.2026 | `npm run check` (lint, stylelint, typecheck, typecheck:e2e, `test:ui` 88/88) и `npm run test:commerce` 239/239 |
