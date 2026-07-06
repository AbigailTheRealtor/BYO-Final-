<?php

namespace Tests\Feature\Dna;

use App\Jobs\MaterializeMatchesForSubject;
use App\Models\Matching\MatchRun;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 C7 — the two drive mechanisms (OD-2): the matching:materialize
 * batch command and the MaterializeMatchesForSubject job. Both must honour the
 * same three write gates and never enable anything themselves.
 */
class MatchingMaterializeCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const EXIT_REFUSED = 2;

    protected function setUp(): void
    {
        parent::setUp();
        config(['matching.v2_enabled' => true]);
        config(['matching.persistence.enabled' => true]);
        config(['matching.persistence.version' => 'c7-test']);
    }

    public function test_command_refuses_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('matching:materialize', ['listingType' => 'buyer_agent', 'listingId' => 1])
            ->assertExitCode(self::EXIT_REFUSED);

        $this->assertSame(0, MatchRun::count());
    }

    public function test_command_refuses_when_persistence_gate_off(): void
    {
        config(['matching.persistence.enabled' => false]);

        $this->artisan('matching:materialize', ['listingType' => 'buyer_agent', 'listingId' => 1])
            ->assertExitCode(self::EXIT_REFUSED);

        $this->assertSame(0, MatchRun::count());
    }

    public function test_command_refuses_when_v2_gate_off(): void
    {
        config(['matching.v2_enabled' => false]);

        $this->artisan('matching:materialize', ['listingType' => 'buyer_agent', 'listingId' => 1])
            ->assertExitCode(self::EXIT_REFUSED);

        $this->assertSame(0, MatchRun::count());
    }

    public function test_command_rejects_partial_subject_args(): void
    {
        $this->artisan('matching:materialize', ['listingType' => 'buyer_agent'])
            ->assertExitCode(self::EXIT_REFUSED);
    }

    public function test_command_materializes_explicit_subject_with_empty_result(): void
    {
        // No dna_scores/auctions → engine returns an empty result; C7 still writes
        // one empty summary row (OD-6) and exits success.
        $this->artisan('matching:materialize', ['listingType' => 'buyer_agent', 'listingId' => 1])
            ->assertExitCode(0);

        $run = MatchRun::where('subject_type', 'buyer_agent')->where('subject_id', 1)->first();
        $this->assertNotNull($run);
        $this->assertSame(0, $run->determined_count);
    }

    public function test_job_is_inert_when_persistence_gate_off(): void
    {
        config(['matching.persistence.enabled' => false]);

        MaterializeMatchesForSubject::dispatchSync('buyer_agent', 1);

        $this->assertSame(0, MatchRun::count());
    }

    public function test_job_writes_when_gates_open(): void
    {
        MaterializeMatchesForSubject::dispatchSync('buyer_agent', 2);

        $this->assertSame(1, MatchRun::where('subject_type', 'buyer_agent')->where('subject_id', 2)->count());
    }
}
