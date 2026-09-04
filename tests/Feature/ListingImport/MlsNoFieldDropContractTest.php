<?php

namespace Tests\Feature\ListingImport;

use App\Services\ListingImport\Mls\MlsFieldCatalog;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Services\ListingImport\MlsPropertyDetailsPresenter;
use Tests\TestCase;

/**
 * THE NO-DROP CONTRACT.
 *
 * This is the test whose absence made the 2026-09-04 payload audit necessary.
 * Bridge sends 553 Property fields, `raw_json` keeps every one of them, and 288
 * populated fields were read by no code at all — not because anyone decided to
 * discard them, but because nothing existed that could notice.
 *
 * The rule this enforces: **every populated field in a real Bridge record must
 * resolve to exactly one written-down disposition.** A field nobody has
 * classified fails the build, by name, with the property type it appeared on.
 * The eleven dispositions are the ones the brief names — an existing BYO field,
 * supplemental MLS details, listing context, contacts, a related resource, a
 * display control, an address component, internal metadata, an explicit
 * display/permission exclusion, a duplicate of something already shown, or a
 * documented unsupported case. There is deliberately no generic bucket.
 *
 * It runs against seven fixtures, one per Stellar property type, each carrying
 * ~200 populated fields. That breadth is the point: a residential-only test
 * would have passed throughout the entire period the commercial vocabulary was
 * being dropped.
 */
class MlsNoFieldDropContractTest extends TestCase
{
    private const TYPES = [
        'residential', 'residential_lease', 'income', 'commercial_sale',
        'commercial_lease', 'business_opportunity', 'vacant_land',
    ];

    /** @return array<string,mixed> */
    private function fixture(string $slug): array
    {
        $path = base_path("tests/fixtures/mls/bridge/{$slug}.json");

        $this->assertFileExists($path, "Missing Bridge fixture for {$slug}");

        $decoded = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($decoded, "Bridge fixture {$slug} is not valid JSON");

        return $decoded;
    }

    /** @return list<string> */
    private function populatedFields(array $record): array
    {
        $out = [];

        foreach ($record as $field => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $out[] = $field;
        }

        return $out;
    }

    public static function propertyTypeProvider(): array
    {
        return array_combine(
            self::TYPES,
            array_map(static fn (string $t) => [$t], self::TYPES),
        );
    }

    /**
     * @test
     * @dataProvider propertyTypeProvider
     */
    public function every_populated_bridge_field_has_a_written_down_disposition(string $slug): void
    {
        $record    = $this->fixture($slug);
        $populated = $this->populatedFields($record);

        $this->assertGreaterThan(
            100,
            count($populated),
            "The {$slug} fixture has thinned out — it is meant to exercise the whole vocabulary of its type"
        );

        $unclassified = [];

        foreach ($populated as $field) {
            if (MlsFieldCatalog::classify($field) === MlsFieldCatalog::D_UNKNOWN) {
                $unclassified[] = $field;
            }
        }

        $this->assertSame(
            [],
            $unclassified,
            "Bridge is sending populated fields nothing accounts for on the {$slug} fixture. "
            . "Add each to MlsFieldCatalog under the disposition it deserves — a BYO mapping, "
            . "PROPERTY_FACTS, LISTING_CONTEXT, CONTACTS, RELATED_RESOURCE, DISPLAY_CONTROL, "
            . "ADDRESS_COMPONENT, INTERNAL, RESTRICTED, DERIVED or UNSUPPORTED — rather than "
            . "letting it fall out of the import unnoticed: " . implode(', ', $unclassified)
        );
    }

    /**
     * @test
     *
     * Every disposition that withholds a field has to say why, in words. A
     * blank reason is a field somebody removed from display without recording
     * the decision, which is how "we already have the data" becomes an
     * unanswerable question six months later.
     */
    public function every_withheld_field_carries_a_reason(): void
    {
        $groups = [
            'RESTRICTED'  => MlsFieldCatalog::RESTRICTED,
            'INTERNAL'    => MlsFieldCatalog::INTERNAL,
            'DERIVED'     => MlsFieldCatalog::DERIVED,
            'UNSUPPORTED' => MlsFieldCatalog::UNSUPPORTED,
        ];

        foreach ($groups as $name => $fields) {
            foreach ($fields as $field => $reason) {
                $this->assertIsString($reason, "{$name}[{$field}] must carry a reason");
                $this->assertNotSame('', trim($reason), "{$name}[{$field}] has an empty reason");

                // A DERIVED entry may name a field that is ALSO Tier 1 — several
                // do, and the entry is what records why the supplemental layer
                // does not repeat it. Such a field is not withheld at all, so it
                // correctly has no withheld reason; the classification is what
                // must be right.
                if (MlsFieldCatalog::classify($field) === MlsFieldCatalog::D_TIER1) {
                    $this->assertSame(
                        'DERIVED',
                        $name,
                        "{$name}[{$field}] is Tier 1 — only DERIVED may also document a Tier-1 field"
                    );

                    continue;
                }

                $this->assertNotNull(
                    MlsFieldCatalog::withheldReason($field),
                    "{$name}[{$field}] must resolve through withheldReason()"
                );
            }
        }
    }

    /**
     * @test
     *
     * A field cannot be both displayed and withheld. The two constants are read
     * by different layers, so an overlap would mean one layer publishing what
     * the other believes it suppressed.
     */
    public function no_field_is_both_displayed_and_restricted(): void
    {
        $displayed = [];

        foreach ([MlsFieldCatalog::PROPERTY_FACTS, MlsFieldCatalog::LISTING_CONTEXT, MlsFieldCatalog::CONTACTS] as $groups) {
            foreach ($groups as $fields) {
                $displayed = array_merge($displayed, array_keys($fields));
            }
        }

        $overlap = array_values(array_intersect($displayed, array_keys(MlsFieldCatalog::RESTRICTED)));

        $this->assertSame([], $overlap, 'A restricted field is also in a display allow-list');

        $internalOverlap = array_values(array_intersect($displayed, array_keys(MlsFieldCatalog::INTERNAL)));

        $this->assertSame([], $internalOverlap, 'An internal field is also in a display allow-list');
    }

    /**
     * @test
     * @dataProvider propertyTypeProvider
     *
     * The whole point of the restricted list is that it holds under real data,
     * not under a fixture written to make it pass. Every restricted field in
     * every fixture must be absent from the built payload's rendered values.
     */
    public function no_restricted_value_reaches_the_persisted_payload(string $slug): void
    {
        $record = $this->fixture($slug);
        $blob   = json_encode(MlsSupplementalDetails::fromRecord($record, 'seller')->toArray());

        foreach (array_keys(MlsFieldCatalog::RESTRICTED) as $field) {
            $value = $record[$field] ?? null;

            if (! is_string($value) || mb_strlen(trim($value)) < 12) {
                // Short and non-string values (a boolean, a two-digit code) are
                // not searchable without false positives — "No" appears
                // everywhere. The structural guard below covers those.
                continue;
            }

            $this->assertStringNotContainsString(
                mb_substr(trim($value), 0, 24),
                (string) $blob,
                "Restricted field {$field} leaked into the persisted MLS payload for {$slug}"
            );
        }

        // Structural check, which does not depend on value length: no restricted
        // field NAME may appear as a row key.
        foreach (MlsSupplementalDetails::fromRecord($record, 'seller')->sections as $section) {
            foreach ($section['rows'] as $row) {
                $this->assertArrayNotHasKey(
                    $row['key'],
                    MlsFieldCatalog::RESTRICTED,
                    "Restricted field {$row['key']} was rendered as a row on {$slug}"
                );
            }
        }
    }

    /**
     * @test
     *
     * The presenter's own exclusion list and the catalog must not drift apart:
     * the presenter is what a Blade file talks to, and the catalog is what the
     * contract test talks to.
     */
    public function the_presenter_excludes_everything_the_catalog_restricts(): void
    {
        foreach (array_keys(MlsFieldCatalog::RESTRICTED) as $field) {
            $this->assertArrayHasKey(
                $field,
                MlsPropertyDetailsPresenter::EXCLUDED,
                "Catalog restricts {$field} but the presenter does not list it as excluded"
            );
        }
    }
}
