#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  VERSION=42 ./deploy/swarm-publish-runtime.sh
  ./deploy/swarm-publish-runtime.sh 42

Environment overrides:
  BASE_DIR=/srv/rabit-api/swarm
  BACKEND_ENV_FILE=/srv/rabit-api/swarm/backend.env
  SECRETS_DIR=/srv/rabit-api/swarm/secrets
  LEGACY_SECRET_DIR=/srv/rabit-api/swarm
  REQUIRED_SECRET_NAMES="rebit_encryption_key rebit_geetest_captcha_key rebit_mysql_password rebit_mysql_root_password rebit_rabbitmq_password"
  OPTIONAL_SECRET_NAMES="rebit_smtp_password rebit_telegram_bot_token rebit_max_bot_token morefoto_support_max_webhook_secret"
  OUTPUT_ENV_FILE=/tmp/rabit-api-swarm-runtime.env

Optional explicit object names:
  BACKEND_ENV_CONFIG_NAME
  <SECRET_FILE_NAME_UPPERCASE>_SECRET_NAME

The script is intended to run on a Docker Swarm manager node.
It creates immutable versioned config/secrets if they do not exist yet.
If an object with the same name already exists, it is kept as-is.
EOF
}

log() {
    printf '[swarm-runtime] %s\n' "$1" >&2
}

fail() {
    printf '[swarm-runtime][error] %s\n' "$1" >&2
    exit 1
}

require_readable_file() {
    local file_path="$1"

    if [[ ! -f "$file_path" ]]; then
        fail "File not found: $file_path"
    fi

    if [[ ! -r "$file_path" ]]; then
        fail "File is not readable: $file_path"
    fi
}

build_secret_env_var_name() {
    local secret_file_name="$1"

    printf '%s_SECRET_NAME' "$(printf '%s' "$secret_file_name" | tr '[:lower:]' '[:upper:]' | sed 's/[^A-Z0-9]/_/g')"
}

contains_secret_key() {
    local needle="$1"
    local secret_key

    for secret_key in "${SECRET_KEYS[@]:-}"; do
        if [[ "$secret_key" == "$needle" ]]; then
            return 0
        fi
    done

    return 1
}

register_secret_source() {
    local secret_key="$1"
    local source_file="$2"

    if contains_secret_key "$secret_key"; then
        return
    fi

    SECRET_KEYS+=("$secret_key")
    SECRET_SOURCE_FILES+=("$source_file")
}

resolve_required_secret_file() {
    local secret_key="$1"
    local primary_path="$SECRETS_DIR/$secret_key"
    local legacy_path="$LEGACY_SECRET_DIR/$secret_key"

    if [[ -f "$primary_path" ]]; then
        require_readable_file "$primary_path"
        printf '%s' "$primary_path"
        return
    fi

    if [[ -f "$legacy_path" ]]; then
        require_readable_file "$legacy_path"
        printf '%s' "$legacy_path"
        return
    fi

    fail "Required secret file not found: $secret_key"
}

resolve_optional_secret_file() {
    local secret_key="$1"
    local primary_path="$SECRETS_DIR/$secret_key"
    local legacy_path="$LEGACY_SECRET_DIR/$secret_key"

    if [[ -f "$primary_path" ]]; then
        require_readable_file "$primary_path"
        printf '%s' "$primary_path"
        return
    fi

    if [[ -f "$legacy_path" ]]; then
        require_readable_file "$legacy_path"
        printf '%s' "$legacy_path"
        return
    fi

    log "Optional secret file not found, skipping: $secret_key"
}

discover_secret_sources() {
    local secret_key
    local secret_path

    for secret_key in "${REQUIRED_SECRET_KEYS[@]}"; do
        if [[ -z "$secret_key" ]]; then
            continue
        fi

        secret_path="$(resolve_required_secret_file "$secret_key")"
        register_secret_source "$secret_key" "$secret_path"
    done

    for secret_key in "${OPTIONAL_SECRET_KEYS[@]}"; do
        if [[ -z "$secret_key" ]]; then
            continue
        fi

        secret_path="$(resolve_optional_secret_file "$secret_key")"

        if [[ -n "$secret_path" ]]; then
            register_secret_source "$secret_key" "$secret_path"
        fi
    done

    if [[ ! -d "$SECRETS_DIR" ]]; then
        return
    fi

    while IFS= read -r secret_key; do
        if [[ -z "$secret_key" ]] || contains_secret_key "$secret_key"; then
            continue
        fi

        secret_path="$SECRETS_DIR/$secret_key"
        require_readable_file "$secret_path"
        register_secret_source "$secret_key" "$secret_path"
    done < <(find "$SECRETS_DIR" -mindepth 1 -maxdepth 1 -type f -printf '%f\n' | LC_ALL=C sort)
}

ensure_swarm_manager() {
    local swarm_state
    local control_available

    swarm_state="$(docker info --format '{{.Swarm.LocalNodeState}}')"
    control_available="$(docker info --format '{{.Swarm.ControlAvailable}}')"

    if [[ 'active' != "$swarm_state" ]]; then
        fail 'Docker Swarm is not active on this host'
    fi

    if [[ 'true' != "$control_available" ]]; then
        fail 'This node is not a Swarm manager'
    fi
}

create_config_if_missing() {
    local config_name="$1"
    local source_file="$2"

    if docker config inspect "$config_name" >/dev/null 2>&1; then
        log "Config already exists, keeping immutable version: $config_name"
        return
    fi

    docker config create "$config_name" "$source_file" >/dev/null
    log "Config created: $config_name"
}

create_secret_if_missing() {
    local secret_name="$1"
    local source_file="$2"

    if docker secret inspect "$secret_name" >/dev/null 2>&1; then
        log "Secret already exists, keeping immutable version: $secret_name"
        return
    fi

    docker secret create "$secret_name" "$source_file" >/dev/null
    log "Secret created: $secret_name"
}

write_output_env_file() {
    local output_file="$1"

    {
        printf 'BACKEND_ENV_CONFIG_NAME=%s\n' "$BACKEND_ENV_CONFIG_NAME"

        for output_env_line in "${OUTPUT_ENV_LINES[@]}"; do
            printf '%s\n' "$output_env_line"
        done
    } > "$output_file"

    log "Environment file written: $output_file"
}

if [[ "${1:-}" == '--help' || "${1:-}" == '-h' ]]; then
    usage
    exit 0
fi

VERSION="${VERSION:-${1:-}}"

if [[ -z "$VERSION" ]]; then
    usage
    fail 'VERSION is required'
fi

BASE_DIR="${BASE_DIR:-/srv/rabit-api/swarm}"
BACKEND_ENV_FILE="${BACKEND_ENV_FILE:-$BASE_DIR/backend.env}"
SECRETS_DIR="${SECRETS_DIR:-$BASE_DIR/secrets}"
LEGACY_SECRET_DIR="${LEGACY_SECRET_DIR:-$BASE_DIR}"
REQUIRED_SECRET_NAMES="${REQUIRED_SECRET_NAMES:-rebit_encryption_key rebit_geetest_captcha_key rebit_mysql_password rebit_mysql_root_password rebit_rabbitmq_password}"
OPTIONAL_SECRET_NAMES="${OPTIONAL_SECRET_NAMES:-rebit_smtp_password rebit_telegram_bot_token rebit_max_bot_token morefoto_support_max_webhook_secret}"
OUTPUT_ENV_FILE="${OUTPUT_ENV_FILE:-}"

BACKEND_ENV_CONFIG_NAME="${BACKEND_ENV_CONFIG_NAME:-rebit_backend_env_$VERSION}"

IFS=' ' read -r -a REQUIRED_SECRET_KEYS <<< "$REQUIRED_SECRET_NAMES"
IFS=' ' read -r -a OPTIONAL_SECRET_KEYS <<< "$OPTIONAL_SECRET_NAMES"

declare -a SECRET_KEYS=()
declare -a SECRET_SOURCE_FILES=()
declare -a OUTPUT_ENV_LINES=()

command -v docker >/dev/null 2>&1 || fail 'docker command is not available'

ensure_swarm_manager
require_readable_file "$BACKEND_ENV_FILE"
discover_secret_sources

create_config_if_missing "$BACKEND_ENV_CONFIG_NAME" "$BACKEND_ENV_FILE"

for secret_index in "${!SECRET_KEYS[@]}"; do
    secret_key="${SECRET_KEYS[$secret_index]}"
    secret_source_file="${SECRET_SOURCE_FILES[$secret_index]}"
    secret_env_var_name="$(build_secret_env_var_name "$secret_key")"
    secret_object_name="${!secret_env_var_name:-${secret_key}_$VERSION}"

    create_secret_if_missing "$secret_object_name" "$secret_source_file"
    OUTPUT_ENV_LINES+=("$secret_env_var_name=$secret_object_name")
done

if [[ -n "$OUTPUT_ENV_FILE" ]]; then
    write_output_env_file "$OUTPUT_ENV_FILE"
fi

printf '\nCreated or reused versioned Swarm objects:\n'
printf '  BACKEND_ENV_CONFIG_NAME=%s\n' "$BACKEND_ENV_CONFIG_NAME"

for output_env_line in "${OUTPUT_ENV_LINES[@]}"; do
    printf '  %s\n' "$output_env_line"
done

printf '\nUse the same version value for deployment BUILD_NUMBER so Makefile resolves identical names.\n'
printf 'Example:\n'
printf '  HOST=prod.example.com BUILD_NUMBER=%s REGISTRY=ghcr.io/rebit-pro IMAGE_TAG=%s make deploy\n' "$VERSION" "$VERSION"
