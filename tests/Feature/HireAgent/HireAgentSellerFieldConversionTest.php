<?php

namespace Tests\Feature\HireAgent;

use App\Models\SellerAgentAuction;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S2 — the Seller field conversion.
 *
 * Seller was the last role holding 71 hand-written label/value rows; this milestone routes them
 * through x-hire-agent.field. The suite had NO seller field coverage at all before this file —
 * HireAgentFieldPresentationTest is landlord-only, and HireAgentSectionCardDomEquivalenceTest pins
 * headings, anchor ids, card counts and separators but never looks inside a row. So a conversion
 * that renamed a label, dropped a guard or re-punctuated a value would have gone green.
 *
 * WHAT THIS FILE IS FOR. Flag-off text is the contract the conversion had to preserve, and the
 * recorded expectations below ARE the pre-change values, read off the pre-change render — the same
 * technique HireAgentSectionCardDomEquivalenceTest uses and for the same reason.
 *
 * THE THREE LEGACY SHAPES ARE ASSERTED SEPARATELY because they are different element trees, and the
 * conversion's whole risk is emitting the wrong one:
 *
 *   · ordinary   — div.fw-bold > text + span.removeBold
 *   · bare slot  — div.fw-bold > text + the caller's own pill run, no wrapper
 *   · inverted   — div.removeBold > span.fw-bold + bare text        (Seller only; S2 added it)
 */
class HireAgentSellerFieldConversionTest extends TestCase
{
    use DatabaseTransactions;

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * A Residential listing answering one field of every shape the conversion touches.
     *
     * Residential rather than Income or Commercial because it is the branch that carries Bedrooms,
     * the first Carport/Garage pair and the "Sqft Heated Source" spelling; the other two property
     * types get their own test below for the labels that differ.
     */
    private function residentialMeta(): array
    {
        return [
            // Listing Details
            'working_with_agent'      => 'No',
            'desired_agent_hire_date' => '2026-03-01',
            'listing_date'            => '2026-03-15',
            'expiration_date'         => '2026-09-15',
            'auction_type'            => 'Standard',
            'meeting_Preference'      => 'Video Call',

            // Property Details
            'property_city'           => 'Tampa, FL',
            'property_county'         => 'Hillsborough, FL',
            'state'                   => 'FL',
            'zip_code'                => '33601',
            'property_type'           => 'Residential',
            'property_items'          => json_encode(['Ranch', 'Bungalow']),
            'condition_prop'          => json_encode(['Updated']),
            'bedrooms'                => '3',
            'bathrooms'               => '2',
            'minimum_heated_square'   => '1850',
            'total_square_feet'       => '2400',
            'sqft_heated_source'      => 'Tax Records',
            'total_acreage'           => '0.25',
            'carportOptions'          => 'Yes',
            'custom_carport'          => '2',
            'appliances'              => json_encode(['Dishwasher', 'Dryer']),
            'pool_needed'             => 'Yes',
            'view_preference'         => json_encode(['Water']),
            'leasing_55_plus'         => 'No',
            'non_negotiable_amenities' => json_encode(['Fenced Yard']),
            'pets'                    => 'Yes',
            'number_of_pets'          => '2',
            'type_of_pets'            => 'Dogs',
            'weight_of_pets'          => '40',
            'breed_of_pets'           => 'No aggressive breeds',

            // Sale Terms — including the inverted rows
            'sale_provision'            => json_encode(['As-Is']),
            'sale_provision_assignment' => 'Yes',
            'buyer_sell_contract'       => 'No',
            'assignment_fee_amount'     => '5000',
            'assignment_fee_type'       => '$',
            'target_closing_date'       => '2026-06-30',
            'occupant_status'           => 'Owner',
            'occupant_tenant'           => '2026-05-01',
            'maximum_budget'            => '750000',

            // Financing
            'offered_financing' => json_encode(['Cash', 'Conventional']),

            // Additional / Referral / Owner
            'additional_details'  => 'Evening showings preferred.',
            'referral_percentage' => '25',
            'first_name'          => 'Dana',
            'current_status'      => 'Relocating',
        ];
    }

    private function listing(array $meta): SellerAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'seller']);

        $listing = SellerAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Seller field-conversion listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing;
    }

    private function enableRedesign(array $roles): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => $roles,
        ]);
    }

    private function render(array $meta): DOMXPath
    {
        $listing = $this->listing($meta);

        $response = $this->actingAs($listing->user)
            ->get(route('seller.agent.auction.detail', $listing->id));

        $response->assertOk();

        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return new DOMXPath($doc);
    }

    private function norm(?string $s): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $s));
    }

    /**
     * Every legacy label/value row on the page, as "Label: value", in document order.
     *
     * Selects the ROW div by its class list rather than by looking for `.removeBold`, because the
     * inverted rows carry that class on the row itself — a selector keyed on it returns a different
     * node for one shape than for the others, which is the bug that made the landlord helper
     * unusable here.
     */
    private function legacyRows(DOMXPath $x): array
    {
        $out = [];

        foreach ($x->query('//div[contains(@class, "pt-2")]') as $node) {
            $class = $node->getAttribute('class');

            if (! str_contains($class, 'fw-bold') && ! str_contains($class, 'removeBold')) {
                continue;
            }

            $text = $this->norm($node->textContent);

            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    /** Every redesigned kv cell as "Label: value", in document order. */
    private function kvRows(DOMXPath $x): array
    {
        $out = [];

        foreach ($x->query('//*[contains(concat(" ", normalize-space(@class), " "), " viho-kv ")]') as $node) {
            $label = $value = '';

            foreach ($node->childNodes as $child) {
                if (! $child instanceof \DOMElement) {
                    continue;
                }
                $c = $child->getAttribute('class');
                if (str_contains($c, 'viho-kv-label')) {
                    $label = $this->norm($child->textContent);
                }
                if (str_contains($c, 'viho-kv-value')) {
                    $value = $this->norm($child->textContent);
                }
            }

            if ($label !== '') {
                $out[] = "{$label}: {$value}";
            }
        }

        return $out;
    }

    // ── Flag off: the three legacy shapes ────────────────────────────────────

    /** The ordinary shape — bold row div, value in a removeBold span. */
    public function test_flag_off_ordinary_row_keeps_its_element_tree(): void
    {
        $x = $this->render($this->residentialMeta());

        $node = $x->query('//div[contains(@class,"fw-bold")][span[@class="removeBold"]][contains(., "Listing Date")]');

        $this->assertGreaterThan(0, $node->length, 'The ordinary row shape must survive the conversion.');
        $this->assertSame('Listing Date: March 15, 2026', $this->norm($node->item(0)->textContent));
    }

    /**
     * The INVERTED shape — removeBold row div, label in a fw-bold span, bare value.
     *
     * The shape S2 added the component branch for. Asserted as an element tree rather than as text
     * because the text was never in question: both shapes read "Label: value".
     */
    public function test_flag_off_inverted_rows_keep_their_element_tree(): void
    {
        $x = $this->render($this->residentialMeta());

        foreach ([
            'Assignment Contract'                => 'Yes',
            'Seller Under Contract for Assignment' => 'No',
            'Target Closing Date'                => '2026-06-30',
            'Occupant Type'                      => 'Owner',
            'Occupied Until'                     => 'May 1, 2026',
            'Desired Sale Price'                 => '$750,000',
            "Seller's Current Status"            => 'Relocating',
        ] as $label => $value) {
            $node = $x->query(
                '//div[contains(@class,"removeBold")][span[@class="fw-bold"]]'
                . '[span[normalize-space(text())="' . $label . ':"]]'
            );

            $this->assertSame(
                1,
                $node->length,
                "[{$label}] must render as an inverted row: div.removeBold > span.fw-bold + bare value."
            );

            $this->assertSame("{$label}: {$value}", $this->norm($node->item(0)->textContent));
        }
    }

    /** The assignment fee formats as money when the type key is not a percent. */
    public function test_flag_off_assignment_fee_formats_as_money(): void
    {
        $x = $this->render($this->residentialMeta());

        $this->assertContains('Assignment Contract Fee to Broker: $5,000', $this->legacyRows($x));
    }

    /** And as a percentage when it is. */
    public function test_flag_off_assignment_fee_formats_as_percent(): void
    {
        $x = $this->render(array_merge($this->residentialMeta(), [
            'assignment_fee_type'   => '%',
            'assignment_fee_amount' => '3',
        ]));

        $this->assertContains('Assignment Contract Fee to Broker: 3%', $this->legacyRows($x));
    }

    /** The bare-slot shape — the pill run is emitted with no removeBold wrapper around it. */
    public function test_flag_off_pill_rows_keep_their_pills(): void
    {
        $x = $this->render($this->residentialMeta());

        $pills = $x->query('//div[contains(@class,"fw-bold")][contains(., "Appliances Included")]//span[contains(@class,"badge")]');

        $this->assertSame(2, $pills->length, 'The appliance pill run must survive the conversion.');
        $this->assertSame('Dishwasher', $this->norm($pills->item(0)->textContent));
    }

    // ── Flag off: the values themselves ──────────────────────────────────────

    /**
     * The rows a Residential listing renders, as text, in document order.
     *
     * A characterization pin: these strings are what the page produced BEFORE the conversion, so a
     * renamed label, a lost guard or a re-punctuated value fails here. The media rows are excluded
     * because they were deliberately left unconverted and carry embedded markup rather than text.
     */
    public function test_flag_off_residential_rows_read_exactly_as_before(): void
    {
        $rows = $this->legacyRows($this->render($this->residentialMeta()));

        foreach ([
            'Current Representation Status with Broker: No',
            'Desired Agent Hire Date: March 1, 2026',
            'Listing Date: March 15, 2026',
            'Expiration Date: September 15, 2026',
            'Listing Type: Standard',
            'Meeting Preference: Video Call',
            'City: Tampa',
            'County: Hillsborough',
            'State: FL',
            'ZIP Code: 33601',
            'Property Type: Residential',
            'Property Style: Ranch, Bungalow',
            'Property Condition: Updated',
            'Bedrooms: 3',
            'Bathrooms: 2',
            'Heated Sqft: 1,850',
            'Total Sqft: 2,400',
            'Sqft Heated Source: Tax Records',
            'Carport: Yes (2 Spaces)',
            'Total Acreage: 0.25',
            'Age-Restricted Community: No',
            'Pets Allowed: Yes (2)',
            'Acceptable Pet Types: Dogs',
            'Maximum Weight Per Pet (lbs): 40 lbs',
            'Pet Restrictions: No aggressive breeds',
            'Additional Details: Evening showings preferred.',
            'First Name: Dana',
        ] as $expected) {
            $this->assertContains($expected, $rows, "Flag-off row changed: [{$expected}]");
        }
    }

    /**
     * The city and county lists keep their SEMICOLON separator.
     *
     * Seller joins these with "; " where the shared component's listValue joins with ", ". Handing
     * the array to listValue would have silently re-punctuated the row, so the join stays at the
     * call site — this is the assertion that keeps it there.
     */
    public function test_flag_off_multi_city_and_county_keep_the_semicolon_join(): void
    {
        $rows = $this->legacyRows($this->render(array_merge($this->residentialMeta(), [
            'cities'   => json_encode(['Tampa, FL', 'Brandon, FL']),
            'counties' => json_encode(['Hillsborough, FL', 'Pasco, FL']),
        ])));

        $this->assertContains('City: Tampa; Brandon', $rows);
        $this->assertContains('County: Hillsborough; Pasco', $rows);
    }

    /**
     * THE THREE SQFT TRIOS KEEP THEIR DIVERGENT LABEL CASING.
     *
     * Residential says "Sqft Heated Source"; Commercial, Business and Income say "SqFt Heated
     * Source"; Income alone says "Heated SqFt" where the others say "Heated Sqft". The branches read
     * the same three meta keys and look like copy-paste, so this is the assertion that stops a
     * future tidy-up from silently re-captioning whichever branch loses.
     *
     * @dataProvider sqftLabelCases
     */
    public function test_sqft_label_casing_is_per_property_type(string $propertyType, string $heated, string $source): void
    {
        $rows = $this->legacyRows($this->render(array_merge($this->residentialMeta(), [
            'property_type' => $propertyType,
        ])));

        $this->assertContains("{$heated}: 1,850", $rows, "[{$propertyType}] heated-sqft label changed.");
        $this->assertContains("{$source}: Tax Records", $rows, "[{$propertyType}] sqft-source label changed.");
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function sqftLabelCases(): array
    {
        return [
            'residential' => ['Residential', 'Heated Sqft', 'Sqft Heated Source'],
            'commercial'  => ['Commercial',  'Heated Sqft', 'SqFt Heated Source'],
            'business'    => ['Business',    'Heated Sqft', 'SqFt Heated Source'],
            'income'      => ['Income',      'Heated SqFt', 'SqFt Heated Source'],
        ];
    }

    /** Pool comes from a Seller-only partial and is converted with the rest. */
    public function test_flag_off_pool_row_renders_from_the_partial(): void
    {
        $this->assertContains('Pool: Yes', $this->legacyRows($this->render($this->residentialMeta())));
    }

    /** The pet sub-rows stay behind a "Pets Allowed = Yes" answer, not merely a present one. */
    public function test_pet_detail_rows_require_a_yes(): void
    {
        $rows = $this->legacyRows($this->render(array_merge($this->residentialMeta(), [
            'pets' => 'No',
        ])));

        $this->assertNotContains('Acceptable Pet Types: Dogs', $rows);
        $this->assertNotContains('Pet Restrictions: No aggressive breeds', $rows);
    }

    // ── Flag on ──────────────────────────────────────────────────────────────

    /**
     * With the redesign on, the same answers arrive as kv cells — including the inverted rows.
     *
     * THE CONVERGENCE THAT MAKES legacyInverted A LEGACY-ONLY CONCERN. An inverted row and an
     * ordinary one reach the same x-viho.kv call, so on the redesigned page they are
     * indistinguishable; "Desired Sale Price" reads exactly like "Listing Date". The kv labels carry
     * NO colon, which is the other half of that contract.
     */
    public function test_flag_on_renders_the_same_answers_as_kv_cells(): void
    {
        $this->enableRedesign(['seller']);

        $rows = $this->kvRows($this->render($this->residentialMeta()));

        foreach ([
            'Listing Date: March 15, 2026',
            'Property Type: Residential',
            'Heated Sqft: 1,850',
            'Pets Allowed: Yes (2)',
            // Inverted in legacy, ordinary here — the convergence.
            'Assignment Contract: Yes',
            'Target Closing Date: 2026-06-30',
            'Desired Sale Price: $750,000',
            "Seller's Current Status: Relocating",
            // Pills in legacy, ", "-joined text here.
            'Appliances Included: Dishwasher, Dryer',
        ] as $expected) {
            $this->assertContains($expected, $rows, "Redesigned row missing or changed: [{$expected}]");
        }
    }

    /** No kv label carries a trailing colon, inverted rows included. */
    public function test_flag_on_kv_labels_carry_no_trailing_colon(): void
    {
        $this->enableRedesign(['seller']);

        $x = $this->render($this->residentialMeta());

        $labels = $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " viho-kv-label ")]');

        $this->assertGreaterThan(0, $labels->length, 'The redesigned page must emit kv labels.');

        foreach ($labels as $label) {
            $text = $this->norm($label->textContent);
            $this->assertStringEndsNotWith(':', $text, "Label [{$text}] carries a trailing colon.");
        }
    }

    /** And the legacy row shapes are gone from the redesigned page entirely. */
    public function test_flag_on_emits_no_legacy_field_rows(): void
    {
        $this->enableRedesign(['seller']);

        $x = $this->render($this->residentialMeta());

        // The media block is deliberately unconverted and keeps its own col-md-6 rows, so it is
        // excluded by class rather than by pretending it does not exist.
        $legacy = $x->query('//div[contains(@class,"col-md-12")][contains(@class,"pt-2")]');

        $this->assertSame(
            0,
            $legacy->length,
            'The redesigned seller page must emit no col-md-12 legacy field rows.'
        );
    }
}
