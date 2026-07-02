<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Models\LandlordAgentAuction;
use App\Models\TenantAgentAuction;
use App\Services\Canonical\Adapters\ByoListingAdapter;
use App\Services\Canonical\CanonicalListing;
use App\Services\Canonical\CanonicalListingResolver;
use App\Services\Dna\Confidence\ConfidenceCalculator;
use App\Services\Dna\Scores\PetFriendlinessScoreService;
use App\Services\Dna\Scores\SymmetricScoreDnaGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Beyond-MLS Wave 1 / Phase 2 — first vertical slice (Pet-Friendliness).
 *
 * Proves the symmetric score, F4 confidence, F5 explanation, canonical
 * resolver/adapter, dna_scores persistence, and the F3 Fair-Housing exclusion —
 * end to end and in isolation.
 */
class PetFriendlinessSliceTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): PetFriendlinessScoreService
    {
        return new PetFriendlinessScoreService();
    }

    private function property(array $fields): CanonicalListing
    {
        return new CanonicalListing('landlord_agent', 1, $fields);
    }

    private function demand(array $fields): CanonicalListing
    {
        return new CanonicalListing('tenant_agent', 1, $fields);
    }

    // ── Property side ───────────────────────────────────────────────────────

    public function test_permissive_policy_scores_high_with_full_completeness_and_confidence(): void
    {
        $r = $this->service()->scoreProperty($this->property([
            'pet.policy.pets_allowed'           => true,
            'pet.policy.max_weight_lbs'         => 80.0,
            'pet.policy.species_allowed'        => ['dog', 'cat'],
            'pet.policy.has_breed_restrictions' => false,
            'pet.policy.deposit_amount'         => 0.0,
        ]));

        $this->assertSame('property', $r['side']);
        $this->assertSame(100, $r['value']);
        $this->assertSame(100, $r['data_completeness']);
        $this->assertSame(90, $r['confidence']); // floor(100 * 90/100)
        $this->assertStringContainsString('pets permitted', $r['explanation']);
        $this->assertSame('PET_FRIENDLINESS_V1', $r['version']);
    }

    public function test_pets_not_permitted_scores_low_with_factual_explanation(): void
    {
        $r = $this->service()->scoreProperty($this->property([
            'pet.policy.pets_allowed' => false,
        ]));

        $this->assertSame(5, $r['value']);
        $this->assertSame(40, $r['data_completeness']);
        $this->assertSame(36, $r['confidence']); // floor(40 * 0.9)
        $this->assertStringContainsString('not permitted', $r['explanation']);
    }

    public function test_missing_decisive_input_withholds_value_and_zeroes_confidence(): void
    {
        $r = $this->service()->scoreProperty($this->property([
            // pets_allowed absent; only secondary fields present
            'pet.policy.max_weight_lbs'  => 50.0,
            'pet.policy.species_allowed' => ['dog'],
        ]));

        $this->assertNull($r['value']);
        $this->assertSame(0, $r['confidence']);
        $this->assertSame(40, $r['data_completeness']); // 20 + 20
        $this->assertStringContainsString('Insufficient', $r['explanation']);
    }

    // ── Demand side (symmetric axis) ────────────────────────────────────────

    public function test_demand_with_multiple_large_pets_scores_high_priority(): void
    {
        $r = $this->service()->scoreDemand($this->demand([
            'pet.profile.has_pets'   => true,
            'pet.profile.count'      => 2.0,
            'pet.profile.weight_lbs' => 70.0,
            'pet.profile.species'    => ['dog'],
        ]));

        $this->assertSame('demand', $r['side']);
        $this->assertSame(100, $r['value']); // 65 + 15 + 15 + 5
        $this->assertSame(100, $r['data_completeness']);
        $this->assertSame(90, $r['confidence']);
        $this->assertStringContainsString('has pets', $r['explanation']);
    }

    public function test_demand_without_pets_is_low_priority(): void
    {
        $r = $this->service()->scoreDemand($this->demand([
            'pet.profile.has_pets' => false,
        ]));

        $this->assertSame(10, $r['value']);
        $this->assertSame(55, $r['data_completeness']);
        $this->assertSame(49, $r['confidence']); // floor(55 * 0.9)
    }

    // ── F4 confidence invariant ─────────────────────────────────────────────

    public function test_confidence_is_never_greater_than_completeness(): void
    {
        foreach ([0, 10, 33, 40, 55, 90, 100] as $c) {
            $conf = ConfidenceCalculator::derive($c, ByoListingAdapter::SOURCE_RELIABILITY);
            $this->assertLessThanOrEqual($c, $conf, "confidence inflated at completeness={$c}");
        }
        $this->assertSame(0, ConfidenceCalculator::derive(0, 100));
        $this->assertSame(50, ConfidenceCalculator::derive(50, 100));
    }

    // ── F3 Fair Housing: service/support animals must not affect the score ──

    public function test_service_animal_status_does_not_change_the_score(): void
    {
        $withoutServiceAnimal = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $withoutServiceAnimal->saveMeta('pets', 'yes');
        $withoutServiceAnimal->saveMeta('pet_max_weight_lbs', '80');
        $withoutServiceAnimal->saveMeta('has_breed_restrictions', 'no');

        $withServiceAnimal = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $withServiceAnimal->saveMeta('pets', 'yes');
        $withServiceAnimal->saveMeta('pet_max_weight_lbs', '80');
        $withServiceAnimal->saveMeta('has_breed_restrictions', 'no');
        // Disability-linked signals — must be ignored by scoring.
        $withServiceAnimal->saveMeta('service_animal', 'yes');
        $withServiceAnimal->saveMeta('support_animal', 'yes');

        $resolver = app(CanonicalListingResolver::class);
        $service  = $this->service();

        $a = $service->scoreProperty($resolver->resolve('landlord_agent', $withoutServiceAnimal->id));
        $b = $service->scoreProperty($resolver->resolve('landlord_agent', $withServiceAnimal->id));

        $this->assertSame($a['value'], $b['value']);
        $this->assertSame($a['data_completeness'], $b['data_completeness']);
        $this->assertSame($a['confidence'], $b['confidence']);
    }

    // ── Canonical adapter normalization ─────────────────────────────────────

    public function test_adapter_normalizes_eav_values_to_canonical_fields(): void
    {
        $auction = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $auction->saveMeta('pets', 'Allowed');
        $auction->saveMeta('pet_max_weight_lbs', '$1,200');
        $auction->saveMeta('pet_species_allowed', json_encode(['dog', 'cat', 'bird']));
        $auction->saveMeta('has_breed_restrictions', 'no');

        $canonical = app(CanonicalListingResolver::class)->resolve('landlord_agent', $auction->id);

        $this->assertTrue($canonical->get('pet.policy.pets_allowed'));
        $this->assertSame(1200.0, $canonical->get('pet.policy.max_weight_lbs'));
        $this->assertSame(['dog', 'cat', 'bird'], $canonical->get('pet.policy.species_allowed'));
        $this->assertFalse($canonical->get('pet.policy.has_breed_restrictions'));
        // Absent field is not present.
        $this->assertFalse($canonical->present('pet.policy.monthly_fee'));
        // Provenance metadata is attached.
        $this->assertSame('byo:landlord_agent', $canonical->fieldMeta('pet.policy.pets_allowed')['source']);
        $this->assertSame('pets', $canonical->fieldMeta('pet.policy.pets_allowed')['source_field']);
    }

    // ── End-to-end generator + dna_scores persistence + idempotency ─────────

    public function test_generator_persists_property_score_and_is_idempotent(): void
    {
        $auction = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $auction->saveMeta('pets', 'yes');
        $auction->saveMeta('pet_max_weight_lbs', '80');
        $auction->saveMeta('pet_species_allowed', json_encode(['dog', 'cat']));
        $auction->saveMeta('has_breed_restrictions', 'no');
        $auction->saveMeta('pet_deposit_amount', '0');

        $gen = app(SymmetricScoreDnaGenerator::class);
        $pet = app(PetFriendlinessScoreService::class);

        $row = $gen->generateForListing($pet, 'landlord_agent', $auction->id);
        $this->assertInstanceOf(DnaScore::class, $row);
        $this->assertSame('property', $row->side);
        $this->assertSame('pet_friendliness', $row->score_key);
        $this->assertSame(100, $row->value);
        $this->assertSame(100, $row->data_completeness);
        $this->assertSame(90, $row->confidence);
        $this->assertNotEmpty($row->explanation);
        $this->assertSame('PET_FRIENDLINESS_V1', $row->version);
        $this->assertIsArray($row->inputs_json);

        // Re-run → upsert, not duplicate.
        $gen->generateForListing($pet, 'landlord_agent', $auction->id);
        $this->assertSame(1, DnaScore::where('listing_type', 'landlord_agent')
            ->where('listing_id', $auction->id)
            ->where('score_key', 'pet_friendliness')
            ->count());
    }

    public function test_generator_persists_demand_score_for_tenant(): void
    {
        $tenant = TenantAgentAuction::forceCreate(['auction_type' => 'tenant']);
        $tenant->saveMeta('pets', 'yes');
        $tenant->saveMeta('number_of_pets', '2');
        $tenant->saveMeta('weight_of_pets', '70');
        $tenant->saveMeta('type_of_pets', json_encode(['dog']));

        $row = app(SymmetricScoreDnaGenerator::class)
            ->generateForListing(app(PetFriendlinessScoreService::class), 'tenant_agent', $tenant->id);

        $this->assertSame('demand', $row->side);
        $this->assertSame(100, $row->value);
        $this->assertSame(90, $row->confidence);
    }
}
