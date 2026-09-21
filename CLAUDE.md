# RaBit API — Coding Context

Отвечать всегда на русском языке.
Писать код как Senior PHP. SOLID, KISS, DRY, без оверкодинга. Согласуй изменения с разработчиком. Баги/опечатки не по теме — тоже сообщай.

## Stack & Namespaces

DevOps: Docker, Docker Compose, Docker Swarm, MySQL, Redis, RabbitMQ.
Programmer: PHP 8.4, Bitrix D7, Makefile.

## PHP Style

- `declare(strict_types=1)` везде, строгая типизация (аргументы, return, свойства)
- Yoda style: `null === $value`, `'' !== $string`
- `final readonly` классы где возможно; `match` вместо `switch`
- Enum case: `case FIRST_ELEMENT = 'firstElement'`
- Явные проверки вместо `empty()`: `[] === $array`, `null === $value`. Исключение: `!$bool`, конкатенация
- Cast без пробела: `(int)$value`; PSR-12 скобки метода на новой строке
- Замыкания/стрелки: типы аргументов, возврата, `static` если применимо
- Форматирование: `api/public/local/php-cs-fixer.php`
- phpDoc массивов в стиле phpStan с переносом:

```php
/** @var array<int, array{
 *     id: int,
 *     name: string,
 * }> */
```

## Архитектура

Слои: `Domain`, `Application`, `Infrastructure`, `Presentation`, `Shared`.

- `ServiceLocator` — только в DI-конфигах и bootstrap
- Зависимости через конструктор (constructor property promotion)
- DTO/VO — `final readonly`, named arguments для сложных вызовов
- DTO содержат только public readonly свойства и пустой конструктор с их сигнатурой. Методы, вычисления, валидация и сериализация внутри DTO запрещены; преобразования выполняют отдельные mapper-классы.
- Hot path: без лишних циклов, `array_merge`, spread, промежуточных массивов
- Инфра-исключения → предметные исключения Application/Domain
- Legacy/Bitrix-код → `Infrastructure` или bootstrap
- Сложные массивы: обязателен phpDoc с shape-типами

**Выбор слоя:**
1. Bitrix (кроме ORM) / Elastic / HTTP / `Infrastructure`
2. API-ответ → `Presentation`
3. Предметное правило → `Domain`
4. Сценарий через порты → `Application` (UseCase)

Антипаттерны: SQL в UseCase, бизнес-логика в Builder, форматирование в UseCase.

## Чистые HTTP-контроллеры и логирование

- Concrete controller работает только с типизированными presentation `*RequestDto`, `*ResultDto`, UseCase, stateless presentation mapper для преобразования DTO и API базового controller.
- Один action принимает не более одного request DTO. Query, JSON body, route-параметры и разрешённые headers собирает infrastructure mapper до action.
- В concrete controller запрещены `Bitrix\\*`, `HttpRequest`, `Application::getCurrentRoute()`, `ServiceLocator`, ручной разбор payload, request factory над `HttpRequest`, auth/filter/logger/serializer/request-id и собственный exception mapping.
- Bearer, pre/post filters, `Cache-Control`, единый error response и HTTP-логирование находятся в общей infrastructure-обвязке. Controller не создаёт `LoggerFilter` или Monolog handler.
- Успешный action только передаёт DTO в UseCase и оформляет ответ общими `json()`/`createdJson()`/`noContent()`; предметные проверки остаются в Application/Domain.
- Monolog настраивается глобально в `local/php_interface/settings_extra.php`, подключённом через `local/.settings_extra.php`. Новые модули обязаны иметь осмысленный `LogChannelEnum`; токены, headers и raw payload не логируются.
- Новый или изменённый controller не готов к review, если содержит технологическую сборку либо не имеет unit/architecture-проверки границы и HTTP/E2E-проверки контракта.
- Ранее слитый загрязнённый controller не копируется как эталон: для него создаётся отдельный follow-up issue, а новая волна сразу следует этому правилу.

## Структура модуля

```
local/modules/rebit.<name>/
  di/Layers/{Infrastructure,Presentation,Shared}.php   # слоевые DI
  di/<Domain>.php                                       # предметные DI
  events/events.php                                     # События
  install/components/
  lib/{Application,Domain,Infrastructure,Presentation,Shared}/
  include.php       # bootstrap
  routes.php        # HTTP-маршруты
  .settings.php     # подключает di/, отдаёт Bitrix
```

## DI-конфигурация

Все сервисы — Singleton. Без изменяемого состояния — данные через аргументы методов.
`.settings.php` подключает `di/` файлы через `array_merge`. Эталон: `rebit.auth/.settings.php`

```php
// di/Foo.php — concrete class
GetFooUseCase::class => [
    'className' => GetFooUseCase::class,
    'constructorParams' => static fn(): array => [
        ServiceLocator::getInstance()->get(FooGatewayInterface::class),
    ],
],
```

**Interface-key с ручной сборкой** — использовать `constructor`, не `className + constructorParams` (Bitrix игнорирует `constructorParams` для interface-key):

```php
FooGatewayInterface::class => [
    'constructor' => static fn(): FooGatewayInterface => new ElasticFooGateway(
        ServiceLocator::getInstance()->get(ElasticClientFactory::class)->create(),
    ),
],
```

`className` для interface-key допустим только при автопроводке без спец. аргументов.

## Именование DTO

| Контекст | Суффикс                                                      |
|----------|--------------------------------------------------------------|
| Application вход/выход | `*InputDto` / `*OutputDto`                                   |
| Controller / API | `*RequestDto` (implements `RequestDtoInterface`)             |
| Общий технический | `rebit.share/lib/Application/Contract/<Capability>/` |
| Предметный межмодульный | `rebit.share/lib/Contracts/<Domain>/Dto/` |
| Cache | `Application/<Domain>/Enum/*CacheEnum` (value=ключ, `ttl()`) |

## Профиль RaBit API

- RaBit API — основной backend проекта MoreFoto36.ru. Техническое имя и актуальная папка: `rabit-api`; Linux/WSL: `/home/user/rabit-api`, Windows: `\\wsl.localhost\Ubuntu\home\user\rabit-api`. `rebit-p2p` — прежнее имя, не использовать его как текущий checkout.
- Модули `rebit.*` — общая основа RaBit API: Share, Auth, Dev, Notification, LeadHunter и другие инфраструктурные возможности. Специализация продукта MoreFoto реализуется в `morefoto.*`; не переименовывать общие модули и namespace ради MoreFoto.
- Прикладная основа: `rebit.share` и `rebit.auth`. `rebit.notification` принимает заявки с сайта через `POST /api/v1/lead`, синхронно отправляет их в Telegram и поддерживает резервный email; торговая ветка и её consumer не включены. `rebit.leadhunter` — самостоятельная работающая лидогенерация: получает заявки с внешних площадок, отправляет их в Telegram и поддерживает резервный email. Оба модуля сохранены в RaBit API и не относятся к P2P. `rebit.dev` и `sprint.migration` — технические инструменты.
- Frontend MoreFoto находится в `frontend/` этого репозитория. Продуктовый план остаётся в соседнем `../MoreFoto`. Карта модулей: `docs/04-bitrix-modules/README.md`, API: `docs/05-rest-api/README.md` именно этого соседнего проекта.
- Текущая архитектура основы: `docs/architecture.md`. Планируемые `morefoto.*` не считать реализованными по документации.
- Для нового чтения — SQL сложного запроса или ORM D7 с Result и массивными строками; без Objectify-коллекций. Result не передаётся в HTTP, кеш и межмодульные контракты.
- Общие технические контракты размещены в `rebit.share/lib/Application/Contract/` (единственное число): кеш, транспорт сообщений, файловые операции, разрешение токена. Namespace: `Rebit\Share\Application\Contract\...`.
- Предметные межмодульные контракты размещаются в `rebit.share/lib/Contracts/<Domain>/` (множественное число), например `Order`, `Payment`, `Media`. Namespace: `Rebit\Share\Contracts\<Domain>\...`. Здесь только публичные интерфейсы/DTO/события; реализация и DI принадлежат модулю-поставщику. Внутренние порты и DTO остаются в своём модуле.
- Новые миграции добавлять в активный `api/public/local/php_interface/migrations.foundation/`; исторический `migrations/` не является набором по умолчанию.

## Волны и pull requests

- Для GitHub использовать только настроенный GitHub CLI `gh` (в WSL: `/home/user/.local/bin/gh`); не обращаться к GitHub-коннекторам, другим приложениям или браузеру для этих операций. Явное правило пользователя от 2026-09-21.

- Обязательное правило пользователя: одна независимая волна — одна ветка `codex/<буква><номер>-...` от актуального `main` и один PR в `main`. Буква обозначает направление, номер — самостоятельный срез. Исторические WNN сохраняются в карте соответствия.
- Все необходимые runtime/compile/schema зависимости волны до начала её реализации уже должны быть слиты в `main`. Draft PR и незамерженная ветка не закрывают зависимость. Не использовать stacked PR, перенос соседнего незамерженного кода, allow-all, mock или фиктивный fallback для видимости готовности.
- Несколько готовых волн выполняются одновременно; заблокированные пропускаются. У каждой волны указаны `dependsOn`, обратные `unlocks`, вход/выход и отдельная проверка merge. Волна разблокируется только после merge всех предшественников и разрешения затрагивающих её decisionGates.
- Если нужен более ранний внутренний срез, он должен завершать настоящий сценарий с хранением, UseCase, DI и проверками. Набор одних DTO, миграций или тестов не считать отдельной продуктовой волной. HTTP и интеграция с ещё отсутствующим модулем остаются в зависимом срезе.
- Проверять актуальный `main` плюс только собственный diff, без файлов/коммитов остальных незамерженных волн. Совместная проверка дополняет отдельную. После изменения base повторять затронутые проверки. Общие Composer/bootstrap/routes изменять только когда они нужны завершённому сценарию.
- Не объединять независимые волны в один PR и не вносить продуктовый код прямо в `main`. В PR фиксировать ответственность, зависимости, проверки, ограничения и условия подключения frontend.
- Перед merge обязательны backend/frontend проверки и реальный браузерный E2E затронутого сценария через HTTP и изолированную БД. Для пользовательских экранов также обязательна визуальная проверка desktop/mobile; mock E2E её не заменяет. Использовать `make test-e2e`, порядок — `docs/waves/a8/README.md`. Если сценарий ещё не имеет UI, проверять доступный путь и явно указывать границы покрытия.
- Merge и deployment — отдельные действия после ревью. Автоматически не сливать новые PR; красные/непроведённые обязательные проверки блокируют сдачу. GitHub Actions пока отключён вручную; YAML сохраняется, проверки выполняются локально.
- Активный граф и историческая карта — `docs/waves/graph.json`; канонический полный план — соседний MoreFoto `docs/04-bitrix-modules/backend-waves.json`. Замороженные `docs/waves/w00` не переписывать. При изменении графа обновить генераторы/карты и прогнать проверку DAG, 99 API ID и независимой готовности.

## Комментарии UseCase и сервисов

- Каждый новый или изменяемый класс `*UseCase`, а также каждый класс в каталогах
  `Application/**/Service` и `Domain/**/Service`, обязан иметь class-level phpDoc непосредственно перед классом.
- phpDoc пишется на русском языке и в 1–3 предложениях отвечает на два вопроса: зачем класс существует и какой
  законченный сценарий, преобразование или предметное правило он выполняет.
- Не пересказывать имя класса, не перечислять текущих потребителей/реализации и не маскировать архитектурные
  нарушения формальным комментарием. При изменении ответственности phpDoc обновляется вместе с кодом.
- Отсутствие содержательного phpDoc у затронутого UseCase/Service блокирует готовность ветки к review.

## Работа над задачами: обязательные план и прогресс

Любую нетривиальную задачу начинать с сохраняемого плана. До изменения кода агент обязан определить текущую ветку,
прочитать review/issue, найти существующую папку задачи и актуализировать два файла.

### Расположение и имя

- Канонический путь: `docs/plans/<TASK-ID>_<slug>/`.
- Для волны ID берётся из ветки: `codex/d2-photo-labeling` → `docs/plans/D2_photo-labeling/`.
- Для ветки без формального ID использовать нормализованное имя ветки без служебного префикса. Если назначение
  ветки неочевидно, сначала уточнить его у пользователя.
- В папке задачи обязательны ровно два основных файла: `plan.md` и `progress.md`. Отдельный третий файл для
  тест-кейсов не создавать: ожидаемые тест-кейсы находятся в плане, фактические результаты — в прогрессе.
- Отчёты `docs/waves/<id>/README.md`, `verification.json` и визуальные артефакты дополняют, но не заменяют эти
  два файла.

### `plan.md`

План создаётся или обновляется до реализации и содержит:

- цель, контекст, scope и явно исключённый scope;
- зависимости, решения, риски и ограничения;
- пошаговый checklist со статусами;
- критерии приёмки;
- нумерованные тест-кейсы: предусловия/действие/ожидаемый результат и планируемая команда проверки.

Если scope или решение меняются, сначала обновить план, затем код.

### `progress.md`

Это хронологический журнал задачи. Его обновлять по ходу работы без отдельной просьбы:

- после каждого значимого этапа или найденного решения;
- после каждого прогона проверок с точной командой и результатом;
- перед переключением контекста, паузой, handoff, commit, push, ответом в review и завершением хода;
- при обнаружении блокера, расхождения с планом или незакрытого риска.

В начале файла постоянно поддерживать раздел `Точка продолжения`:

- ветка, base/head commit, PR/issue и связанная документация;
- что уже завершено, что выполняется сейчас и какой один следующий шаг;
- блокеры и открытые решения;
- состояние рабочего дерева и важные незакоммиченные файлы;
- точные команды для следующей проверки.

Ожидаемые тест-кейсы имеют стабильные ID в `plan.md`. В `progress.md` для каждого ID фиксировать
`PASS`, `FAIL`, `BLOCKED` или `PENDING`, дату, команду и доказательство. Не отмечать проверку пройденной,
если она фактически не запускалась.

### Возобновление и хранение

- Новая сессия сначала читает `AGENTS.md`, `CLAUDE.md`, `plan.md` и `progress.md`, затем сверяет
  `git status`, текущий commit и состояние PR. Продолжать с указанной точки, а не начинать задачу заново.
- Если реальное состояние расходится с журналом, сначала исправить `progress.md`.
- Оба файла коммитятся в ветку задачи и после merge не удаляются: это история решений и воспроизводимая точка
  восстановления.
- Секреты, токены, пароли, персональные данные и содержимое закрытых конфигов в plan/progress не записывать.
- Ветка не готова к review, если обязательные шаги плана не закрыты, результаты тестов не перенесены в progress
  или «Точка продолжения» устарела.
