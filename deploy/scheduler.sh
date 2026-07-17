#!/usr/bin/env bash
#
# BLK-04 — Laravel scheduler process for production (Replit VM deployment).
#
# This runs the Laravel scheduler as a single, long-lived foreground process.
# `schedule:work` internally invokes `schedule:run` every minute, so it replaces
# the traditional system crontab entry on hosts (like Replit) where a per-minute
# cron is not available. It must run as its OWN process — separate from the web
# server (`php artisan serve`) — so the two never share a lifecycle and a web
# restart cannot silently take the scheduler down (or vice versa).
#
# Run EXACTLY ONE instance. Two instances would attempt to run the schedule
# concurrently; the `->withoutOverlapping()` guard on the offers:expire-pending
# command (see app/Console/Kernel.php) is the backstop against double execution,
# but a single instance is still the intended topology.
#
# Usage (locally or as a Replit background process):
#   bash deploy/scheduler.sh
#
# See deploy/SCHEDULER.md for the manual Replit setup this script cannot express
# in source control (creating the always-on background process / worker).

set -euo pipefail

cd "$(dirname "$0")/.."

# Mirror the PHP ini scan dir used by the web workflow/deployment so the
# scheduler process loads the same PHP configuration as the app.
export PHP_INI_SCAN_DIR="$PWD/deploy/php"

exec php artisan schedule:work --no-interaction
