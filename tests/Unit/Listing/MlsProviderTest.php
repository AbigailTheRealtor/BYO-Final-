<?php

namespace Tests\Unit\Listing;

use App\Services\Explore\ExploreListingProjector;
use App\Support\Listing\MlsProvider;
use PHPUnit\Framework\TestCase;

/**
 * The provider vocabulary: closed, fail-closed, and agreeing with the value
 * Explore already publishes.
 *
 * Extends PHPUnit's TestCase directly — this vocabulary must answer without a
 * booted container, like LandlordScreeningPolicy and the Smart Tag pure classes.
 */
class MlsProviderTest extends TestCase
{
    /** @test */
    public function the_current_provider_is_stellar_bridge(): void
    {
        $this->assertSame(MlsProvider::StellarBridge, MlsProvider::current());
        $this->assertSame('stellar_bridge', MlsProvider::current()->value);
    }

    /**
     * The stored identity and the value Explore already puts on a public API
     * response must be the same string. If they ever diverge, a row's recorded
     * origin and its published origin would disagree — which is the whole class
     * of bug this dimension exists to prevent.
     *
     * @test
     */
    public function the_value_matches_the_identity_explore_already_publishes(): void
    {
        $this->assertSame(
            ExploreListingProjector::PROVIDER_STELLAR_BRIDGE,
            MlsProvider::StellarBridge->value,
            'The stored provider identity and the one Explore publishes must be one string.'
        );
    }

    /** @test */
    public function reading_a_stored_value_is_fail_closed(): void
    {
        $this->assertSame(MlsProvider::StellarBridge, MlsProvider::fromStored('stellar_bridge'));

        // Unknown, empty and null must all resolve to null — never to the
        // current provider. A record whose origin we cannot name is not a
        // record from the provider we happen to have.
        $this->assertNull(MlsProvider::fromStored('some_other_mls'));
        $this->assertNull(MlsProvider::fromStored('STELLAR_BRIDGE'), 'matching is exact, not case-folded');
        $this->assertNull(MlsProvider::fromStored(''));
        $this->assertNull(MlsProvider::fromStored('   '));
        $this->assertNull(MlsProvider::fromStored(null));
    }

    /** @test */
    public function surrounding_whitespace_on_a_recognised_value_is_tolerated(): void
    {
        $this->assertSame(MlsProvider::StellarBridge, MlsProvider::fromStored('  stellar_bridge '));
    }

    /** @test */
    public function recognises_answers_the_same_question_as_from_stored(): void
    {
        $this->assertTrue(MlsProvider::recognises('stellar_bridge'));
        $this->assertFalse(MlsProvider::recognises('some_other_mls'));
        $this->assertFalse(MlsProvider::recognises(null));
    }

    /**
     * One provider today. This assertion is not here to freeze the count — it
     * is here so that adding a case is a deliberate act that updates a test,
     * rather than something that happens in passing.
     *
     * @test
     */
    public function the_vocabulary_is_closed_and_currently_holds_one_provider(): void
    {
        $this->assertSame(['stellar_bridge'], MlsProvider::values());
    }

    /**
     * The identity is a machine token, not attribution copy. Attribution is a
     * licence-governed display string that lives elsewhere and changes wording;
     * a stored identity must never be one.
     *
     * @test
     */
    public function the_identity_is_not_a_display_name(): void
    {
        foreach (MlsProvider::values() as $value) {
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9_]+$/',
                $value,
                'A provider identity is a lowercase machine token, never a display name.'
            );
            $this->assertStringNotContainsString(' ', $value);
        }
    }
}
