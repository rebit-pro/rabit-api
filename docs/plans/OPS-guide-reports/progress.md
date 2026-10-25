# OPS-guide-reports — журнал

## Точка продолжения

- Ветка `codex/ops-guide-reports`, worktree `/home/user/rabit-api-worktrees/ops-guide-reports`, база main b575816. PR ещё нет.
- Готово: S1, S2, быстрые проверки S3. Сейчас: полный гейт, затем PR, merge и frontend-only выкладка (владелец: «Сделай деплой на сервер»).

## Журнал

### 2026-10-25

- Каталог первой попытки E10 `main-20261025130650-8e3855e` удалён с сервера по поручению владельца (452 МБ; на него не ссылались ни сервисы, ни предыдущие спецификации).
- S1:
  - скриншоты `r01…r07` — живой frontend `main` b575816 в Chromium с подменой API (Vite live + Playwright `page.route`), демо-данные «Детский сад № 15 «Солнышко»», миниатюры — SVG-заглушки; скрипт — scratchpad сессии `guideshots/shots.mjs`;
  - `r08` — первая страница PDF, собранного `MpdfSalesReportWriter` на тех же демо-данных, растрирована pymupdf;
  - WebP через Pillow, 22–46 КБ.
- S2:
  - статья `/guide/reports/` (7 разделов);
  - плитка на `/guide/`;
  - строка и раздел в `team`, заметки в `curator` (шаг «Заказы») и `organizer` (после шагов);
  - раздел «Отчёты» в `faq` (3 вопроса).
- S3, быстрые проверки:
  - новый тест `tests/ui/guide-links.test.mjs` (ссылки, картинки и якоря всех страниц guide);
  - `npm run check` PASS;
  - визуально — 1440 и 390 px без горизонтальной прокрутки, все 8 картинок загружаются после прокрутки, лайтбокс «8 из 8» открывает образец PDF.

## Результаты тест-кейсов

| ID | Статус | Дата | Доказательство |
| --- | --- | --- | --- |
| GR-T01 | PASS | 2026-10-25 | `node --test tests/ui/guide-links.test.mjs` (2) |
| GR-T02 | PASS | 2026-10-25 | Playwright на статике `frontend/public`: overflow false, broken [] после прокрутки, лайтбокс |
| GR-T03 | PENDING | — | `npm run check` PASS; полный гейт — запуск |
| GR-T04 | PENDING | — | после выкладки |
