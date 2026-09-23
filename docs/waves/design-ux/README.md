# Пакет design-ux — дизайн-система и UX кабинета MoreFoto

Ветка `codex/u-design-system` от `main` `bb35665` (merge PR #50 с утверждённым [планом](../../plans/design-ux-plan/plan.md)). Журнал исполнения — [docs/plans/U_design-system](../../plans/U_design-system/progress.md).

Пакет доставки — исключение из правила «одна волна — один PR», принятое пользователем 2026-09-23 только для этой программы. Волны U1 → U2 → U3 → U4 → B4 → B3 → U5 → U6 → U7 → U8 выполняются одной веткой и сдаются одним PR; каждая волна — отдельный зелёный коммит с быстрыми проверками. Браузерные E2E, demo-регресс и визуальная проверка desktop/mobile выполняются один раз для всего пакета до merge.

## Граф

- `docs/waves/graph.json`: направление U, `deliveryBundles.design-ux`, у каждой волны пакета `deliveryBundle`; D01 принят (`decisionEvidence.DESIGN-UX`); D3 записан слитым (PR #46, `533c06b`; follow-up PR #49 и #52).
- `tools/verify-wave-graph.py`: ID `[A-NU]`, зависимость внутри пакета допустима для inProgress/review, пакет сливается целиком; негативные fixtures «зависимость вне пакета», «неизвестный пакет», «частичный merge пакета».
- Endpoint ID назначаются коммитами волн вместе с контрактами: B4 — AUTH-05…AUTH-09 и ACC-11 (`endpointCount` 99 → 105, N2 перепроверяет и их), B3 — ACC-07…ACC-10.

## Внешние изменения MoreFoto

Соседний `../MoreFoto` без git; воспроизводимый diff — [morefoto-contract.patch](morefoto-contract.patch) (16 файлов) относительно состояния до пакета: канонический `backend-waves.json`/`.md`, `wave_graph.py`, `render-waves.py` (раздел «Пакеты доставки», счётчики из `endpointCount`), `build.py`/`validate.py` (ID `[A-NU]`; контракты B4 AUTH-05…AUTH-09, ACC-11 и фильтр `accountStatus` в ACC-02), пересобранные `endpoints.json`, README, Postman-коллекции и окружения (`invitation_token`, `reset_token`, `new_password` — секретные и пустые), `verification.json`, счётчик операций в `04-bitrix-modules/README.md`, ссылка на v2 в `frontend/design-plan.md`, запись о D01/D08 в `frontend/business-review/decisions.md`.

Проверка после применения: `python3 docs/04-bitrix-modules/wave_graph.py docs/04-bitrix-modules/backend-waves.json`, `python3 docs/05-rest-api/build.py`, `python3 docs/05-rest-api/validate.py`, `node docs/05-rest-api/validate-postman.cjs`.

## Статус

| Волна | Коммит | Состояние |
| --- | --- | --- |
| U1 | `a144f1e` очистка frontend, `de5a5f0` граф | готово, быстрые проверки |
| U2 | `d7c3661` токены, шрифты, тема | готово, быстрые проверки |
| U3 | `1a944fa` логотип, каркас, навигация, аватар на инициалах | готово, быстрые проверки |
| U4 | `c001fc5`, `eedc437`, `6962cf9` поверхности, статусы, hex → токены, SVG-иконки | готово, быстрые проверки |
| B4 | `3295ad1` backend, `92ae90d` экраны, `717136b` «Что дальше» и фильтр статуса, коммит реестра AUTH-05…09/ACC-11 | готово, быстрые проверки |
| B3, U5–U8 | — | запланированы в пакете |

Отчёт о финальном gate и визуальные артефакты добавляются сюда после всех волн.
