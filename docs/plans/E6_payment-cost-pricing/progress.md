# E6 — прогресс

## Точка продолжения

- Ветка `codex/e6-payment-cost-pricing` от main `49f40f97fb5ee8b16425fa3a6b2e2b3da0c2b771` (merge плана PR #35). Checkout: `/home/user/rabit-api-worktrees/e6-payment-cost-pricing`. PR ещё не создан. Upstream ветки снят, push только явным `git push -u origin HEAD:codex/e6-payment-cost-pricing`.
- Завершено: S1 — план, журнал, учёт merge пакета design-ux в обоих графах, правка валидатора, E6 inProgress.
- Сейчас: S2 — домен (`PaymentCostPolicy`, пределы цены, лимит сумм).
- Следующий шаг: `PaymentCostPolicy` с unit-тестом E6-T01/T02.
- Блокеров нет. Решения E6-DEC-01…03 приняты пользователем 25.09.2026.
- Канонический MoreFoto изменён на месте: `backend-waves.json`, `backend-waves.md`, `wave_graph.py`. Снимок до E6 — в scratchpad сессии (`morefoto-before-e6`); итоговый patch — `docs/waves/e6/morefoto-contract.patch` в S9.
- Следующая проверка: `python3 tools/verify-wave-graph.py docs/waves/graph.json`; PHPUnit модуля commerce в образе `rabit-api-php-cli` с монтированием этого worktree.

## Хронология

### 2026-09-25 — старт

- Пользователь выбрал E6 как единственную волну, свободную от ИП. PR #35 (план ЮKassa и E6) обновлён на актуальный main и слит по решению пользователя: `49f40f9`.
- Ветка создана от `49f40f9`. `python3 tools/verify-wave-graph.py docs/waves/graph.json` на main: 51 волна, 110 API ID, 35 WNN, readyFromMain E6, U1, U5 (U1/U5 — формально: пакет design-ux слит PR #53, но в графе ещё `inProgress`).
- Учёт merge пакета design-ux (U1–U8, B3, B4): `deliveryState=merged`, `mergedWaves`, `mergedPullRequests` → PR #53, `mergeCommits` → `b20423f08c5794f66346a607c376bc89ffe3bb82`, baseline → `49f40f9`, дата 2026-09-25. E6 → `inProgress`. Те же изменения внесены в канонический `backend-waves.json`; `render-waves.py` перерисовал Markdown (перед этим проверено на копии, что генерация идемпотентна).
- Валидатор (`tools/verify-wave-graph.py` и канонический `wave_graph.py`, файлы идентичны) падал на полностью слитом пакете: негативная фикстура «bundle depends on unmerged wave outside the bundle» принималась. Добавлено правило «слитая волна зависит только от слитых». Фикстура «bundle partially merged» теперь переключает состояние первого участника в обе стороны.
- Разведка кода: цена продажи должна считаться в одном месте (use case чтения условий), потому что одно поле `price` читают витрина, `SalesProduct`, строка расчёта (→ `PRODUCT_PRICE` заказа) и F2. Контроллер условий старого образца; валидация во входных DTO; `lockOrganizer` требует токен.
- Решения пользователя 25.09.2026: ставка до 99,99% (E6-DEC-01), предел цены 1 000 000 ₽ (E6-DEC-02), чистка контроллера в E6 (E6-DEC-03). Следствие E6-DEC-01 и 02: цена продажи до 10¹² копеек. Заказ хранит BIGINT; в памяти поднимается предел `SalesProduct`, сумма расчёта ограничена 2⁵³−1 (A6 в плане).

## Проверки

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| E6-T16 (граф, старт) | PASS | 2026-09-25 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 51/110/35, 13 негативных фикстур, readyFromMain [E6]; канон `python3 docs/04-bitrix-modules/wave_graph.py docs/04-bitrix-modules/backend-waves.json` — тот же результат; старый граф main с новым валидатором — PASS (readyFromMain E6, U1, U5) |
| E6-T01…T15, T17, T18 | PENDING | 2026-09-25 | Реализация не начата |
