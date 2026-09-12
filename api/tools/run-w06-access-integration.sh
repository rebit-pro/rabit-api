#!/usr/bin/env bash
# Reuses the isolated real Bitrix/MySQL fixture; never uses project .env or persistent data.
set -euo pipefail
export RABIT_INTEGRATION_VERIFIER=tools/verify-w06-access.php
exec bash "$(dirname -- "${BASH_SOURCE[0]}")/run-w02-auth-integration.sh" "$@"
