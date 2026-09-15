<?php

namespace App\Support\Safeguards;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;

/**
 * The one way a standalone developer / QA script boots Laravel.
 *
 * Replaces the two lines every script used to carry:
 *
 *     $app = require __DIR__ . '/../bootstrap/app.php';
 *     $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 *
 * with
 *
 *     require __DIR__ . '/../vendor/autoload.php';
 *     $app = \App\Support\Safeguards\ManualScriptBootstrap::boot(__FILE__);
 *
 * WHEN THE CHECK RUNS
 * -------------------
 * Right after LoadConfiguration: environment and config are loaded, and exception handling,
 * facades, service providers, observers and the script body have not run. That is the earliest
 * point the resolved connection can be known, and it comes before anything could write. It is
 * hooked through Laravel's own `afterBootstrapping()`, so the normal sequence runs exactly once
 * with nothing re-loaded.
 *
 * The check runs a second time after the full bootstrap, so a provider that rewrote database
 * config at boot cannot turn an approved target into an unapproved one unnoticed.
 *
 * A refusal writes to STDERR and exits with ProductionDatabaseGuard::EXIT_REFUSED. It exits instead
 * of throwing because nothing is registered to handle an exception at that point, and a PHP fatal
 * with a stack trace buries the one sentence the reader needs.
 *
 * @see tests/Feature/Safeguards/ManualScriptGuardCoverageTest.php every script must come through here
 */
final class ManualScriptBootstrap
{
    /**
     * @param  string             $script                     the calling script, normally `__FILE__`
     * @param  list<string>|null  $argv                       defaults to the process argv
     * @param  bool               $permitProductionOverride   whether this script accepts
     *                                                        `--i-know-this-is-production`. No current
     *                                                        script does; see docs/manual-qa-database-safety.md.
     */
    public static function boot(string $script, ?array $argv = null, bool $permitProductionOverride = false): Application
    {
        $argv = $argv ?? (array) ($_SERVER['argv'] ?? []);
        $basePath = dirname(__DIR__, 3);
        $subject = self::subject($script, $basePath);

        /** @var Application $app */
        $app = require $basePath . '/bootstrap/app.php';

        $announced = false;

        $enforce = static function (Application $app) use ($argv, $permitProductionOverride, $subject, &$announced): void {
            $assessment = ProductionDatabaseGuard::assessApplication($app);

            $outcome = ProductionDatabaseGuard::decide(
                $assessment,
                ProductionDatabaseGuard::overrideSuppliedIn($argv),
                $permitProductionOverride,
            );

            if ($outcome === ProductionDatabaseGuard::OUTCOME_REFUSED) {
                fwrite(STDERR, $assessment->refusalMessage($subject, $permitProductionOverride, 'php ' . $subject));
                exit(ProductionDatabaseGuard::EXIT_REFUSED);
            }

            if ($outcome === ProductionDatabaseGuard::OUTCOME_OVERRIDDEN && ! $announced) {
                fwrite(STDERR, $assessment->overrideBanner($subject));
                $announced = true;
            }
        };

        $app->afterBootstrapping(LoadConfiguration::class, $enforce);

        $app->make(ConsoleKernel::class)->bootstrap();

        $enforce($app);

        return $app;
    }

    private static function subject(string $script, string $basePath): string
    {
        $real = realpath($script) ?: $script;
        $prefix = rtrim($basePath, '/') . '/';

        return str_starts_with($real, $prefix) ? substr($real, strlen($prefix)) : $real;
    }
}
