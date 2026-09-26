# Issue #111 — обращения со страницы входа: лимит по IP клиента

## Цель и контекст

[#111](https://github.com/rebit-pro/rabit-api/issues/111) — неблокирующие замечания ревью PR #108 (OPS-login-feedback,
`POST /api/v1/public/feedback`). Это issue-ветка, не волна: `docs/waves/graph.json` не меняется.

- Ветка: `codex/issues-111-feedback-ip-limit`, worktree `/home/user/rabit-api-worktrees/issues-111-feedback-ip-limit`.
- Base: `origin/main` `23642d4`.
- Затронутый код: `morefoto.support` — `SendGuestFeedbackUseCase`, `GuestFeedbackController`, `FeedbackMapper`,
  `BitrixQuestionRepository`; общая сборка request DTO в `rebit.share`.

Суть проблемы (п. 1 issue): `GUEST_QUESTIONS_PER_HOUR = 30` считается на весь сайт. Один источник, отправив
30 обращений с разными `Idempotency-Key`, на час закрывает форму всем гостям (`429 RATE_LIMITED`).

## Установленные факты

- **Цепочка прокси.** Stage/prod: клиент → Traefik (TLS, в docker-сети) → nginx frontend
  (`frontend/docker/production/nginx/conf.d/default.conf`: `X-Real-IP $remote_addr`,
  `X-Forwarded-For $proxy_add_x_forwarded_for`) → nginx backend (fastcgi, `real_ip` не настроен) → php-fpm.
  Прямой вход `api.rebit-pro.ru`: Traefik → nginx backend → php-fpm. E2E: браузер → nginx frontend → nginx backend.
- Поэтому `REMOTE_ADDR` в PHP — адрес соседнего контейнера (частная сеть), а `X-Real-IP` перезаписан nginx frontend
  адресом Traefik: у всех гостей он один и тот же. Реальный клиент есть только в `X-Forwarded-For`
  (Traefik без доверенных `forwardedHeaders` удаляет пришедший от клиента `X-Forwarded-*` и ставит адрес клиента сам).
  Левые элементы `X-Forwarded-For` клиент может подделать, правые добавлены нашими прокси.
- Redis в модуле и в `rebit.share` как контракт кеша не подключён (есть только `CacheCleanerInterface`; в prod-стеке
  memcached). `BitrixDedupCache` атомарен только при KeyValueEngine, иначе best-effort. Счётчик в Bitrix-кеше ненадёжен.
- Транзакция `BitrixSupportTransaction` — READ COMMITTED; `RuntimeException` внутри неё превращается в
  `503 SUPPORT_UNAVAILABLE`, `HttpException` пробрасывается как есть.
- Секреты: `REBIT_ENCRYPTION_KEY` — существующий серверный секрет платформы (docker secret
  `/run/secrets/rebit_encryption_key`, загрузка в `runtime-env.php`, есть в `docker-compose.yml` и
  `docker-compose-production.yml`), кодом сейчас не используется и третьим сторонам не передаётся.
  `MOREFOTO_SUPPORT_MAX_WEBHOOK_SECRET` известен платформе MAX (она подписывает им webhook) — для соли не годится.
- Стенд E2E применяет миграции явным списком `api/tools/e2e/prepare.php`.

## Решения

- **R1. Источник IP — общая инфраструктура `rebit.share`.** Новый атрибут параметра `#[ClientAddress]` и
  `ClientAddressResolver`: `RequestTechnicalValues` подставляет адрес так же, как `#[RequestHeader]`. Поле нельзя
  передать в body (`UNKNOWN_FIELD`). Контроллер не читает headers и `HttpRequest`.
  Алгоритм (как `real_ip_recursive` nginx): доверенный прокси — любой непубличный адрес (частные, loopback,
  link-local, ULA по `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`). Если `REMOTE_ADDR` публичный — это
  клиент. Иначе `X-Forwarded-For` читается справа налево, доверенные звенья пропускаются; первый публичный адрес —
  клиент. Мусорное звено останавливает разбор — берётся последний проверенный адрес. Подделанный клиентом левый
  элемент не используется, пока правее есть его настоящий публичный адрес.
  Почему не `X-Real-IP` и не `real_ip` в nginx: заголовок в текущей цепочке содержит адрес Traefik, а правка nginx
  frontend потребовала бы пересборки и выката frontend-образа; решение в backend работает при любом числе звеньев.
- **R2. Хеширование.** Порт `GuestAddressHasherInterface` (Application), реализация `GuestAddressHasher`
  (Infrastructure/Crypto): HMAC-SHA256 по ключу `HKDF(REBIT_ENCRYPTION_KEY, info=morefoto.support.guest-address)`.
  IPv4-mapped IPv6 приводится к IPv4, IPv6 — к префиксу /64 (иначе один абонент меняет адрес внутри своей сети).
  Открытый IP нигде не хранится и не логируется. Без ключа (короче 32 символов) сценарий отказывает
  `503 SUPPORT_UNAVAILABLE` — хешировать ПДн без секрета нельзя, фиктивного fallback нет.
- **R3. Счётчик — таблица `mf_support_guest_address` в MySQL**, не Redis: Redis в модуле не подключён, а MySQL-транзакция
  сценария уже есть. Строка на хеш адреса: `ADDRESS_HASH` (PK), `WINDOW_STARTED_AT`, `QUESTIONS`.
  В транзакции сценария: удалить окна старше часа (это же хранение: хеш живёт не дольше окна + до следующего
  гостевого обращения) → `INSERT … ON DUPLICATE KEY UPDATE` блокирует строку адреса → проверить число →
  после создания обращения увеличить. Окно фиксированное, от первого обращения адреса. Строка не связана с беседой и
  контактом. Отказ 429 откатывает транзакцию, отклонённые попытки окно не расходуют.
  Колонку в `mf_support_idempotency`/`mf_support_question` не выбрал: хеш лёг бы рядом с именем и контактом гостя
  навсегда, и подсчёт остался бы с гонкой.
- **R4. Лимит `GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR = 5`** рядом с общим 30. Обоснование: гость пишет 1–2 раза,
  повторы с тем же `Idempotency-Key` не считаются. 5 оставляет запас на исправления и общий NAT (офис, мобильный
  оператор) при низком потоке формы. Чтобы исчерпать общий лимит 30, нужно ≥ 6 разных адресов. Значение
  пользователь может поменять на ревью.
- **R5. Порядок проверок:** повтор ключа (без лимитов) → лимит адреса → общий лимит → создание. Оба отказа — тот же
  `429 RATE_LIMITED`, фронт уже показывает «Сейчас слишком много обращений. Попробуйте через час.».
- **R6. П. 2 (гонка).** Для лимита адреса закрыта бесплатно: строка счётчика заблокирована до конца транзакции,
  параллельные запросы одного адреса выполняются по очереди. Общий лимит 30 и лимит группы
  `AskGalleryQuestionUseCase` осознанно не меняются (превышение на единицы, вреда нет) — остаются в #111.
- **R7. П. 3 (индекс `(AUTHOR, CREATED_AT)`)** новому запросу не нужен: счётчик адреса читается по первичному ключу.
  Осознанно исключён, остаётся в #111. PR — `Refs #111`.

## Scope

- `rebit.share`: `Attribute/ClientAddress`, `ClientAddressResolver`, `RequestTechnicalValues`; stub
  `HttpRequest::getRemoteAddress()` для PHPStan; unit-тест резолвера.
- `morefoto.support`: request/input DTO, `FeedbackMapper`, порт и реализация хешера, репозиторий, UseCase, DI,
  `install/index.php`; unit-тесты.
- Миграция `Version20260926120001` (таблица счётчика) + ID в `api/tools/e2e/prepare.php`.
- E2E: `REBIT_ENCRYPTION_KEY` для php-fpm стенда (`tools/run-browser-e2e.py`), проверка лимита в `verify-support.php`.
- `.env.example`: `REBIT_ENCRYPTION_KEY`.

Исключено: п. 2 для общего лимита и `AskGalleryQuestionUseCase`, п. 3, frontend (тексты 429 не меняются),
настройка nginx/Traefik, деплой.

## Риски и ограничения

- На stage/prod должен быть задан `REBIT_ENCRYPTION_KEY` (≥ 32 символов) у php-fpm, иначе гостевая форма → 503.
  Проверить до переключения backend.
- Если в цепочку встанет прокси с публичным адресом (внешний CDN), клиентом будет считаться он — тогда нужен явный
  список доверенных сетей. Сейчас все звенья в docker-сетях.
- Гость из частной сети (внутренний стенд) может подделать `X-Forwarded-For` — это доверенная зона.
- Общий NAT: до 5 обращений в час на адрес на всех его пользователей.

## Checklist

- [x] S1. План и журнал.
- [x] S2. `rebit.share`: атрибут, резолвер, `RequestTechnicalValues`, тест.
- [x] S3. `morefoto.support`: DTO, mapper, хешер, репозиторий, UseCase, DI, installer.
- [x] S4. Миграция + `prepare.php`.
- [x] S5. Unit-тесты UseCase и хешера.
- [x] S6. E2E: env стенда, `verify-support.php`.
- [x] S7. Быстрые проверки: PHPUnit, PHPStan, php-cs-fixer по изменённым файлам.
- [x] S8. Commit, push, PR #121 (`Refs #111`).
- [ ] S9. (после ревью) полный `make test-e2e`.

## Критерии приёмки

- 6-е за час новое обращение с одного адреса → `429 RATE_LIMITED`. С другого адреса в то же время → `202`.
- Общий лимит 30 сохраняется для запросов с разных адресов.
- Повтор с тем же `Idempotency-Key` не расходует лимит и возвращает тот же номер.
- В БД нет открытого IP, только HMAC; строки старше часа удаляются при следующем гостевом обращении.
- Подделанный левый элемент `X-Forwarded-For` не меняет адрес клиента; body не может задать `clientAddress`.
- Без `REBIT_ENCRYPTION_KEY` → `503 SUPPORT_UNAVAILABLE`, обращение не создаётся.

## Тест-кейсы

| ID | Предусловия / действие | Ожидаемый результат | Проверка |
|---|---|---|---|
| T01 | 5 обращений с адреса A, 6-е с A | 6-е → 429 `RATE_LIMITED`, обращение не создано | PHPUnit `GuestFeedbackTest` |
| T02 | 5 с A (лимит исчерпан), затем с B | B → принято | PHPUnit `GuestFeedbackTest` |
| T03 | 30 обращений с 30 разных адресов, 31-е с нового адреса | 429; через час принято | PHPUnit `GuestFeedbackTest` |
| T04 | Окно адреса старше часа | счётчик адреса начинается заново, старые строки удалены | PHPUnit `GuestFeedbackTest` |
| T05 | Повтор того же ключа при исчерпанном лимите адреса | тот же номер, без 429 | PHPUnit `GuestFeedbackTest` |
| T06 | Резолвер: публичный `REMOTE_ADDR`; частный + XFF; подделанный левый XFF; мусор в XFF; только частные; IPv6 | клиент — правый публичный адрес; подделка игнорируется | PHPUnit `ClientAddressResolverTest` |
| T07 | Хешер: один IPv6 /64, mapped IPv4, разные ключи, нет ключа, невалидный адрес | одинаковый хеш для /64 и mapped; в хеше нет IP; без ключа RuntimeException | PHPUnit `GuestAddressHasherTest` |
| T08 | Архитектура модуля | контроллер без headers/Bitrix, DTO без методов, phpDoc UseCase | PHPUnit `SupportArchitectureTest` |
| T09 | Статический анализ | без новых ошибок | PHPStan по изменённым файлам |
| T10 | Стиль | без замечаний | php-cs-fixer `--dry-run` по изменённым файлам |
| T11 | Реальный MySQL + nginx: 5 обращений с `X-Forwarded-For: 203.0.113.7`, 6-е, затем другой адрес | 202×5, 429, 202; в `mf_support_guest_address` 64-hex хеш, нет строки с IP | `verify-support.php` в `make test-e2e` (gate) |
| T12 | Browser E2E гостевой формы `/login` | как раньше, 202 и номер обращения | `zz-questions.spec.ts` в `make test-e2e` (gate) |
| T13 | Миграция на стенде | таблица создаётся, `down()` удаляет | `make test-e2e` (gate) |
