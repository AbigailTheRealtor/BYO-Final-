#!/bin/bash
set -e

# ---------------------------------------------------------------------------
# OpenAI env-gap fix: Replit secrets are visible in bash but are NOT inherited
# by the artisan serve workflow process unless the values are present in .env
# (phpdotenv reads .env at Laravel startup; it does not read platform secrets).
# Write any missing OpenAI keys to .env so every workflow restart picks them up.
# This block is idempotent: it only appends a key if the key is not already set.
# ---------------------------------------------------------------------------
for VAR in OPENAI_API_KEY OPENAI_MODEL OPENAI_PROMPT_VERSION ASK_AI_ENABLE_OPENAI_INTENT_NORMALIZATION ASK_AI_ENABLE_DESCRIPTION_FALLBACK GOOGLE_PLACES_API_KEY; do
    VALUE="${!VAR}"
    if [ -n "$VALUE" ] && ! grep -q "^${VAR}=" .env 2>/dev/null; then
        echo "${VAR}=${VALUE}" >> .env
    fi
done

composer install --no-interaction --no-ansi 2>/dev/null || true

# ---------------------------------------------------------------------------
# NO MIGRATIONS HERE. THIS IS DELIBERATE — DO NOT ADD THEM BACK.
#
# This script used to run `php artisan migrate --force --no-interaction`. That
# made the Replit [postMerge] hook a SECOND migration owner: it fires on a
# platform merge, from whatever branch the workspace happens to have checked
# out, with no backup, no preflight and no lock.
#
# This application is on Laravel 8, which has no `migrate --isolated` — the
# built-in migration lock arrived in Laravel 9. There is no mutex to fall back
# on, so concurrency safety rests ENTIRELY on exactly one process owning the
# job. Two owners is not untidy; it is the only way two migrators could ever
# run at once against one database.
#
# Production migrations belong to `deploy/start-production.sh` and nothing else.
# It reports via `deploy:preflight`, migrates, and only then binds a port — so a
# failed migration stops the release instead of serving new code against an old
# schema.
#
# Enforced by tests/Feature/Deployment/PostMergeMigrationOwnershipTest.php,
# which resolves this script's path out of `.replit` rather than assuming it.
# ---------------------------------------------------------------------------

# Only seed shared-credential dev/test accounts outside production. The seeder
# itself also refuses to run in production (see database/seeders/UserSeeder.php).
if [ "${APP_ENV:-local}" != "production" ]; then
    php artisan db:seed --class=UserSeeder --force --no-interaction || true
else
    echo "post-merge: skipping UserSeeder (production environment)."
fi
php artisan config:clear
php artisan view:clear
npm install --silent 2>/dev/null || true
npm run dev -- --no-progress 2>/dev/null || true
