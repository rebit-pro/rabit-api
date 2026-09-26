# Issue #117 — bootstrap `morefoto.commerce` без установленного `morefoto.legal`

## Цель и контекст

[#117](https://github.com/rebit-pro/rabit-api/issues/117): при выкладке PR #112 (OPS legal) на stage
`migrate.sh up` на новом коде не стартовал. Bootstrap через `init.php` подключает `morefoto.commerce`, его
`include.php` требует установленный `morefoto.legal` и бросает `RuntimeException`. А регистрирует `morefoto.legal`
как раз миграция `Version20260925230001`, которая из-за этого не может запуститься. На stage круг обошли вручную
(`registerModule` на коде K3). Это issue-ветка, не волна: `docs/waves/graph.json` не меняется.

- Ветка: `codex/issues-117-commerce-legal-bootstrap`, worktree
  `/home/user/rabit-api-worktrees/issues-117-commerce-legal-bootstrap`.
- Base: `origin/main` `4fc9dce`.

## Установленные факты

- **Загрузка модулей.** `local/php_interface/init.php` подключает модули через `Loader::includeModule()` без проверки
  результата: `rebit.share`, `rebit.auth`, `morefoto.organization`, `rebit.notification`, `morefoto.legal`,
  `morefoto.access`, `morefoto.commerce`, `morefoto.payment`, `morefoto.support`. `morefoto.media` и
  `morefoto.handoff` явно не подключаются: их загружают `include.php` зависимых модулей. `init.php` грузится при
  любом bootstrap Bitrix, в том числе у `migrate.sh` и `install-module.sh`.
- **Bitrix `Loader::includeModule()`** (ядро `main/lib/loader.php`): модуль не в `b_module` → `false`, результат
  кешируется, `include.php` не выполняется. Сервисы из `.settings.php` регистрируются в `ServiceLocator` только при
  успешной загрузке модуля. `ModuleManager::registerModule()` сбрасывает кеш `Loader` для модуля.
- **Зависимость commerce от legal — только контракт.** В `morefoto.commerce` нет ни одного класса
  `Morefoto\Legal\*`. Используется `Rebit\Share\Application\Contract\Consent\ConsentRecorderInterface` в
  `di/orders.php` → `CreateOrderUseCase` (`constructorParams` — ленивое замыкание). Реализацию регистрирует
  `morefoto.legal/di/legal.php`.
- **Когда контракт реально нужен.** Только при создании `CreateOrderUseCase` — оформление заказа покупателем.
  Bootstrap, миграции, `DoInstall`, чтение каталога и заказов его не резолвят. Если `morefoto.legal` не
  зарегистрирован, bootstrap проходит, а оформление заказа падает на резолве сервиса (500 через общий error handler).
  Так же уже устроен `rebit.auth`: `AcceptAccessInvitationUseCase` зависит от того же контракта, а `rebit.auth`
  не требует `morefoto.legal` в `include.php`.
- **Порядок загрузки DI сохраняется.** `init.php` подключает `morefoto.legal` раньше `morefoto.commerce`, поэтому
  в рабочем runtime (FPM, консольные воркеры) реализация контракта зарегистрирована до первого заказа.
- **E2E этого не ловит.** `api/tools/e2e/prepare.php` применяет миграции без bootstrap `init.php` и регистрирует
  `morefoto.legal` миграцией раньше, чем устанавливает `morefoto.commerce`.

## Анализ похожих кругов

Модули, которые регистрируют миграции `migrations.foundation/`: `rebit.leadhunter` (`20260713120001`),
`morefoto.access` (`20260911200001`), `morefoto.organization` (`20260911210001`), `morefoto.support`
(`20260925150001`), `morefoto.legal` (`20260925230001`). Остальные `morefoto.*` ставит `DoInstall`
(`install-module.sh`), `rebit.notification` регистрируется вручную.

Круг возникает, когда модуль X уже в `b_module`, а обязательный для его `include.php` модуль Y ещё нет и Y
регистрирует миграция, которая сама запускается через bootstrap с X.

| `include.php` X | требует Y (регистрирует миграция) | Как ставится X | Итог |
|---|---|---|---|
| `morefoto.commerce` | `morefoto.legal` (`20260925230001`, последняя) | `DoInstall` задолго до legal | **Круг #117 — исправляется** |
| `morefoto.organization` | `morefoto.access` (`200001`) | миграция `210001` позже | нет круга |
| `morefoto.media`, `morefoto.handoff`, `morefoto.payment` | `morefoto.access`, `morefoto.organization` | `DoInstall`, который сам требует эти модули | нет круга: X нельзя поставить раньше Y |
| `morefoto.commerce` | `morefoto.access`, `morefoto.organization` | `DoInstall` без проверки зависимостей | теоретический риск; access/organization — самые ранние миграции, на stage и в E2E стоят раньше commerce |
| `morefoto.support` | `morefoto.access`, `morefoto.organization` | миграция `150001` позже | нет круга |

Обратная связка, не тот же круг (ниже — риск порядка установки, не исправляется в этой ветке):

- **`morefoto.support` → `morefoto.media`.** Миграция `20260925150001` регистрирует `morefoto.support`, а
  `include.php` support требует `morefoto.media`, который ставит только `DoInstall`. На чистой установке, если
  прогнать все миграции разом до `install-module.sh morefoto.media`, любой следующий bootstrap бросит
  `Required module is unavailable: morefoto.media`, в том числе сам `install-module.sh`. Выход без правки кода есть:
  поставить `morefoto.media` до миграции `20260925150001` (его таблицы создают более ранние миграции D1). Исправление
  требует явного подключения `morefoto.media` в `init.php` (сейчас его грузят commerce и support) — это другая
  правка bootstrap, предлагается отдельным issue.
- `morefoto.commerce/install/index.php` — единственный `DoInstall` без проверки зависимостей; в рамках #117 не
  меняется.

## Решения

- **R1.** Убрать `morefoto.legal` из обязательных модулей `morefoto.commerce/include.php` и оставить комментарий,
  почему legal не нужен при bootstrap. Жёсткая проверка остальных зависимостей сохраняется.
- **R2.** Не подключать `morefoto.legal` из commerce «мягко» (`includeModule` без исключения): порядок уже
  задаёт `init.php`, а скрытая загрузка чужого модуля только маскирует незарегистрированный модуль.
- **R3. Воспроизводимая проверка на реальном ядре — шаг в `api/tools/e2e/prepare.php`.** Стенд повторяет
  состояние stage перед выкладкой legal: применены все миграции, кроме `Version20260925230001`, установлены
  commerce/media/handoff/payment/support, `morefoto.legal` нет в `b_module`. Затем модули подключаются в порядке
  `init.php` (список читается из самого `init.php`) так же, как bootstrap `migrate.sh`, и только после этого
  применяется миграция legal и `DoInstall` legal. На старом коде `prepare.php` падает на этом шаге, на новом
  проходит. Сам `init.php` не подключается: он читает `.env` приложения, а стенд его не использует.
- **R4. Быстрая проверка — unit-тест `CommerceBootstrapTest`.** В отдельном PHP-процессе минимальный `Loader`
  отвечает по списку установленных модулей, как Bitrix по `b_module`; проверяется, что `include.php` commerce
  без `morefoto.legal` загружается и не запрашивает legal, а без настоящей зависимости (`morefoto.media`) по-прежнему
  бросает исключение. Отдельный процесс нужен, чтобы подставной `Bitrix\Main\Loader` не попал в другие тесты.

## Scope

- `api/public/local/modules/morefoto.commerce/include.php`.
- `api/public/local/modules/morefoto.commerce/tests/Unit/CommerceBootstrapTest.php` (новый).
- `api/tools/e2e/prepare.php` — порядок: миграция legal после установки модулей и bootstrap как в `init.php`.
- `docs/plans/issues-117-commerce-legal-bootstrap/{plan,progress}.md`.

## Исключено

- Круг/риск `morefoto.support` → `morefoto.media` и проверка зависимостей в `DoInstall` commerce — только описаны
  (см. выше), предлагаются отдельным issue.
- `init.php`, миграции, DI, frontend — без изменений. Деплой, merge, SSH — не выполняются.

## Риски и ограничения

- Без зарегистрированного `morefoto.legal` сайт теперь поднимается, но оформление заказа отвечает 500 (нет
  реализации `ConsentRecorderInterface`) и маршрутов legal нет. Раньше при этом падал любой запрос. Состояние
  возникает только если пропустить миграцию `Version20260925230001`; DI-smoke после переключения FPM его ловит.
- Шаг в `prepare.php` выполняется только полным `make test-e2e` (запускает координатор).

## Порядок деплоя (для PR)

1. `migrate.sh up` на новом коде — миграция `Version20260925230001` проходит при уже установленном commerce и
   регистрирует `morefoto.legal` (на stage это уже сделано вручную, повторно ничего не меняется).
2. Runtime-symlink `morefoto.legal` и `install-module.sh morefoto.legal` (идемпотентно).
3. Переключение FPM и воркеров, DI-smoke внутри FPM (`prolog_before.php` + резолв `CreateOrderUseCase`).
4. Для prod до переключения: `SELECT ID FROM b_module` — должны быть `morefoto.legal` и все модули, которые ставит
   `prepare.php`; если на prod нет `morefoto.media`/`morefoto.support`, ставить `morefoto.media` до миграции
   `20260925150001` (см. риск support → media).

## Checklist

- [x] Прочитать CLAUDE.md, AGENTS.md, issue, `include.php`, `init.php`, миграцию, `prepare.php`.
- [x] Проверить безопасность для DI и найти похожие круги.
- [x] R1: `morefoto.commerce/include.php`.
- [x] R4: `CommerceBootstrapTest` + негативный контроль (тест падает на старом `include.php`).
- [x] R3: шаг в `prepare.php`.
- [x] Быстрые проверки: PHPUnit unit/functional, PHPStan, php-cs-fixer, `php -l`.
- [ ] Commit, push, проверка дублей, PR `Closes #117`.

## Критерии приёмки

- На базе, где `morefoto.commerce` установлен, а `morefoto.legal` нет в `b_module`, bootstrap в порядке `init.php`
  не бросает исключение, и миграция `Version20260925230001` проходит и регистрирует модуль.
- `include.php` commerce по-прежнему отказывает при отсутствии настоящих зависимостей.
- Оформление заказа после миграций работает как раньше (реализация контракта из legal).
- Быстрые проверки зелёные, результаты в `progress.md`.

## Тест-кейсы

| ID | Предусловия / действие | Ожидаемый результат | Команда |
|---|---|---|---|
| T01 | Установлены share, access, organization, media, handoff; legal нет. Загрузить `include.php` commerce | Процесс завершается 0, legal не запрашивается | PHPUnit `CommerceBootstrapTest` |
| T02 | Нет `morefoto.media`. Загрузить `include.php` commerce | `RuntimeException: Required module is unavailable: morefoto.media` | то же |
| T03 | Негативный контроль: временно вернуть `morefoto.legal` в `include.php` | T01 падает | то же, затем откат |
| T04 | Unit-набор целиком | OK | `phpunit --testsuite=unit` |
| T05 | Functional-набор | OK | `phpunit --testsuite=functional` |
| T06 | PHPStan | `[OK] No errors` | `phpstan analyse --configuration=phpstan.neon` |
| T07 | php-cs-fixer по изменённым файлам | 0 файлов к исправлению | `php-cs-fixer fix --dry-run` |
| T08 | Стенд E2E: все миграции, кроме legal, модули установлены, legal нет в `b_module` → bootstrap в порядке `init.php` → миграция `Version20260925230001` → `DoInstall` legal | `prepare.php` проходит, дальше весь gate зелёный | `make test-e2e …` (координатор) |
