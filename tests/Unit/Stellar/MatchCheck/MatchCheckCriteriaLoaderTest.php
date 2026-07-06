<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Models\User;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\MatchCheck\MatchCheckCriteriaLoader;
use App\Services\Stellar\MatchCheck\MatchCheckPreparation;
use App\Services\Stellar\MatchCheck\VisibilityDecision;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * Phase 4 · Wave 2 / C7 — MatchCheckCriteriaLoader.
 *
 * Verifies the DELEGATION only: the adapter dispatches a MatchCheckPreparation's preferred-
 * criteria descriptor to the correct existing per-type loader, scopes the load with
 * resolveAllowedUserIds(), and wraps the flat array in a BuyerCriteriaPayload — failing closed
 * to null (never throwing) whenever a scorable payload cannot be produced. The loaders' own
 * mapping and the DTO's own construction rules are covered by their own tests; here we assert
 * only the wiring/short-circuits. No DB, no scoring.
 */
class MatchCheckCriteriaLoaderTest extends TestCase
{
    private function consumer(int $id = 42): User
    {
        $u = new User();
        $u->id = $id;
        $u->user_type = 'buyer';
        return $u;
    }

    private function readyPrep(?array $descriptor): MatchCheckPreparation
    {
        return MatchCheckPreparation::ready(
            VisibilityDecision::visible('idx_true'),
            'buyer',
            $descriptor,
        );
    }

    private function descriptor(string $type, int $id = 7): array
    {
        return ['id' => $id, 'type' => $type, 'label' => 'L', 'created_at' => Carbon::parse('2026-05-01')];
    }

    /**
     * @return array{
     *   BuyerCriteriaLoader&\Mockery\MockInterface,
     *   TenantCriteriaLoader&\Mockery\MockInterface,
     *   BuyerOfferListingCriteriaLoader&\Mockery\MockInterface,
     *   TenantOfferListingCriteriaLoader&\Mockery\MockInterface,
     *   CriteriaListingResolver&\Mockery\MockInterface
     * }
     */
    private function mocks(): array
    {
        return [
            Mockery::mock(BuyerCriteriaLoader::class),
            Mockery::mock(TenantCriteriaLoader::class),
            Mockery::mock(BuyerOfferListingCriteriaLoader::class),
            Mockery::mock(TenantOfferListingCriteriaLoader::class),
            Mockery::mock(CriteriaListingResolver::class),
        ];
    }

    private function make(array $mocks): MatchCheckCriteriaLoader
    {
        return new MatchCheckCriteriaLoader($mocks[0], $mocks[1], $mocks[2], $mocks[3], $mocks[4]);
    }

    /**
     * A minimal-but-valid flat array the real BuyerCriteriaPayload accepts. The DTO requires a
     * non-empty property_types AND an explicit boolean is_55_plus_eligible; the real per-type
     * loaders always emit both, so the fixture does too.
     */
    private function validFlatArray(): array
    {
        return ['property_types' => ['Residential'], 'is_55_plus_eligible' => false];
    }

    /** @test */
    public function no_preferred_criteria_returns_null_and_touches_no_loader(): void
    {
        $mocks = $this->mocks();
        // Nothing to load: no resolver call, no loader call.
        $mocks[4]->shouldNotReceive('resolveAllowedUserIds');
        foreach ([0, 1, 2, 3] as $i) {
            $mocks[$i]->shouldNotReceive('loadById');
        }

        $result = $this->make($mocks)->load($this->readyPrep(null), $this->consumer());

        $this->assertNull($result);
    }

    /** @test */
    public function buyer_type_dispatches_to_buyer_loader_and_builds_payload(): void
    {
        $mocks = $this->mocks();
        $mocks[4]->shouldReceive('resolveAllowedUserIds')->once()->andReturn([42]);
        $mocks[0]->shouldReceive('loadById')->once()->with(7, [42])->andReturn($this->validFlatArray());
        // The other three loaders must never be consulted.
        $mocks[1]->shouldNotReceive('loadById');
        $mocks[2]->shouldNotReceive('loadById');
        $mocks[3]->shouldNotReceive('loadById');

        $result = $this->make($mocks)->load($this->readyPrep($this->descriptor('buyer')), $this->consumer());

        $this->assertInstanceOf(BuyerCriteriaPayload::class, $result);
        $this->assertSame(['Residential'], $result->propertyTypes);
    }

    /** @test */
    public function tenant_type_dispatches_to_tenant_loader(): void
    {
        $mocks = $this->mocks();
        $mocks[4]->shouldReceive('resolveAllowedUserIds')->once()->andReturn([42]);
        $mocks[1]->shouldReceive('loadById')->once()->with(7, [42])->andReturn($this->validFlatArray());
        $mocks[0]->shouldNotReceive('loadById');
        $mocks[2]->shouldNotReceive('loadById');
        $mocks[3]->shouldNotReceive('loadById');

        $result = $this->make($mocks)->load($this->readyPrep($this->descriptor('tenant')), $this->consumer());

        $this->assertInstanceOf(BuyerCriteriaPayload::class, $result);
    }

    /** @test */
    public function buyer_offer_type_dispatches_to_buyer_offer_loader(): void
    {
        $mocks = $this->mocks();
        $mocks[4]->shouldReceive('resolveAllowedUserIds')->once()->andReturn([42]);
        $mocks[2]->shouldReceive('loadById')->once()->with(7, [42])->andReturn($this->validFlatArray());
        $mocks[0]->shouldNotReceive('loadById');
        $mocks[1]->shouldNotReceive('loadById');
        $mocks[3]->shouldNotReceive('loadById');

        $result = $this->make($mocks)->load($this->readyPrep($this->descriptor('buyer_offer')), $this->consumer());

        $this->assertInstanceOf(BuyerCriteriaPayload::class, $result);
    }

    /** @test */
    public function tenant_offer_type_dispatches_to_tenant_offer_loader(): void
    {
        $mocks = $this->mocks();
        $mocks[4]->shouldReceive('resolveAllowedUserIds')->once()->andReturn([42]);
        $mocks[3]->shouldReceive('loadById')->once()->with(7, [42])->andReturn($this->validFlatArray());
        $mocks[0]->shouldNotReceive('loadById');
        $mocks[1]->shouldNotReceive('loadById');
        $mocks[2]->shouldNotReceive('loadById');

        $result = $this->make($mocks)->load($this->readyPrep($this->descriptor('tenant_offer')), $this->consumer());

        $this->assertInstanceOf(BuyerCriteriaPayload::class, $result);
    }

    /** @test */
    public function unrecognized_type_fails_closed_to_null_without_loading(): void
    {
        $mocks = $this->mocks();
        // resolveAllowedUserIds still runs (scope is computed before dispatch), but no loader
        // should be called for a type outside the known set.
        $mocks[4]->shouldReceive('resolveAllowedUserIds')->andReturn([42]);
        foreach ([0, 1, 2, 3] as $i) {
            $mocks[$i]->shouldNotReceive('loadById');
        }

        $result = $this->make($mocks)->load($this->readyPrep($this->descriptor('nonsense')), $this->consumer());

        $this->assertNull($result);
    }

    /** @test */
    public function non_positive_id_fails_closed_before_any_load(): void
    {
        $mocks = $this->mocks();
        $mocks[4]->shouldNotReceive('resolveAllowedUserIds');
        foreach ([0, 1, 2, 3] as $i) {
            $mocks[$i]->shouldNotReceive('loadById');
        }

        $result = $this->make($mocks)->load($this->readyPrep($this->descriptor('buyer', 0)), $this->consumer());

        $this->assertNull($result);
    }

    /** @test */
    public function loader_returning_null_yields_null(): void
    {
        $mocks = $this->mocks();
        $mocks[4]->shouldReceive('resolveAllowedUserIds')->once()->andReturn([42]);
        // Record gone / not accessible / unresolvable property_types.
        $mocks[0]->shouldReceive('loadById')->once()->with(7, [42])->andReturnNull();

        $result = $this->make($mocks)->load($this->readyPrep($this->descriptor('buyer')), $this->consumer());

        $this->assertNull($result);
    }

    /** @test */
    public function invalid_payload_data_is_caught_and_returns_null(): void
    {
        $mocks = $this->mocks();
        $mocks[4]->shouldReceive('resolveAllowedUserIds')->once()->andReturn([42]);
        // Empty property_types makes the real BuyerCriteriaPayload throw InvalidArgumentException;
        // the adapter must catch it and fail closed rather than let it escape.
        $mocks[0]->shouldReceive('loadById')->once()->with(7, [42])->andReturn(['property_types' => []]);

        $result = $this->make($mocks)->load($this->readyPrep($this->descriptor('buyer')), $this->consumer());

        $this->assertNull($result);
    }

    /** @test */
    public function access_scope_from_resolver_is_passed_through_to_loader(): void
    {
        $mocks = $this->mocks();
        // Agent scope: self + client ids. Whatever the resolver returns must be the exact
        // allowedUserIds handed to loadById().
        $mocks[4]->shouldReceive('resolveAllowedUserIds')->once()->andReturn([42, 100, 101]);
        $mocks[0]->shouldReceive('loadById')->once()->with(7, [42, 100, 101])->andReturn($this->validFlatArray());

        $result = $this->make($mocks)->load($this->readyPrep($this->descriptor('buyer')), $this->consumer());

        $this->assertInstanceOf(BuyerCriteriaPayload::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
