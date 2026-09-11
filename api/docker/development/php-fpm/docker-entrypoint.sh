#!/bin/sh
set -e
# Trust self-signed Traefik cert (dev)
if [ -f /usr/local/share/ca-certificates/rebit-p2p.loc.crt ]; then
    update-ca-certificates > /dev/null 2>&1 || true
fi
# Create dirs for FPM
for dir in /app/public/local/routes /var/lib/php/sessions; do
    mkdir -p "$dir"
    chown -R www-data:www-data "$dir"
done
exec "$@"
