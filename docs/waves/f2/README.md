# F2 — подготовка и факт передачи ссылки

Ветка `codex/f2-link-handoff` от main `8cba22c7655b5886d5fe663523214bcd059e674b`, PR https://github.com/rebit-pro/rabit-api/pull/40.

- **Зависимости:** E3 (#20), F1 (#25), E4 (#30); гейты D05, D07 и D10 приняты.
- **Порядок волн:** по решению пользователя от 22.09.2026 F2 идёт раньше D3. D3 теперь зависит от E5 и F2. Изменены `graph.json` и канонический план MoreFoto.
- **План и журнал:** `docs/plans/F2_link-handoff/`.

Организатор проверяет готовность группы, и сервер выдаёт ключ галереи. Сотрудник отмечает, когда фактически передал ссылку родителям. От этого момента Organization считает приём (+7 дней) и доставку (+7 дней) в Europe/Moscow. Ошибочную дату можно исправить: причина и прежние сроки сохраняются в истории.

## Контракты

| ID | Маршрут | Роли (D08) | Суть |
| --- | --- | --- | --- |
| HND-01 | `GET /api/v1/group-links` | О, К, Р, В в своей области | Список: готовность, проблемы, сроки. Без `galleryToken` и истории |
| HND-02 | `GET /api/v1/groups/{id}/link` | О, К, Р, В | Карточка: ключ, `revision`, `signature`, проблемы, сроки, `referenceNow`, история |
| HND-03 | `POST …/link-preparations` | О | Подтверждение проверки и выдача ключа галереи |
| HND-04 | `POST …/link-transmissions` | О, К, В | Запись факта передачи. Повтор возвращает текущие сроки без изменений |
| HND-05 | `POST …/link-date-corrections` | О, К | Исправление даты с причиной 5–500 символов и историей |

- **Подпись (`signature`)** — SHA-256 серверного состояния: кадры с revision и статусом, назначения, обложка, действующие условия и товары, название и тип группы, воспитатель, незавершённые заявки сотрудников. Любое изменение сбрасывает `prepared` до передачи.
- **Проблемы готовности:** `noPhotos`, `photosProcessing`, `unassignedPhotos`, `noProducts`, `staffRequestsPending`. Незавершённые заявки F1 блокируют подготовку до D3.
- **Дата `sentAt`:** ISO 8601 с offset, не позже серверного now и не раньше минуты выдачи ключа.
- **`revision`** — версия ссылки Handoff. Это не версия группы.
- **Коды ошибок:**
  - 409: `REVISION_CONFLICT`, `SIGNATURE_CONFLICT`, `LINK_NOT_READY`, `LINK_NOT_PREPARED`, `LINK_ALREADY_SENT`, `LINK_NOT_SENT`, `IDEMPOTENCY_CONFLICT`;
  - 422: `SENT_AT_IN_FUTURE`, `SENT_AT_BEFORE_LINK`, `SENT_AT_UNCHANGED`, `INVALID_SENT_AT`, `INVALID_REASON`, `REVIEW_REQUIRED`, `CONFIRMATION_REQUIRED`;
  - 404 `GROUP_NOT_FOUND` — группа вне области, 403 `FORBIDDEN` — действие запрещено в своей области.
- Уточнения внесены в канонический `build.py` MoreFoto. Воспроизводимый diff источников графа и контракта — `morefoto-contract.patch`.

## Устройство

- **Handoff** владеет сценарием:
  - таблицы `mf_group_link`, `mf_group_link_history`, `mf_group_link_idempotency`;
  - доменные политики готовности, прав D08 и даты передачи;
  - сессия команды и пять UseCase с чистым контроллером.
- **Порядок блокировок:** состояние Access → учреждение, съёмка, группа (Organization) → профиль и учётная запись актора → идемпотентность → запись ссылки → ревизия медиа съёмки → условия Commerce (share). Повтор с тем же ключом после блокировки группы возвращает сохранённый результат.
- **Organization:**
  - `GroupCalendarInterface::recordLinkSent/correctLinkSent` пишут журнал `b_hlbd_mf_organization_change`;
  - `GroupDirectoryInterface` — каталог групп по области, состоянию и страницам.
- **Media:**
  - `GalleryLinkInterface` хранит сырой ключ `mf_gallery_capability.TOKEN` открытым текстом. Это решение пользователя: риск раскрытия действующих галерей при утечке БД принят;
  - `GroupMaterialsInterface` отдаёт материалы группы под той же блокировкой, что и разметка;
  - MED-05/06 повторно проверяют редактируемость группы после блокировки ревизии. Разметка и обложка не проскочат после открытия.
- **Commerce:** `GroupSalesReadinessInterface` — действующие условия через share-блокировки, как у E3.
- **Frontend:**
  - live-режим `/cabinet/links`: список HND-01, ключ и история по запросу HND-02, диалоги HND-03/04/05, пункт меню для всех ролей персонала;
  - карточка съёмки показывает «Ссылка передана».

## Проверки

- **Выполнено до review** (факты — `verification.json` и progress):
  - PHP lint;
  - PHPStan;
  - PHPUnit unit (318 тестов, 37 новых);
  - php-cs-fixer;
  - frontend `npm run check` и `npm run test:commerce` (160);
  - граф волн;
  - валидаторы контракта MoreFoto.
- **По решению пользователя — только после review без блокеров:**
  - реальный браузерный E2E `frontend/e2e/live/zzzz-links.spec.ts`: подготовка → передача → галерея → quote, исправление, роли, идемпотентность, desktop/mobile;
  - verifier на MySQL `api/tools/e2e/verify-links.php`: след в БД, календарь с управляемыми часами, граница закрытия.

```sh
make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor
```

## Подключение и ограничения

- **Миграция** `20260922150001` добавляет колонку `TOKEN`, индекс активных ключей и три таблицы Handoff. `down` отказывает, если данные ссылок уже есть.
- **Ключи, выданные до миграции** (фикстуры E4), продолжают работать, но их нельзя показать повторно. Подготовка выдаёт новый ключ.
- **Handoff получает контракт Commerce лениво** через ServiceLocator. Встречное `includeModule` в Bitrix даёт `E_USER_WARNING`, поэтому `init.php` загружает оба модуля.
- **Не входит в F2:**
  - переносы D3;
  - заказы E5 (цепочку «→ заказ» и запрет разметки после заказа проверяет D3);
  - продление K2;
  - ограничения D12 после оплаты и производства;
  - перевыпуск ключа из интерфейса.
- **Остаточный риск:** заявка сотрудника, созданная одновременно с передачей, не блокирует её, потому что F1 не блокирует группу.
- **Подключать только в тестовой среде.** Реальных покупателей — после N2.
