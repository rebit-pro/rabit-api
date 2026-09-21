# F1 — списки детей сотрудников

F1 опубликована для review: [PR #25](https://github.com/rebit-pro/rabit-api/pull/25) из `codex/f1-staff-requests` в `main`. База `28bcad9e575489deb1113d7ce2ac459a43dd7132` подтверждена fetch 2026-09-21; B2, D2 и E3 уже слиты. Checkout — `/home/user/rabit-api`.

## Результат

`morefoto.handoff` реализует HND-06/07/08/09/12: список, карточку, создание, повторную подачу и запрос уточнения. Заявка хранит UUID, строки детей, серверный снимок права сотрудника, revision, историю и идемпотентный результат. Access подтверждает роль и назначения на каждом запросе; Organization и Media разрешают учреждение, съёмку, группу и ребёнка в доступной области. Чужая область даёт 404, неназначенный сотрудник — 403, stale revision и несовпадающий повтор ключа — 409.

Контроллер получает типизированный RequestDto и вызывает UseCase через stateless presentation mapper. В DTO только public readonly свойства и пустой constructor: валидация, преобразования, pagination и Location вынесены из DTO. Общая инфраструктура отвечает за Bearer, JSON/query/path/header, wire-типы, error response, no-store и Monolog. Глобальная конфигурация логов — `local/.settings_extra.php` → `local/php_interface/settings_extra.php`, канал — `handoff`.

Live frontend сохраняет заявки через API и показывает право сотрудника и историю. HND-10/11 и перенос фотографий остаются в D3. Рефакторинг ранее слитых контроллеров — [issue #24](https://github.com/rebit-pro/rabit-api/issues/24).

## Проверки — 2026-09-21

Полный `make test-e2e` на fixture `rabit-e2e-c27280e2987a`: PHP lint 591 файл, PHPStan 0 ошибок, PHPUnit 403 теста / 1492 assertions; frontend ESLint/typecheck, 158 unit-тестов и production build; реальные миграции Bitrix/MySQL и Notification contract; Chromium 43/43 без повторов и пропусков. Адресный PHPUnit F1 — 11/270; CS Fixer — 0 замечаний по 65 PHP-файлам diff. Стенд удалён без ошибок.

E2E проверяет создание через UI, чужого автора в списке/карточке, чужую группу, прямой POST неназначенного сотрудника, replay/conflict, revision, уточнение и повторную подачу с тремя событиями истории. Дополнительно проверены строгие типы JSON, подмена header/path, meta, Location и no-store. Desktop/mobile просмотрены; mobile проверяет отсутствие горизонтального переполнения. Полные результаты — [verification.json](verification.json), [visual.json](visual.json) и [журнал](../../plans/F1_staff_requests/progress.md).

Предыдущий FAIL E3 при reload не воспроизвёлся без изменения его теста; причина исторического сбоя не установлена. Первый новый прогон выявил повтор UUID строки в fixture второй заявки F1; исправлены данные теста, assertions сохранены.

## Воспроизведение

```sh
make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor
```

Прежний каталог используется только как источник лицензированного Bitrix-ядра. Код и vendor берутся из актуального checkout. Runner создаёт отдельные сети, тома, MySQL и RabbitMQ и удаляет только ресурсы своего запуска.

## Ограничения и подключение

В списочном чтении остаются отдельные запросы на карточку (N+1); нагрузочная приёмка не проводилась. В UI остаются «1 детей» и упоминание переноса полного набора до D3. Эти замечания сообщены разработчику; в DTO-правку они не включены.

Frontend подключается к same-origin `/api/v1` после установки модуля и foundation-миграции `Version20260920120001`. Merge и deployment выполняются отдельно после review. После появления рабочих заявок destructive rollback таблиц запрещён: откат приложения должен сохранять схему и данные до совместимого forward-fix.
