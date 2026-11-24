#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [ ! -f .env.local ]; then echo 'Run php bin/setup.php first.' >&2; exit 1; fi
set -a
# shellcheck source=/dev/null
source .env.local
set +a
exec php -S 127.0.0.1:8787 -t public public/index.php
