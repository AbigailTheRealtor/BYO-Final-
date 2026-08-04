<?php

namespace Tests\Feature\Storage;

use App\Models\AcceptedBidSummary;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6 — listing document delivery.
 *
 * WHAT EACH HALF PROVES, BECAUSE THEY ARE NOT THE SAME CLAIM.
 * ----------------------------------------------------------
 *   1. The ROUTE is the protection. ListingDocumentController re-checks
 *      ListingDocumentAccessService::canViewDownload() on every request and streams from the
 *      private disk. test_the_route_refuses_* asserts that directly, without going near the page,
 *      so it holds even if every gate in the Blade layer were deleted tomorrow.
 *   2. The RENDER GATE is a courtesy, not a control. It stops the page offering a button the
 *      server will refuse. The absence tests below assert it, and they are worth having, but on
 *      their own they could pass against a page that simply renders nothing to anyone — which is
 *      why the owner/agent positive cases are here too.
 *
 * The distinction is the same one M5.4 learned the hard way: rendered-HTML assertions alone
 * cannot prove a server-side rule, so both halves are asserted separately and neither is allowed
 * to stand in for the other.
 *
 * BOTH ROLES, ON PURPOSE. The partial is shared by the landlord and seller Hire Agent views and
 * by nothing else. Testing one would leave the other free to regress silently, and the whole
 * reason seller is in this milestone's scope is that it carried the same public URL.
 *
 * Buyer and tenant do not include this partial and are not exercised here.
 */
class ListingDocumentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const DOC = 'a0000000-0000-4000-8000-00000000abcd.pdf';

    /** @return array<string, array{0: string}> */
    public static function roles(): array
    {
        return ['landlord' => ['landlord'], 'seller' => ['seller']];
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * @return array{owner: User, agent: User, competitor: User, outsider: User, listing: object}
     */
    private function scenario(string $role, bool $withDocument = true): array
    {
        $owner      = User::factory()->create(['user_type' => $role === 'seller' ? 'seller' : 'landlord']);
        $agent      = User::factory()->create(['user_type' => 'agent']);
        $competitor = User::factory()->create(['user_type' => 'agent']);
        $outsider   = User::factory()->create(['user_type' => 'seller']);

        $class = $role === 'seller' ? SellerAgentAuction::class : LandlordAgentAuction::class;

        $listing = $class::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'M6 document listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        // SellerAgentAuctionController::viewDetail() redirects any record that looks like a Seller
        // Offer Listing, and its fallback treats the presence of an offer-listing meta key as the
        // signal. listing_documents is one of those keys, so a Hire Agent fixture that stores a
        // document must carry the workflow stamp a real Hire Agent listing carries — otherwise the
        // test would be exercising a redirect rather than the page.
        $listing->saveMeta('workflow_type', 'hire_agent');

        if ($withDocument) {
            $listing->saveMeta('listing_documents', self::DOC);
        }

        // The authorized listing agent is the one with an AcceptedBidSummary on this listing —
        // the same signal ListingDocumentAccessService::isAuthorizedAgent() reads. Created
        // through the model rather than asserted about, so this test tracks the real rule.
        AcceptedBidSummary::forceCreate([
            'listing_type'    => $role,
            'listing_id'      => $listing->id,
            'agent_user_id'   => $agent->id,
            // NOT NULL in the schema and irrelevant to isAuthorizedAgent(), which matches on
            // listing_type + listing_id + agent_user_id only. Present to satisfy the column.
            'accepted_bid_id' => 1,
            'tenant_user_id'  => $owner->id,
            'summary_html'    => '<p>summary</p>',
        ]);

        return compact('owner', 'agent', 'competitor', 'outsider', 'listing');
    }

    private function url(string $role, $listing): string
    {
        return route($role === 'seller' ? 'seller.agent.auction.detail' : 'landlord.agent.auction.view', $listing->id);
    }

    private function render(string $role, $listing, ?User $viewer): string
    {
        if ($viewer) {
            $this->actingAs($viewer);
        } else {
            app('auth')->forgetGuards();
        }

        return $this->get($this->url($role, $listing))->assertOk()->getContent();
    }

    private function documentRoute(string $role, $listing): string
    {
        return route('listing.document.show', [
            'listingType' => $role,
            'listingId'   => $listing->id,
            'documentKey' => 'listing_documents',
        ]);
    }

    // ── The control is offered to the two authorized classes ─────────────────

    /** @dataProvider roles */
    public function test_the_owner_is_offered_the_document_link(string $role): void
    {
        $s    = $this->scenario($role);
        $html = $this->render($role, $s['listing'], $s['owner']);

        $this->assertStringContainsString($this->documentRoute($role, $s['listing']), $html);
        $this->assertStringContainsString('Download / View Document', $html);
    }

    /** @dataProvider roles */
    public function test_the_authorized_listing_agent_is_offered_the_document_link(string $role): void
    {
        $s    = $this->scenario($role);
        $html = $this->render($role, $s['listing'], $s['agent']);

        $this->assertStringContainsString($this->documentRoute($role, $s['listing']), $html);
    }

    // ── The control is withheld from everyone else ───────────────────────────

    /**
     * Guest, competing agent and unrelated authenticated user together, because it is one rule.
     * Splitting it into three near-identical methods invites one of them being updated alone.
     *
     * Each asserts BOTH that the route URL is absent and that no storage path leaked, so a future
     * change that swapped one for the other could not pass.
     *
     * @dataProvider roles
     */
    public function test_no_document_control_is_offered_to_an_unauthorized_viewer(string $role): void
    {
        $s = $this->scenario($role);

        $cases = [
            'guest'                   => null,
            'competing agent'         => $s['competitor'],
            'unrelated authenticated' => $s['outsider'],
        ];

        foreach ($cases as $label => $viewer) {
            $html = $this->render($role, $s['listing'], $viewer);

            $this->assertStringNotContainsString(
                $this->documentRoute($role, $s['listing']),
                $html,
                "{$role}: a {$label} must not be offered the document route."
            );
            $this->assertStringNotContainsString(
                'Download / View Document',
                $html,
                "{$role}: a {$label} must not be offered the document control."
            );
            $this->assertStringNotContainsString(
                'storage/auction/documents',
                $html,
                "{$role}: a {$label} must never receive a public storage path for a document."
            );
        }
    }

    // ── Section visibility follows the AUTHORIZED document ───────────────────

    /**
     * The empty-heading defect, which is the M5.5 empty-console defect in a different place: a
     * listing whose only extra content is a document the viewer may not have must render no
     * section at all, rather than a heading with nothing under it.
     *
     * @dataProvider roles
     */
    public function test_a_document_only_section_does_not_render_an_empty_heading(string $role): void
    {
        $s = $this->scenario($role);

        $ownerHtml = $this->render($role, $s['listing'], $s['owner']);
        $this->assertStringContainsString('Photos, Tours &amp; Documents', $ownerHtml, 'Owner: section renders.');

        foreach ([null, $s['competitor'], $s['outsider']] as $viewer) {
            $html = $this->render($role, $s['listing'], $viewer);
            $this->assertStringNotContainsString(
                'Photos, Tours &amp; Documents',
                $html,
                "{$role}: the section must not render when its only content is a withheld document."
            );
        }
    }

    /**
     * ...but the gate is DOCUMENT-scoped, not SECTION-scoped. Photos and tours are unchanged by
     * this milestone and must still render the section for every viewer, including a guest.
     *
     * This is the test that stops the fix from over-reaching into a content change.
     *
     * @dataProvider roles
     */
    public function test_photos_and_tours_still_render_the_section_for_any_viewer(string $role): void
    {
        $s = $this->scenario($role);
        $s['listing']->saveMeta('video_tour_url', 'https://example.com/tour.mp4');

        foreach ([null, $s['competitor'], $s['outsider']] as $viewer) {
            $html = $this->render($role, $s['listing'], $viewer);

            $this->assertStringContainsString(
                'Photos, Tours &amp; Documents',
                $html,
                "{$role}: photos/tours must still render the section."
            );
            $this->assertStringNotContainsString(
                'Download / View Document',
                $html,
                "{$role}: ...but still without the document control."
            );
        }
    }

    // ── The route is the actual protection ───────────────────────────────────

    /**
     * Asserted against the route directly, not the page. This is the half that still holds if
     * every Blade gate were removed, and it is the reason the render gate is allowed to be a
     * Blade condition at all.
     *
     * @dataProvider roles
     */
    public function test_the_route_refuses_an_unauthorized_viewer(string $role): void
    {
        $s   = $this->scenario($role);
        $url = $this->documentRoute($role, $s['listing']);

        $this->actingAs($s['competitor'])->get($url)->assertForbidden();
        $this->actingAs($s['outsider'])->get($url)->assertForbidden();

        // A guest is bounced by the route's auth middleware before authorization is consulted.
        app('auth')->forgetGuards();
        $this->get($url)->assertRedirect(route('login'));
    }

    // ── No public storage URL survives anywhere in the view layer ────────────

    /**
     * Repo-wide, and deliberately independent of BladePublicMediaSeamTest: that suite scans for
     * the asset('storage/…') idiom, while this asserts the specific document directory never
     * appears in any view by any construction.
     */
    public function test_no_public_storage_document_url_remains_in_any_view(): void
    {
        $offenders = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) as $n => $line) {
                if (str_contains($line, 'storage/auction/documents')) {
                    $offenders[] = ltrim(str_replace(base_path(), '', $file->getPathname()), '/') . ':' . ($n + 1);
                }
            }
        }

        $this->assertSame([], $offenders, "Public document storage paths remain:\n" . implode("\n", $offenders));
    }

    /**
     * The partial fails closed when the including view forgets the listing type.
     *
     * Not a hypothetical: the type selects both the model and the document rules, so a partial
     * that guessed would apply one listing type's authorization to another. Rendered through a
     * bare view() call because no real page omits it — which is the point.
     */
    public function test_a_missing_listing_type_renders_no_document_control(): void
    {
        $s = $this->scenario('landlord');

        $this->actingAs($s['owner']);
        $html = view('partials.listing-photos-tours-documents', ['auction' => $s['listing']])->render();

        $this->assertStringNotContainsString('Download / View Document', $html);
        $this->assertStringNotContainsString('storage/auction/documents', $html);
    }
}
