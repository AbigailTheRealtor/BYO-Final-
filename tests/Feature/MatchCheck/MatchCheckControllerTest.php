<?php

namespace Tests\Feature\MatchCheck;

use App\Models\User;
use App\Services\Property\PropertyCandidate;
use App\Services\Stellar\MatchCheck\MatchCheckAnalysis;
use App\Services\Stellar\MatchCheck\MatchCheckOrchestrator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

/**
 * git-C14 — controller dispatch + validation for the Match Check surface.
 *
 * The controller is a thin delegator: it validates the identifier and calls the matching
 * MatchCheckOrchestrator entry, then renders the returned MatchCheckAnalysis. These tests
 * bind a mock orchestrator so no real Bridge/DB/enrichment work runs — they assert the
 * dispatch mapping (mode → method) and that invalid input never reaches the orchestrator.
 */
class MatchCheckControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('mls_match_check.enabled', true);
    }

    private function user(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    /** @test */
    public function mls_mode_dispatches_to_analyze_by_mls_number(): void
    {
        $mock = Mockery::mock(MatchCheckOrchestrator::class);
        $mock->shouldReceive('analyzeByMlsNumber')
            ->once()
            ->with('A4567890', Mockery::type(User::class))
            ->andReturn(MatchCheckAnalysis::notFound());
        $mock->shouldNotReceive('analyzeByAddress');
        $this->instance(MatchCheckOrchestrator::class, $mock);

        $this->actingAs($this->user())
            ->post('/match-check', ['mode' => 'mls', 'mls_number' => 'A4567890'])
            ->assertOk()
            ->assertSee('data-status="not_found"', false);
    }

    /** @test */
    public function address_mode_dispatches_to_analyze_by_address(): void
    {
        $mock = Mockery::mock(MatchCheckOrchestrator::class);
        $mock->shouldReceive('analyzeByAddress')
            ->once()
            ->with(['address' => '123 Ocean Dr'], Mockery::type(User::class))
            ->andReturn(MatchCheckAnalysis::notFound());
        $mock->shouldNotReceive('analyzeByMlsNumber');
        $this->instance(MatchCheckOrchestrator::class, $mock);

        $this->actingAs($this->user())
            ->post('/match-check', ['mode' => 'address', 'address' => '123 Ocean Dr'])
            ->assertOk()
            ->assertSee('data-status="not_found"', false);
    }

    /** @test */
    public function missing_identifier_fails_validation_and_never_calls_the_orchestrator(): void
    {
        // A bare mock with no expectations throws on any call — proving no dispatch on bad input.
        $this->instance(MatchCheckOrchestrator::class, Mockery::mock(MatchCheckOrchestrator::class));

        $this->actingAs($this->user())
            ->post('/match-check', ['mode' => 'mls'])   // no mls_number
            ->assertStatus(302)
            ->assertSessionHasErrors('mls_number');
    }

    /** @test */
    public function invalid_mode_fails_validation(): void
    {
        $this->instance(MatchCheckOrchestrator::class, Mockery::mock(MatchCheckOrchestrator::class));

        $this->actingAs($this->user())
            ->post('/match-check', ['mode' => 'listing_key', 'mls_number' => 'X'])
            ->assertStatus(302)
            ->assertSessionHasErrors('mode');
    }

    /** @test */
    public function ambiguous_analysis_renders_the_disambiguation_list_without_raw_fields(): void
    {
        $mock = Mockery::mock(MatchCheckOrchestrator::class);
        $mock->shouldReceive('analyzeByAddress')
            ->once()
            ->andReturn(MatchCheckAnalysis::ambiguous(collect([$this->candidate()])));
        $this->instance(MatchCheckOrchestrator::class, $mock);

        $response = $this->actingAs($this->user())
            ->post('/match-check', ['mode' => 'address', 'address' => '1 Ocean Dr'])
            ->assertOk()
            ->assertSee('data-status="ambiguous"', false)
            ->assertSee('1 Ocean Dr Unit 5')
            ->assertSee('A4567890');

        // F7 — no restricted source data ever reaches the rendered HTML.
        $response->assertDontSee('raw_json');
        $response->assertDontSee('PublicRemarks');
    }

    private function candidate(): PropertyCandidate
    {
        return new PropertyCandidate(
            source: 'bridge',
            sourceRecordId: '101',
            mlsNumber: 'A4567890',
            listingKey: 'BRIDGE-KEY-1',
            standardStatus: 'Active',
            mlsStatus: 'Active',
            propertyType: 'Residential',
            propertySubType: 'Condominium',
            listPrice: 550000.0,
            unparsedAddress: '1 Ocean Dr Unit 5',
            city: 'Sarasota',
            stateOrProvince: 'FL',
            postalCode: '34236',
            countyOrParish: 'Sarasota',
            bedrooms: 3,
            bathrooms: 2,
            livingAreaSqft: 1800,
            lotSizeSqft: null,
            yearBuilt: 2005,
            latitude: null,
            longitude: null,
            associationFee: null,
            taxAnnualAmount: null,
            petsAllowed: null,
            pool: null,
            garage: null,
            waterfront: null,
            view: null,
            waterView: null,
            seniorCommunity: null,
            association: null,
            newConstruction: null,
            cdd: null,
        );
    }
}
