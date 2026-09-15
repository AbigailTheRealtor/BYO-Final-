<?php

namespace App\Support\Safeguards;

/**
 * The result of ProductionDatabaseGuard::assess(): every reason the target counts as production,
 * and what the default connection resolved to (for the message only, never as a decision input).
 *
 * The messages name hosts and database names, and nothing else. No credentials and no full URLs.
 */
final class ProductionDatabaseAssessment
{
    /**
     * @param  list<string>  $signals
     * @param  array{name: string, driver: ?string, hosts: list<string>, database: ?string}|null  $defaultConnection
     */
    public function __construct(
        private array $signals,
        private ?array $defaultConnection,
    ) {
    }

    public function isProduction(): bool
    {
        return $this->signals !== [];
    }

    /** @return list<string> */
    public function signals(): array
    {
        return $this->signals;
    }

    /** @return array{name: string, driver: ?string, hosts: list<string>, database: ?string}|null */
    public function defaultConnection(): ?array
    {
        return $this->defaultConnection === null ? null : [
            'name' => $this->defaultConnection['name'],
            'driver' => $this->defaultConnection['driver'],
            'hosts' => $this->defaultConnection['hosts'],
            'database' => $this->defaultConnection['database'],
        ];
    }

    /** One line naming the resolved default target, e.g. `pgsql host=helium database=heliumdb`. */
    public function resolvedTarget(): string
    {
        if ($this->defaultConnection === null) {
            return 'unresolved (no usable default connection)';
        }

        $c = $this->defaultConnection;

        return sprintf(
            "connection '%s': driver=%s host=%s database=%s",
            $c['name'],
            $c['driver'] ?? '(none)',
            $c['hosts'] === [] ? '(none)' : implode(',', $c['hosts']),
            $c['database'] ?? '(none)',
        );
    }

    public function refusalMessage(string $subject, bool $overridePermitted, string $invocation): string
    {
        $lines = [
            '',
            str_repeat('=', 78),
            'PRODUCTION DATABASE DETECTED. REFUSED TO RUN: ' . $subject,
            str_repeat('=', 78),
            'Resolved default target: ' . $this->resolvedTarget(),
            '',
            'Why this counts as production:',
        ];

        foreach ($this->signals as $signal) {
            $lines[] = '  - ' . $signal;
        }

        $lines[] = '';
        $lines[] = 'Nothing ran. The guard reads configuration only and opened no database connection.';
        $lines[] = '';
        $lines[] = 'To run against a local database, override EVERY path that can select one, e.g.:';
        $lines[] = '  APP_ENV=local DATABASE_URL= DB_CONNECTION=sqlite DB_DATABASE=/tmp/qa.sqlite \\';
        $lines[] = '  PGHOST= PGHOSTADDR= PGDATABASE= PGSERVICE= ' . $invocation;
        $lines[] = '';

        $lines[] = $overridePermitted
            ? 'This entry point accepts a deliberate production run. Re-run it with '
                . ProductionDatabaseGuard::OVERRIDE_FLAG . ' only if production is really what you intend.'
            : 'This entry point does not accept a production override. It must never run against production.';

        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    public function overrideBanner(string $subject): string
    {
        return implode(PHP_EOL, [
            '',
            str_repeat('!', 78),
            'PRODUCTION OVERRIDE: ' . $subject . ' is running against PRODUCTION.',
            'Target: ' . $this->resolvedTarget(),
            'Authorised by ' . ProductionDatabaseGuard::OVERRIDE_FLAG . ' on the command line.',
            str_repeat('!', 78),
            '',
        ]);
    }
}
