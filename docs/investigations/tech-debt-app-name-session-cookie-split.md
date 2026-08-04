# Tech debt — APP_NAME differs between CLI and web, splitting the session cookie name

**Status:** OPEN, unowned. **Explicitly out of scope for M5.**
**Found:** 2026-08-04, during M5.1 before/after verification.
**Severity:** low day-to-day, but it silently breaks tooling and can surprise anyone comparing
CLI-derived state against the running app.

---

## The inconsistency

`APP_NAME` has two different values depending on how PHP is started:

| Source | Value | Who sees it |
|---|---|---|
| `.env` | `Laravel` | the web worker spawned by `php artisan serve` |
| `.replit` → `[userenv.shared]` | `BidYourOffer` | the CLI (`php artisan …`, tinker), and any process started directly from a shell |

`artisan serve` filters the environment it hands its child process down to a fixed allowlist
(`APP_ENV`, `LARAVEL_SAIL`, `PHP_CLI_SERVER_WORKERS`, `PHP_IDE_CONFIG`, `SYSTEMROOT`, …) — see
`Illuminate\Foundation\Console\ServeCommand::startProcess()`. `APP_NAME` is not on that list, so the
Replit-supplied value is stripped and the child falls back to `.env`.

## The visible consequence

`config('session.cookie')` defaults to a slug of `app.name`, and `SESSION_COOKIE` is not set in
`.env`. So the two halves of the same application disagree about the session cookie name:

```
CLI / tinker                     -> bidyouroffer_session
web worker under artisan serve   -> laravel_session
```

Confirmed live: the running app on port 5000 emits `Set-Cookie: laravel_session=…`, while
`php artisan tinker --execute="echo config('session.cookie');"` prints `bidyouroffer_session`.

This is not merely cosmetic. Laravel's `CookieValuePrefix` HMACs the **cookie name** into the cookie
value, so a session cookie minted with one name is rejected outright by a runtime using the other —
it does not warn, it silently starts a fresh anonymous session.

## How it was hit

M5.1's verification needed an authenticated browser session. A session minted from the CLI was
rejected by the app with no error; the pages simply rendered logged-out, and the first read of the
screenshots looked like a successful "no visual change" result. The captures were only correct after
minting a cookie per runtime with the matching name.

Anything that mints, inspects or asserts on sessions from the CLI against the running app has the
same trap waiting for it.

## Blast radius beyond sessions

Anything deriving from `config('app.name')` inherits the split:

- session cookie name (confirmed)
- default mail "from" name
- notification and mail templates that print the application name
- log channel names and any `env('APP_NAME')` read at runtime

A user could plausibly receive mail branded "Laravel" from the web app while the CLI believes the
app is "BidYourOffer".

## Options, unowned

1. **Set `SESSION_COOKIE` explicitly in `.env`.** Smallest fix for the session half; pins the cookie
   name regardless of `APP_NAME`. Does not fix mail or logs. Note it invalidates existing sessions
   for whichever runtime changes — everyone gets logged out once.
2. **Set `APP_NAME=BidYourOffer` in `.env`** so both runtimes agree, and treat `.replit`'s
   `[userenv]` entry as redundant. Fixes every derived value at once. Same one-time logout.
3. **Add `APP_NAME` to `ServeCommand::$passthroughVariables`.** Works, but it is framework
   configuration solving an application-configuration problem, and it only helps under
   `artisan serve`.

Option 2 is the most complete, but each has a user-visible logout, so it wants a deliberate moment
rather than being folded into a UI milestone.

## Constraints

- **Not to be fixed inside M5.** It touches authentication state for every logged-in user and has
  nothing to do with the detail-page redesign.
- Whoever fixes it should check whether any stored data (queued mail, notification payloads,
  persisted log metadata) already contains the string `Laravel` from the web runtime.
