#!/usr/bin/env bash
# Disposable real Bitrix/MySQL verifier. Run from any directory; no .env is loaded.
set -euo pipefail

api_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
kernel_root="${W02_KERNEL_ROOT:-/home/user/rebit-p2p/api/public/bitrix}"
vendor_root="${W02_VENDOR_ROOT:-/home/user/rebit-p2p/api/vendor}"
php_image="${W02_PHP_IMAGE:-rabit-api-php-cli:20260911-074507}"
mysql_image="${W02_MYSQL_IMAGE:-mysql:8.0}"
network=rabit-w02-tests
container=rabit-w02-mysql
network_created=0
container_created=0

cleanup() {
    result=$?
    trap - EXIT
    if [ "$container_created" = 1 ]; then docker rm -f "$container" >/dev/null; fi
    if [ "$network_created" = 1 ]; then docker network rm "$network" >/dev/null; fi
    exit "$result"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

for path in "$kernel_root/modules/main/install/mysql/install.sql" "$vendor_root/autoload.php"; do
    if [ ! -f "$path" ]; then printf 'Missing read-only dependency: %s\n' "$path" >&2; exit 1; fi
done
if docker container inspect "$container" >/dev/null 2>&1 || docker network inspect "$network" >/dev/null 2>&1; then
    printf 'Refusing to reuse existing W02 container/network. Remove only your stale fixture explicitly.\n' >&2
    exit 1
fi
docker image inspect "$mysql_image" >/dev/null
docker image inspect "$php_image" >/dev/null

docker network create --internal --label rabit.fixture=w02 "$network" >/dev/null
network_created=1
# No host ports, external network, bind-mounted database or persistent volume.
# Empty root password belongs only to this disposable internal fixture.
docker run --detach --name "$container" --label rabit.fixture=w02 \
    --network "$network" --mount type=tmpfs,destination=/var/lib/mysql \
    --env MYSQL_ALLOW_EMPTY_PASSWORD=yes --env MYSQL_ROOT_HOST=% --env MYSQL_DATABASE=rabit_w02 \
    "$mysql_image" --skip-log-bin >/dev/null
container_created=1
ready=0
for attempt in $(seq 1 60); do
    if docker exec "$container" mysqladmin --host=127.0.0.1 ping --silent >/dev/null 2>&1; then ready=1; break; fi
    sleep 1
done
if [ "$ready" != 1 ]; then printf 'Disposable MySQL did not become ready.\n' >&2; exit 1; fi

docker run --rm --read-only --network "$network" --tmpfs /tmp:rw,nosuid,size=256m \
    --entrypoint php \
    --mount "type=bind,source=$api_root,target=/app,readonly" \
    --mount "type=bind,source=$vendor_root,target=/app/vendor,readonly" \
    --mount "type=bind,source=$kernel_root/modules,target=/kernel/modules,readonly" \
    --workdir /app "$php_image" -d short_open_tag=1 -d date.timezone=UTC \
    tools/verify-w02-auth-integration.php "$@" \
    | awk 'json || /^\{/ { json=1; print; next } { print > "/dev/stderr" }'
