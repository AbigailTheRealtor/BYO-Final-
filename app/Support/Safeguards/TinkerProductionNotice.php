<?php

namespace App\Support\Safeguards;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * A warning, not a block, when `php artisan tinker` starts against production.
 *
 * WHY IT DOES NOT REFUSE
 * ----------------------
 * Tinker is how production has been inspected in this repository. docs/runbook-location-seeders.md
 * and docs/matching-v2-validation-runbook.md both document `tinker --execute` read-only checks, and
 * the workspace that runs them is connected to production. Refusing would break those runbooks and
 * leave no sanctioned replacement, which is a production restriction nobody has agreed to. The
 * danger is a session that *creates QA fixtures* while its user believes it is local. That session
 * is exactly the one that never reads a runbook, so it gets told where it is before the prompt
 * appears.
 *
 * The notice goes to STDERR, so `tinker --execute="echo ..."` output captured from STDOUT is
 * unchanged. It never throws. A notice that could break tinker would be a restriction by accident.
 *
 * Registered by App\Console\Kernel, so it exists only in Artisan processes, never in web requests.
 *
 * @see docs/manual-qa-database-safety.md for the remaining risk and the proposed QA fixture command
 */
final class TinkerProductionNotice
{
    public static function register(Dispatcher $events): void
    {
        $events->listen(CommandStarting::class, static function (CommandStarting $event): void {
            if ($event->command !== 'tinker') {
                return;
            }

            try {
                $assessment = ProductionDatabaseGuard::assessApplication();

                if (! $assessment->isProduction()) {
                    return;
                }

                self::stderr($event->output)->writeln(self::message($assessment), OutputInterface::OUTPUT_RAW);
            } catch (Throwable $e) {
                // Deliberately silent. See the class docblock.
            }
        });
    }

    public static function message(ProductionDatabaseAssessment $assessment): string
    {
        $lines = [
            '',
            str_repeat('!', 78),
            'TINKER IS CONNECTED TO PRODUCTION.',
            'Target: ' . $assessment->resolvedTarget(),
        ];

        foreach ($assessment->signals() as $signal) {
            $lines[] = '  - ' . $signal;
        }

        $lines[] = 'Everything created, updated or deleted in this session is production data.';
        $lines[] = 'Do NOT create QA fixtures here. See docs/manual-qa-database-safety.md.';
        $lines[] = str_repeat('!', 78);
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private static function stderr(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
