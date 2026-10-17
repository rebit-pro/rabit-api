# OPS mail attachment limit — прогресс

## Точка продолжения

- PR [#211](https://github.com/rebit-pro/rabit-api/pull/211) merged 163afb7 (17.10.2026); на stage применена миграция `Version20261017150001`, `main.max_file_size` = 30 000 000.
- Задача завершена. Письма заказа на stage выключены до #161; после включения письма J5 с фото проходят лимит Bitrix.
- Откат: `migrate.sh down Version20261017150001` в релизе `main-20261017131534-163afb7` (вернёт 20 000 000); резервная копия базы — `database-before.sql.gz` релиза.

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

### 17.10.2026 — merge и выкладка

- PR #211 смержен по «да» владельца (163afb7).
- Релиз `main-20261017131534-163afb7` — только миграция (генератор — scratchpad `limitrel/mkrelease.sh`):
  - `prepare-release.sh` — vendor из J5-релиза;
  - `backup.sh` — 283 740 байт, `restore-check.sh` — 183 таблицы;
  - `migrate.sh up Version20261017150001` — success, Installed 39 → 40.
- Сервисы остаются на `main-20261017121241-4fe304b`: код отличается только миграцией и файлами стенда E2E, frontend не менялся.
- DI smoke J5 в FPM: 17/17, `OK main.max_file_size=30000000`, отправка писем выключена.

## Тест-кейсы

| ID | Статус | Дата | Доказательство |
| --- | --- | --- | --- |
| MAL-T01 | PASS | 17.10.2026 | гейт `rabit-e2e-c8746f66918e`: `verify-notification.php` (durable Notification contract) |
| MAL-T02 | PASS | 17.10.2026 | stage: `migrate.sh up Version20261017150001`, DI smoke `OK main.max_file_size=30000000` |
| MAL-T03 | PASS | 17.10.2026 | `php -l` миграции и верификатора; php-cs-fixer по миграции — 0 исправлений |
