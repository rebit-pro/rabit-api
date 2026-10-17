# J5 — прогресс

## Точка продолжения

- Ветка `codex/j5-order-email-attachments` (worktree `/home/user/rabit-api-worktrees/j5-order-email-attachments`) от main 1a28aa7. PR [#209](https://github.com/rebit-pro/rabit-api/pull/209).
- Завершено:
  - S0–S5: план, решения, граф;
  - H1 вложения ссылками, Files — план писем, письма «k из n», статус по письмам;
  - frontend, unit, E2E-спека и верификаторы;
  - быстрые проверки PASS.
- S6–S7 завершены: саморевью без блокеров, полный гейт PASS (`rabit-e2e-86465c5e7533`), visual desktop/mobile, J5 → review в графе.
- Следующий шаг: по «да» владельца — merge PR #209 и выкладка: миграция `up 20261017100001` → backend (с notification consumer/dispatcher, order_email_runner) → DI smoke → frontend. Отправка остаётся выключенной до #161; перед включением — проверить `main.max_file_size`.
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
  - `tools/tests/test_run_browser_e2e.py::test_f2_prepares_first_and_reports_later_in_one_group` падает и на main 1a28aa7 (порядок групп) — это уже #163.

### 17.10.2026 — PR и саморевью

- PR #209. Саморевью — блокеров нет. Исправлено по ходу:
  - H1 отвергал бы настоящие имена кадров (код с дефисом в начале, кириллица) и пустой файл — дефект застопорил бы отправку. Проверка сведена к безопасности: имя без кавычек, слэшей и управляющих символов; путь абсолютный без `..`; размер ≥ 0. Unit дополнен;
  - пока план писем ещё не записан (до минуты после оплаты), блок говорил «Отправляем ссылку». Теперь «Готовим письмо с заказом».
- Неблокирующее — issue #210 (вложение заявки с пустыми `method=`/`charset=`); падение теста раннера — уже #163.
- Инструкция для родителей `/kak-kupit/` про письма не дополнялась: отправка выключена до #161.

### 17.10.2026 — полный гейт

- Прогон 1 `rabit-e2e-68b832413fea` — FAIL только G1-T14 (тестовый магазин ЮKassa: СБП не ответил, страница осталась «Проверяем оплату», известная #174). Спека `zzzzzzzzzz-order-email` прошла (6,1 с); группа a отменена раннером.
- Прогон 2 `rabit-e2e-86465c5e7533` — **PASS**, 418,8 с, ЮKassa включена:
  - 155 браузерных тестов (a 81, b 74), все verify-*.php, включая `verify-order-email.php` и `verify-notification.php` (durable Notification contract);
  - G1-T14 прошёл (24,1 с), `zzzzzzzzzz-order-email` — 6,9 с.
- Visual `docs/waves/j5/screenshots/j5-{desktop,mobile}-order-email.png`: «Фото и ссылка на заказ отправлены на print.j4@example.test … — 2 письма, 2 фото. Если писем нет — проверьте папку «Спам»». На 390 px без горизонтальной прокрутки.

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
| J5-T07 | PASS | 17.10.2026 | unit `DeliveryUseCasesTest`, `BitrixEmailTransportTest`; MySQL `verify-notification.php` в гейте `rabit-e2e-86465c5e7533` |
| J5-T08 | PASS | 17.10.2026 | `FilesArchitectureTest`, `AttachmentPlannerTest` (предел = H1) |
| J5-T09 | PASS | 17.10.2026 | `tests/commerce/order-email.test.mjs` |
| J5-T10 | PASS | 17.10.2026 | гейт `rabit-e2e-86465c5e7533`: письмо на кадр, реальный MIME Bitrix, sha256 вложений = оригиналы, ссылка в каждом письме |
| J5-T11 | PASS | 17.10.2026 | тот же гейт: статус accepted, «2 письма», повтор после сдвига времени — 202 и все письма снова, сразу — 429 |
| J5-T12 | PASS | 17.10.2026 | тот же гейт: `verify-order-email.php` PASS |
| J5-T13 | PASS | 17.10.2026 | тот же гейт: `j5-desktop/mobile-order-email.png` |
| J5-T14 | PASS | 17.10.2026 | быстрые проверки и `make test-e2e` PASS |
| J5-T15 | PENDING | | ручная проверка после #161 |
