#!/bin/bash
set -e

# ---------------------------------------------------------------------------
# OpenAI env-gap fix: Replit secrets are visible in bash but are NOT inherited
# by the artisan serve workflow process unless the values are present in .env
# (phpdotenv reads .env at Laravel startup; it does not read platform secrets).
# Write any missing OpenAI keys to .env so every workflow restart picks them up.
# This block is idempotent: it only appends a key if the key is not already set.
# ---------------------------------------------------------------------------
for VAR in OPENAI_API_KEY OPENAI_MODEL OPENAI_PROMPT_VERSION; do
    VALUE="${!VAR}"
    if [ -n "$VALUE" ] && ! grep -q "^${VAR}=" .env 2>/dev/null; then
        echo "${VAR}=${VALUE}" >> .env
    fi
done

composer install --no-interaction --no-ansi 2>/dev/null || true
php artisan migrate --force --no-interaction
php artisan db:seed --class=UserSeeder --force --no-interaction || true
php artisan config:clear
php artisan view:clear
npm install --silent 2>/dev/null || true
npm run dev -- --no-progress 2>/dev/null || true
