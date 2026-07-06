<?php

namespace Tests\Feature\Dna;

use App\Models\DnaScore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * 55+ leak remediation — straggler scrub command. Deletes ONLY pre-V2
 * Lock-and-Leave demand rows; leaves clean V2 rows, property-side rows, and other
 * score keys untouched. Idempotent and dry-run-safe.
 *
 * @see docs/matching-v2-55plus-leak-remediation-scope.md
 */
class DnaScrubLockAndLeaveAgeTest extends TestCase
{
    use DatabaseTransactions;

    private function row(int $id, string $scoreKey, string $side, string $version, array $inputs = []): void
    {
        DnaScore::create([
            'listing_type'      => 'buyer_agent',
            'listing_id'        => $id,
            'score_key'         => $scoreKey,
            'side'              => $side,
            'value'             => 90,
            'data_completeness' => 100,
            'confidence'        => 90,
            'explanation'       => 'seed',
            'inputs_json'       => $inputs,
            'version'           => $version,
            'generator_version' => $version,
            'generated_by'      => 'system',
        ]);
    }

    private function seedRows(): void
    {
        // Stale leaked V1 demand row — the target.
        $this->row(940001, 'lock_and_leave', 'demand', 'LOCK_AND_LEAVE_V1', ['age_targeted' => true]);
        // Clean current V2 demand row — must survive.
        $this->row(940002, 'lock_and_leave', 'demand', 'LOCK_AND_LEAVE_V2', ['current_status' => 'x']);
        // Property-side lock_and_leave — different side, must survive.
        $this->row(940003, 'lock_and_leave', 'property', 'LOCK_AND_LEAVE_V1');
        // Different score key on the demand side — must survive.
        $this->row(940004, 'pet_friendliness', 'demand', 'PET_V1');
    }

    private function exists(int $id): bool
    {
        return DnaScore::where('listing_id', $id)->exists();
    }

    public function test_deletes_only_stale_demand_lock_and_leave_rows(): void
    {
        $this->seedRows();

        Artisan::call('dna:scrub-lock-and-leave-age');

        $this->assertFalse($this->exists(940001), 'stale V1 demand row should be deleted');
        $this->assertTrue($this->exists(940002), 'clean V2 demand row must survive');
        $this->assertTrue($this->exists(940003), 'property-side row must survive');
        $this->assertTrue($this->exists(940004), 'other score key must survive');
    }

    public function test_is_idempotent(): void
    {
        $this->seedRows();

        Artisan::call('dna:scrub-lock-and-leave-age');
        Artisan::call('dna:scrub-lock-and-leave-age'); // second run finds nothing

        $this->assertFalse($this->exists(940001));
        $this->assertTrue($this->exists(940002));
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $this->seedRows();

        Artisan::call('dna:scrub-lock-and-leave-age', ['--dry-run' => true]);

        $this->assertTrue($this->exists(940001), 'dry-run must not delete');
    }
}
