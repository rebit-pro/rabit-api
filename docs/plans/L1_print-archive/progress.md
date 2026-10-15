# L1 — архив оплаченной печати — прогресс

## Точка продолжения

- Дата: 15.10.2026. Ветка `codex/l1-print-archive`, base main cc92006 (после rebase); рабочая копия `/home/user/rabit-api-worktrees/l1-print-archive`. PR нет. План: `docs/plans/L1_print-archive/plan.md`; граф `docs/waves/graph.json` (L1 `inProgress`).
- Завершено: S0 (инвентаризация, граф), S1 — решения L1-DEC-01…08 приняты (все рекомендации), записаны в `decisionEvidence.L1-DEC-01-08`; MVP-LATE-01 закрыт, MVP-SIVMA-01 сужен до операционной части.
- Сейчас: S2 — контракты поставщиков (Commerce, Organization, Payment, Media).
- Следующий шаг: S3 — модуль `morefoto.production` (миграция, Domain, репозитории, DI).
- Блокеры: нет.
- Рабочее дерево: только документы (план, прогресс, граф, `docs/waves/README.md`).
- Проверка графа: `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

## Журнал

### 15.10.2026 — старт

- Выбор волны: готовы из main только L1 и J2 (G2 ждёт D09 и реквизиты; J2 поставлен на паузу владельцем 12.10, SMTP не работает — #161). Рекомендована L1; пользователь подтвердил D06: «D06 подтверждаю».
- Инвентаризация (Explore по main 60bd97f): модуля `morefoto.production` и таблиц нет; оплаченный снимок `mf_order_line` содержит всё для печати (PRODUCT_PUBLIC_ID/NAME/FORMAT, PRINT_COUNT, QUANTITY, PHOTO_PUBLIC_ID, CHILD_CODE); `UF_CLOSES_AT` = printSalesUntil, после закрытия неизменен; ничего не срабатывает по окончании продаж (crontab); `PaymentReconciler` даёт `latePayment`, но нет выборки открытых попыток по группе; `OriginalFilesInterface` без исходного имени; ZIP/хранилище/токен J1 привязаны к заказу (токен без отзыва); live-карточки группы нет — блок «Печать» в `LinksScreen.vue`; нет `LogChannelEnum` для production; api-cron не видит private-media — сборка в consumer.
- Граф: D06 → `acceptedImplementationDecisions`, `decisionEvidence.D06-2026-10-15`, MVP-D06-SYNC убран из `openLaunchDecisions`; F3 merged (PR #186, 68355a0); baseline 60bd97f; L1 `inProgress`.
- Проверка 15.10.2026: `python3 tools/verify-wave-graph.py docs/waves/graph.json` — exit 0: 59 волн, 122 API, 35 legacy; готовы из main J2, L1; негативные фикстуры отклонены.

### 15.10.2026 — решения приняты

- Пользователь: «рекомендации L1 принимаю все». Граф: `decisionEvidence.L1-DEC-01-08`; из `openLaunchDecisions` убран MVP-LATE-01 (закрыт DEC-02), MVP-SIVMA-01 оставлен только операционной частью (рабочий каталог, ручной порядок передачи).
- Rebase на main cc92006 (PR #194 и запись выкладки — только frontend/docs, пересечений нет); baseline графа → cc92006. `python3 tools/verify-wave-graph.py docs/waves/graph.json` — exit 0.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| L1-T01…T16 | PENDING | — | реализация после согласования решений |
| L1-T17 | PENDING (граф — PASS) | 15.10.2026 | `verify-wave-graph.py` exit 0; код ещё не написан |
| L1-T18 | PENDING | — | после ревью |
