# Issue #127 — журнал

## Точка продолжения

- Ветка `codex/issues-127-gallery-path`, worktree `/home/user/rabit-api-worktrees/issues-127-gallery-path`,
  base `origin/main` `71cd0f4`. Issue #127. PR — см. ниже.
- Завершено: `galleryPath()` + node-тесты, `GalleryPath.vue`, «Ссылки и сроки», блок на странице фотографий,
  тексты галереи родителя, live E2E `#127` в `z-links-preparation.spec.ts` (группа b).
- Сейчас: самопроверка PR и полный gate; после PASS — merge в main по команде пользователя. Деплой отдельно.
- Блокеров нет. Открытых решений нет.
- Команды: frontend — `docker run --rm --network none -v $PWD/frontend:/app -v rabit-issues6263-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy npm run check`;
  gate — `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Статус тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01–T06 | PASS | 2026-09-26 | `npm run check` | `tests/ui/gallery-path.test.mjs`, 5 тестов (61 node-тест всего) |
| T07–T10 | PENDING | — | gate | live `#127` |
| T11 | PENDING | — | `make test-e2e` | — |
| T12 | PENDING | — | gate | скриншоты `i127-*` desktop/mobile из live E2E вместо заглушек |

## Журнал

### 2026-09-26

- Замечание пользователя (скриншот галереи «Фотографии ещё готовятся»): непонятно, как кадры попадают в галерею.
  Причина: кадры видны только после «Отметить передачу», а «Открыть галерею» доступна уже после «Проверить ссылку».
  Issue #127; пользователь взял в реализацию и разрешил merge в main (деплой отдельной командой).
- Реализовано по плану. Во время работы пользователь прислал ещё два замечания — оформлены отдельными issues:
  #128 (перевыпуск ссылки покупателя на заказ: ключ хранится только как SHA-256, сервис `OrderAccessKeys`
  уже умеет `recovery`/`replaced`, нужен HTTP и UI) и #129 (ссылка на базу знаний `/guide/` со страницы входа и из кабинета).
- `npm run check` PASS (lint, stylelint, typecheck, typecheck:e2e, 61 node-тест).
