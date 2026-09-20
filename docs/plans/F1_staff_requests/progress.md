# F1 — progress

## Точка продолжения

- Ветка: `codex/f1-staff-requests`.
- Base: `main` / `28bcad9e575489deb1113d7ce2ac459a43dd7132`.
- Сейчас: реализация HND-06/07/08/09/12 и полный локальный gate завершены; деплоя не было.
- Далее: проверить итоговый diff, создать коммит и PR F1 для отдельного code review.
- Блокеры: отсутствуют; D05 частично открыт только для перечня товаров, что не входит в F1.
- Working tree: реализация F1, E2E и документация ещё не закоммичены.
- Точная команда проверки состояния: `git status --short --branch`.

## Хронология

### 2026-09-20 — старт

- D2, H1 и D1 MOS diesel/email lead влиты в `main`.
- Основной checkout `/home/user/rabit-api` обновлён до `28bcad9` и переключён на `codex/f1-staff-requests`.
- По календарю F1 — третья волна 20 сентября, 18:00–20:00 МСК.
- Сверены HND-06/07/08/09/12, зависимости B2/D2/E3 и решения D05/D08/D11.
- Зафиксировано исключение HND-10/11 и любого переноса фото до D3.
- Составлена карта frontend-компонентов и acceptance/test IDs.

### 2026-09-20 — реализация и локальная приёмка

- Добавлен модуль `morefoto.handoff` с заявкой, строками, историей, optimistic lock и идемпотентностью.
- Подключены узкие серверные контракты Access, Organization и Media; право сотрудника и область проверяются на каждом запросе.
- Live frontend переведён с demo-сервиса на HND-06/07/08/09/12; действие переноса D3 в live-режиме скрыто.
- Исправлен live route guard для страниц списков ролей organizer/curator/teacher.
- Новый E2E проверяет создание воспитателем, replay/conflict, чужую группу, stale revision, отказ неназначенному пользователю, уточнение куратором, повторную подачу и историю.
- Адресный Chromium-сценарий прошёл: 1/1 за 10,2 с.
- Desktop 1280 px и mobile 390 px просмотрены; mobile assertion подтверждает отсутствие горизонтального overflow.
- На итоговом self-review вечная уникальность ребёнка заменена блокировкой его строки в транзакции и индексом: активные заявки защищены от гонки, а D3 сможет разрешить новую заявку после `transferred`.
- Окончательный disposable gate после этой правки прошёл на `rabit-e2e-679467cf9660`; `stopped=true`, `cleanupErrors=[]`.

## Проверки

- PHP lint: 566/566 файлов.
- PHPStan: 0 ошибок.
- PHPUnit: 394 теста, 1238 assertions.
- F1 unit: 2 теста, 16 assertions.
- Frontend: ESLint, Vue typecheck и E2E TypeScript — пройдены.
- Frontend unit: 158/158.
- Production build с реальным API: пройден.
- Чистая MySQL-схема, миграции, fixture и Notification integration с RabbitMQ: пройдены.
- Chromium: 43/43, failed 0, skipped 0, flaky 0.
- PHP CS Fixer: 35 изменённых PHP-файлов, исправлено 14; финальный dry-run — 0 исправляемых файлов.

## Артефакты

- Отчёт: `docs/waves/f1/README.md`.
- Машиночитаемый gate: `docs/waves/f1/verification.json`.
- Visual evidence: `docs/waves/f1/visual.json`.
- Локальные изображения: `frontend/reports/e2e-live/artifacts/zzz-handoff-F1-воспитатель-555f7-р-сохраняет-право-и-историю-chromium/`.

## Повторная проверка

```bash
python3 tools/run-browser-e2e.py run --php-cli rabit-api-php-cli:d1-local --php-fpm rabit-api-php-fpm:d1-local
```
