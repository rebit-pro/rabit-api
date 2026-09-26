# Issue #92 (срез 1) — журнал

## Точка продолжения

- Ветка `codex/issues-92-compact-tables`, worktree `/home/user/rabit-api-worktrees/issues-92-compact-tables`,
  base `origin/main` `41b1146e`. Issue [#92](https://github.com/rebit-pro/rabit-api/issues/92). PR [#152](https://github.com/rebit-pro/rabit-api/pull/152) (draft).
- Завершено: разбор экранов и схемы, прототип, скриншоты desktop/mobile, план с решениями DEC-01…07.
- Сейчас: ожидание одобрения скриншотов и решений пользователем. Код реализации не начат.
- Следующий шаг: после одобрения — пункт 4 плана (контракты и участники удаления).
- Блокеров нет. Открытые решения: DEC-01…DEC-07 (`plan.md`).
- Рабочее дерево: только `docs/plans/issues-92-compact-tables/` (план, журнал, скриншоты, `prototype.diff`,
  `stub-screens.mjs`). `frontend/node_modules` — том `rabit-issues92-node` (свежий `npm ci` 2026-09-26).
- Повтор скриншотов: `git apply docs/plans/issues-92-compact-tables/screens/prototype.diff` в копии `frontend/src`,
  затем Vite (`VITE_API_URL=/api VITE_API_MOCKS_ENABLED=false`) и `node stub-screens.mjs` в
  `mcr.microsoft.com/playwright:v1.52.0-jammy` с `--network none`, `TAG=<папка>`, выход в `/shots/<TAG>`.
- Полный гейт (после review): `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Статус тест-кейсов

| ID | Статус | Дата | Комментарий |
|---|---|---|---|
| T01–T15 | PENDING | 2026-09-26 | Реализация ждёт одобрения макетов |

## Журнал

### 2026-09-26

- Запрос пользователя: каталог, страница учреждения и «Ссылки и сроки» сделать компактными, в табличном виде с CRUD
  по образцу «Сотрудников»; заказы и платежи — вторым срезом. Сначала PR с планом и скриншотами, реализация — после
  одобрения скриншотов.
- Проверено, что #92 никто не взял: открытых PR и веток по #92 нет (есть только слитые `issues-91-staff-table`,
  `issues-103-table-toolbar`).
- Разбор схемы: все FK на продукцию/учреждение/съёмку/группу — `ON DELETE RESTRICT`; заказы хранят снимки названий,
  но `mf_order.GROUP_PUBLIC_ID` ссылается на группу → группу с заказами удалить нельзя без архива (DEC-01).
  Контракта «группа с заказами» нет, событий удаления нет → новые контракты в `rebit.share/lib/Contracts/`.
  Удаление кадров (#105/#106) стирает файлы после commit, без очереди — тот же приём для каскада.
  Чтение учреждения не отдаёт у съёмок число групп, у групп — название съёмки → добавить.
- Прототип на реальных компонентах в копии `src` (scratchpad), API — заглушки Playwright. Найдено по ходу:
  - в наборе иконок нет 10 нужных `mdi-*` (список в плане);
  - `UiDataTable` делит ширину колонок поровну, колонка действий фиксирована 176 px → в прототипе добавлены
    `width`, `actionsWidth`, `autoPager`.
- Скриншоты: `screens/before` (3 desktop) и `screens/after` (11: desktop, mobile, выбор, диалоги удаления).
- Попутно (не по теме, не блокер): `stores/auth.ts` ставит `setTimeout` на весь срок сессии; при сроке больше
  ~24,8 суток (предел `setTimeout`) таймер срабатывает сразу и сессия сбрасывается. Сейчас срок токена задаётся
  в часах и так далеко не заходит, поэтому issue не заводится; учтено в заглушках стенда (срок +2 часа).
- Проверки кода не запускались: в ветке нет изменений кода, только документы и скриншоты.
- Открыт draft PR [#152](https://github.com/rebit-pro/rabit-api/pull/152) с планом и скриншотами (`1889ca5`).
