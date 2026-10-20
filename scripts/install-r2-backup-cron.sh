#!/bin/sh

set -eu

APP_DIR=${XIAOGU_APP_DIR:-/var/www/xiaoguyouqu}
BACKUP_SCRIPT="$APP_DIR/usr/plugins/R2Backup/cron.php"
MARKER="# xiaogu-r2-backup"

if [ ! -f "$BACKUP_SCRIPT" ]; then
    echo "R2 backup script not found: $BACKUP_SCRIPT" >&2
    exit 1
fi

PHP_BIN=$(command -v php || true)
CRONTAB_BIN=$(command -v crontab || true)
LOGGER_BIN=$(command -v logger || true)

if [ -z "$PHP_BIN" ]; then
    echo "PHP CLI is not installed or not available in PATH." >&2
    exit 1
fi
if [ -z "$CRONTAB_BIN" ]; then
    echo "crontab is not installed or not available in PATH." >&2
    exit 1
fi
if [ -z "$LOGGER_BIN" ]; then
    echo "logger is not installed or not available in PATH." >&2
    exit 1
fi

CRON_LINE="30 3 * * * cd $APP_DIR && $PHP_BIN $BACKUP_SCRIPT 2>&1 | $LOGGER_BIN -t xiaogu-r2-backup $MARKER"
CRON_FILE=$(mktemp)
trap 'rm -f "$CRON_FILE"' EXIT HUP INT TERM

{
    "$CRONTAB_BIN" -l 2>/dev/null | grep -vF "$MARKER" || true
    printf '%s\n' "$CRON_LINE"
} > "$CRON_FILE"

"$CRONTAB_BIN" "$CRON_FILE"
"$CRONTAB_BIN" -l | grep -F "$MARKER"

echo "Cloudflare R2 backup cron installed."
