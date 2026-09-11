<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\BridgeProperty;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The currency prefix must sit BESIDE its input on Quick Import, not above it.
 *
 * THE DEFECT THIS CLOSES
 * ----------------------
 * Every rule in resources/css/app.css is scoped to `#wizard-form-container`, and
 * its header names the consumers: "the Hire Agent and Offer Listing wizard forms
 * (create + edit/draft, all 8 flows)". MLS Quick Import renders two of those
 * wizards' own canonical partials and was never added to the list, so its root
 * element carried no such ID and none of those rules matched.
 *
 * `.input-cover` is `display:flex` ONLY under that ID. Unmatched it falls back to
 * `display:block`, so `<span class="input-group-text-seller">$</span>` took its
 * own line above an input Bootstrap renders at 100% width:
 *
 *     $                       instead of      $ [ amount ]
 *     [ amount ]
 *
 * across all 27 `$`/`%` controls in the two partials, and the same miss cost the
 * canonical input min-height, the `.input-cover`/`.has-icon` padding,
 * `.percentage-value-set` and the Select2 sizing.
 *
 * WHY THESE ASSERTIONS AND NOT A SCREENSHOT
 * -----------------------------------------
 * The bug is a CSS-scoping miss, and CSS scoping is decidable from the rendered
 * DOM: the rules are keyed on an ancestor ID, so "is the prefix inside an element
 * with that ID" is the whole question. That is asserted here against real
 * rendered output for both roles. There is deliberately no browser in this suite,
 * and a test that re-implemented the cascade would be testing its own model of
 * CSS rather than the page.
 *
 * The structural half — that the markup contract the CSS depends on still holds,
 * and that nobody has re-added an unscoped local copy of these rules to a Blade
 * file — is asserted from the templates, because those are the two ways this
 * regresses.
 */
class MlsQuickImportCurrencyPresentationTest extends TestCase
{
    use DatabaseTransactions;

    /** The ID every rule in resources/css/app.css is scoped to. */
    private const SCOPE_ID = 'wizard-form-container';

    /** The canonical currency/percentage prefix class those rules style. */
    private const PREFIX_CLASS = 'input-group-text-seller';

    private const SELLER_TERMS   = 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php';
    private const LANDLORD_TERMS = 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php';
    private const QUICK_IMPORT   = 'resources/views/livewire/offer-listing/quick-import/mls-quick-import.blade.php';

    private User $seller;
    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_media.enabled'                      => false,
            'mls_media.license_acknowledged'         => false,
            'bridge.dataset'                         => 'phpunit_dataset',
            'bridge.token'                           => 'phpunit-token',
        ]);

        $this->seller   = User::factory()->create(['user_type' => 'seller']);
        $this->landlord = User::factory()->create(['user_type' => 'landlord']);
    }

    private function seedRecord(string $mls, string $propertyType): void
    {
        BridgeProperty::create([
            'listing_key'       => $mls . '-KEY',
            'listing_id'        => $mls,
            'standard_status'   => 'Active',
            'mls_status'        => 'Active',
            'property_type'     => $propertyType,
            'list_price'        => 250000,
            'unparsed_address'  => '9 Currency Way',
            'city'              => 'TAMPA',
            'state_or_province' => 'FL',
            'postal_code'       => '33601',
            'raw_json'          => json_encode([
                'ListingKey'      => $mls . '-KEY',
                'ListingId'       => $mls,
                'PropertyType'    => $propertyType,
                'UnparsedAddress' => '9 Currency Way',
            ]),
        ]);
    }

    /** Drive a quick import to the terms step, where the currency fields live. */
    private function toTerms(string $role, string $mls): string
    {
        $this->seedRecord($mls, $role === 'seller' ? 'Residential' : 'Residential Lease');

        $component = Livewire::actingAs($role === 'seller' ? $this->seller : $this->landlord)
            ->test($role === 'seller' ? SellerMlsQuickImport::class : LandlordMlsQuickImport::class)
            ->set('mlsNumber', $mls)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms');

        $this->assertSame('terms', $component->get('step'), 'the terms step must be reachable');

        // `lastRenderedDom` is Livewire 2.x's raw rendered output. There is no
        // ->html() on TestableLivewire in this version; an unknown method is
        // forwarded to the underlying JsonResponse, which is why reaching for one
        // fails with a BadMethodCallException rather than an assertion.
        return (string) $component->lastRenderedDom;
    }

    private function source(string $relative): string
    {
        return file_get_contents(base_path($relative));
    }

    /**
     * A template's MARKUP, with Blade comments removed.
     *
     * The structural assertions below are about what a template emits, not about
     * what its authors wrote in prose. Blade comments are stripped before render
     * and are exactly where a file explains which CSS classes it must NOT declare
     * — so a naive substring search over the raw source finds the explanation and
     * reports it as the violation it warns against.
     */
    private function markup(string $relative): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', $this->source($relative)) ?? '';
    }

    /**
     * Every prefix span in $html that is NOT inside an element carrying the
     * scope ID.
     *
     * Deliberately naive and deliberately strict: it finds the scope element's
     * opening tag and requires every prefix occurrence to appear after it. The
     * quick-import page has exactly one such container and it wraps the whole
     * body, so "after the opening tag" is the right question. A more clever
     * DOM walk would be a second implementation of the thing under test.
     *
     * @return int number of prefixes rendered outside the scope
     */
    private function prefixesOutsideScope(string $html): int
    {
        $scopeAt = strpos($html, 'id="' . self::SCOPE_ID . '"');

        if ($scopeAt === false) {
            return substr_count($html, self::PREFIX_CLASS);
        }

        $outside = 0;
        $offset  = 0;

        while (($at = strpos($html, self::PREFIX_CLASS, $offset)) !== false) {
            if ($at < $scopeAt) {
                $outside++;
            }
            $offset = $at + 1;
        }

        return $outside;
    }

    // ─── A. The page is inside the styling scope ─────────────────────────────

    /** @test */
    public function seller_quick_import_renders_inside_the_wizard_styling_scope(): void
    {
        $html = $this->toTerms('seller', 'QI-CUR-S1');

        $this->assertStringContainsString(
            'id="' . self::SCOPE_ID . '"',
            $html,
            'Quick Import must render inside #' . self::SCOPE_ID . ' or none of the canonical '
            . 'currency, input-sizing or Select2 rules in resources/css/app.css apply to it.'
        );
    }

    /** @test */
    public function landlord_quick_import_renders_inside_the_wizard_styling_scope(): void
    {
        $html = $this->toTerms('landlord', 'QI-CUR-L1');

        $this->assertStringContainsString('id="' . self::SCOPE_ID . '"', $html);
    }

    // ─── B. Every prefix is inside it ────────────────────────────────────────

    /**
     * @test
     *
     * The assertion that would have caught the defect. Being on the page is not
     * enough — every `$` and `%` prefix has to be INSIDE the container, because
     * that ancestor is what makes `.input-cover` a flex row.
     */
    public function every_seller_currency_prefix_renders_inside_the_scope(): void
    {
        $html = $this->toTerms('seller', 'QI-CUR-S2');

        $this->assertGreaterThan(
            0,
            substr_count($html, self::PREFIX_CLASS),
            'the seller terms step must render currency prefixes at all'
        );

        $this->assertSame(
            0,
            $this->prefixesOutsideScope($html),
            'a currency prefix rendered outside #' . self::SCOPE_ID . ' shows its $ stacked '
            . 'above the input instead of beside it.'
        );
    }

    /** @test */
    public function every_landlord_currency_prefix_renders_inside_the_scope(): void
    {
        $html = $this->toTerms('landlord', 'QI-CUR-L2');

        $this->assertGreaterThan(0, substr_count($html, self::PREFIX_CLASS));
        $this->assertSame(0, $this->prefixesOutsideScope($html));
    }

    // ─── C. The markup contract the CSS depends on ──────────────────────────

    /**
     * @test
     *
     * `#wizard-form-container .input-cover { display:flex }` is what puts the
     * prefix beside the input, so a prefix whose parent is not an `.input-cover`
     * is unstyled even inside the container. This is the half of the contract
     * that lives in the partials, and it is asserted from the partials because
     * that is where it would be broken.
     */
    public function every_currency_prefix_sits_inside_an_input_cover(): void
    {
        foreach ([self::SELLER_TERMS, self::LANDLORD_TERMS] as $partial) {
            $markup = $this->markup($partial);
            $offset = 0;
            $seen   = 0;

            while (($at = strpos($markup, self::PREFIX_CLASS, $offset)) !== false) {
                $seen++;
                $offset = $at + 1;

                // The nearest enclosing wrapper. Checked by looking BACKWARDS for
                // the last <div ...> before this span rather than by matching a
                // fixed div-then-span shape: a `$` prefix precedes its input and a
                // `%` suffix follows it, so the span is sometimes the first child
                // of the .input-cover and sometimes the last. Both are correct
                // markup and both need the same flex parent.
                $openedAt = strrpos(substr($markup, 0, $at), '<div');

                $this->assertNotFalse($openedAt, $partial . ': prefix with no enclosing div');

                $wrapper = substr($markup, $openedAt, $at - $openedAt);

                $this->assertStringContainsString(
                    'input-cover',
                    $wrapper,
                    $partial . ': every ' . self::PREFIX_CLASS . ' must sit inside an .input-cover, '
                    . 'or #' . self::SCOPE_ID . " .input-cover{display:flex} has nothing to lay out "
                    . 'and the prefix stacks above the input again.'
                );
            }

            $this->assertGreaterThan(0, $seen, $partial . ': expected currency prefixes');
        }
    }

    // ─── D. Nobody re-added a local copy of the rules ───────────────────────

    /**
     * @test
     *
     * resources/css/app.css says, in as many words, "DO NOT re-add these rules to
     * blade files". Quick Import is the surface most likely to attract a local
     * copy, because a local copy is the shortcut that also makes the `$` line up.
     * It would work, and it would put a third definition of this styling in the
     * codebase — livewire/landlord/landlord-agent-auction-bid.blade.php is
     * already the second.
     */
    public function quick_import_declares_no_currency_css_of_its_own(): void
    {
        $markup = $this->markup(self::QUICK_IMPORT);

        foreach (['.input-cover', '.' . self::PREFIX_CLASS, '.has-icon', '.percentage-value-set'] as $selector) {
            $this->assertStringNotContainsString(
                $selector . ' {',
                $markup,
                'Quick Import must inherit the canonical wizard styling by being inside #'
                . self::SCOPE_ID . ', never by declaring ' . $selector . ' locally.'
            );
        }

        $this->assertStringNotContainsString('<style', $markup);
    }

    /**
     * @test
     *
     * The scope is the SHARED contract, so the fix has to be documented where the
     * rules live. A future reader adding a tenth surface needs the list in
     * app.css to be true.
     */
    public function the_stylesheet_documents_quick_import_as_an_intended_consumer(): void
    {
        $css = $this->source('resources/css/app.css');

        $this->assertStringContainsString(
            'Quick Import',
            $css,
            'app.css enumerates which surfaces its #' . self::SCOPE_ID . ' rules serve; '
            . 'Quick Import must appear there or the next consolidation will drop it again.'
        );
    }

    // ─── E. Presentation parity with manual Create ──────────────────────────

    /**
     * @test
     *
     * Quick Import and manual Create render the SAME partial, so the prefix count
     * on the terms step must match between them for the same role and the same
     * financing selection. A drift here means one surface grew its own copy of
     * the markup — the duplication this whole branch exists to avoid.
     */
    public function quick_import_and_manual_create_share_one_presentation_contract(): void
    {
        $sellerPartial   = $this->markup(self::SELLER_TERMS);
        $landlordPartial = $this->markup(self::LANDLORD_TERMS);

        // The canonical partials are the single source of the markup…
        $this->assertGreaterThan(0, substr_count($sellerPartial, self::PREFIX_CLASS));
        $this->assertGreaterThan(0, substr_count($landlordPartial, self::PREFIX_CLASS));

        // …and Quick Import renders them rather than restating them.
        $quick = $this->markup(self::QUICK_IMPORT);
        $this->assertStringNotContainsString(
            self::PREFIX_CLASS,
            $quick,
            'Quick Import must render the canonical terms partials, never restate their '
            . 'currency markup.'
        );

        // The four wizard surfaces that already worked, and Quick Import, all
        // wrap their body in the same container. Asserted together so "the page
        // that renders a canonical *-terms partial is inside the scope" reads as
        // one rule rather than as five coincidences.
        foreach ([
            'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
            'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
            self::QUICK_IMPORT,
        ] as $surface) {
            $this->assertStringContainsString(
                'id="' . self::SCOPE_ID . '"',
                $this->markup($surface),
                $surface . ' renders a canonical terms partial and must be inside the scope.'
            );
        }
    }

    // ─── F. Nothing about storage changed ───────────────────────────────────

    /**
     * @test
     *
     * The fix is one HTML attribute. If it has moved a stored value, a validation
     * rule or a numeric format, it is not the fix it claims to be.
     */
    public function the_presentation_fix_changes_no_stored_value(): void
    {
        $mls = 'QI-CUR-S3';
        $this->seedRecord($mls, 'Residential');

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', $mls)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms')
            ->set('maximum_budget', '250000')
            ->set('offered_financing', ['Cash'])
            ->call('continueToReview');

        $this->assertSame('review', $component->get('step'));

        $component->call('publish');

        $listing = \App\Models\SellerAgentAuction::find($component->get('listingId'))->fresh();

        // Same key, same digits, no formatting injected by the presentation layer.
        $this->assertSame('250000', (string) $listing->info('maximum_budget'));
        $this->assertSame('maximum_budget', $component->instance()->priceField());
    }
}
