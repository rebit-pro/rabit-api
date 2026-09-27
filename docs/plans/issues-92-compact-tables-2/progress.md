# Issue #92 (срез 2) — журнал

## Точка продолжения

- Ветка `codex/issues-92-compact-tables-2`, worktree `/home/user/rabit-api-worktrees/issues-92-compact-tables-2`,
  base `origin/main` `9e00856c`. Issue [#92](https://github.com/rebit-pro/rabit-api/issues/92). PR — draft, ссылка
  появится после открытия.
- Завершено: разбор, решения DEC-01…04 с пользователем, прототип фронтенда, скриншоты «было/стало».
- Сейчас: draft PR с планом и макетами ждёт одобрения пользователя.
- Следующий шаг: после одобрения макетов и DEC-05…09 — backend: серверная сортировка трёх списков (checklist 5).
- Блокеров нет. Открытые решения: DEC-05…09 (plan.md).
- Рабочее дерево: чисто после коммитов (план + прототип).
- Frontend-проверки из `frontend/`: `docker run --rm --network none -v "$PWD":/app -v rabit-issues92-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy bash -c 'npm run check && npm run test:commerce'`.
- Скриншоты: Vite + заглушки в том же образе, скрипт `screens/stub-screens.mjs`, запуск `TAG=before|after`
  (исходники main — `git archive origin/main frontend/src` в scratchpad, смонтировать поверх `/app/src:ro`).
- Полный гейт: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Статус тест-кейсов

| ID | Статус | Дата | Комментарий |
|---|---|---|---|
| T01–T17, T19 | PENDING | 2026-09-27 | реализация после одобрения макетов |
| T18 | PENDING | 2026-09-27 | макеты сняты (`screens/after`), сверка реализации — после backend |

## Журнал

### 2026-09-27

- Запрос пользователя: остальные разделы сайта привести к табличному виду с CRUD, как каталог/учреждение/ссылки в
  срезе 1. Пример: в «Списках сотрудников» нельзя удалить заявку на проверке. Заказы и платежи — тоже таблицами.
- Проверено, что срез никто не взял: открытых PR и веток по #92, кроме слитого среза 1, нет.
- Меню боевого кабинета: табличными остаются «Списки сотрудников», «Заказы», «Платежи»; остальные экраны уже сделаны
  или только демо.
- Разбор API: удаления, отмены, смены статуса и сортировки нет ни в одном из трёх списков; порядок фиксирован
  (новые сверху). FK на `mf_order` — 5 таблиц RESTRICT (см. plan.md «Факты»). Оплата и сверка берут `orders->lock()`.
- Решения пользователя (ответы на вопросы): DEC-01 удалять неперенесённые списки (автор/организатор), DEC-02 удалять
  только неоплаченные заказы, DEC-03 выбор в заказах/платежах → CSV, DEC-04 сначала макеты.
- Прототип фронтенда: `StaffRequestTable`, `StaffOrderTable`, `PaymentTable` на `UiDataTable`; `ui/table-query.ts`
  (sort/page/pageSize ↔ URL и параметры API), `ui/csv.ts` (BOM, `;`), `tableMoment` (дата одной строкой);
  иконки `mdi-download-outline`, `mdi-file-delimited-outline`, `mdi-tune-variant`.
- Сбой по ходу: `npx prettier --write` по модулю без конфигурации проекта (она задана в `eslint.config.js`)
  переформатировал ~190 файлов; все отслеживаемые файлы восстановлены `git checkout`, правки применены заново, новые
  файлы пересозданы. Форматировать только `npx eslint --fix <файлы>`.
- Правки по скриншотам: даты одной строкой, ширины колонок, стиль ссылок; при выборе только оплаченных заказов вместо
  диалога «Удалить 0» — предупреждение.
- Проверки: `npm run check` — EXIT 0, ui-тесты 88/88; `npm run test:commerce` — 223/223 (образ Playwright, том
  `rabit-issues92-node`).
- Стенд: `pageerror Cannot read properties of undefined (reading 'length')` есть и на main (заглушка отдаёт пустой
  ответ на `/api/v1/group-links` и `/legal/consents/pending`) — артефакт стенда, не прототипа.
