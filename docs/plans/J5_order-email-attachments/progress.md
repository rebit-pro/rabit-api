# J5 — прогресс

## Точка продолжения

- Ветка `codex/j5-order-email-attachments` (worktree `/home/user/rabit-api-worktrees/j5-order-email-attachments`) от main 1a28aa7. PR — создаётся.
- Завершено:
  - S0–S5: план, решения, граф;
  - H1 вложения ссылками, Files — план писем, письма «k из n», статус по письмам;
  - frontend, unit, E2E-спека и верификаторы;
  - быстрые проверки PASS.
- Сейчас: S6 — PR и саморевью. Следующий шаг после саморевью без блокеров — полный гейт `make test-e2e` (S7).
- Блокеры: нет. Открытых решений нет.
- Повтор гейта: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Журнал

### 17.10.2026 — старт

- Владелец: «Берём J5, рекомендации принимаю» — J5-DEC-01…03 по рекомендации OPS-waves-e9-j5.
- Инвентаризация:
  - Bitrix `SendImmediate` принимает `filesContent`;
  - `custom_mail()` перехватывает MIME;
  - `main.max_file_size` молча подменяет вложения;
  - на stage потребитель уведомлений монтирует `runtime/var` — оригиналы ему видны;
  - production compose — нет, добавлено (T-10).
- Чтение `main.max_file_size` и msmtp на stage отклонено auto mode — проверить перед включением отправки (риск в плане и README).
- Граф: E9 → merged (PR #208, d8236a4), baseline 1a28aa7, J5 → inProgress, `decisionEvidence.J5-DEC-01-03`. Валидатор PASS (61 волна, 123 ID, readyMvp J5).

### 17.10.2026 — реализация

- **H1:**
  - `EmailAttachmentInputDto` и `attachments` в `EmailNotificationInputDto`, `MAX_ATTACHMENT_BYTES` = 20 MiB;
  - `QueueEmailUseCase` проверяет имя, тип, абсолютный путь без `..`, размер и сумму; вложения входят в хеш payload;
  - колонка `ATTACHMENTS` (JSON), очистка вместе с телом после финала;
  - `BitrixEmailTransport` читает файл при отправке, сверяет размер (`attachment_unavailable`), защищает от подмены Bitrix (`attachment_limit`); `METHOD`/`CHARSET` null.
- **Files:**
  - `AttachmentPlanner` (DEC-01/02), VO `LetterFile`/`LetterPlan`/`DeliveryPart`;
  - миграция `20261017100001`: `mf_file_delivery_part`, `LETTERS`, `LINK_ONLY`, перенос писем J2;
  - `OrderEmailSender` пишет план один раз и ставит письма по ключу `order-email:<id>:<k>`;
  - `OrderEmailComposer` — «письмо k из n», диапазон кадров, кадры по ссылке;
  - `DeliveryView` — статус по всем письмам, поля `letters/acceptedLetters/photos/linkOnly`.
- **Frontend:** тексты блока письма со счётом писем и фото, подсказка «отправим фото и ссылку на заказ».
- **E2E:**
  - фикстура стенда: `dispatch` / `deliver` через реальный Bitrix mail с `custom_mail()` / `age`;
  - стенд ставит `MOREFOTO_ORDER_EMAIL_PART_BYTES=1`;
  - спека сверяет sha256 вложений с оригиналами и повтор всех писем;
  - `verify-order-email.php` и `verify-notification.php` дополнены.
- Найдено попутно (не по теме):
  - `EmailLeadNotifier` передаёт вложению `METHOD=''` и `CHARSET=''`, Bitrix тогда пишет `method=; charset=` в Content-Type вложения заявки — проверить отдельно;
  - `tools/tests/test_run_browser_e2e.py::test_f2_prepares_first_and_reports_later_in_one_group` падает и на main 1a28aa7 (порядок групп).

### Проверки

| Команда | Результат |
| --- | --- |
| `phpunit --testsuite unit` (php-cli d1-local, vendor `rabit-e7-vendor`) | PASS, 991 тест, 49877 проверок |
| `phpstan analyse` (весь проект) | PASS после замены `?->` на `->` слева от `??` |
| `php-cs-fixer --dry-run` по 32 изменённым файлам | PASS, 0 исправлений |
| `npm run check` (docker playwright v1.52.0, том `rabit-e7-node`) | PASS после `eslint --fix` двух файлов (prettier), test:ui 100/100 |
| `npm run test:commerce` | PASS, 249/249 |
| `npm run typecheck:e2e`, `npm run lint` после правки спеки | PASS |
| `python3 tools/verify-wave-graph.py docs/waves/graph.json` | PASS |
| `python3 -m unittest tools/tests/test_run_browser_e2e.py` | 21/22, падение `test_f2_…` есть и на main |

## Тест-кейсы

| ID | Статус | Дата | Доказательство |
| --- | --- | --- | --- |
| J5-T01 | PASS | 17.10.2026 | `AttachmentPlannerTest` (1/2/3/10 кадров, точный лимит, без файлов) |
| J5-T02 | PASS | 17.10.2026 | `AttachmentPlannerTest` (19 МБ один, 25 МБ по ссылке, все крупные) |
| J5-T03 | PASS | 17.10.2026 | `OrderEmailAttachmentsTest::testThreeFramesOfEightMegabytesGoInTwoLettersPlannedOnce` |
| J5-T04 | PASS | 17.10.2026 | `OrderEmailAttachmentsTest::testAFailureBetweenLettersRepeatsOnlyTheLetterThatIsNotQueued` |
| J5-T05 | PASS | 17.10.2026 | `OrderEmailAttachmentsTest` (тексты, кадры по ссылке, письмо без файлов), `OrderEmailFlowTest` |
| J5-T06 | PASS | 17.10.2026 | `OrderEmailAttachmentsTest::testTheStatusCountsEveryLetter` |
| J5-T07 | PASS / PENDING | 17.10.2026 | unit `DeliveryUseCasesTest`, `BitrixEmailTransportTest`; MySQL `verify-notification.php` — в гейте |
| J5-T08 | PASS | 17.10.2026 | `FilesArchitectureTest`, `AttachmentPlannerTest` (предел = H1) |
| J5-T09 | PASS | 17.10.2026 | `tests/commerce/order-email.test.mjs` |
| J5-T10 | PENDING | | гейт `make test-e2e` |
| J5-T11 | PENDING | | гейт `make test-e2e` |
| J5-T12 | PENDING | | гейт → `verify-order-email.php` |
| J5-T13 | PENDING | | гейт → `j5-*-order-email.png` |
| J5-T14 | PENDING | | быстрые PASS, гейт ждёт |
| J5-T15 | PENDING | | ручная проверка после #161 |
