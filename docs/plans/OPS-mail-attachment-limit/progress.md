# OPS mail attachment limit — прогресс

## Точка продолжения

- Ветка `codex/ops-mail-attachment-limit` (worktree `/home/user/rabit-api-worktrees/ops-mail-attachment-limit`) от main 8293fcd. PR [#211](https://github.com/rebit-pro/rabit-api/pull/211).
- Сделано: миграция `Version20261017150001`, стенд E2E, проверка в `verify-notification.php`, README J5.
- Саморевью без блокеров, полный гейт PASS (`rabit-e2e-c8746f66918e`, ЮKassa выключена).
- Следующий шаг: по «да» владельца — merge и выкладка: релиз с миграцией → `migrate.sh up Version20261017150001` → DI smoke J5 (`OK main.max_file_size=30000000`).
- Блокеры: нет.

## Журнал

### 17.10.2026

- Владелец: «Да, поднимаем до 30 МБ, делай PR».
- Значение 30 000 000: самое большое письмо J5 после base64 — 27 962 028 байт, лимит письма Яндекса — 30 МБ.

### 17.10.2026 — PR и гейт

- PR #211, саморевью — блокеров нет.
- Гейт `rabit-e2e-45f78b07bec9` (ЮKassa включена):
  - FAIL только G1-T14 — СБП тестового магазина не ответил, известная #174; комментарий с наблюдениями добавлен в #174;
  - `durable Notification contract` с новой проверкой лимита прошёл.
- Гейт `rabit-e2e-c8746f66918e` с `E2E_YOOKASSA_ENV=/nonexistent/...`, как при D4 — **PASS**, 385 с:
  - 152 браузерных теста (a 81, b 71: сценарии тестового магазина в выключенном режиме);
  - все verify-*.php, `durable Notification contract` — лимит на стенде вмещает самое большое письмо J5.

## Тест-кейсы

| ID | Статус | Дата | Доказательство |
| --- | --- | --- | --- |
| MAL-T01 | PASS | 17.10.2026 | гейт `rabit-e2e-c8746f66918e`: `verify-notification.php` (durable Notification contract) |
| MAL-T02 | PENDING | | выкладка |
| MAL-T03 | PASS | 17.10.2026 | `php -l` миграции и верификатора; php-cs-fixer по миграции — 0 исправлений |
