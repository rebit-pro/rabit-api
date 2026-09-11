# Загрузить переменные окружения; ENV_FILE=/dev/null отключает локальный .env.
ENV_FILE ?= .env
ifneq (,$(wildcard $(ENV_FILE)))
    include $(ENV_FILE)
    export $(shell sed 's/=.*//' $(ENV_FILE))
endif

init: api-clear docker-down-clear docker-pull docker-build docker-up cron-up queue-up
up: docker-up
down: docker-down
restart: down up
lint: api-lint
fix: api-cs-fix
analyze: api-analyze
check: api-cs-check lint analyze
test: api-test
test-unit: api-test-unit
test-unit-coverage: api-test-unit-coverage
test-functional: api-test-functional
test-functional-coverage: api-test-functional-coverage

update-deps: api-composer-update restart
migrate: api-migrate

docker-up:
	docker compose up -d

docker-down:
	docker compose down --remove-orphans

docker-down-clear:
	docker compose down --remove-orphans

docker-pull:
	docker compose pull

docker-build:
	docker compose build --pull

docker-cache-clear:
	docker builder prune -f

# api
api-clear:
	docker run --rm -v ${PWD}/api:/app -w /app alpine:3.21 sh -c 'rm -rf var/*'

api-init: api-composer-install

api-composer-install:
	docker compose run --rm api-php-cli composer install

api-composer-update:
	docker compose run --rm api-php-cli composer update

api-lint:
	docker compose run --rm api-php-cli composer lint

api-cs-check:
	docker compose run --rm api-php-cli composer cs-check

api-cs-fix:
	docker compose run --rm api-php-cli composer cs-fix

api-analyze:
	docker compose run --rm api-php-cli composer phpstan

api-analyze-baseline:
	docker compose run --rm api-php-cli php vendor/bin/phpstan analyse --configuration=phpstan.neon --generate-baseline=phpstan-baseline.neon

api-test:
	docker compose run --rm api-php-cli composer test

api-test-unit:
	docker compose run --rm api-php-cli composer test -- --testsuite=unit

api-test-unit-coverage:
	docker compose run --rm api-php-cli composer test-coverage -- --testsuite=unit

api-test-functional:
	docker compose run --rm api-php-cli composer test -- --testsuite=functional

api-test-functional-coverage:
	docker compose run --rm api-php-cli composer test-coverage -- --testsuite=functional

api-cli:
	docker compose run --rm api-php-cli php public/local/bin/bitrix-console

MODULE_NAME ?= rebit.share

annotate:
	docker compose run --rm api-php-cli sh -c "cd /app/public && php bitrix/bitrix.php orm:annotate -c -m $(MODULE_NAME) local/modules/$(MODULE_NAME)/orm_annotation.php"

api-migrate:
	docker compose run --rm api-php-cli php /app/public/local/modules/sprint.migration/tools/migrate.php up

api-migrate-status:
	docker compose run --rm api-php-cli php /app/public/local/modules/sprint.migration/tools/migrate.php ls

# --- Cron ---
cron-up:
	docker compose up -d api-cron

cron-down:
	docker compose stop api-cron

cron-restart:
	docker compose restart api-cron

cron-logs:
	docker compose logs -f api-cron

# --- Queue ---
queue-up:
	docker compose up -d api-audit-consumer

queue-down:
	docker compose stop api-audit-consumer

queue-restart:
	docker compose restart api-audit-consumer

queue-logs:
	docker compose logs -f api-audit-consumer

consume-audit:
	docker compose run --rm api-php-cli php public/local/bin/bitrix-console app:audit:consume

consume-audit-once:
	docker compose run --rm api-php-cli php public/local/bin/bitrix-console app:audit:consume --limit=10 --time-limit=30

# ==============================================================================
# PRODUCTION
# ==============================================================================

PORT ?= 22
DEPLOY_USER ?= deploy
STACK_NAME ?= rabit-api
REMOTE ?= $(DEPLOY_USER)@$(HOST)
RELEASE_DIR ?= rabit-api_$(BUILD_NUMBER)
LINK_DIR ?= rabit-api
COMPOSE_SRC ?= docker-compose-production.yml
COMPOSE_DST ?= docker-compose.yml
APP_DEBUG ?= 0
APP_ENV ?= production
KEEP_RELEASES ?= 2
BITRIX_HOST_DIR ?= /srv/rabit-api/bitrix
LOGS_HOST_DIR ?= /srv/rabit-api/logs
BACKEND_ENV_CONFIG_NAME ?= rebit_backend_env_$(BUILD_NUMBER)
REBIT_ENCRYPTION_KEY_SECRET_NAME ?= rebit_encryption_key_$(BUILD_NUMBER)
REBIT_GEETEST_CAPTCHA_KEY_SECRET_NAME ?= rebit_geetest_captcha_key_$(BUILD_NUMBER)
REBIT_MYSQL_PASSWORD_SECRET_NAME ?= rebit_mysql_password_$(BUILD_NUMBER)
REBIT_MYSQL_ROOT_PASSWORD_SECRET_NAME ?= rebit_mysql_root_password_$(BUILD_NUMBER)
REBIT_SMTP_PASSWORD_SECRET_NAME ?= rebit_smtp_password_$(BUILD_NUMBER)
REBIT_RABBITMQ_PASSWORD_SECRET_NAME ?= rebit_rabbitmq_password_$(BUILD_NUMBER)
REBIT_TELEGRAM_BOT_TOKEN_SECRET_NAME ?= rebit_telegram_bot_token_$(BUILD_NUMBER)

guard-%:
	@if [ -z '$($*)' ]; then echo 'Required variable $* is not set'; exit 1; fi

deploy-check-env: \
	guard-HOST \
	guard-BUILD_NUMBER \
	guard-REGISTRY \
	guard-IMAGE_TAG

# --- Build ---
# Собирает Docker-образы для production
#
# Требуемые переменные: REGISTRY, IMAGE_TAG
#
# Пример:
#   REGISTRY=ghcr.io/rebit-pro IMAGE_TAG=abc12345 make build
build: build-api

build-api:
	docker --log-level=debug build --pull --load --file=api/docker/production/nginx/Dockerfile --tag=$(REGISTRY)/rabit-api-nginx:$(IMAGE_TAG) api
	docker --log-level=debug build --pull --load --file=api/docker/production/php-fpm/Dockerfile --tag=$(REGISTRY)/rabit-api-php-fpm:$(IMAGE_TAG) api
	docker --log-level=debug build --pull --load --file=api/docker/production/php-cli/Dockerfile --tag=$(REGISTRY)/rabit-api-php-cli:$(IMAGE_TAG) api

try-build:
	REGISTRY=localhost IMAGE_TAG=0 make build

# --- Push ---
# Пушит собранные образы в Docker-реестр
#
# Требуемые переменные: REGISTRY, IMAGE_TAG
#
# Пример:
#   REGISTRY=ghcr.io/rebit-pro IMAGE_TAG=abc12345 make push
push: push-api

push-api:
	docker push $(REGISTRY)/rabit-api-nginx:$(IMAGE_TAG)
	docker push $(REGISTRY)/rabit-api-php-fpm:$(IMAGE_TAG)
	docker push $(REGISTRY)/rabit-api-php-cli:$(IMAGE_TAG)

# --- Deploy ---
# Деплоит приложение в Docker Swarm на удалённый сервер.
# После деплоя удаляет старые релизы (оставляет KEEP_RELEASES=2) и неиспользуемые Docker-образы.
#
# Требуемые переменные:
#   HOST, PORT, DEPLOY_USER, BUILD_NUMBER, REGISTRY, IMAGE_TAG
# Опционально (для docker login на сервере):
#   REGISTRY_HOST, REGISTRY_USER, TOKEN_GIT_HUB
# Опционально (если нужно переопределить versioned Swarm names):
#   BACKEND_ENV_CONFIG_NAME, REBIT_ENCRYPTION_KEY_SECRET_NAME,
#   REBIT_GEETEST_CAPTCHA_KEY_SECRET_NAME, REBIT_MYSQL_PASSWORD_SECRET_NAME,
#   REBIT_MYSQL_ROOT_PASSWORD_SECRET_NAME, REBIT_SMTP_PASSWORD_SECRET_NAME,
#   REBIT_RABBITMQ_PASSWORD_SECRET_NAME
#
# Пример:
#   HOST=1.2.3.4 PORT=22 DEPLOY_USER=deploy BUILD_NUMBER=42 \
#   REGISTRY=ghcr.io/rebit-pro IMAGE_TAG=abc12345 \
#   REGISTRY_HOST=ghcr.io REGISTRY_USER=user TOKEN_GIT_HUB=ghp_xxx \
#   make deploy
deploy: deploy-check-env
	scp -P $(PORT) $(COMPOSE_SRC) api/deploy/bitrix-settings-extra.php $(REMOTE):~/
	ssh $(REMOTE) -p $(PORT) ' \
		docker network create --driver=overlay traefik-public 2>/dev/null || true \
		&& rm -rf $(RELEASE_DIR) && mkdir $(RELEASE_DIR) \
		&& mv ~/docker-compose-production.yml $(RELEASE_DIR)/$(COMPOSE_DST) \
		&& mkdir -p $(BITRIX_HOST_DIR) \
		&& mv ~/bitrix-settings-extra.php $(BITRIX_HOST_DIR)/.settings_extra.php \
		&& mkdir -p $(LOGS_HOST_DIR)/logstash \
		&& cd $(RELEASE_DIR) \
		&& printf "REGISTRY=%s\nIMAGE_TAG=%s\nBACKEND_ENV_CONFIG_NAME=%s\nREBIT_ENCRYPTION_KEY_SECRET_NAME=%s\nREBIT_GEETEST_CAPTCHA_KEY_SECRET_NAME=%s\nREBIT_MYSQL_PASSWORD_SECRET_NAME=%s\nREBIT_MYSQL_ROOT_PASSWORD_SECRET_NAME=%s\nREBIT_SMTP_PASSWORD_SECRET_NAME=%s\nREBIT_RABBITMQ_PASSWORD_SECRET_NAME=%s\nREBIT_TELEGRAM_BOT_TOKEN_SECRET_NAME=%s\n" \
			"$(REGISTRY)" \
			"$(IMAGE_TAG)" \
			"$(BACKEND_ENV_CONFIG_NAME)" \
			"$(REBIT_ENCRYPTION_KEY_SECRET_NAME)" \
			"$(REBIT_GEETEST_CAPTCHA_KEY_SECRET_NAME)" \
			"$(REBIT_MYSQL_PASSWORD_SECRET_NAME)" \
			"$(REBIT_MYSQL_ROOT_PASSWORD_SECRET_NAME)" \
			"$(REBIT_SMTP_PASSWORD_SECRET_NAME)" \
			"$(REBIT_RABBITMQ_PASSWORD_SECRET_NAME)" \
			"$(REBIT_TELEGRAM_BOT_TOKEN_SECRET_NAME)" > .env \
		&& env | LC_ALL=C sort | grep -E "^[A-Z0-9_]+_(CONFIG|SECRET)_NAME=" | grep -Ev "^(BACKEND_ENV_CONFIG_NAME|REBIT_ENCRYPTION_KEY_SECRET_NAME|REBIT_GEETEST_CAPTCHA_KEY_SECRET_NAME|REBIT_MYSQL_PASSWORD_SECRET_NAME|REBIT_MYSQL_ROOT_PASSWORD_SECRET_NAME|REBIT_SMTP_PASSWORD_SECRET_NAME|REBIT_RABBITMQ_PASSWORD_SECRET_NAME|REBIT_TELEGRAM_BOT_TOKEN_SECRET_NAME)=" >> .env || true \
		&& cd ~ && ln -sfn $(RELEASE_DIR) $(LINK_DIR) \
		&& if [ -n "$(TOKEN_GIT_HUB)" ]; then echo "$(TOKEN_GIT_HUB)" | docker login $(REGISTRY_HOST) -u $(REGISTRY_USER) --password-stdin; fi \
		&& docker pull $(REGISTRY)/rabit-api-nginx:$(IMAGE_TAG) \
		&& docker pull $(REGISTRY)/rabit-api-php-fpm:$(IMAGE_TAG) \
		&& docker pull $(REGISTRY)/rabit-api-php-cli:$(IMAGE_TAG) \
		&& cd $(LINK_DIR) && set -a && . ./.env && set +a \
		&& docker stack deploy --with-registry-auth --prune --resolve-image=never -c $(COMPOSE_DST) $(STACK_NAME)'
	ssh $(REMOTE) -p $(PORT) ' \
		cd ~ && ls -d rabit-api_* 2>/dev/null | sort -t_ -k2 -n | head -n -$(KEEP_RELEASES) | xargs -r rm -rf'
	ssh $(REMOTE) -p $(PORT) ' \
		docker image prune --force \
		|| { status=$$?; printf "[deploy][warn] docker image prune failed with exit %s; deployment is already applied, continuing.\n" "$$status" >&2; true; }'
#	@echo "Waiting for services to start..."
#	sleep 15
#	$(MAKE) api-migrate-deploy

# --- Migrate (production) ---
api-migrate-deploy:
	ssh $(REMOTE) -p $(PORT) 'docker run --rm \
		-v /srv/rabit-api/bitrix:/app/public/bitrix \
		-v /srv/rabit-api/upload:/app/public/upload \
		--network $(STACK_NAME)_default \
		$(REGISTRY)/rabit-api-php-cli:$(IMAGE_TAG) \
		php /app/public/local/modules/sprint.migration/tools/migrate.php up'

# --- Rollback ---
# Откатывает на указанный билд
#
# Требуемые переменные: HOST, PORT, ROLLBACK_BUILD_NUMBER
#
# Пример:
#   HOST=1.2.3.4 ROLLBACK_BUILD_NUMBER=41 make rollback
rollback:
	@if [ -z "$(ROLLBACK_BUILD_NUMBER)" ]; then echo "Set ROLLBACK_BUILD_NUMBER"; exit 1; fi
	ssh $(REMOTE) -p $(PORT) 'test -d rabit-api_$(ROLLBACK_BUILD_NUMBER)'
	ssh $(REMOTE) -p $(PORT) 'ln -sfn rabit-api_$(ROLLBACK_BUILD_NUMBER) $(LINK_DIR)'
	ssh $(REMOTE) -p $(PORT) 'cd $(LINK_DIR) && set -a && . ./.env && set +a && docker stack deploy --with-registry-auth --prune --resolve-image=never -c $(COMPOSE_DST) $(STACK_NAME)'

php-cli:
	docker compose run --rm api-php-cli bash

php-fpm:
	docker compose exec api-php-fpm bash
