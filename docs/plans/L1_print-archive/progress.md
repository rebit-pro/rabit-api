# L1 — архив оплаченной печати — прогресс

## Точка продолжения

- Дата: 15.10.2026. Ветка `codex/l1-print-archive` от main 60bd97f; рабочая копия `/home/user/rabit-api-worktrees/l1-print-archive`. PR нет. План: `docs/plans/L1_print-archive/plan.md`; граф `docs/waves/graph.json` (L1).
- Завершено: S0 — инвентаризация кода, граф (D06 принят пользователем 15.10.2026, F3 merged по PR #186 / 68355a0, L1 `inProgress`), план с решениями L1-DEC-01…08.
- Сейчас: S1 — согласование L1-DEC-01…08 с пользователем.
- Следующий шаг: после ответа — зафиксировать решения в `decisionEvidence` графа и плане, затем S2 (контракты поставщиков).
- Блокеры: решения L1-DEC-01…08. Внешних нет (почта и ЮKassa для L1 не нужны).
- Рабочее дерево: план, прогресс, граф, `docs/waves/README.md`.
- Проверка графа: `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Журнал

### 15.10.2026 — старт

- Выбор волны: готовы из main только L1 и J2 (G2 ждёт D09 и реквизиты; J2 поставлен на паузу владельцем 12.10, SMTP не работает — #161). Рекомендована L1; пользователь подтвердил D06: «D06 подтверждаю».
- Инвентаризация (Explore по main 60bd97f): модуля `morefoto.production` и таблиц нет; оплаченный снимок `mf_order_line` содержит всё для печати (PRODUCT_PUBLIC_ID/NAME/FORMAT, PRINT_COUNT, QUANTITY, PHOTO_PUBLIC_ID, CHILD_CODE); `UF_CLOSES_AT` = printSalesUntil, после закрытия неизменен; ничего не срабатывает по окончании продаж (crontab); `PaymentReconciler` даёт `latePayment`, но нет выборки открытых попыток по группе; `OriginalFilesInterface` без исходного имени; ZIP/хранилище/токен J1 привязаны к заказу (токен без отзыва); live-карточки группы нет — блок «Печать» в `LinksScreen.vue`; нет `LogChannelEnum` для production; api-cron не видит private-media — сборка в consumer.
- Граф: D06 → `acceptedImplementationDecisions`, `decisionEvidence.D06-2026-10-15`, MVP-D06-SYNC убран из `openLaunchDecisions`; F3 merged (PR #186, 68355a0); baseline 60bd97f; L1 `inProgress`.
- Проверка 15.10.2026: `python3 tools/verify-wave-graph.py docs/waves/graph.json` — exit 0: 59 волн, 122 API, 35 legacy; готовы из main J2, L1; негативные фикстуры отклонены.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| L1-T01…T16 | PENDING | — | реализация после согласования решений |
| L1-T17 | PENDING (граф — PASS) | 15.10.2026 | `verify-wave-graph.py` exit 0; код ещё не написан |
| L1-T18 | PENDING | — | после ревью |
