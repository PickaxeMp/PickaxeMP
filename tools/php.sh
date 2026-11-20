#!/usr/bin/env bash
# PickaxeMP — proje duzeyi PHP calistirici (Linux/macOS)
# Kullanim: tools/php.sh script.php
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export OPENSSL_CONF="${DIR}/openssl.cnf"
exec php -c "$DIR/php.ini" "$@"
