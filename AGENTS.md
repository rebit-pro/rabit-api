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

- Техническое имя: `rabit-api`; текущий checkout может сохранять старое имя `rebit-p2p`.
- Прикладная основа: `rebit.share` и `rebit.auth`. `rebit.notification` принимает заявки с сайта через `POST /api/v1/lead`, синхронно отправляет их в Telegram и поддерживает резервный email; торговая ветка и её consumer не включены. `rebit.leadhunter` — самостоятельная работающая лидогенерация: получает заявки с внешних площадок, отправляет их в Telegram и поддерживает резервный email. Оба модуля сохранены в RaBit API и не относятся к P2P. `rebit.dev` и `sprint.migration` — технические инструменты.
- Frontend и продуктовый план MoreFoto находятся в соседнем `../MoreFoto`. Карта модулей: `docs/04-bitrix-modules/README.md`, API: `docs/05-rest-api/README.md` именно этого соседнего проекта.
- Текущая архитектура основы: `docs/architecture.md`. Планируемые `morefoto.*` не считать реализованными по документации.
- Для нового чтения — SQL сложного запроса или ORM D7 с Result и массивными строками; без Objectify-коллекций. Result не передаётся в HTTP, кеш и межмодульные контракты.
- Общие технические контракты размещены в `rebit.share/lib/Application/Contract/` (единственное число): кеш, транспорт сообщений, файловые операции, разрешение токена. Namespace: `Rebit\Share\Application\Contract\...`.
- Предметные межмодульные контракты размещаются в `rebit.share/lib/Contracts/<Domain>/` (множественное число), например `Order`, `Payment`, `Media`. Namespace: `Rebit\Share\Contracts\<Domain>\...`. Здесь только публичные интерфейсы/DTO/события; реализация и DI принадлежат модулю-поставщику. Внутренние порты и DTO остаются в своём модуле.
- Новые миграции добавлять в активный `api/public/local/php_interface/migrations.foundation/`; исторический `migrations/` не является набором по умолчанию.

## Волны и pull requests

- Обязательное правило пользователя: одна независимая волна — одна ветка `codex/<буква><номер>-...` от актуального `main` и один PR в `main`. Буква обозначает направление, номер — самостоятельный срез. Исторические WNN сохраняются в карте соответствия.
- Все необходимые runtime/compile/schema зависимости волны до начала её реализации уже должны быть слиты в `main`. Draft PR и незамерженная ветка не закрывают зависимость. Не использовать stacked PR, перенос соседнего незамерженного кода, allow-all, mock или фиктивный fallback для видимости готовности.
- Несколько готовых волн выполняются одновременно; заблокированные пропускаются. У каждой волны указаны `dependsOn`, обратные `unlocks`, вход/выход и отдельная проверка merge. Волна разблокируется только после merge всех предшественников и разрешения затрагивающих её decisionGates.
- Если нужен более ранний внутренний срез, он должен завершать настоящий сценарий с хранением, UseCase, DI и проверками. Набор одних DTO, миграций или тестов не считать отдельной продуктовой волной. HTTP и интеграция с ещё отсутствующим модулем остаются в зависимом срезе.
- Проверять актуальный `main` плюс только собственный diff, без файлов/коммитов остальных незамерженных волн. Совместная проверка дополняет отдельную. После изменения base повторять затронутые проверки. Общие Composer/bootstrap/routes изменять только когда они нужны завершённому сценарию.
- Не объединять независимые волны в один PR и не вносить продуктовый код прямо в `main`. В PR фиксировать ответственность, зависимости, проверки, ограничения и условия подключения frontend.
- Merge и deployment — отдельные действия после ревью. Автоматически не сливать новые PR. GitHub Actions пока отключён вручную; YAML сохраняется, проверки выполняются локально.
- Активный граф и историческая карта — `docs/waves/graph.json`; канонический полный план — соседний MoreFoto `docs/04-bitrix-modules/backend-waves.json`. Замороженные `docs/waves/w00` не переписывать. При изменении графа обновить генераторы/карты и прогнать проверку DAG, 99 API ID и независимой готовности.
