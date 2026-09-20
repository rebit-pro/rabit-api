# Текущий HTTP API RaBit API

В исходниках базовых модулей объявлены семь маршрутов:

| Метод | Путь | Модуль / action |
| --- | --- | --- |
| POST | `/api/v1/auth/login` | `rebit.auth` / `AuthController::loginAction` |
| POST | `/api/v1/auth/register/request-code` | `rebit.auth` / `AuthController::requestRegistrationCodeAction` |
| POST | `/api/v1/auth/register/confirm` | `rebit.auth` / `AuthController::confirmRegistrationAction` |
| POST | `/api/v1/auth/logout` | `rebit.auth` / `AuthController::logoutAction` |
| POST | `/api/v1/share/file/upload/` | `rebit.share` / `FileController::uploadAction` |
| POST | `/api/v1/lead` | `rebit.notification` / `LeadController::submitAction` |
| POST | `/api/v1/lead/mos-dizel` | `rebit.notification` / `MosDizelLeadController::submitAction` |

Конечный slash у upload сохранён из существующего контракта. Единый versioned реестр `api/public/local/routes/rabit-api.php` подключает `routes.php` этих модулей. `local/.settings_extra.php` подключает настройки из `local/php_interface/settings_extra.php`; секция routing переопределяет старый список в ядре. При добавлении будущих модулей реестр расширяется явно. `ModuleRoutingTrait` создаёт совместимые symlink-файлы, но не изменяет этот readonly-реестр. Установка модуля сама по себе не публикует новый маршрут; перед удалением любого сохранённого модуля нужно убрать его из реестра и bootstrap.

`POST /api/v1/lead` — публичная форма сайта, вход `multipart/form-data`. Обязательны `name`, `phone`, `description`; опциональны `email`, `page`, `source` и вложение `file`. Скрытое поле `company` служит honeypot: заполненная форма принимается без доставки. Валидация контактов и файла выполняется до вызова доставки. Notification отправляет заявку в Telegram синхронно; резервный email используется при сбое Telegram и наличии получателя в настройках. Ветка не хранит заявку в очереди и не требует notification consumer. HTTP-успех не следует считать универсальной гарантией доставки: honeypot намеренно не отправляет сообщение.

`POST /api/v1/lead/mos-dizel` принимает тот же `multipart/form-data` контракт и отправляет письмо напрямую, без Telegram. Получатель берётся только из серверной переменной `REBIT_NOTIFICATION_MOS_DIZEL_EMAIL`; HTTP-запрос не может его переопределить. Для письма используется отдельное событие `REBIT_NOTIFICATION_MOS_DIZEL_LEAD` из миграции `Version20260915110001`.

Наличие маршрута подтверждено по коду; работа HTTP на развёрнутом Bitrix и доставка email в этой задаче не проверялись. Известные ограничения Auth/File перечислены в [аудите](audit-rabit-api.md).

Полный будущий API MoreFoto: [контракты и Postman](../../MoreFoto/docs/05-rest-api/README.md). Общий инвентарь теперь содержит 99 операций: исходные 97 плюс SHR-01 (этот upload) и NTF-01 (этот Lead). Продуктовые маршруты остаются проектом; новые продуктовые операции не добавлены. [35 волн реализации](../../MoreFoto/docs/04-bitrix-modules/backend-waves.md) определяют зависимости и критерии подключения.
