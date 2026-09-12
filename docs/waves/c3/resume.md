# Продолжение C3

Рабочая копия: `/home/user/rebit-p2p/api/var/worktrees/c3`, ветка `codex/c3-shoots-groups-calendar`.
Начальная база: `895fee9873cd62316579d18512c8b2e3315c3d62`; PR A8 №14 всё ещё открыт. Слияние A8 отдельно запрошено, ответа пока нет. Не копировать frontend/E2E A8 в C3 до его merge; не сливать и не развёртывать автоматически.

## Реализовано

Organization ORG-06/07/08/09/10/12, новые миграции 20260913010001/2, teacher assignments Access, внутренний GroupCalendarInterface и ChangeGroupCalendarUseCase. Политика транзакций: AccessState → Institution → Shoot → Group → StaffProfile → Auth. Обычный PATCH не меняет календарь. Назначение занятого воспитателя требует reason и signature; новый токен не отзывается при replay.

`api/tools/run-c3-structure-integration.sh` запускает отдельную временную MySQL без host ports. Главный native runner подключает `fixtures/c3/{bootstrap,http-checks,access-checks,calendar-checks,concurrency-checks,worker}.php`. Это настоящий Bitrix/DB/Router; транспорт HTTP в CLI подаёт php://input и не заменяет браузерный E2E.

## Окружение

Docker 29.4.1 работает через Ubuntu. Из-за повторного сбоя AF_UNIX сокетов 13 сентября сохранены `Docker/run.c3-backup-*` и `docker-secrets-engine.c3-backup-*` в Windows LocalAppData. Запуск Docker из Windows-каталога после готовности Ubuntu восстановил интеграцию. Данные контейнеров/томов не сбрасывались.

PHP образ: `rabit-api-php-fpm:20260911-074507` (8.4.25), Bitrix `/home/user/rebit-p2p/api/public/bitrix` (25.750.0), MySQL 8.0.45. У C3 собственный игнорируемый api/vendor после composer dump-autoload. Старый главный vendor имеет неполный Morefoto autoload; для полных checks использовать C3 vendor. Docker composer нужен только для autoload, новые зависимости не устанавливались.

Полный PHPStan запускать одним процессом `--debug`: параллельный анализ видит все ядра хоста и получил OOM с лимитом 1 GiB, однопроцессный прошёл без ошибок. PHP lint:401 файлов, PHPUnit:341 тест/1089 assertions на промежуточном проверенном состоянии; после последних изменений проверки повторить по необходимости. Native C2 и E2 регрессии прошли. Точные финальные результаты хранятся в verification.json после завершения.

## Оставшаяся приёмка

1. Backend-проверки завершены: native C3 192, C2 136, E2 95, PHPUnit 341/1089, PHPStan/style — PASS. Дефект CUser nested rollback найден и исправлен SQL-очисткой токена в Auth; см. verification.json.
2. Итоговый backend diff и отчёты сохранены в локальном checkpoint C3. Не публиковать волну как готовую до браузерной приёмки.
3. После отдельно разрешённого merge A8 обновить базу C3 до main. Подключить существующий редактор Organization к реальному C3 API, без mock-фолбэка. B2 employee UI не реализован: не выдумывать список сотрудников; используй только доступные реальные контракты либо оставь назначение отдельным явно ограниченным внутренним сценарием до B2.
4. Реальный браузерный E2E + desktop/mobile визуальная проверка C3. Неподготовленные ORG-04/11 и соседние модули не включать. Повторить затронутые проверки после обновления базы.
5. Одна ветка C3 и один PR в main; до браузерной проверки C3 не объявлять завершённой. Merge/deployment отдельными действиями после ревью.

Канонический MoreFoto не является Git-репозиторием. Его build.py/backend-waves.json обновлены для C3 inProgress и уточнённых контрактов; source patch и SHA256 сгенерированных документов — morefoto-plan-sync.patch/morefoto-sync.json. Граф:40 волн,99API,35исторических волн; канонические DAG/REST/Postman проверки пройдены.
