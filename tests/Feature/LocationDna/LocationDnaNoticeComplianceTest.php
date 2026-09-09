<?php

namespace Tests\Feature\LocationDna;

use App\Support\LocationDna\LocationDataAttribution;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The Apache-2.0 obligations attached to the Foursquare slice of the Overture Places
 * theme, checked as artifacts rather than as intentions.
 *
 * The upstream NOTICE states the three duties in its own words: provide recipients
 * with a copy of the License, include prominent notices of changes to the Data, and
 * preserve attribution to Foursquare including the full content of that NOTICE. Each
 * has a file and a user-reachable surface, and each is asserted here.
 *
 * WHY THERE IS NO WHOLE-FILE HASH. Foursquare publishes the NOTICE as a web page, not
 * as a downloadable artifact, and this repository has no third-party artifact-integrity
 * mechanism to hang a checksum off. A hash would therefore pin our own HTML-to-text
 * extraction rather than an upstream digest, and would fail on a trailing newline while
 * telling nobody anything about compliance. These assert the substantive markers instead.
 */
class LocationDnaNoticeComplianceTest extends TestCase
{
    use DatabaseTransactions;

    private function overture(): array
    {
        $source = LocationDataAttribution::source('overture_places');

        $this->assertNotNull($source, 'The overture_places source is no longer declared.');

        return $source;
    }

    private function noticeText(): string
    {
        return (string) file_get_contents(base_path($this->overture()['notice_path']));
    }

    // ── The upstream NOTICE ─────────────────────────────────────────────────

    /** @test */
    public function the_upstream_notice_is_present_and_is_not_a_placeholder(): void
    {
        $notice = $this->noticeText();

        $this->assertNotSame('', trim($notice));
        $this->assertStringNotContainsString('PLACEHOLDER', $notice);
        $this->assertStringNotContainsString('TO DISCHARGE THIS OBLIGATION', $notice);
    }

    /** @test */
    public function the_upstream_notice_carries_its_authoritative_markers(): void
    {
        $notice = $this->noticeText();

        // Substantive phrases from the published notice — the three duties it states,
        // and the licence it states them under. If a future edit drops one of these,
        // the file is no longer the notice we are required to preserve.
        foreach ([
            'Foursquare OS Places',
            'Apache License, Version 2.0',
            'provide recipients with a copy of the License',
            'include prominent notices to the extent',
            'preserve attribution to Foursquare',
            'full content of this NOTICE.txt file',
            'http://www.apache.org/licenses/LICENSE-2.0',
        ] as $marker) {
            $this->assertStringContainsString($marker, $notice, "The NOTICE no longer contains: {$marker}");
        }
    }

    /** @test */
    public function the_foursquare_copyright_attribution_is_preserved(): void
    {
        // The single element the NOTICE names as non-negotiable.
        $this->assertMatchesRegularExpression(
            '/Foursquare Labs, Inc\. All rights reserved\./',
            $this->noticeText(),
            'The Foursquare copyright line is missing from the committed NOTICE.'
        );
    }

    /** @test */
    public function the_notice_records_where_it_came_from(): void
    {
        // So a later reader can re-verify the bytes against the source instead of
        // trusting this repository.
        $source = $this->overture();

        $this->assertSame('https://opensource.foursquare.com/places-notice-txt/', $source['notice_source_url']);
        $this->assertNotEmpty($source['notice_retrieved']);
    }

    // ── The licence copy ────────────────────────────────────────────────────

    /** @test */
    public function the_apache_licence_text_is_committed_in_full(): void
    {
        $licence = (string) file_get_contents(base_path($this->overture()['license_path']));

        $this->assertStringContainsString('Apache License', $licence);
        $this->assertStringContainsString('Version 2.0, January 2004', $licence);
        $this->assertStringContainsString('END OF TERMS AND CONDITIONS', $licence);
        // §4(d) is the clause that makes the NOTICE travel; a truncated copy that
        // dropped it would still "contain the Apache License".
        $this->assertStringContainsString('Redistribution', $licence);
    }

    /** @test */
    public function a_copy_of_the_licence_is_served_to_recipients(): void
    {
        // "Provide recipients with a copy of the License" — served from our own bytes,
        // not delegated to apache.org staying online.
        $response = $this->get(route('data-sources.license'));

        $response->assertOk();
        $response->assertSee('END OF TERMS AND CONDITIONS', false);
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
    }

    /** @test */
    public function the_application_licence_was_not_overwritten(): void
    {
        // The Apache text is third-party material and belongs beside the other
        // third-party artifacts. Dropping it at the repository root as LICENSE would
        // relicense this application.
        $this->assertStringStartsWith('resources/legal/', $this->overture()['license_path']);
    }

    // ── The change notice, kept separate ────────────────────────────────────

    /** @test */
    public function our_modification_notice_exists_and_discloses_the_actual_changes(): void
    {
        $mods = (string) file_get_contents(base_path($this->overture()['modifications_path']));

        foreach ([
            'overture-2026-06-17.0-fl',   // the corpus version, from config
            '2026-06-17.0',               // the Overture release pin
            '0.90',                       // the confidence floor
            'regions.florida',            // the geographic filter's SSOT
            'sources[].dataset',          // the discarded per-source attribution
        ] as $marker) {
            $this->assertStringContainsString($marker, $mods, "The change notice does not disclose: {$marker}");
        }
    }

    /** @test */
    public function our_words_are_never_mixed_into_foursquares_notice(): void
    {
        // The separation is the safeguard: a merged file leaves a reader unable to
        // tell which sentences Foursquare wrote, and preserving the NOTICE *as theirs*
        // is the obligation.
        $notice = $this->noticeText();
        $mods   = (string) file_get_contents(base_path($this->overture()['modifications_path']));

        $this->assertNotSame(
            base_path($this->overture()['notice_path']),
            base_path($this->overture()['modifications_path']),
            'The change notice and the upstream NOTICE are the same file.'
        );

        // Nothing application-specific may appear inside the upstream text.
        foreach (['BidYourOffer', 'corpus_version', 'pgsql_spatial', 'APPLICATION-AUTHORED'] as $ours) {
            $this->assertStringNotContainsString($ours, $notice, "Application text leaked into the upstream NOTICE: {$ours}");
        }

        // And our file must say plainly that it is ours.
        $this->assertStringContainsString('APPLICATION-AUTHORED', $mods);
    }

    // ── Licence facts ───────────────────────────────────────────────────────

    /** @test */
    public function places_is_never_labelled_odbl_anywhere_a_user_or_a_row_can_see(): void
    {
        // The most likely wrong answer, because four of Overture's six themes ARE ODbL.
        $licenceIds = array_column($this->overture()['licenses'], 'id');

        $this->assertNotContains('odbl', $licenceIds);

        // The provider registry's own licence token, which is what reaches a persisted
        // row's provenance_json.
        $registryLicence = (string) config('location_providers.providers.overture_corpus.license');

        $this->assertStringNotContainsString('odbl', $registryLicence);
        $this->assertStringContainsString('cdla-permissive-2.0', $registryLicence);
        $this->assertStringContainsString('apache-2.0', $registryLicence);
        $this->assertStringContainsString('cc0-1.0', $registryLicence);

        // And the public page. Asserted as "no ODbL is LISTED as a licence for Places",
        // not as "the string never appears" — the notice deliberately explains that the
        // theme is NOT ODbL, and a blunt string ban would forbid saying so.
        $body = $this->get(route('data-sources'))->getContent();

        foreach ($this->overture()['licenses'] as $licence) {
            $this->assertStringContainsString($licence['name'], $body);
        }

        $this->assertStringNotContainsString('Open Database License', $body);
        $this->assertDoesNotMatchRegularExpression(
            '/Licenses?:.{0,400}ODbL/is',
            $body,
            'ODbL appears inside a rendered licence list.'
        );
    }

    /** @test */
    public function the_three_places_licences_are_all_declared(): void
    {
        $ids = array_column($this->overture()['licenses'], 'id');

        sort($ids);

        $this->assertSame(['apache-2.0', 'cc0-1.0', 'cdla-permissive-2.0'], $ids);
    }

    // ── The public surface ──────────────────────────────────────────────────

    /** @test */
    public function the_data_sources_page_publishes_both_notices_and_labels_whose_is_whose(): void
    {
        $response = $this->get(route('data-sources'));

        $response->assertOk();

        // Foursquare's words, labelled as theirs.
        $response->assertSee('preserve attribution to Foursquare', false);
        $this->assertMatchesRegularExpression(
            '/theirs, not ours/i',
            $response->getContent(),
            'The upstream notice is published without being labelled as Foursquare\'s.'
        );

        // Ours, labelled as ours.
        $this->assertMatchesRegularExpression(
            '/Written by us, not by Foursquare/i',
            $response->getContent()
        );

        // And the licence copy is reachable from it.
        $response->assertSee(route('data-sources.license'), false);
    }

    /** @test */
    public function the_data_sources_page_carries_the_required_foursquare_credit(): void
    {
        $this->get(route('data-sources'))->assertSee('Foursquare', false);
    }

    /** @test */
    public function the_data_sources_page_leaks_no_credentials_or_internal_infrastructure(): void
    {
        // A licensing page assembled from config is exactly the shape of thing that
        // ends up rendering a config dump. Nothing about our database, our cluster,
        // our dataset ids or any credential belongs on a public page.
        $body = $this->get(route('data-sources'))->getContent();

        foreach ([
            'pgsql_spatial',           // the spatial cluster connection name
            'places_p_overture',       // internal partition naming
            'SPATIAL_DATABASE_URL',
            'DATABASE_URL',
            'BRIDGE_SERVER_TOKEN',
            'BRIDGE_DATASET',
            'GOOGLE_PLACES_API_KEY',
            'OPENAI_API_KEY',
            'APP_KEY',
            'OVERTURE_CORPUS_POI_ENABLED',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $body, "The data-sources page rendered '{$secret}'.");
        }
    }
}
