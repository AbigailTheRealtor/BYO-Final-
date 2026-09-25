<?php

namespace Tests\Feature\Stellar;

use App\Models\TenantAgentAuction;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Tenant Offer Listing pets answer → `wants_pet_friendly`.
 *
 * THE FORM (offer-tenant-tabs/commission-based/pre-screening.blade.php) emits exactly
 * three `pets` values: '' (Select), 'Yes', 'No'. `pet_information` is legacy free text the
 * Offer Listing wizards only round-trip, stored as ''.
 *
 * TWO DEFECTS THIS PINS
 *   1. `pets = 'No'` was read as a pet-friendly request, so a tenant WITHOUT pets was told
 *      "Pet policy restricts pets in this community" on every no-pets listing.
 *   2. The loader read `pet_information ?? pets`. `??` falls through on null only, so the
 *      stored '' masked a real 'Yes' and a tenant WITH pets asked for nothing.
 *
 * Only `=== true` acts anywhere downstream; "no request" is `null`, never `false`, so a
 * 'No' does not mint a new criteria hash for an otherwise identical search.
 */
class TenantOfferListingPetPreferenceTest extends TestCase
{
    use DatabaseTransactions;

    private function skipIfTableMissing(): void
    {
        foreach (['tenant_agent_auctions', 'tenant_agent_auction_metas', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }
    }

    private function makeUser(): int
    {
        return DB::table('users')->insertGetId([
            'first_name'  => 'PetPref',
            'last_name'   => 'Test',
            'name'        => 'PetPref Test',
            'short_id'    => 'PETPREF'.uniqid(),
            'user_name'   => 'petpref_'.uniqid(),
            'email'       => 'petpref-'.uniqid().'@example.com',
            'password'    => bcrypt('password'),
            'user_type'   => 'tenant',
            'is_approved' => true,
            'is_super'    => false,
            'is_deleted'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** @param array<string, string> $meta */
    private function wantsPetFriendly(array $meta): ?bool
    {
        $this->skipIfTableMissing();

        $userId = $this->makeUser();
        $id = DB::table('tenant_agent_auctions')->insertGetId([
            'user_id'         => $userId,
            'is_approved'     => true,
            'is_draft'        => false,
            'is_sold'         => false,
            'auction_ended'   => false,
            'referral_locked' => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $auction = TenantAgentAuction::findOrFail($id);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('rental_purpose', 'residential');
        $auction->saveMeta('property_type', 'Residential Property');
        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        $payload = (new TenantOfferListingCriteriaLoader())->loadById($auction->id, [$userId]);
        $this->assertNotNull($payload);
        $this->assertArrayHasKey('wants_pet_friendly', $payload);

        return $payload['wants_pet_friendly'];
    }

    public function test_pets_yes_requests_a_pet_friendly_home(): void
    {
        $this->assertTrue($this->wantsPetFriendly(['pets' => 'Yes']));
    }

    public function test_pets_no_does_not_request_a_pet_friendly_home(): void
    {
        $this->assertNull($this->wantsPetFriendly(['pets' => 'No']));
    }

    public function test_pets_left_on_select_is_neutral(): void
    {
        $this->assertNull($this->wantsPetFriendly(['pets' => '']));
        $this->assertNull($this->wantsPetFriendly([]));
    }

    /** The shape every production tenant listing has: 'Yes' beside a stored empty pet_information. */
    public function test_an_empty_pet_information_no_longer_masks_a_yes(): void
    {
        $this->assertTrue($this->wantsPetFriendly(['pets' => 'Yes', 'pet_information' => '']));
    }

    public function test_an_empty_pet_information_does_not_turn_a_no_into_a_request(): void
    {
        $this->assertNull($this->wantsPetFriendly(['pets' => 'No', 'pet_information' => '']));
    }

    /** The select is the authority: legacy text cannot overrule an explicit 'No'. */
    public function test_legacy_pet_text_never_overrules_an_explicit_no(): void
    {
        $this->assertNull($this->wantsPetFriendly(['pets' => 'No', 'pet_information' => 'One small dog']));
    }

    /** With no select answer, legacy free text keeps its old meaning. */
    public function test_legacy_pet_text_is_read_only_when_the_select_is_unanswered(): void
    {
        $this->assertTrue($this->wantsPetFriendly(['pet_information' => 'One small dog']));

        foreach (['none', 'None', 'no', ' NO ', ''] as $nothing) {
            $this->assertNull($this->wantsPetFriendly(['pet_information' => $nothing]), "'{$nothing}' must not request pets");
        }
    }
}
