<?php

namespace App\Support\Migrations;

use Illuminate\Database\Migrations\Migrator;

/**
 * The one place that answers "which directories hold this application's migrations?".
 *
 * WHY THIS EXISTS AS A CLASS
 * --------------------------
 * The same expression has now been written wrong twice, in two different files,
 * for the same reason:
 *
 *     $migrator->paths() ?: [database_path('migrations')]
 *
 * `Migrator::paths()` returns ONLY the extra directories registered through
 * `loadMigrationsFrom()` — it never includes `database/migrations`. Laravel
 * Sanctum registers one, so in this application `paths()` is never empty, the
 * `?:` fallback never fires, and `database/migrations` is excluded from the scan
 * entirely. Every count derived from it is taken over a single vendor migration
 * and reads zero forever, including on a deploy where the whole application
 * schema is pending.
 *
 * That failure is silent and it looks exactly like success, which is why it
 * survived review twice. Putting the rule in one place means the third caller
 * cannot get it wrong.
 *
 * THE RULE
 * --------
 * Merge, never fall back — and in the framework's order. This mirrors
 * Illuminate\Database\Console\Migrations\BaseCommand::getMigrationPaths():
 *
 *     array_merge($this->migrator->paths(), [$this->getMigrationPath()])
 *
 * Registered paths first, the application's own path LAST. The order is
 * load-bearing: `Migrator::getMigrationFiles()` keys its result by migration
 * name, so when a migration ships in both places — `personal_access_tokens` is
 * exactly this case — the later entry wins. That must be the application's copy,
 * because the application's copy is the one that has been edited.
 *
 * NOT APPLIED TO DeployPreflight
 * ------------------------------
 * `App\Console\Commands\DeployPreflight` carries an equivalent expression inline
 * and is already correct; `DeployPreflightPendingMigrationsTest` pins it to
 * framework-equivalent semantics, so it cannot drift back unnoticed. It is left
 * alone deliberately — this change set exists to make deployment readiness
 * answerable, and rewriting a working deployment command to prove a point is not
 * worth the blast radius.
 */
final class MigrationPaths
{
    /**
     * Every directory a pending-migration count must scan.
     *
     * @return list<string>
     */
    public static function forScanning(Migrator $migrator): array
    {
        return array_values(array_merge(
            $migrator->paths(),
            [database_path('migrations')]
        ));
    }
}
