<?php

namespace Tests\Feature\Storage;

use App\Models\AcceptedBidSummary;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6 — listing document delivery. AMENDED IN M7.3, AND THE AMENDMENT IS THE POINT OF THIS NOTE.
 *
 * WHAT M6 ASSERTED, AND WHY HALF OF IT NO LONGER HOLDS.
 * ----------------------------------------------------
 * M6 hardened the delivery of listing documents and proved it on the two pages that rendered the
 * control: the landlord and seller HIRE AGENT detail views. Its positive cases asserted that an
 * owner and an authorized listing agent were OFFERED the document link on those pages.
 *
 * M7.3 established, and product confirmed, that Photos/Tours/Documents is not part of the Hire
 * Agent workflow at all. The four fields the section reads are written only by the Offer Listing
 * components; no Hire Agent questionnaire in any role captures one. The section was reachable from
 * a Hire Agent page only because both workflows share one table. So the render site is gone, and
 * the assertions that a Hire Agent page OFFERS a document have been inverted rather than deleted —
 * they now assert the section is absent for every viewer, which is the behaviour that replaced it.
 *
 * WHAT DID NOT CHANGE, AND MUST NOT.
 * ----------------------------------
 *   1. The ROUTE is the protection. ListingDocumentController re-checks
 *      ListingDocumentAccessService::canViewDownload() on every request and streams from the
 *      private disk. test_the_route_refuses_* asserts that directly, without going near a page, so
 *      it holds however the Blade layer changes — including this change.
 *   2. The route still SERVES an authorized viewer. M6 got that positive coverage incidentally,
 *      from the page tests now inverted, so removing the render site would have quietly left
 *      `listing_documents` with refusal coverage and no proof it ever delivers anything. A direct
 *      route test now carries that claim explicitly. This is the one addition M7.3 makes here, and
 *      it exists to keep the infrastructure's coverage whole while its Hire Agent render goes away.
 *
 * The M6 distinction still stands: rendered-HTML assertions cannot prove a server-side rule, so
 * page-level and route-level claims are asserted separately and neither stands in for the other.
 * What changed is only what the page-level half claims.
 *
 * BOTH ROLES, ON PURPOSE. The partial was shared by the landlord and seller Hire Agent views and
 * by nothing else, so both are exercised here. Testing one would leave the other free to regress —
 * or, now, free to have the include quietly restored.
 *
 * Buyer and tenant never included this partial and are not exercised here.
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

    // ── M7.3: the section is absent from a Hire Agent page for EVERY viewer ──

    /**
     * The inversion of M6's two positive cases, and the reason they are inverted here rather than
     * deleted: "the owner is offered the document link" was a true and deliberately asserted fact
     * about this page, so the file should record that it stopped being true and when.
     *
     * THE TWO MOST AUTHORIZED VIEWERS ARE THE ONES ASSERTED. The owner and the authorized listing
     * agent are exactly who M6 proved COULD see the control, and who ListingDocumentAccessService
     * still says may download the file. If the include came back, these two are the viewers it
     * would come back for — a test that only checked a guest would pass against a fully restored
     * section.
     *
     * The fixture stores a real document, so this cannot pass merely because there is nothing to
     * show. Note that authorization is not what makes the section absent now: the section is gone
     * for everyone, and the route below still decides who may have the file.
     *
     * @dataProvider roles
     */
    public function test_the_hire_agent_page_offers_no_document_to_its_most_authorized_viewers(string $role): void
    {
        $s = $this->scenario($role);

        foreach (['owner' => $s['owner'], 'authorized listing agent' => $s['agent']] as $label => $viewer) {
            $html = $this->render($role, $s['listing'], $viewer);

            $this->assertStringNotContainsString(
                $this->documentRoute($role, $s['listing']),
                $html,
                "{$role}: a Hire Agent page must not offer the document route to the {$label}."
            );
            $this->assertStringNotContainsString(
                'Download / View Document',
                $html,
                "{$role}: a Hire Agent page must not offer the document control to the {$label}."
            );
        }
    }

    /**
     * The structural half, and the one that actually stops a regression.
     *
     * Every assertion above is about rendered output, and rendered output can be absent for
     * reasons that have nothing to do with the decision made here — an empty fixture, a guard
     * further up, a route that redirected. This asserts the cause rather than the symptom: no Hire
     * Agent view includes the partial. It fails immediately and legibly if the include is restored
     * to either view, or added to buyer or tenant, whether or not any fixture happens to carry
     * data that would make it visible.
     *
     * The partial itself is NOT asserted absent. It is retained deliberately — this change removed
     * two call sites, not a file, and nothing about the document infrastructure was touched.
     */
    public function test_no_hire_agent_view_includes_the_photos_tours_documents_partial(): void
    {
        $offenders = [];

        foreach (glob(base_path('resources/views/hire_*_agent/*.blade.php')) as $path) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) as $n => $line) {
                // The @include directive specifically — a comment naming the partial (both views
                // carry one explaining the removal) must not register as a call site.
                if (preg_match('/@include\s*\(\s*[\'"]partials\.listing-photos-tours-documents/', $line)) {
                    $offenders[] = ltrim(str_replace(base_path(), '', $path), '/') . ':' . ($n + 1);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Photos/Tours/Documents is not part of the Hire Agent workflow and must not be rendered "
            . "by a Hire Agent view. Restored in:\n" . implode("\n", $offenders)
        );
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

    // ── M7.3: the SECTION is absent, not merely its document control ─────────

    /**
     * M6 asserted the opposite of this, and for a good reason at the time: its gate was
     * DOCUMENT-scoped, not SECTION-scoped, so photos and tours had to keep rendering the section
     * for every viewer even when the document inside it was withheld. That test existed to stop
     * the M6 fix over-reaching into a content change.
     *
     * M7.3 IS the content change, made deliberately and with the product decision behind it, so
     * the assertion flips. The fixture plants BOTH an Offer-Listing-written tour URL and a
     * document — the two things that could each independently open the section — and requires the
     * heading to be absent regardless.
     *
     * Planting `video_tour_url` on a Hire Agent listing is not something the application can do;
     * it is written here directly, which is the point. The two workflows share a table, so such a
     * row is constructible, and twenty untagged legacy rows really do carry these keys. The
     * section must stay absent even then.
     *
     * @dataProvider roles
     */
    public function test_the_section_is_absent_even_when_offer_listing_data_is_present(string $role): void
    {
        $s = $this->scenario($role);
        $s['listing']->saveMeta('video_tour_url', 'https://example.com/tour.mp4');
        $s['listing']->saveMeta('property_photos', '["a0000000-0000-4000-8000-00000000abcd.jpg"]');

        $viewers = [
            'owner'                   => $s['owner'],
            'authorized listing agent' => $s['agent'],
            'competing agent'         => $s['competitor'],
            'unrelated authenticated' => $s['outsider'],
            'guest'                   => null,
        ];

        foreach ($viewers as $label => $viewer) {
            $html = $this->render($role, $s['listing'], $viewer);

            $this->assertStringNotContainsString(
                'Photos, Tours &amp; Documents',
                $html,
                "{$role}: the section must not render for a {$label}, even with photos and a tour present."
            );
            $this->assertStringNotContainsString(
                'Download / View Document',
                $html,
                "{$role}: the document control must not render for a {$label}."
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

    /**
     * M7.3 — THE POSITIVE HALF, WHICH USED TO BE INCIDENTAL AND IS NOW EXPLICIT.
     *
     * M6 never asserted that the `listing_documents` route DELIVERS. It got that assurance for
     * free from the page tests: if the owner saw the link, the key was wired end to end. Those
     * tests are inverted above, so without this the key would keep its refusal coverage and lose
     * every proof that it ever serves a file — the classic shape of a permission check that has
     * quietly started refusing everyone.
     *
     * Asserted against the route directly, with the file actually on the private disk, so it
     * proves delivery rather than merely a 200. Nothing here touches a Hire Agent page: this is
     * the infrastructure the removal above deliberately left alone, and the coverage exists to
     * show that leaving it alone is what happened.
     *
     * `seller_disclosure_file` already has equivalent coverage in PrivateDocumentDualReadTest;
     * `listing_documents` is the key that had none of its own.
     *
     * @dataProvider roles
     */
    public function test_the_route_still_serves_the_document_to_an_authorized_viewer(string $role): void
    {
        \Illuminate\Support\Facades\Storage::fake('private');
        \Illuminate\Support\Facades\Storage::disk('private')
            ->put('auction/documents/' . self::DOC, '%PDF-1.4 fake');

        $s   = $this->scenario($role);
        $url = $this->documentRoute($role, $s['listing']);

        $this->actingAs($s['owner'])->get($url)->assertOk();
        $this->actingAs($s['agent'])->get($url)->assertOk();
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
