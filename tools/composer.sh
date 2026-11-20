#!/usr/bin/env bash
# PickaxeMP — proje duzeyi Composer calistirici (Linux/macOS)
# Kullanim: tools/composer.sh install
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export OPENSSL_CONF="${DIR}/openssl.cnf"
exec php -c "$DIR/php.ini" "$DIR/composer.phar" "$@"
