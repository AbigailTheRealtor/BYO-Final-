<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        // ── TEST DATABASE SAFETY ─────────────────────────────────────────────
        //
        // Replit injects DB_CONNECTION=pgsql / DB_DATABASE=heliumdb as SYSTEM-
        // level environment variables.  phpdotenv 5's ImmutableStringRepository
        // respects already-set env vars and will not override them, so even a
        // correct .env.testing cannot change those values after the fact.
        //
        // We call putenv() here — before bootstrap() — to force the in-memory
        // SQLite values into the environment FIRST.  ImmutableStringRepository
        // then sees them as "already set" and leaves them alone.  The result is
        // that all database config reads (config('database.default') etc.) use
        // the safe, isolated SQLite :memory: database for every test run.
        //
        // Matching values are also declared in phpunit.xml (<env>) and
        // .env.testing as belt-and-suspenders for environments where Replit
        // does not inject system env vars.
        // ────────────────────────────────────────────────────────────────────
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
