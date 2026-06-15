---
name: Replit secret vs workflow process env gap
description: Replit secrets appear in bash/shell but may NOT be injected into artisan serve workflow processes; requires .env to bridge the gap.
---

# Replit Secret vs Workflow Process Env Gap

## The Rule
Replit secrets set via the Secrets UI are available in interactive shell sessions (verified by `printenv`), but the `php artisan serve` process started by the Replit workflow runner may NOT see them — `env('KEY')` returns null, `config('services.x')` returns null.

**Why:** The Replit workflow runner may start processes with a different environment scope than the interactive bash shell. Restarting the workflow via `restart_workflow` does NOT reliably fix this — the workflow runner's own environment may not include secrets.

**Symptoms:**
- `printenv KEY` shows the value in bash
- `php artisan tinker --execute="echo env('KEY')"` shows the value (new artisan process inherits bash env)
- Browser-accessed page calls `config('services.x')` → null → component renders the "not configured" fallback

**Diagnostic:** Add a temporary public route `Route::get('/dbg-key', fn() => response()->json(['len' => strlen(config('services.x', ''))]))` and curl it. If `len=0` while tinker gives the correct value, the workflow process is missing the env var.

**Fix:** Add the key to `.env` so phpdotenv loads it at server startup:
```bash
echo "MY_KEY=$(printenv MY_KEY)" >> .env
php artisan view:clear
```
Google Maps API keys are public-facing (sent to browsers in script URLs), so writing to `.env` is acceptable. For genuinely secret values, ask the user to set it as a Replit env var (not just a secret) so the workflow runner injects it.
