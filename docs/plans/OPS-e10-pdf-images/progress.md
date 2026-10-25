# OPS-e10-pdf-images — журнал

## Точка продолжения

- Ветка `codex/ops-e10-pdf-images`, worktree `/home/user/rabit-api-worktrees/ops-e10-pdf-images`, база main 8e3855e. PR ещё нет.
- Готово: S1, S2. Сейчас: S3 — полный гейт. Затем PR и решение владельца о merge и выкладке.
- Stage: FPM на `main-20261017192004-65bd194` (откат после smoke), релиз `main-20261025130650-8e3855e` подготовлен, но не включён.

## Журнал

### 2026-10-25

- DI smoke E10 на stage нашёл ошибку PCRE в PDF группы с 12 миниатюрами; FPM откатан (`docker service rollback`), сайт 200.
- S1: миниатюры передаются через `$mpdf->imageVars['childN']`, HTML ссылается на `var:childN`, помощник `mime()` удалён.
  Регрессионный тест `testAGroupWithRealSizedFramesStaysUnderTheHtmlLimit` (30 детей, JPEG-шум ≈20 КБ):
  на старом коде — `MpdfException: The HTML code size is larger than pcre.backtrack_limit 100000` (как на stage),
  на новом — PDF валиден, 30 изображений `/Subtype /Image`.
- S2: PHPUnit `SalesReport|GroupSales|GroupChildren` → OK 45/226; PHPStan → No errors; cs-fixer dry-run → 0 из 3.

## Результаты тест-кейсов

| ID | Статус | Дата | Доказательство |
| --- | --- | --- | --- |
| OPS-T01 | PASS | 2026-10-25 | PHPUnit, 30 изображений в PDF; на старом коде — ошибка PCRE |
| OPS-T02 | PASS | 2026-10-25 | `testTheGroupPageShowsChildrenStatusesAndRoubles`: `<img src="var:child0">`, нет data URI |
| OPS-T03 | PASS | 2026-10-25 | PHPUnit 45/226, PHPStan, cs-fixer |
| OPS-T04 | PENDING | — | полный гейт |
| OPS-T05 | PENDING | — | после выкладки |
