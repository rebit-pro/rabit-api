# Прогресс K3 — вопросы куратору с сайта через MAX

## Точка продолжения

- Ветка `codex/k3-max-curator-questions`, worktree `/home/user/rabit-api-worktrees/k3-max-curator-questions`,
  base `4ca7e9c` (origin/main после PR #78/#81/#82/#84/#85; ветка перебазирована 25.09.2026 без конфликтов). PR ещё не открыт.
- Связанное: [план K3](plan.md), [план PR #48](../max-support-chat-plan/plan.md), `docs/waves/graph.json`,
  канон `../MoreFoto/docs/04-bitrix-modules/backend-waves.json` (не git, изменения — патчем в `docs/waves/k3/`).
- Завершено: merge PR #48; граф синхронизирован (E6 merged, K3 inProgress).
- Сейчас: частичный E2E группы a на новой базе (браузер + `verify-support.php`).
- Следующий шаг: при PASS — push и PR на ревью; полный `make test-e2e` — после ревью без блокеров.
- Пользователь: бот прошёл модерацию; группа создана; токен кладёт в `~/.config/morefoto/max-bot.env`, бот
  добавляется в группу администратором.
- Блокеры: нет. Открыто MAX-D06 (срок хранения) — только для production.
- Рабочее дерево: всё закоммичено. Канон MoreFoto изменён на месте (патчи в `docs/waves/k3/`).
- Команды: `git status --short`; `python3 tools/verify-wave-graph.py docs/waves/graph.json`;
  `E2E_GROUPS=a make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Журнал

### 25.09.2026 — старт волны

- `gh pr merge 48 --merge` — PASS, `main` = `1dd4a5f`.
- `git worktree add -b codex/k3-max-curator-questions /home/user/rabit-api-worktrees/k3-max-curator-questions origin/main` — PASS.
- Граф и канон: E6 → merged (PR #66, `54bd4abbb62fbda2dc030281e4b4893820d83e84`), baseline.commit = `1dd4a5f…`,
  K3 → inProgress. `python3 tools/verify-wave-graph.py docs/waves/graph.json` — PASS: 52 волны, 116 API ID, ready K3.
  Канон: `render-waves.py`, `build.py`, `validate.py`, `validate-postman.cjs` — PASS.
- MAX API сверен со схемой `1a4a502f`: host `https://platform-api2.max.ru`, `Authorization: <token>`,
  POST /messages?chat_id → `SendMessageResult.message.body.mid`; reply — `message.link.type=reply`,
  `message.link.message.mid`; `bot_added`: `chat_id`, `user`, `is_channel`; 2 сообщения/с на чат;
  webhook: `X-Max-Bot-Api-Secret`, секрет `[a-zA-Z0-9_-]{5,256}`, 10 повторов, отписка через 8 ч.

### 25.09.2026 — токен и TLS MAX

- Пользователь: бот прошёл модерацию, добавлен в группу «МореФото — вопросы родителей.» администратором; токен в `~/.config/morefoto/max-bot.env` (600).
- Без дополнительного CA запрос к MAX падает: `curl: (60) unable to get local issuer certificate`. Цепочка `*.max.ru` → Russian Trusted Sub CA → Russian Trusted Root CA (Минцифры).
- Root CA скачан с `https://gu-st.ru/content/lending/russian_trusted_root_ca_pem.crt`, SHA-256 `D2:6D:…:CF:31`; `openssl s_client -CAfile` → `Verify return code: 0 (ok)`.
- Решение K3-T08: сертификат хранится в `rebit.notification/resources/max/`, клиент MAX задаёт `CURLOPT_CAINFO` только для своих запросов; системное доверие не меняется.

### 25.09.2026 — backend K3

- Исследование паттернов (F2/E5/H1/frontend) выполнено тремя read-only агентами; выводы: публичные контроллеры — `PrivateApiJsonController`, staff — `AuthenticatedApiJsonController`; общего rate limiter нет (лимиты — подсчёт строк в БД); контекст галереи и куратора — из существующих контрактов `GalleryAccess`, `GroupDirectory`, `InstitutionAccess` (MED-01 отдаёт `curator` = ''); в E2E нет HTTP-двойников и воркеров — доставка проверяется PHP-верификатором.
- `rebit.share`: `LogChannelEnum::support`, `MessengerQueueEnum::SUPPORT_MAX`, контракты `Contract/Notification/{MaxChatMessengerInterface,MaxBotAdminInterface}` + DTO + `MaxSendStatusEnum`; allowlist логов.
- `rebit.notification`: `Infrastructure/Max/{MaxBotApiClient,MaxSendOutcomeClassifier}`, `di/max.php`, CA Минцифры; секреты `rebit_max_bot_token`, `morefoto_support_max_webhook_secret` в `runtime-env.php`.
- `morefoto.support`: миграция `Version20260925150001` (4 таблицы, регистрация модуля), Domain/Application/Infrastructure/Presentation, 3 контроллера, 4 команды (`app:support:consume`, `app:support:dispatch-pending`, `app:support:max-status`, `app:support:max-subscribe`), DI; подключение в `init.php`, `routes/rabit-api.php`, E2E `prepare.php`.
- Упрощения к плану PR #48: реплика = outbox (K3-T01), webhook пишет ответ синхронно с дедупликацией по уникальному mid (K3-T02); история родителя открывается только ключом беседы, без повторной проверки ссылки галереи (ключ галереи не хранится).
- Проверки (docker `rabit-api-php-cli:d1-local`, vendor `rabit-e6-vendor`, `--network none`):
  - `vendor/bin/phpunit` — PASS, 764 теста / 44861 assertions (новые: support 34, MAX classifier 11 случаев).
  - `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` — PASS, No errors.
  - `vendor/bin/php-cs-fixer fix --config=public/local/php-cs-fixer.php --allow-risky=yes --path-mode=intersection --dry-run <104 изменённых файла>` — после автоисправления 5 файлов: 0 замечаний.

### 25.09.2026 — frontend, E2E и развёртывание

- Frontend: `support/{types,rules,api}.ts`, `useQuestionThread` (опрос 15 с на видимой вкладке, повтор с тем же Idempotency-Key только при неизвестном исходе), `useGalleryQuestion` (ключ беседы и «прочитано» в localStorage по токену галереи), `QuestionThread.vue`, `GalleryQuestionDialog.vue`, кнопка «Вопрос куратору» с отметкой нового ответа, ссылка из «Помощи», раздел `/cabinet/questions` для head/teacher (маршрут, whitelist, навигация), иконка `mdi-send-outline` в реестре. В демо-режиме канал скрыт.
- E2E: `frontend/e2e/live/zz-questions.spec.ts` (группа a), `shell.spec.ts` проверяет пункт меню по ролям, `api/tools/e2e/verify-support.php` (след браузера в MySQL, доставка с подменой MAX, webhook через nginx стенда `backend`, CHECK БД); runner: MAX-переменные стенда и верификатор.
- Развёртывание: `api-support-consumer` (dev профиль `support`, Swarm с секретами `rebit_max_bot_token`, `morefoto_support_max_webhook_secret`), webhook-секрет в `api-php-fpm`, cron `app:support:dispatch-pending`, Makefile (`dispatch-support`, `support-max-status`, queue-*), `.env.example`, `deploy/swarm-publish-runtime.sh`.
- Канон MoreFoto: SUP-08/10 уточнены под реализацию (ключ беседы без проверки ссылки галереи, `author=staff`), синхронизация E6/K3 — `docs/waves/k3/morefoto-implementation.patch`; наложение на снимок до волны воспроизводит канон — PASS; `validate.py`, `validate-postman.cjs` — PASS.
- Проверки: `npm run check` (lint, stylelint, typecheck, typecheck:e2e, test:ui 32/32) — PASS; `npm run test:commerce` — 197/197 PASS; `npm run build-only` — PASS (docker `mcr.microsoft.com/playwright:v1.52.0-jammy`, node_modules `rabit-issues6263-node`, `--network none`). PHPStan с `tools/e2e/phpstan.neon` — PASS; `php -l` верификатора — PASS.

### 25.09.2026 — частичные E2E группы a и исправления

- Прогон 1 (`rabit-e2e-33e7f2531baa`): 66 passed, 2 failed — `zz-questions`: `getByRole('alert')` нашёл и подсказки полей Vuetify (strict mode); второй тест упал следом (нет `var/k3-questions.json`). Исправлено: `data-testid="question-problem"` (коммит «test(k3): отдельный test id…»).
- Прогон 2 (`rabit-e2e-7bd431fabcd6`): браузер 68/68 PASS; `verify-support.php` FAIL «accepted update is acknowledged». По логам FPM webhook отвечал 422 (`DtoMetadataService:304`): `RequestHelper` декодирует JSON в `stdClass`, перевод в массивы делал только `StrictRequestValues`; webhook MAX не может быть строгим (MAX добавляет поля без версии). Исправлено в `rebit.share`: `RequestHelper::jsonObjectsToArrays` для нестрогих JSON-DTO + `MaxUpdateContractTest` на реальные Update MAX (без E2E ловит этот класс ошибок). До исправления в верификаторе прошли: след браузера в MySQL, отсутствие ключа беседы в БД, доставка через подменённый MAX (mid, повтор, отказ), 401 для неверного и пустого секрета.
- Прогон 3 оборван завершением прошлой сессии; его стенд остановлен (`make e2e-down`), чужой стенд другой сессии не трогался.
- Неблокирующее (вне K3): `DtoMetadataService:445` вызывает устаревший `SerializedName::getSerializedName()` (Symfony 7.4) — deprecation при любом `SerializedName`; оформить issue.
- После rebase на `4ca7e9c`: `verify-wave-graph.py` — PASS (52/116, ready K3); `vendor/bin/phpunit` — PASS 779 тестов / 44928 assertions (1 deprecation — выше); `phpstan` — PASS; `npm run check` — PASS (test:ui 32/32); `npm run test:commerce` — PASS 200/200.

## Результаты проверок

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| K3-01 | PASS | 25.09.2026 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 52/116, ready K3 |
| K3-02 | PASS | 25.09.2026 | `SupportArchitectureTest`: 3 контроллера без Bitrix/ServiceLocator/HttpException, action с одним `*RequestDto`; DTO без методов; 16 UseCase/Service с русским phpDoc; Application/Domain без Bitrix |
| K3-03 | PASS (unit) | 25.09.2026 | `MaxDeliveryAndWebhookTest::testWithoutConfiguredGroupReplyStaysPending`; HTTP-часть — в E2E |
| K3-04 | PASS | 25.09.2026 | `curl --cacert russian_trusted_root_ca.pem -H 'Authorization: …' https://platform-api2.max.ru/me` → user_id 488939651, `se14459249_bot`, is_bot=true; `GET /subscriptions` → 0. Токен не выводился |
| MAX-01 | PASS (unit) | 25.09.2026 | `QuestionUseCasesTest`: беседа, реплика, ключ; повтор с тем же ключом без второй реплики; 409 при другом теле; HTTP 201 — E2E `zz-questions` |
| MAX-02 | PASS (unit+E2E) | 25.09.2026 | Неверный/чужой ключ — 404 (`testParentContinuesOnlyWithItsOwnKey`); другой браузер не видит переписку (`zz-questions`, прогон 2) |
| MAX-03 | PASS (E2E) | 25.09.2026 | `zz-questions`: история после reload; отметка «новый ответ» (прогон 2) |
| MAX-04 | PASS (E2E) | 25.09.2026 | `zz-questions`: воспитатель пишет из кабинета, reload; куратору 403 (прогон 2) |
| MAX-05 | PASS (unit+E2E verifier) | 25.09.2026 | `MaxDeliveryAndWebhookTest`; `verify-support.php` раздел 2 (прогон 2) |
| MAX-06 | PASS (unit) | 25.09.2026 | `testCuratorReplyToBotMessageIsStoredOnce`; HTTP — ждёт прогон 4 |
| MAX-07 | PASS (unit) / PENDING (HTTP) | 25.09.2026 | `testOtherGroupMessagesDoNotReachTheSite`; 401 по HTTP PASS (прогон 2); 200 без изменений — ждёт прогон 4 |
| MAX-08 | PASS (unit) / PENDING (HTTP) | 25.09.2026 | Повтор webhook — unit; HTTP-повтор — ждёт прогон 4 |
| MAX-09 | PASS (unit) | 25.09.2026 | Retry/unknown/stale/без группы — `MaxDeliveryAndWebhookTest`; RabbitMQ-сбой — не моделировался |
| MAX-10 | PASS (unit+E2E) | 25.09.2026 | `TextPolicyAndSealTest`; сетевой сбой с повтором тем же ключом — `zz-questions` |
| MAX-11 | PENDING | 25.09.2026 | Логи стенда не проверены на тексты/ключи |
| MAX-12 | PENDING | 25.09.2026 | Живая проверка на stage после деплоя |
| MAX-13 | PENDING | 25.09.2026 | Снимки desktop/mobile — артефакты `zz-questions`, визуальный просмотр пользователем |
