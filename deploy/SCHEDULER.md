# Production Scheduler (BLK-04)

The application schedules `offers:expire-pending` to run **every minute**
(`app/Console/Kernel.php`). Laravel's scheduler only fires if *something* invokes
`php artisan schedule:run` once a minute. On a standard server that is a system
crontab entry; on Replit there is no per-minute cron, so we run a dedicated
**scheduler worker process** instead.

## What is in source control

- **`app/Console/Kernel.php`** — the `offers:expire-pending` schedule, hardened
  with `->withoutOverlapping(5)` so overlapping or accidentally duplicated runs
  cannot double-process offers.
- **`deploy/scheduler.sh`** — a one-line launcher that runs
  `php artisan schedule:work` (a long-lived process that triggers `schedule:run`
  every minute). This is the production-compatible replacement for cron.

## What still requires manual Replit setup

The Replit VM **Deployment** (`.replit` → `[deployment]`) runs a single command,
the web server:

```
PHP_INI_SCAN_DIR="$PWD/deploy/php" php artisan serve --host=0.0.0.0 --port=5000
```

The scheduler must run as a **second, independent always-on process** — it cannot
be expressed inside that single `run` line without fragile shell chaining, and we
deliberately do **not** couple it to the web server's lifecycle. Set it up once,
manually, in the Replit UI:

1. **Reserved VM deployment (recommended):** add a **Background Worker** to the
   same Reserved VM deployment with the run command:

   ```
   bash deploy/scheduler.sh
   ```

   A Reserved VM keeps background workers alive independently of the web process.

2. **Alternative — Scheduled Deployment:** if a background worker is not
   available, create a **Scheduled Deployment** that runs
   `php artisan schedule:run` on a **1-minute** cadence. (Do **not** use
   `schedule:work` here — a scheduled deployment already provides the per-minute
   trigger; `schedule:run` executes exactly one due-tick per invocation.)

### Rules

- **Run exactly one scheduler.** Do not enable both option 1 and option 2, and do
  not start `deploy/scheduler.sh` more than once. `->withoutOverlapping(5)` is a
  backstop, not a licence to run duplicates.
- **Do not** add `schedule:work` to the web `run`/`serve` command.

## Why this is safe even if the scheduler is down

Expiration is enforced **twice**. Request-time expiry (BLK-06) blocks
accept / reject / withdraw / counter on any offer whose `expires_at` has passed
and transitions it to `expired` at that moment — synchronously, inside the same
locked transaction — regardless of whether the scheduler ran. The scheduler is a
convenience that expires idle offers proactively; it is never the sole line of
defence. The UI countdown is presentational only and is **not** relied upon for
enforcement.

## Verifying

```bash
php artisan schedule:list            # shows offers:expire-pending every minute
php artisan offers:expire-pending    # run one expiry sweep by hand
bash deploy/scheduler.sh             # run the long-lived worker in a shell
```
