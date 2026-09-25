# Issue #90 — журнал

## Точка продолжения

- Дата: 2026-09-25.
- Ветка: `codex/issues-90-serialized-name`.
- Worktree: `/home/user/rabit-api-worktrees/issues-90-serialized-name`. Общий checkout `/home/user/rabit-api` не трогать.
- Base: `origin/main` `94502a1` (merge PR #87).
- Issue: [#90](https://github.com/rebit-pro/rabit-api/issues/90). PR: [#100](https://github.com/rebit-pro/rabit-api/pull/100), head с кодом — `ffc6b5e`.
- Документация: [план](plan.md).
- Завершено: план, тест, исправление, быстрые проверки (T01–T07).
- Сейчас: ожидание review PR.
- Следующий шаг: review; после review без блокеров — gate `make test-e2e` (T08).
- Блокеров нет. Открытых решений нет.
- Рабочее дерево: изменения закоммичены; пустые `api/vendor`, `api/var` — точки монтирования docker-проверок, в git не попадают.
- Команды проверок:
  - vendor-том `rabit-issues42-vendor`: `api/composer.lock` ветки совпадает с `/home/user/rabit-api/api/composer.lock`,
    `vendor/composer/installed.json` тома совпадает с `/home/user/rabit-api/api/vendor` (md5), symfony/serializer v7.4.7;
  - `docker run --rm --network none --memory 1536m --cpus 2 --env XDEBUG_MODE=off --mount type=bind,source=<worktree>/api,target=/app,readonly --mount type=volume,source=rabit-issues42-vendor,target=/app/vendor --tmpfs /app/var:rw,size=256m --workdir /app --entrypoint php rabit-api-php-cli:d1-local vendor/bin/phpunit --colors=never --display-deprecations`
    (так же `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` и `vendor/bin/phplint`);
  - php-cs-fixer: тот же образ с записываемым bind, `vendor/bin/php-cs-fixer fix --config=public/local/php-cs-fixer.php --allow-risky=yes --using-cache=no --path-mode=intersection --dry-run <изменённые .php>`.

## Результаты тест-кейсов

| ID | Статус | Дата | Доказательство |
|----|--------|------|----------------|
| T01 | PASS | 2026-09-25 | `DtoMetadataServiceSerializedNameTest` датасет `Annotation\SerializedName` |
| T02 | PASS | 2026-09-25 | тот же тест, датасет `Attribute\SerializedName` |
| T03 | PASS | 2026-09-25 | на старом коде 2/2 FAIL: Annotation — 1 deprecation `getSerializedName() is deprecated since symfony/serializer:7.4`; Attribute — `serializedMap` пуст |
| T04 | PASS | 2026-09-25 | OK (860 tests, 45372 assertions), 0 deprecations |
| T05 | PASS | 2026-09-25 | `[OK] No errors` |
| T06 | PASS | 2026-09-25 | `[OK] 1104 files` |
| T07 | PASS | 2026-09-25 | `Found 0 of 2 files that can be fixed` |
| T08 | PENDING | — | gate после review |

## Хронология

### 2026-09-25 — разведка

- Прочитаны `CLAUDE.md`, `AGENTS.md`, текст #90, `Attribute/SerializedName.php` и `Annotation/SerializedName.php` в vendor.
- Установлено: `Annotation\SerializedName` — `class_alias`; строковое сравнение `getName()` пропускало
  `Attribute\SerializedName` (второй дефект, в issue не описан).
- В `lib` на main нет DTO с `#[SerializedName]`.
- Для docker-монтирования созданы пустые `api/vendor`, `api/var` (игнорируются git).

### 2026-09-25 — deprecations до исправления

- `vendor/bin/phpunit --colors=never --display-deprecations` на `94502a1` — OK (858 tests, 45364 assertions), **0 deprecations**
  (на main нет потребителей атрибута).
- Новый тест на старом коде — 2/2 FAIL (T03), в том числе **1 deprecation** на DTO с `Annotation\SerializedName`.

### 2026-09-25 — исправление и проверки

- `DtoMetadataService`: импорт `Attribute\SerializedName`, проверка `is_a(..., SerializedName::class, true)`,
  чтение `$instance->serializedName`.
- Тест `rebit.share/tests/Infrastructure/Dto/Metadata/DtoMetadataServiceSerializedNameTest.php`.
- php-cs-fixer переупорядочил data provider и убрал пробел в `static function(`; повторный dry-run — 0 файлов.
- Полный PHPUnit — OK (860 tests), **0 deprecations после**; phpstan, phplint — OK (T04–T07).

### 2026-09-25 — публикация

- Push `codex/issues-90-serialized-name`, создан PR #100 в `main` (`Closes #90`). Не сливать до review; gate T08 — PENDING.
