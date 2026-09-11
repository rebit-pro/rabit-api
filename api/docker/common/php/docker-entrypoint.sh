#!/bin/sh
set -e

load_runtime_env() {
    if [ -f /app/public/.env ]; then
        set -a
        # shellcheck disable=SC1091
        . /app/public/.env
        set +a
    fi

    if [ -f /run/secrets/rebit_smtp_password ]; then
        REBIT_SMTP_PASSWORD="$(tr -d '\r' < /run/secrets/rebit_smtp_password)"
        export REBIT_SMTP_PASSWORD
    fi

    # Подставляем пароль RabbitMQ из secret в MESSENGER_TRANSPORT_DSN.
    # DSN в .env содержит плейсхолдер __RABBITMQ_PASSWORD__, который заменяется на реальный пароль.
    # Если secret отсутствует — DSN остаётся как есть (dev-окружение).
    if [ -f /run/secrets/rebit_rabbitmq_password ] && [ -n "${MESSENGER_TRANSPORT_DSN:-}" ]; then
        RABBITMQ_SECRET_PASSWORD="$(tr -d '\r' < /run/secrets/rebit_rabbitmq_password)"
        RABBITMQ_SECRET_PASSWORD_ENCODED="$(php -r 'echo rawurlencode($argv[1]);' "$RABBITMQ_SECRET_PASSWORD")"
        MESSENGER_TRANSPORT_DSN="$(php -r 'echo str_replace($argv[1], $argv[2], $argv[3]);' '__RABBITMQ_PASSWORD__' "$RABBITMQ_SECRET_PASSWORD_ENCODED" "$MESSENGER_TRANSPORT_DSN")"
        export MESSENGER_TRANSPORT_DSN
        unset RABBITMQ_SECRET_PASSWORD RABBITMQ_SECRET_PASSWORD_ENCODED
    fi
}

configure_msmtp() {
    host="${REBIT_SMTP_HOST:-}"
    port="${REBIT_SMTP_PORT:-25}"
    encryption="${REBIT_SMTP_ENCRYPTION:-none}"
    username="${REBIT_SMTP_USERNAME:-}"
    password="${REBIT_SMTP_PASSWORD:-}"
    from_email="${REBIT_SMTP_FROM_EMAIL:-noreply@localhost}"
    tls_certcheck="${REBIT_SMTP_TLS_CERTCHECK:-off}"

    if [ -z "$host" ]; then
        return
    fi

    tls="off"
    tls_starttls="off"

    case "$encryption" in
        tls)
            tls="on"
            tls_starttls="on"
            ;;
        ssl)
            tls="on"
            tls_starttls="off"
            ;;
        none)
            ;;
        *)
            echo "[mail] Unsupported REBIT_SMTP_ENCRYPTION: $encryption" >&2
            exit 1
            ;;
    esac

    auth="off"
    if [ -n "$username" ]; then
        auth="on"
    fi

    cat > /etc/msmtprc <<EOF
# Generated automatically by docker-entrypoint.sh
defaults
syslog LOG_MAIL
account default
host $host
port $port
from $from_email
auth $auth
user $username
password $password
tls $tls
tls_starttls $tls_starttls
tls_certcheck $tls_certcheck
EOF

    chmod 644 /etc/msmtprc
}

fix_log_permissions() {
    log_dir="/app/logs"

    if [ -d "$log_dir" ]; then
        mkdir -p "$log_dir/logstash"
        chown -R www-data:www-data "$log_dir"
        find "$log_dir" -type d -exec chmod 2775 {} +
        find "$log_dir" -type f -exec chmod 664 {} +
    fi
}

load_runtime_env
configure_msmtp
fix_log_permissions

exec "$@"
