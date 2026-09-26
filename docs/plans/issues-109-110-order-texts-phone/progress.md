# Issues #109 и #110 — журнал

## Точка продолжения

- Дата: 2026-09-26.
- Ветка: `codex/issues-109-110-order-texts-phone`.
- Worktree: `/home/user/rabit-api-worktrees/issues-109-110-order-texts-phone`. Общий checkout `/home/user/rabit-api` не трогать.
- Base: `origin/main` `23642d4` (merge PR #112).
- Issues: [#109](https://github.com/rebit-pro/rabit-api/issues/109), [#110](https://github.com/rebit-pro/rabit-api/issues/110). PR: [#118](https://github.com/rebit-pro/rabit-api/pull/118), head с кодом `c27aeb6`.
- Документация: [план](plan.md).
- Завершено: разведка, план, реализация #109 и #110, unit-тест, быстрые проверки (T01–T05 PASS).
- Сейчас: self-review PR #118 без блокеров (комментарий в PR), неблокирующее — плашка тестового режима → #120. Полный gate PASS (T06–T08).
- Следующий шаг: merge по решению пользователя, затем деплой только frontend.
- Блокеров нет. Открытые решения:
  - плашка тестового магазина требует поля API (R3) — вынесено в #120;
  - live-подпись отметки после PR #112 была «Состав заказа проверен»; по выбору пользователя стала
    «Состав и условия проверены» (рядом отдельные отметки согласия и оферты) — вернуть, если пользователь решит иначе;
  - маска ввода телефона `+7 (900) 123-45-67` отличается от формата отображения и от примера в ошибке
    «например +7 900 123-45-67» — отдельное решение.
- Рабочее дерево: изменения закоммичены; `frontend/node_modules` — точка монтирования docker-тома, в git не попадает.
- Команды проверок (том `rabit-issues109110-node`, создан `npm ci` 2026-09-26):
  - `docker run --rm -v <worktree>/frontend:/app -v rabit-issues109110-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy npm ci`
    (только при изменении `package-lock.json`);
  - `docker run --rm --network none -v <worktree>/frontend:/app -v rabit-issues109110-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy sh -c 'npm run check && npm run test:commerce'`;
  - gate после review: `make test-e2e` по `docs/waves/a8/README.md`.

## Результаты тест-кейсов

| ID | Статус | Дата | Доказательство |
|----|--------|------|----------------|
| T01 | PASS | 2026-09-26 | `npm run test:commerce`: `stored phones read as +7 900 123-45-67 on order pages and in staff orders` ok |
| T02 | PASS | 2026-09-26 | тот же прогон: `display formatting leaves non-standard and foreign phones as stored` ok |
| T03 | PASS | 2026-09-26 | тот же прогон: прежние тесты `displayPhone` ok; всего 209/209 pass |
| T04 | PASS | 2026-09-26 | `npm run check`: eslint, stylelint, vue-tsc, typecheck e2e без ошибок; `test:ui` 47/47 pass |
| T05 | PASS | 2026-09-26 | grep: 0 вхождений старых текстов; «Состав заказа проверен» — 1, образец `UiSelectionExamples.vue` |
| T06 | PASS | 2026-09-26 | `make test-e2e` `rabit-e2e-35fb1c22fc7f`: `zzzzz-orders` E5 desktop/mobile passed, браузерных сценариев 123 (a 76, b 47), verify-orders/payments passed, Total 433.6 s |
| T07 | PASS | 2026-09-26 | тот же прогон `rabit-e2e-35fb1c22fc7f`: Cucumber-шаги в составе групп a/b зелёные, exit 0 |
| T08 | PASS | 2026-09-26 | просмотрены `e5-{desktop,mobile}-{checkout,order,staff-list,staff-card}.png` в `api/var/e2e/rabit-e2e-35fb1c22fc7f/b/b/artifacts/`: «Состав и условия проверены», «Оформить заказ», телефон `+7 900 555-03-04` в списке и карточке |

## Хронология

### 2026-09-26 — разведка

- Дублей PR нет: `gh pr list --state all --search "109 in:title"` и `"110 in:title"` пусты, веток с 109/110 нет.
- Найдены live-остатки сверх issue: вводная и `receipt-unavailable` в `CheckoutContacts`, демо-фраза в
  `CheckoutTerms`, текст `FeatureUnavailablePage`. Демо-экраны оставлены.
- Признака тестового режима платежей во frontend и в ответах API нет → плашка не добавляется (R3).
- Хелпер телефона `displayPhone()` уже есть (маска ввода со скобками); разбор переиспользуется в новой `formatPhone()`.
- После PR #112 live-подпись отметки уже «Состав заказа проверен»; по выбору пользователя обе ветки заменены на
  «Состав и условия проверены».

### 2026-09-26 — реализация и быстрые проверки

- #110: в `ui/field-values.ts` общий разбор `russianPhoneDigits()`; `displayPhone()` (маска ввода) без изменений,
  новая `formatPhone()` → `+7 900 123-45-67`. Применена в `OrderLiveFacts` (страница заказа покупателя и карточка
  куратора), строке списка `StaffOrdersLiveScreen`, демо `OrderContacts`. Unit-тесты в `tests/commerce/ui-values.test.mjs`.
- #109: отметка «Состав и условия проверены», кнопка «Оформить заказ», подпись под кнопкой про оплату на странице
  заказа; подсказка плитки «Заказов найдено» убрана; `CheckoutContacts`, `CheckoutTerms`, `FeatureUnavailablePage` —
  live-тексты без демонстрации; сообщения ошибки отметки приведены к новой формулировке.
- E2E-спеки: подпись отметки в Cucumber-шагах и `e2e/live/zzzzz-orders.spec.ts`; демо-шаг «контакты покупателя
  показаны в заказе» ждёт `+7 900 123-45-67`; в live E5 добавлены проверки кнопки и телефона `+7 900 555-03-04`
  у покупателя, в списке и карточке куратора.
- `npm ci` в свежий том `rabit-issues109110-node` — exit 0.
- Первый прогон `npm run check` — FAIL: prettier в `CheckoutForm.vue` и `CheckoutTerms.vue` (перенос строк);
  исправлено `npx eslint --fix` по двум файлам.
- Повторный прогон `sh -c 'npm run check && npm run test:commerce'` (`--network none`) — exit 0: `test:ui` 47/47,
  `test:commerce` 209/209. `git diff --check` — чисто.

### 2026-09-26 — PR

- Коммиты `b1b3550` (план и журнал) и `c27aeb6` (код и спеки); повторная проверка дублей PR — пусто.
- `origin/main` сдвинулся на `00c507f`: только `docs/plans/OPS-legal-compliance/`, пересечений нет, проверки не повторялись.
- Push `codex/issues-109-110-order-texts-phone`, PR [#118](https://github.com/rebit-pro/rabit-api/pull/118) в `main`.

### 2026-09-26 — ревью и полный gate

- Self-review PR #118: блокирующих замечаний нет (комментарий в PR). Неблокирующее — признак тестового магазина из API → issue #120.
- `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`
  на head `3ddede2`, прогон `rabit-e2e-35fb1c22fc7f`: exit 0, Total 433.6 s, 123 браузерных сценария, все verify-*.php passed.
- Визуальная проверка desktop/mobile по скриншотам E5 — PASS (T08).
