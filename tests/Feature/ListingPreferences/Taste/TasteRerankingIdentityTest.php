<?php

namespace Tests\Feature\ListingPreferences\Taste;

use App\Models\BridgeProperty;
use App\Models\ListingPreferenceEvent;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingPreferences\ListingPreferenceSubjectResolver;
use App\Services\ListingPreferences\Taste\TasteEvidenceReader;
use App\Support\Listing\MlsProvider;
use App\Support\ListingPreferences\ListingPreferenceSubjectRef;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Canonical identity as Phase 5 inherits it — pinned, not changed.
 *
 * Reranking builds no subject key and parses no provider identity: Taste DNA
 * groups history by the stored `subject_key`, and the subject layer on main
 * (`MlsProvider::nativeIdentity()` since PR #192) is the only producer. These
 * tests make the CURRENT whitespace behaviour explicit so a later change to it
 * is a visible, reviewed decision — and prove Phase 5 rewrites no history.
 */
class TasteRerankingIdentityTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function a_padded_listing_key_produces_the_trimmed_provider_scoped_subject(): void
    {
        $ref = new SmartTagListingRef(SmartTagListingType::Bridge, 1);

        $this->assertSame(
            ListingPreferenceSubjectRef::mls($ref, MlsProvider::StellarBridge, 'KEY-1')->subjectKey,
            ListingPreferenceSubjectRef::mls($ref, MlsProvider::StellarBridge, "  KEY-1\t")->subjectKey,
        );
        $this->assertSame('mls:' . MlsProvider::StellarBridge->value . ':KEY-1', MlsProvider::StellarBridge->nativeIdentity(' KEY-1 '));
    }

    /** @test */
    public function a_bridge_row_whose_stored_key_is_padded_resolves_to_the_trimmed_subject(): void
    {
        $row = $this->bridge(' PAD-2 ');

        $subject = app(ListingPreferenceSubjectResolver::class)->resolve(new SmartTagListingRef(SmartTagListingType::Bridge, (int) $row->id));

        $this->assertSame('mls:' . MlsProvider::StellarBridge->value . ':PAD-2', $subject->subjectKey);
    }

    /**
     * The provider lookup for a native listing matches the stored key EXACTLY,
     * as MlsListingLink documents: a native meta that matches the row byte for
     * byte unifies with it; one differing only by padding does not, and falls
     * back to its own byo: subject. Explicit current behaviour — a visible,
     * harmless non-unification, never a mis-attribution.
     *
     * @test
     */
    public function native_linkage_matches_the_stored_key_exactly(): void
    {
        $this->bridge(' PAD-3 ');

        $exact  = $this->linkedByo(' PAD-3 ');
        $padded = $this->linkedByo('PAD-3');

        $resolved = app(ListingPreferenceSubjectResolver::class)->resolveMany([
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $exact),
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $padded),
        ]);

        $this->assertSame('mls:' . MlsProvider::StellarBridge->value . ':PAD-3', $resolved["seller_agent:{$exact}"]->subjectKey);
        $this->assertSame("byo:seller_agent:{$padded}", $resolved["seller_agent:{$padded}"]->subjectKey);
    }

    /** @test */
    public function taste_reads_stored_subject_keys_verbatim_and_rewrites_nothing(): void
    {
        $user = User::factory()->create(['user_type' => 'buyer']);
        $key  = 'mls:' . MlsProvider::StellarBridge->value . ':VERBATIM-4';

        DB::table('listing_preference_events')->insert([
            'user_id' => $user->id, 'seeker_role' => 'buyer', 'subject_key' => $key,
            'listing_type' => 'bridge', 'listing_id' => 999999, 'from_state' => null, 'to_state' => 'save',
            'reasons_json' => json_encode(['natural_light']), 'created_at' => now(),
        ]);

        $before = ListingPreferenceEvent::query()->orderBy('id')->get()->toArray();

        $records = app(TasteEvidenceReader::class)->for((int) $user->id, SeekerRole::Buyer)->records;

        $this->assertSame([$key], array_map(static fn ($r) => $r->subjectKey, $records));
        $this->assertSame($before, ListingPreferenceEvent::query()->orderBy('id')->get()->toArray(), 'history is never rewritten');
    }

    private function bridge(string $key): BridgeProperty
    {
        return BridgeProperty::create([
            'provider' => 'stellar_bridge', 'listing_key' => $key, 'standard_status' => 'Active',
            'property_type' => 'Residential', 'raw_json' => json_encode(['IDXParticipationYN' => true]),
        ]);
    }

    private function linkedByo(string $metaKey): int
    {
        $byo = SellerAgentAuction::create(['user_id' => 905000, 'address' => 'Linked', 'is_approved' => 1, 'is_draft' => false, 'is_archived' => 0]);
        SellerAgentAuctionMeta::create(['seller_agent_auction_id' => $byo->id, 'meta_key' => MlsQuickImportDraftWriter::META_LISTING_KEY, 'meta_value' => $metaKey]);

        return (int) $byo->id;
    }
}
