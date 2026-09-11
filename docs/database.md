# Данные RaBit API

Активный набор Sprint Migration находится в `api/public/local/php_interface/migrations.foundation/`; его выбирает `migrations.cfg.php`.

| Объект | Владелец |
| --- | --- |
| Пользователь Bitrix и поля токена | Auth |
| `rebit_auth_registration_confirmation` | Auth |
| Почтовое событие регистрации | Auth |
| `RebitAuditLog` / `rebit_audit_log` | Share |
| `rebit_leadhunter_external_lead` | LeadHunter: заявки, результаты отбора и состояние доставки |
| Почтовое событие резервной доставки внешних заявок | LeadHunter |
| `REBIT_NOTIFICATION_LEAD` — событие и шаблон резервной доставки заявок с сайта | Notification |
| Базовые таблицы пользователей и файлов | Ядро Bitrix |

[Подробности семи миграций и ограничения](../api/public/local/php_interface/migrations.foundation/README.md). К четырём миграциям Auth/Audit возвращены `Version20260713120001` (регистрация LeadHunter и таблица заявок) и `Version20260715120001` (резервная доставка по email). Для Notification восстановлена активная `Version20260820120001` с почтовым событием и шаблоном резервной доставки заявок с сайта. Таблицы старой торговой очереди Notification/NotificationPreference для этого сценария не нужны. Восстановление файлов не выполняет миграции в существующей БД.

Историческая директория `migrations/` сохранена и исключена из набора по умолчанию. Существующие P2P-таблицы, регистрации модулей в БД и история не удалялись. Подготовка чистой БД и перенос существующих данных требуют отдельного плана; запускать rollback исторических миграций для этой цели нельзя.

Схема будущих объектов MoreFoto остаётся в [соседней документации](../../MoreFoto/docs/README.md).
