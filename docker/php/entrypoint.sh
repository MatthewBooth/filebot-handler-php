#!/bin/sh
set -eu

timezone_config_dir="${TMPDIR:-/tmp}/php-conf.d"
mkdir -p "$timezone_config_dir"
printf 'date.timezone=%s\n' "${TZ:-UTC}" > "$timezone_config_dir/zz-timezone.ini"

export PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:$timezone_config_dir"

exec /usr/local/bin/docker-php-entrypoint "$@"
