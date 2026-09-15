<?php

namespace App\Console\Commands;

use App\Support\VirtualDrive\VirtualDriveGoogleAuthBlock;
use App\Support\VirtualDrive\VirtualDriveGoogleLaunchLedger;
use App\Support\VirtualDrive\VirtualDriveProofGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Show, and explicitly clear, the Virtual Drive proof's Google auth-failure block.
 *
 * This is the ONLY way the block is lifted. It is a console command rather than
 * a route on purpose: clearing it re-enables billable launches, so it must be a
 * deliberate act by somebody at the machine, not something a page, a reload or
 * a link can do.
 *
 *   php artisan virtual-drive:google-auth-block            read-only status
 *   php artisan virtual-drive:google-auth-block --reset    prompts, then clears
 *   php artisan virtual-drive:google-auth-block --reset --yes   non-interactive clear
 *
 * Run it with the same environment as the proof server (APP_ENV=local, the same
 * storage directory), because that is where the block file lives.
 *
 * CLEARING REFUNDS NOTHING. The day's launch tally is not touched: the launch
 * Google rejected stays counted.
 *
 * Refuses outright outside VirtualDriveProofGate's allowed environments, and in
 * production first.
 */
class VirtualDriveGoogleAuthBlockCommand extends Command
{
    protected $signature = 'virtual-drive:google-auth-block
                            {--reset : Clear the block so the proof may grant Google launches again}
                            {--yes : Confirm the reset without a prompt}';

    protected $description = 'Show or explicitly clear the Virtual Drive proof\'s Google auth-failure launch block (proof environments only)';

    public function handle(VirtualDriveGoogleAuthBlock $authBlock, VirtualDriveGoogleLaunchLedger $ledger): int
    {
        if (app()->environment('production') || ! app()->environment(VirtualDriveProofGate::ALLOWED_ENVIRONMENTS)) {
            $this->error('Refused: the Virtual Drive proof exists only in '
                . implode(' / ', VirtualDriveProofGate::ALLOWED_ENVIRONMENTS) . ' environments (APP_ENV='
                . app()->environment() . '). Nothing was read or changed.');

            return self::FAILURE;
        }

        $this->line('Block file: ' . $authBlock->path());

        try {
            $block = $authBlock->current();
            $unreadable = false;
        } catch (Throwable $e) {
            $block = null;
            $unreadable = true;
        }

        $tally = $ledger->peek();
        $this->line('Launch tally for ' . $tally['day'] . ': '
            . ($tally['readable'] ? $tally['used'] . ' of ' . $tally['limit'] . ' used' : 'unreadable')
            . ' (a reset does not change this).');

        if ($unreadable) {
            $this->warn('BLOCKED: a block file exists but cannot be read. Launches are refused.');
        } elseif ($block === null) {
            $this->info('Not blocked: no Google auth failure is recorded.');

            return self::SUCCESS;
        } else {
            $view = $authBlock->publicView($block);
            $this->warn('BLOCKED: Google rejected the browser key. Launches are refused.');
            $this->table(['Field', 'Recorded'], collect($view)
                ->map(fn ($value, $field) => [$field, $value === null ? '—' : (string) $value])
                ->values()
                ->all());
        }

        if (! $this->option('reset')) {
            $this->line('Read-only. To clear it after fixing the key in Google Cloud: ' . VirtualDriveGoogleAuthBlock::RESET_COMMAND);

            return self::SUCCESS;
        }

        $confirmed = $this->option('yes')
            || $this->confirm('Clear the block and allow Google Street View launches again? Confirm the key restriction has been fixed first.', false);

        if (! $confirmed) {
            $this->warn('Not cleared. The block stays in place.');

            return self::FAILURE;
        }

        try {
            $cleared = $authBlock->clear();
        } catch (Throwable $e) {
            $this->error('The block could not be removed: ' . $e->getMessage());

            return self::FAILURE;
        }

        Log::info('virtual_drive_google_auth_block_reset', [
            'code'        => is_array($cleared) ? ($cleared['code'] ?? null) : null,
            'reported_at' => is_array($cleared) ? ($cleared['reported_at'] ?? null) : null,
            'unreadable'  => $unreadable,
        ]);

        $this->info('Cleared. The next launch claim may be granted, within the daily ceiling. No launch was refunded.');

        return self::SUCCESS;
    }
}
