<?php

namespace App\Console\Concerns;

use App\Support\Safeguards\ProductionDatabaseGuard;

/**
 * For Artisan QA / debug / fixture commands: refuse to run when the application resolves to the
 * production database.
 *
 * Call it as the first statement of handle():
 *
 *     if ($this->refusesProductionDatabase()) {
 *         return ProductionDatabaseGuard::EXIT_REFUSED;
 *     }
 *
 * By the time handle() runs, Artisan has booted the providers. That is still before any query:
 * no provider in this application queries during register() or boot() (audited 2026-09-14), so
 * the command's own body is the first thing that could touch the database.
 *
 * THE OVERRIDE IS OPT-IN PER COMMAND
 * ----------------------------------
 * A command accepts a deliberate production run only if it does BOTH of these:
 *   1. declares `{--i-know-this-is-production : ...}` in its signature, and
 *   2. calls `refusesProductionDatabase(true)`.
 * If the option is not declared, Symfony rejects the flag before handle() runs, so it cannot be
 * supplied to a command that never agreed to take it. Declaring the option while passing `false`
 * does nothing. The override is never read from the environment.
 */
trait RefusesProductionDatabase
{
    protected function refusesProductionDatabase(bool $overridePermitted = false): bool
    {
        $assessment = ProductionDatabaseGuard::assessApplication($this->getLaravel());

        $supplied = $this->input->hasOption(ProductionDatabaseGuard::OVERRIDE_OPTION)
            && $this->input->getOption(ProductionDatabaseGuard::OVERRIDE_OPTION) === true;

        $outcome = ProductionDatabaseGuard::decide($assessment, $supplied, $overridePermitted);
        $subject = 'php artisan ' . $this->getName();
        $stderr = $this->output->getErrorStyle();

        if ($outcome === ProductionDatabaseGuard::OUTCOME_REFUSED) {
            $stderr->writeln(
                $assessment->refusalMessage($subject, $overridePermitted, $subject),
                \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW,
            );

            return true;
        }

        if ($outcome === ProductionDatabaseGuard::OUTCOME_OVERRIDDEN) {
            $stderr->writeln($assessment->overrideBanner($subject), \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);
        }

        return false;
    }
}
