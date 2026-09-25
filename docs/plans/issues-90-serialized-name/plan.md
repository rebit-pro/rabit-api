# Issue #90 — `SerializedName` в `DtoMetadataService` без устаревшего метода

## Цель и контекст

[#90](https://github.com/rebit-pro/rabit-api/issues/90), найдено при работе над K3 (PR #89).
`rebit.share/lib/Infrastructure/Dto/Metadata/DtoMetadataService.php` вызывает `SerializedName::getSerializedName()`.
В symfony/serializer 7.4 (`composer.lock`: v7.4.7) метод помечен `#[\Deprecated]` и выдаёт `E_USER_DEPRECATED`.
В Symfony 8 метода не будет.

Это issue-ветка, не волна: `docs/waves/graph.json` не меняется.

- Ветка: `codex/issues-90-serialized-name`.
- Worktree: `/home/user/rabit-api-worktrees/issues-90-serialized-name`.
- Base: `origin/main` `94502a1` (merge PR #87).

## Установленные факты (main 94502a1)

- `Attribute\SerializedName` в 7.4 — основной класс с публичным `readonly string $serializedName`.
  `Annotation\SerializedName` — `class_alias` на него (объявление в `Annotation/` под `if (false)` только для IDE).
- `DtoMetadataService::buildMetadata` сравнивал `ReflectionAttribute::getName()` со строкой
  `Symfony\Component\Serializer\Annotation\SerializedName`. `getName()` возвращает имя, как оно записано в DTO,
  без разрешения алиаса. Поэтому DTO с `use ...\Attribute\SerializedName` молча терял переименование полей.
- В `lib` на main ни один DTO не использует `#[SerializedName]`; PHPUnit на main — 0 deprecations.
  Дефект проявляется у первого такого DTO (K3, `MaxUpdateContractTest`).
- `CustomNameConverter` вызывает `AttributeMetadata::getSerializedName()` — метод метаданных, не устаревший; вне scope.

## Решения

- **R1.** Читать публичное свойство `serializedName` вместо метода.
- **R2.** Распознавать атрибут через `is_a($attr->getName(), SerializedName::class, true)` с импортом
  `Attribute\SerializedName`: алиас `Annotation\SerializedName` и подклассы проходят одной проверкой.
  Автозагрузка классов атрибутов происходит только при построении метаданных, которые кешируются.
- **R3.** Тест в `rebit.share/tests/Infrastructure/Dto/Metadata/` с фикстурами на оба имени атрибута:
  перехват `E_DEPRECATED | E_USER_DEPRECATED` при `analyze()`, проверка `serializedMap` и гидрации через
  `ArrayToDtoMapper::map`. Один тест на датасет — независимость от порядка тестов и статического кеша.

## Scope

- `DtoMetadataService`: импорт и распознавание `SerializedName` (R1, R2).
- Новый unit-тест (R3).

### Исключено

- Прочий рефакторинг `DtoMetadataService`, `CustomNameConverter`, включение `failOnDeprecation` в `phpunit.xml`.
- `make test-e2e` — gate после review (правило пользователя).

## Риски

- Низкие: на main нет DTO с `#[SerializedName]`, поведение существующих DTO не меняется.

## Checklist

- [x] 1. Прочитать issue, `CLAUDE.md`, `AGENTS.md`, vendor-класс, `composer.lock`.
- [x] 2. Базовый прогон PHPUnit с `--display-deprecations`.
- [x] 3. Тест, падающий на старом коде.
- [x] 4. Исправление `DtoMetadataService`.
- [x] 5. Быстрые backend-проверки.
- [x] 6. Commit, push, PR с `Closes #90`.
- [x] 7. Review; gate после review без блокеров (PASS 2026-09-25).

## Критерии приёмки

- `DtoMetadataService` не вызывает `getSerializedName()`.
- DTO с `Annotation\SerializedName` и с `Attribute\SerializedName` дают одинаковый `serializedMap` и гидрацию.
- PHPUnit с `--display-deprecations` — 0 deprecations; phpstan, phplint, php-cs-fixer — без ошибок.

## Тест-кейсы

Команды — docker-образ `rabit-api-php-cli:d1-local`, том `rabit-issues42-vendor` (см. progress, «Точка продолжения»).

- **T01.** Предусловие: DTO с `#[Annotation\SerializedName('external_id')]`. Действие: `analyze()` под перехватчиком
  deprecation, затем `ArrayToDtoMapper::map`. Ожидание: 0 deprecations, `['external_id' => 'externalId']`, поле
  гидрировано. Команда: `vendor/bin/phpunit --display-deprecations <DtoMetadataServiceSerializedNameTest.php>`.
- **T02.** То же для `#[Attribute\SerializedName('external_id')]`. Та же команда.
- **T03.** T01/T02 падают на старом коде (deprecation и пустой `serializedMap` соответственно). Та же команда до исправления.
- **T04.** Полный PHPUnit: `vendor/bin/phpunit --colors=never --display-deprecations` — OK, 0 deprecations.
- **T05.** `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` — без ошибок.
- **T06.** `vendor/bin/phplint` — без ошибок.
- **T07.** php-cs-fixer dry-run по изменённым PHP — 0 файлов к исправлению.
- **T08.** `make test-e2e` — после review без блокеров.
