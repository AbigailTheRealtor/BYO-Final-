<?php

namespace Tests\Feature\ListingImport;

use App\Models\BridgeProperty;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\Mls\MlsListingDetailsReader;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HOW AN IMPORTED LISTING PRESENTS ITS MLS DATA.
 *
 * The published page used to carry TWO descriptions of the same property: its
 * own "Property Details" card, and — directly beneath it — a dense block also
 * titled "MLS Property Details", in its own typography, listing the facts the
 * form had no field for. Nothing was duplicated at the FIELD level (the import
 * already suppresses a Tier-1 fact that reached an editable field) but the
 * reader met two competing presentations of one house and had to decide which
 * to believe.
 *
 * These tests pin the fix and, more importantly, its two boundaries:
 *
 *   NOTHING MAY BE LOST. Every row of the stored payload still reaches the page.
 *   That is asserted by counting, not by spot-checking a few labels, because the
 *   failure mode of a layout change is a section quietly landing nowhere.
 *
 *   NOTHING MAY BE INVENTED. A manually created listing shows no MLS section and
 *   no Stellar attribution, whatever it happens to have in its meta.
 */
class MlsListingDetailPresentationTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'PRESENT-KEY';
    private const MLS = 'PRESENT-MLS';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
        ]);
    }

    private function seedRecord(array $overrides = []): BridgeProperty
    {
        $raw = array_merge([
            'ListingKey'                     => self::KEY,
            'ListingId'                      => self::MLS,
            'StandardStatus'                 => 'Active',
            'MlsStatus'                      => 'Active',
            'PropertyType'                   => 'Residential',
            'PropertySubType'                => 'Condominium',
            'UnparsedAddress'                => '9 Example Way',
            'City'                           => 'CLEARWATER',
            'StateOrProvince'                => 'FL',
            'PostalCode'                     => '33760',
            'ListPrice'                      => 425000,
            'BedroomsTotal'                  => 3,
            'BathroomsTotalInteger'          => 2,
            'YearBuilt'                      => 1991,
            'SubdivisionName'                => 'Bradford Acres',
            // MLS-only facts: no editable Create Offer field exists for any of
            // these, so they can only ever reach the page through MLS Details.
            'Flooring'                       => 'Laminate',
            'LaundryFeatures'                => 'Inside, Laundry Closet',
            'ExteriorFeatures'               => 'Balcony, Storage',
            'AssociationFeeIncludes'         => 'Cable TV, Insurance',
            'AssociationAmenities'           => 'Clubhouse, Pool',
            'TaxBlock'                       => '017',
            'MinimumLeaseTerm'               => null,
            'ListOfficeName'                 => 'Example Realty Group',
            'ListAgentFullName'              => 'Alex Agent',
            'ModificationTimestamp'          => '2026-08-01T12:00:00.000Z',
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,
        ], $overrides);

        BridgeProperty::where('listing_key', self::KEY)->delete();

        return BridgeProperty::create([
            'listing_key'       => self::KEY,
            'listing_id'        => self::MLS,
            'standard_status'   => 'Active',
            'property_type'     => 'Residential',
            'unparsed_address'  => $raw['UnparsedAddress'],
            'city'              => $raw['City'],
            'state_or_province' => $raw['StateOrProvince'],
            'postal_code'       => $raw['PostalCode'],
            'list_price'        => $raw['ListPrice'],
            'raw_json'          => json_encode($raw),
            'imported_at'       => now(),
        ]);
    }

    private function publishedImport(): SellerAgentAuction
    {
        $this->seedRecord();

        $result = app(MlsQuickImportService::class)->lookup(self::MLS, 'seller');
        $this->assertTrue($result->isFound(), "lookup failed: {$result->status}");

        /** @var SellerAgentAuction $listing */
        $listing = app(MlsQuickImportDraftWriter::class)
            ->materialise('seller', User::factory()->create()->id, $result);

        $listing->is_draft    = 0;
        $listing->is_approved = true;
        $listing->save();

        return $listing->fresh();
    }

    private function metaOf(object $listing): array
    {
        $meta = [];

        foreach ($listing->fresh()->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded)))
                ? $decoded
                : $row->meta_value;
        }

        return $meta;
    }

    private function page(SellerAgentAuction $listing): string
    {
        $response = $this->get(route('offer.listing.seller.view', ['id' => $listing->id]));
        $response->assertStatus(200);

        return $response->getContent();
    }

    // ─── Nothing is lost ─────────────────────────────────────────────────────

    /**
     * @test
     *
     * THE GUARANTEE THAT MATTERS. Every populated row of the stored supplemental
     * payload appears on the published page. Counted rather than sampled: a
     * section that lands in no slot is exactly the failure a handful of
     * assertSee() calls would miss.
     */
    public function every_stored_mls_row_reaches_the_published_page(): void
    {
        $listing = $this->publishedImport();
        $details = app(MlsListingDetailsReader::class)->detailsFrom($this->metaOf($listing));

        $this->assertFalse($details->isEmpty(), 'fixture produced no MLS payload; the test would be vacuous');

        $html   = $this->page($listing);
        $absent = [];

        foreach ($details->sections as $section) {
            foreach ($section['rows'] as $row) {
                if (! str_contains($html, e($row['label'])) || ! str_contains($html, e($row['value']))) {
                    $absent[] = $section['title'] . ' › ' . $row['label'] . ' = ' . $row['value'];
                }
            }
        }

        $this->assertSame([], $absent, "MLS rows missing from the published page:\n  " . implode("\n  ", $absent));
    }

    /**
     * @test
     *
     * The MLS-only facts specifically — the ones with no editable Create Offer
     * field, whose ONLY surface is this payload. If the layout ever drops a
     * section these are what is lost.
     */
    public function mls_only_facts_remain_visible(): void
    {
        $html = $this->page($this->publishedImport());

        foreach (['Flooring', 'Laminate', 'Laundry', 'Exterior Features', 'Fee Includes', 'Tax Block', 'Subdivision'] as $needle) {
            $this->assertStringContainsString($needle, $html, "MLS-only fact '{$needle}' is no longer rendered");
        }
    }

    // ─── One presentation per fact ───────────────────────────────────────────

    /**
     * @test
     *
     * The imported facts are merged into the page's own Property Details card,
     * so the page carries exactly ONE card with that header. The MLS sub-heading
     * inside it still says where its rows came from.
     */
    public function the_page_carries_one_property_details_card_not_two(): void
    {
        $html = $this->page($this->publishedImport());

        $this->assertSame(
            1,
            substr_count($html, '</i>Property Details</div>'),
            'an imported listing must not carry a second Property Details block',
        );

        $this->assertStringContainsString('MLS Property Details', $html);
    }

    /**
     * @test
     *
     * No label appears twice inside one card. That is the invariant behind "one
     * property fact, one visible presentation" — the merge puts MLS rows next to
     * canonical rows, and a repeated label there is the duplication returning.
     */
    public function no_card_renders_the_same_label_twice(): void
    {
        $html = $this->page($this->publishedImport());

        foreach ($this->cards($html) as $title => $labels) {
            $repeated = array_keys(array_filter(array_count_values($labels), static fn (int $n) => $n > 1));

            $this->assertSame(
                [],
                $repeated,
                "card '{$title}' renders these labels more than once: " . implode(', ', $repeated),
            );
        }
    }

    /**
     * @test
     *
     * The old block's own typography is gone: imported rows use the page's row
     * markup, not a definition list of their own.
     */
    public function imported_rows_use_the_pages_own_row_markup(): void
    {
        $html = $this->page($this->publishedImport());

        $this->assertStringNotContainsString('<dl class="row mb-0 small">', $html);
        $this->assertStringContainsString('col-md-5 text-muted fw-semibold', $html);
    }

    // ─── Manual listings ─────────────────────────────────────────────────────

    /**
     * @test
     *
     * A listing that did not come from the MLS renders no MLS section and no
     * Stellar attribution. A false provenance claim is worse than a missing one.
     */
    public function a_manual_listing_carries_no_mls_section_or_attribution(): void
    {
        $listing = new SellerAgentAuction();
        $listing->user_id     = User::factory()->create()->id;
        $listing->is_draft    = 0;
        $listing->is_approved = true;
        $listing->title       = 'Hand-typed listing';
        $listing->address     = 'Hand-typed listing';
        $listing->save();
        $listing->saveMeta('bedrooms', '3');
        $listing->saveMeta('auction_type', 'Traditional');
        ListingWorkflow::stamp($listing, ListingWorkflow::OFFER_LISTING);

        $html = $this->page($listing->fresh());

        $this->assertStringNotContainsString('MLS Property Details', $html);
        $this->assertStringNotContainsString('Information provided by Stellar MLS', $html);
        $this->assertStringNotContainsString('Stellar MLS via Bridge', $html);

        // …and its own listing data is untouched by any of this.
        $this->assertStringContainsString('Bedrooms', $html);
    }

    // ─── Attribution and the manual BidYourOffer answers ────────────────────

    /** @test */
    public function the_required_attribution_and_brokerage_survive_the_reformatting(): void
    {
        $html = $this->page($this->publishedImport());

        $this->assertStringContainsString('Information provided by Stellar MLS via Bridge Data Output', $html);
        // Whitespace-normalised: the notice wraps across lines in the template.
        $this->assertStringContainsString(
            'deemed reliable but not guaranteed',
            preg_replace('/\s+/', ' ', $html),
        );
        $this->assertStringContainsString('Stellar MLS', $html);
        $this->assertStringContainsString('Example Realty Group', $html);
        $this->assertStringContainsString('Alex Agent', $html);
        $this->assertStringContainsString('MLS Information', $html);
        $this->assertStringContainsString(self::MLS, $html);
    }

    /**
     * @test
     *
     * Listing Method is a manually entered BidYourOffer answer and must survive
     * on a listing whose property data came from the feed — it is the one thing
     * on the page the MLS did not supply and cannot replace.
     */
    public function the_manually_chosen_listing_method_still_renders(): void
    {
        $listing = $this->publishedImport();
        $listing->saveMeta('auction_type', 'Traditional');

        $html = $this->page($listing->fresh());

        $this->assertStringContainsString('Listing Method', $html);
        $this->assertStringContainsString('Traditional', $html);
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    /**
     * Card header => the row labels rendered inside that card.
     *
     * @return array<string, list<string>>
     */
    private function cards(string $html): array
    {
        $starts = [];

        if (preg_match_all('/<div class="card section-card/', $html, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $hit) {
                $starts[] = $hit[1];
            }
        }

        $out    = [];
        $bounds = array_merge($starts, [strlen($html)]);

        foreach ($starts as $i => $start) {
            $segment = substr($html, $start, $bounds[$i + 1] - $start);

            preg_match('/<div class="card-header[^"]*">(.*?)<div class="card-body">/s', $segment, $head);
            $title = isset($head[1]) ? trim(preg_replace('/\s+/', ' ', strip_tags($head[1]))) : '(untitled)';

            preg_match_all(
                '/<div class="col-md-5 text-muted fw-semibold"[^>]*>(.*?)<\/div>/s',
                $segment,
                $labels,
            );

            $out[$title] = array_map(
                static fn (string $l) => trim(preg_replace('/\s+/', ' ', strip_tags($l))),
                $labels[1] ?? [],
            );
        }

        return $out;
    }
}
