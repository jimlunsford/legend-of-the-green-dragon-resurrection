#!/bin/sh
# Local play setup (Phase 4 task 3). LOCAL USE ONLY, not for hosting. Runs in the setup container before the game
# starts, through `python3 scripts/local-play.py up`.
#
# No configuration file yet: a new game. scripts/install.php writes the file from the environment (owner-only, 0600)
# and installs into the empty database with the recommended modules active and the generated administrator.
# A configuration file: an existing game, never installed again. scripts/upgrade.php --dry-run reports whether the
# database is current; it changes nothing, and nothing here applies an upgrade.
#
# Exit codes for local-play.py: 0 an existing game, current; 3 a new game was installed; 10 an existing game whose
# database is behind the code; anything else a refusal or failure, reported above it.
set -eu
# The root password is the database container's alone.
unset MARIADB_ROOT_PASSWORD
CONFIG=/config/dbconnect.php
cd /srv/game
if [ ! -e "$CONFIG" ]; then
    DB_HOST=db DB_USER="$MARIADB_USER" DB_PASS="$MARIADB_PASSWORD" DB_NAME="$MARIADB_DATABASE"
    export DB_HOST DB_USER DB_PASS DB_NAME
    unset MARIADB_PASSWORD
    php scripts/install.php --write-config "$CONFIG"
    exit 3
fi
unset MARIADB_PASSWORD RESURRECTION_ADMIN_PASSWORD
report=$(php scripts/upgrade.php --dry-run "$CONFIG") || { status=$?; printf '%s\n' "$report"; exit "$status"; }
printf '%s\n' "$report"
case "$report" in
    "The database is current: "*) exit 0 ;;
    *) exit 10 ;;
esac
