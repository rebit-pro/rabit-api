# Продолжение после C3

Рабочая копия: `/home/user/rebit-p2p/api/var/worktrees/c3`, ветка `codex/c3-shoots-groups-calendar`.
A8 #14 слита с разрешения пользователя; актуальная проверенная база C3 — `b01be326e607c5cfafaeff587f29048f6802a636`. C3 перебазирована без конфликтов и прошла обязательную приёмку на main плюс только собственный diff.

## Готовый результат

ORG-06/07/08/09/10/12, миграции 20260913010001/2, назначения воспитателя через Access, внутренние календарные команды Organization и реальный редактор «учреждение → съёмки → группы». Порядок блокировок: AccessState → Institution → Shoot → Group → StaffProfile → Auth. Обычный PATCH не меняет сроки, тип и родителя. Повтор команды не отзывает новые сессии.

Frontend сохраняет точный pending method/path/body/key до запроса, повторяет его после reload и повторного входа; конфликт ревизии сохраняет ввод до явного обновления. Учётные записи/assignment-options B2, ORG-04/11 и публичные команды Handoff/Support не выдумываются.

## Проверки

PHP lint 401, style 68 изменённых PHP, PHPStan 0 ошибок, PHPUnit 341/1089; native C3 198, C2 138, каталог E2 95. Frontend check/build, 155 unit-тестов; live Chromium E2E 25/25 на свежей MySQL без mock, retry и skip. Затронутый demo R07: 21 сценарий / 48 шагов. Просмотрено 6 desktop/mobile снимков. Точные результаты — verification.json и связанные отчёты; неуспешные предварительные попытки — browser.json, demo.json, known-issues.md.

Docker работает через Ubuntu. PHP образ rabit-api-php-fpm:20260911-074507 (PHP 8.4.25), Bitrix 25.750.0, MySQL 8.0.45, Playwright v1.52.0. PHPStan запускать одним процессом --debug с лимитом памяти. Browser harness создаёт и удаляет только собственные ресурсы. Для demo Docker --network none не подходит: Chromium действительно становится offline.

## Следующий шаг

Одна C3 — одна ветка и один PR в main. После ревью нужно отдельное решение о merge C3; deployment не выполнялся. C4 (полная карточка учреждения, ORG-04) разблокируется только после merge C3. При изменении main перед слиянием повторить затронутые проверки. C4 начинать новой веткой от принятого main, не stacked PR.

Канонический MoreFoto не является Git-репозиторием. Его source patch и SHA256 сгенерированных документов сохранены в morefoto-plan-sync.patch/morefoto-sync.json. Граф C3 — review, не merged; 40 волн, 99 API ID, 35 исторических волн.
