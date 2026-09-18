<?php

namespace Tests\Feature\ListingPreferences;

use PHPUnit\Framework\TestCase;

/**
 * Structural facts about the four Phase 3A card surfaces that a rendered
 * assertion cannot see.
 *
 * Three of these templates need a controller's worth of view data to render at
 * all, and the fourth is a 350-line component. What matters here is not what
 * they output for one fixture but how they are WIRED — which component, primed
 * or not, and (for landlord) whether an interactive control ended up inside an
 * anchor. Those are properties of the source, so the source is what is read.
 *
 * Plain PHPUnit, no application: nothing here needs one.
 */
class ListingPreferenceCardMarkupTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 3);
    }

    /**
     * Every Phase 3A surface renders the SHARED control in its compact form.
     * None of them grew a preference implementation of its own.
     *
     * @test
     */
    public function every_card_surface_renders_the_shared_compact_control(): void
    {
        foreach ($this->surfaces() as $label => [$path, $type]) {
            $source = $this->read($path);

            $this->assertStringContainsString(
                '<x-listing-preference.control',
                $source,
                "{$label} must render the shared control"
            );

            $this->assertStringContainsString(
                "listing-type=\"{$type}\"",
                $source,
                "{$label} must address its listings as {$type}"
            );

            $this->assertStringContainsString(
                ':compact="true"',
                $source,
                "{$label} must use the compact layout of the shared control"
            );
        }
    }

    /**
     * Every card surface primes its page first. Without it each control
     * resolves itself and the page is back to a query pair per card.
     *
     * @test
     */
    public function every_card_page_primes_its_listings_before_the_loop(): void
    {
        $pages = [
            'seller search'   => ['resources/views/offer-listing/seller/search.blade.php', 'seller_agent'],
            'landlord search' => ['resources/views/offer-listing/landlord/search.blade.php', 'landlord_agent'],
            'stellar results' => ['resources/views/stellar/buyer/results.blade.php', 'bridge'],
        ];

        foreach ($pages as $label => [$path, $type]) {
            $source = $this->read($path);

            $this->assertStringContainsString(
                '<x-listing-preference.prefetch',
                $source,
                "{$label} must prime its listings in batch"
            );

            $this->assertStringContainsString(
                "listing-type=\"{$type}\"",
                $source,
                "{$label} must prime the listing type it renders"
            );

            // The prime must come BEFORE the loop it exists to serve; priming
            // afterwards resolves nothing in time and is silently useless.
            $this->assertLessThan(
                strpos($source, '<x-listing-preference.control') ?: PHP_INT_MAX,
                strpos($source, '<x-listing-preference.prefetch'),
                "{$label} must prime before it renders cards"
            );
        }
    }

    /**
     * THE LANDLORD ANCHOR. A button nested inside a link follows the link when
     * pressed, and reads to assistive technology as one confused target. The
     * fix is markup, not `preventDefault()` — which would mask the click and
     * repair neither the HTML nor the keyboard behaviour.
     *
     * @test
     */
    public function the_landlord_card_control_is_not_nested_inside_the_card_link(): void
    {
        $source = $this->read('resources/views/offer-listing/landlord/search.blade.php');

        $anchorOpen  = strpos($source, "<a href=\"{{ route('offer.listing.landlord.view'");
        $anchorClose = strpos($source, '</a>', $anchorOpen ?: 0);
        $control     = strpos($source, '<x-listing-preference.control');

        $this->assertNotFalse($anchorOpen, 'the card link must still exist');
        $this->assertNotFalse($anchorClose);
        $this->assertNotFalse($control);

        $this->assertGreaterThan(
            $anchorClose,
            $control,
            'the preference control must sit AFTER the card link closes, never inside it'
        );

        // Navigation, and its affordance, are preserved.
        $this->assertStringContainsString("route('offer.listing.landlord.view', \$auction->id)", $source);
        $this->assertStringContainsString('cursor:pointer', $source);

        // The anchor is balanced: one card link, opened and closed once.
        $this->assertSame(
            1,
            substr_count($source, "<a href=\"{{ route('offer.listing.landlord.view'"),
            'exactly one card link per card keeps the tab order as it was'
        );
    }

    /**
     * The fix must not have been "remove the link". A card whose body no longer
     * navigates is a worse regression than the one being fixed.
     *
     * @test
     */
    public function the_landlord_card_body_still_navigates(): void
    {
        $source = $this->read('resources/views/offer-listing/landlord/search.blade.php');

        $anchorOpen = strpos($source, "<a href=\"{{ route('offer.listing.landlord.view'");
        $bodyOpen   = strpos($source, '<div class="card-body pb-2 pt-2">');

        $this->assertNotFalse($anchorOpen);
        $this->assertNotFalse($bodyOpen);

        $this->assertLessThan(
            $bodyOpen,
            $anchorOpen,
            'the card body must still be inside the link'
        );
    }

    /**
     * The dead Save affordances on these four surfaces are gone — replaced,
     * not merely joined by a working one. Two Saves on one card is worse than
     * the fake one alone.
     *
     * @test
     */
    public function the_dead_save_affordances_are_gone_from_the_wired_surfaces(): void
    {
        foreach ($this->surfaces() as $label => [$path, $_]) {
            $source = $this->read($path);

            // The POPOVER ATTRIBUTE, not the phrase: the replacement leaves a
            // comment naming what stood there, and a comment explaining a
            // removal must not read as the thing still being present.
            $this->assertStringNotContainsString(
                'data-bs-content="Add Favorites"',
                $source,
                "{$label} must no longer advertise a favourites feature that does not exist"
            );
        }

        $this->assertStringNotContainsString(
            'title="Save feature coming soon"',
            $this->read('resources/views/components/stellar/buyer-result-card.blade.php'),
            'the Stellar card must no longer offer a disabled Save'
        );
    }

    /**
     * ONE COMPONENT. Phase 3A must not have produced a card-specific copy.
     *
     * @test
     */
    public function there_is_exactly_one_preference_control_component(): void
    {
        $dir = $this->root . '/resources/views/components/listing-preference';

        $files = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
        sort($files);

        $this->assertSame(
            // `assets` is the control's own stylesheet and delegated behaviour,
            // extracted in Phase 3B so a page that reveals controls after load
            // can emit it first. It renders no control markup.
            ['assets.blade.php', 'control.blade.php', 'prefetch.blade.php'],
            $files,
            'the control, its assets and its prefetch helper are the only components; a card variant is a prop, not a file'
        );
    }

    /** @return array<string, array{0:string,1:string}> */
    private function surfaces(): array
    {
        return [
            'seller search cards'   => ['resources/views/offer-listing/seller/search.blade.php', 'seller_agent'],
            'landlord search cards' => ['resources/views/offer-listing/landlord/search.blade.php', 'landlord_agent'],
            'stellar result cards'  => ['resources/views/components/stellar/buyer-result-card.blade.php', 'bridge'],
        ];
    }

    private function read(string $relative): string
    {
        $path = $this->root . '/' . $relative;

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
