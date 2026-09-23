# Пакет design-ux — дизайн-система и UX кабинета MoreFoto

Ветка `codex/u-design-system` от `main` `bb35665` (merge PR #50 с утверждённым [планом](../../plans/design-ux-plan/plan.md)). Журнал исполнения — [docs/plans/U_design-system](../../plans/U_design-system/progress.md).

Пакет доставки — исключение из правила «одна волна — один PR», принятое пользователем 2026-09-23 только для этой программы. Волны U1 → U2 → U3 → U4 → B4 → B3 → U5 → U6 → U7 → U8 выполняются одной веткой и сдаются одним PR; каждая волна — отдельный зелёный коммит с быстрыми проверками. Браузерные E2E, demo-регресс и визуальная проверка desktop/mobile выполняются один раз для всего пакета до merge.

## Граф

- `docs/waves/graph.json`: направление U, `deliveryBundles.design-ux`, у каждой волны пакета `deliveryBundle`; D01 принят (`decisionEvidence.DESIGN-UX`); D3 записан слитым (PR #46, `533c06b`; follow-up PR #49 и #52).
- `tools/verify-wave-graph.py`: ID `[A-NU]`, зависимость внутри пакета допустима для inProgress/review, пакет сливается целиком; негативные fixtures «зависимость вне пакета», «неизвестный пакет», «частичный merge пакета».
- Endpoint ID B3 (ACC-07…ACC-10) и B4 (AUTH-05…AUTH-09, ACC-11) назначаются коммитами B3/B4 вместе с контрактами.

## Внешние изменения MoreFoto

Соседний `../MoreFoto` без git; воспроизводимый diff — [morefoto-contract.patch](morefoto-contract.patch) (12 файлов): канонический `backend-waves.json`/`.md`, `wave_graph.py`, `render-waves.py` (раздел «Пакеты доставки»), `build.py`/`validate.py` (ID `[A-NU]`), пересобранные `endpoints.json`, Postman и `verification.json`, ссылка на v2 в `frontend/design-plan.md`, запись о D01/D08 в `frontend/business-review/decisions.md`.

Проверка после применения: `python3 docs/04-bitrix-modules/wave_graph.py docs/04-bitrix-modules/backend-waves.json`, `python3 docs/05-rest-api/build.py`, `python3 docs/05-rest-api/validate.py`, `node docs/05-rest-api/validate-postman.cjs`.

## Статус

| Волна | Коммит | Состояние |
| --- | --- | --- |
| U1 | U1a очистка frontend, U1b граф | в работе |
| U2–U8, B3, B4 | — | запланированы в пакете |

Отчёт о финальном gate и визуальные артефакты добавляются сюда после всех волн.
