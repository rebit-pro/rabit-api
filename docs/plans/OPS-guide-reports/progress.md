# OPS-guide-reports — журнал

## Точка продолжения

- Ветка `codex/ops-guide-reports`, worktree `/home/user/rabit-api-worktrees/ops-guide-reports`, base main `b575816`, head `642813de4741e81168b1e601614b1f60a938cc52`, PR #225.
- Завершено: скриншоты и ссылки. Найден блокер состава PR: `frontend/public/guide/reports/index.html` существует локально, но игнорируется правилом `reports` в `frontend/.gitignore` и отсутствует в head PR.
- Сейчас: исправление статьи опубликовано в PR #225, тест ссылок PASS 2/2. Один следующий шаг: после восстановления локального Docker Desktop повторить полный интеграционный gate.
- Блокеры: полный E2E на текущем стенде пока не завершён из-за зависших операций Docker Desktop; merge и релиз не выполнены.
- Рабочее дерево: после push `642813d` было чистым; текущая запись — единственное изменение.
- Следующие проверки: `node --test tests/ui/guide-links.test.mjs`; `git ls-tree -r --name-only HEAD frontend/public/guide/reports/`; после восстановления Docker `make test-e2e`.

## Журнал

- 2026-11-01: исправление `642813d` отправлено в PR #225 по HTTPS через gh credential helper. На интеграционном кандидате файл виден в `git ls-tree`, `node --test tests/ui/guide-links.test.mjs` PASS 2/2. Полный E2E остаётся заблокирован локальным Docker Desktop; merge/deploy не выполнены.

### 2026-11-01 — блокер состава PR

- На интеграционном кандидате `node --test tests/ui/guide-links.test.mjs` дал FAIL: статья `guide/reports/index.html` отсутствует. `git check-ignore -v` показал правило `frontend/.gitignore:15:reports`; файл есть в рабочем дереве ветки, но не в Git. Ранее записанный PASS был получен с локальным игнорируемым файлом и не доказывал состав PR. План обновлён до исправления.
- `git add -f public/guide/reports/index.html` и `node --test tests/ui/guide-links.test.mjs`: PASS 2/2; файл опубликован в commit `642813d` PR #225.


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
| GR-T01 | PASS | 2026-11-01 | После `git add -f public/guide/reports/index.html`: `node --test tests/ui/guide-links.test.mjs` — 2/2. До исправления в составе PR был FAIL 1/2. |
| GR-T02 | PASS | 2026-10-25 | Playwright на статике `frontend/public`: overflow false, broken [] после прокрутки, лайтбокс |
| GR-T03 | BLOCKED | 2026-11-01 | Ранее `npm run check` PASS в исходном worktree; интеграционный `make test-e2e` остановился до тестов из-за зависания Docker network create. |
| GR-T04 | PENDING | — | после выкладки |
