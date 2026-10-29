#!/bin/sh
# Local play scheduler (Phase 4 task 3). LOCAL USE ONLY. Runs the game's maintenance, php cron.php, every
# RESURRECTION_CRON_SECONDS (default 900). cron.php itself skips a game day it already completed and refuses a
# database that is not current; a refusal is logged and retried at the next interval.
interval=${RESURRECTION_CRON_SECONDS:-900}
cd /srv/game || exit 1
while :; do
    php cron.php || echo "cron.php did not complete; it runs again in ${interval}s." >&2
    sleep "$interval"
done
