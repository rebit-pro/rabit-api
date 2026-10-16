# E9 — прогресс

## Точка продолжения

- PR [#208](https://github.com/rebit-pro/rabit-api/pull/208) merged d8236a4 (16.10.2026); выложен на stage как `main-20261016152109-d8236a4` (backend и frontend, без миграций).
- Волна завершена. Следующая волна отмечает E9 merged в графе.
- Открыто: проверка владельцем под реальным воспитателем — нужен вход в кабинет (приглашение B2, доставка писем #161).
- Откат: `docker service rollback` для 13 backend-сервисов и `morefoto_frontend` (спецификации в `services-before.json` релиза).

## Журнал

### 16.10.2026 — старт и реализация

- Владелец взял E9 и принял рекомендации E9-DEC-01…03. При реализации добавлены уточнения DEC-04 (комплект «все фото» считается отдельно: в снимке нет числа кадров) и DEC-05 (поздняя оплата не входит, как в L1/J2).
- Граф: E9 → inProgress, baseline e728d8c; валидатор PASS (61 волна, 123 ID, 17 негативных фикстур).
- Backend: `GET /api/v1/groups/{group_id}/sales-summary`:
  - `GetGroupSalesUseCase` проверяет область через `SalesAccess` (Access `StaffRequestAccessInterface`) и группу через Organization `GroupDirectoryInterface`;
  - `GroupSalesRepository` делает два SQL по `ix_mf_order_group`.
- Frontend: блок «Продажи» в карточке группы «Обзора» воспитателя — сумма, четыре счётчика, комплекты, печать по форматам, пустое состояние и ошибка с повтором.
- E2E `zzzzzzzzzzz-teacher-sales` идёт в группе b после `tables`, до `print-archive` L1. Спека назначает `another-teacher` на печатную группу J4.

### Проверки

| Команда | Результат |
| --- | --- |
| `phpunit --testsuite unit --filter 'GroupSales\|OrderArchitecture\|CommerceBootstrap'` (php-cli d1-local, vendor `rabit-e7-vendor`) | PASS, 20 тестов, 178 проверок |
| `phpstan analyse public/local/modules/morefoto.commerce` | PASS после удаления лишних `array_values` (2 замечания `arrayValues.list`) |
| `php-cs-fixer --dry-run` по новым файлам | 1 файл переупорядочен (data provider), исправлено |
| `npm run check` (docker playwright v1.52.0, том `rabit-e7-node`) | PASS, test:ui 100/100 |
| `npm run test:commerce` | PASS, 248/248 |

### 16.10.2026 — полный гейт и PR

- `make test-e2e …` (run `rabit-e2e-26488bdb3641`) — PASS, 404,2 с. Группы a 317,8 с и b 299,8 с, 74 браузерных теста без skip/flaky, все verify-*.php. Спека `zzzzzzzzzzz-teacher-sales` — passed за 7,3 с.
- Visual (`e9-desktop-teacher-sales.png`, `e9-mobile-teacher-sales.png`): в карточке «E4 print» блок «Продажи» — 550 ₽, 1 заказ, 2 семьи, 5 печатных фото, 0 цифровых (копии J4 не считаются, DEC-01), «E4 Печать 10×15 — 4 шт.» и «D3 Печать … — 1 шт.». На 390 px без горизонтальной прокрутки.
- PR #208 создан; граф: E9 → review. Саморевью: блокеров нет. Заголовок «Продажи» визуально скромный — не дефект, issue не заведён.

### 16.10.2026 — merge и выкладка

- PR #208 смержен по «да» владельца (d8236a4).
- Релиз `main-20261016152109-d8236a4`: `prepare-release.sh` (vendor и backend.conf из J2-релиза, composer.lock не менялся) → `switch-backend.sh morefoto_stage_fpm` → DI smoke 10/10 (`GroupSalesController`, `GetGroupSalesUseCase`, `SalesAccessInterface` и прежние; отправка писем выключена) → остальные 12 backend-сервисов, включая `order_email_runner` → `switch-frontend.sh`.
- Smoke stage: COM-14 без Bearer — 401 (маршрут есть). Все 14 сервисов 1/1, frontend 2/2. В бандле есть блок «Продажи». В логах FPM и backend нет 5xx и fatal.

## Тест-кейсы

| ID | Статус | Дата | Доказательство |
| --- | --- | --- | --- |
| E9-T01 | PASS | 16.10.2026 | `GroupSalesTest::testOnlyTheOwnScopeSeesTheGroup` (7 областей), `testAMissingGroupLooksLikeAForeignOne` |
| E9-T02 | PASS | 16.10.2026 | `GroupSalesTest::testTheSummaryKeeps…`, `testAGroupWithoutSales…` |
| E9-T03 | PASS | 16.10.2026 | `GroupSalesArchitectureTest` (4 теста) |
| E9-T04 | PASS | 16.10.2026 | `tests/ui/teacher-sales.test.mjs` 2/2 |
| E9-T05 | PASS | 16.10.2026 | гейт `rabit-e2e-26488bdb3641`: 401, 404 (некорректный ID, чужой воспитатель), 403 head |
| E9-T06 | PASS | 16.10.2026 | тот же гейт: COM-14 = расчёт по COM-12 |
| E9-T07 | PASS | 16.10.2026 | тот же гейт: неоплаченный digital-заказ не меняет сводку |
| E9-T08 | PASS | 16.10.2026 | тот же гейт: `e9-desktop/mobile-teacher-sales.png` |
| E9-T09 | PASS | 16.10.2026 | `npm run check`, `test:commerce`, phpstan, PHPUnit, `make test-e2e` |
