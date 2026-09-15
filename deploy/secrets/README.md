# Production secrets templates

В этой папке лежат только шаблоны.

## SMTP пароль для регистрации по e-mail

1. Скопируйте шаблон:

```bash
cp deploy/secrets/rebit_smtp_password.example /srv/rabit-api/swarm/secrets/rebit_smtp_password
```

2. Откройте файл на production-сервере и замените содержимое на **пароль приложения Яндекса** для ящика `rebit-2017@yandex.ru`.
3. Файл должен содержать только пароль, без комментариев и без лишних данных.
4. После этого создайте versioned Docker Swarm secret командой `deploy/swarm-publish-runtime.sh` с тем же `VERSION`, что и `BUILD_NUMBER` деплоя.

Пример:

```bash
ssh rebit-pro 'VERSION=145 OUTPUT_ENV_FILE=/srv/rabit-api/swarm/runtime-objects.env /srv/rabit-api/swarm/swarm-publish-runtime.sh 145'
```

Важно:
- `rebit_smtp_password` — имя source-файла;
- `rebit_smtp_password_145` — имя versioned Swarm secret для `BUILD_NUMBER=145`.

## RabbitMQ пароль для очередей сообщений

1. Скопируйте шаблон:

```bash
cp deploy/secrets/rebit_rabbitmq_password.example /srv/rabit-api/swarm/secrets/rebit_rabbitmq_password
```

2. Откройте файл на production-сервере и замените содержимое на **надёжный пароль** для RabbitMQ.
3. Файл должен содержать только пароль, без комментариев и без лишних данных.
4. В `MESSENGER_TRANSPORT_DSN` в файле `backend.env` используйте плейсхолдер `__RABBITMQ_PASSWORD__` — entrypoint подставит реальный пароль из secret при старте контейнера:

```
MESSENGER_TRANSPORT_DSN=amqp://rebit:__RABBITMQ_PASSWORD__@rabbitmq:5672/rebit
```

5. Создайте versioned Swarm secret через `deploy/swarm-publish-runtime.sh`.

Важно:
- `rebit_rabbitmq_password` — имя source-файла;
- `rebit_rabbitmq_password_145` — имя versioned Swarm secret для `BUILD_NUMBER=145`.

## Telegram bot token для общего клиента rebit.share

1. Скопируйте шаблон:

```bash
cp deploy/secrets/rebit_telegram_bot_token.example /srv/rabit-api/swarm/secrets/rebit_telegram_bot_token
```

2. Укажите токен бота в файле на production-сервере. Файл должен содержать только токен, без комментариев.
3. При необходимости настройте адрес Bot API и прокси в `/srv/rabit-api/swarm/backend.env`:

```dotenv
REBIT_NOTIFICATION_TELEGRAM_API_URL=https://api.telegram.org
REBIT_NOTIFICATION_TELEGRAM_PROXY=
```

4. Создайте versioned Swarm secret через `deploy/swarm-publish-runtime.sh`. `runtime-env.php` передаёт секрет в `REBIT_NOTIFICATION_TELEGRAM_BOT_TOKEN`.

Общий клиент принимает `chat_id` при вызове. Модули `rebit.notification` и `rebit.leadhunter` используют его соответственно для заявок с сайта и найденных на внешних площадках заявок.

- `rebit_telegram_bot_token` — имя source-файла;
- `rebit_telegram_bot_token_145` — имя versioned Swarm secret для `BUILD_NUMBER=145`.

## Охота за лидами (rebit.leadhunter, команда app:leadhunter:scan)

Отдельного секрета не требует: используется общий Telegram bot token из `rebit.share` (см. выше).
Чат и правила мониторинга добавьте в `/srv/rabit-api/swarm/backend.env`. JSON правил записывается одной строкой:

```dotenv
REBIT_NOTIFICATION_TELEGRAM_CHAT_ID=123456789
REBIT_LEADHUNTER_RULES='[{"source":"flRu","keywords":["битрикс","bitrix"]},{"source":"flRu","params":{"category":2,"subcategory":27},"keywords":[]}]'
```

Формат правил и остальные переменные (`REBIT_LEADHUNTER_TELEGRAM_CHAT_ID` — если нужен
отдельный чат вместо `REBIT_NOTIFICATION_TELEGRAM_CHAT_ID`; `REBIT_LEADHUNTER_FALLBACK_EMAIL` — резервная
доставка письмом при недоступном Telegram) — см. `.env.example` в корне репозитория.
Для fl.ru «Сайты под ключ» = `category=2, subcategory=27`.

Сканер запускает `api-cron` каждые 5 минут. Он отправляет сообщения напрямую через общий Telegram-клиент; отдельный consumer не нужен. Локально эти настройки читает `api/public/.env`.

## Заявки с сайта (rebit.notification)

`POST /api/v1/lead` доставляет заявку и опциональный файл напрямую в Telegram. Consumer RabbitMQ для этого сценария не требуется.

Настройки в `backend.env`: общий `REBIT_NOTIFICATION_TELEGRAM_CHAT_ID`, `REBIT_NOTIFICATION_LEAD_MAX_FILE_MB` (по умолчанию 15), необязательный `REBIT_NOTIFICATION_LEAD_FALLBACK_EMAIL`. Если собственный fallback email не задан, используется `REBIT_LEADHUNTER_FALLBACK_EMAIL`; когда оба пусты, резерв отключён. Для резервной доставки требуется почтовое событие `REBIT_NOTIFICATION_LEAD` из миграции `Version20260820120001` и настроенный SMTP.

`POST /api/v1/lead/mos-dizel` использует прямую email-доставку. В `backend.env` требуется `REBIT_NOTIFICATION_MOS_DIZEL_EMAIL`; почтовое событие `REBIT_NOTIFICATION_MOS_DIZEL_LEAD` создаёт миграция `Version20260915110001`. Адрес получателя не передаётся в публичном запросе.

В новой установке модуль регистрируется своим installer после `rebit.share`. В существующей установке восстановление исходников не требует повторного применения уже выполненных миграций. Восстановление конфигурации не меняет реальные секреты и не запускает отправку.
