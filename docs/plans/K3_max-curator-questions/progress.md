# Прогресс K3 — вопросы куратору с сайта через MAX

## Точка продолжения

- Ветка `codex/k3-max-curator-questions`, worktree `/home/user/rabit-api-worktrees/k3-max-curator-questions`,
  base `1dd4a5f` (merge PR #48). PR ещё не открыт.
- Связанное: [план K3](plan.md), [план PR #48](../max-support-chat-plan/plan.md), `docs/waves/graph.json`,
  канон `../MoreFoto/docs/04-bitrix-modules/backend-waves.json` (не git, изменения — патчем в `docs/waves/k3/`).
- Завершено: merge PR #48; граф синхронизирован (E6 merged, K3 inProgress).
- Сейчас: backend K3 написан и проходит быстрые проверки; далее E2E-верификатор, развёртывание (compose/cron/swarm), frontend.
- Следующий шаг: `api/tools/e2e/verify-support.php` и окружение E2E-стенда.
- Пользователь: бот прошёл модерацию; группа создана; токен кладёт в `~/.config/morefoto/max-bot.env`, бот
  добавляется в группу администратором.
- Блокеры: нет. Открыто MAX-D06 (срок хранения) — только для production.
- Рабочее дерево: `docs/waves/graph.json`, `docs/plans/K3_max-curator-questions/`. Канон MoreFoto изменён на месте.
- Команды: `git status --short`; `python3 tools/verify-wave-graph.py docs/waves/graph.json`.

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

## Результаты проверок

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| K3-01 | PASS | 25.09.2026 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 52/116, ready K3 |
| K3-02 | PASS | 25.09.2026 | `SupportArchitectureTest`: 3 контроллера без Bitrix/ServiceLocator/HttpException, action с одним `*RequestDto`; DTO без методов; 16 UseCase/Service с русским phpDoc; Application/Domain без Bitrix |
| K3-03 | PASS (unit) | 25.09.2026 | `MaxDeliveryAndWebhookTest::testWithoutConfiguredGroupReplyStaysPending`; HTTP-часть — в E2E |
| K3-04 | PASS | 25.09.2026 | `curl --cacert russian_trusted_root_ca.pem -H 'Authorization: …' https://platform-api2.max.ru/me` → user_id 488939651, `se14459249_bot`, is_bot=true; `GET /subscriptions` → 0. Токен не выводился |
| MAX-01…MAX-13 | PENDING | 25.09.2026 | Код не написан |
