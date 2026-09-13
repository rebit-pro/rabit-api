# C4 — полная карточка учреждения

Статус: готово к ревью, обязательная локальная приёмка завершена. Ветка codex/c4-institution-detail от main 8962230accdc8fe913ef0a12211bee14c332fdd2. Зависимость C3 фактически слита 13 сентября 2026 в PR15; D08 и A6 union приняты ранее.

## Результат волны

GET /api/v1/institutions/{institution_id} (ORG-04) возвращает реальные реквизиты, curatorId/headId, съёмки и группы всех съёмок учреждения. Списки независимо пагинируются shootsPage/groupsPage, pageSize 1–100 (сервер default50, UI25); сортировка createdAt desc + id. Каждый список содержит items и meta с полным total. Пустой список допустим только при фактическом отсутствии данных или выходе за последнюю страницу.

Организатор получает assignmentSignature и прежние C3 редакторы. Куратор/руководитель читают только назначенную область без mutation signature; воспитатель не имеет ORG-04. Ссылки к organizer-only редактору съёмки доступны организатору.

Финансовая сводка использует принятый A6 контракт availability=unavailable, reason=dependenciesNotReady. Финансовый поставщик ещё не подключён; готовые суммы и ready появятся с реальным владельцем в N1. Ошибка структурного чтения не заменяется пустым массивом.

## Проверка

Обязательны PHP lint/style/PHPStan/PHPUnit, native Bitrix/MySQL ORG04/scope/query/pagination/ошибки, frontend check/build/unit, полный live Chromium E2E C3+A8+C4 на свежей БД и desktop/mobile visual. Затронутая demo Organization @r07 проверяется отдельно. Пройдены: PHP lint405, style16, PHPStan0, PHPUnit350/1102; native C4 99, C2 138, C3 198; frontend check/build и155 unit; live E2E33/33 без retry/skip; demo R07 21/48; визуально просмотрены4 desktop/mobile снимка. Точные результаты — verification.json и связанные отчёты.

Карта frontend: InstitutionScreen компонует состояние и прежний редактор; InstitutionOverview показывает реквизиты/назначения/сводку; InstitutionCollection — отдельный список/пагинацию; useInstitutionPage управляет загрузкой, повторами и устаревшими ответами. Demo остаётся отдельным режимом.

Временные ресурсы создаются runner tools/run-browser-e2e.py и очищаются по owner-label. Source patch канонического MoreFoto и SHA256 сгенерированных документов приложены: morefoto-plan-sync.patch/morefoto-sync.json. C4 опубликована в [PR16](https://github.com/rebit-pro/rabit-api/pull/16), ожидает ревью и не слита; deployment не выполнялся.

[Памятка для ручной проверки по всем готовым волнам](../../testing/manual-wave-checklist.md) содержит тестовые входы, подготовленные данные и отдельный локальный стенд, оставленный для проверки 13 сентября 2026.

## Повторная проверка

Из корня C4:

```sh
bash api/tools/run-c4-institution-detail-integration.sh
make e2e-up
make e2e-test E2E_STATE=/absolute/path/to/state.json
make e2e-down E2E_STATE=/absolute/path/to/state.json
```

Runner требует новую БД для полного live-набора, генерирует изолированный Composer autoload и выполняет frontend/backend проверки до запуска браузера. Настройки образов/kernel/vendor — в docs/waves/a8/README.md. Для C2 standalone native установить W02_PHP_IMAGE=rabit-api-php-fpm:20260911-074507. Demo R07 запускается прежней командой из docs/waves/c3/README.md, во внутренней Docker-сети с настоящим NIC.

C4 добавляет read-only endpoint без новых миграций. Подключение экрана — production-сборка frontend с VITE_API_MOCKS_ENABLED=false после отдельно принятого merge. Откат приложения не требует изменения данных.
