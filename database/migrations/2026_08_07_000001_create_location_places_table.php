<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-3 — the commercial place layer that sits ON TOP of the Census corpus.
 *
 * WHY A SECOND TABLE RATHER THAN ROWS IN `census_places`
 * ------------------------------------------------------
 * The Phase 1d-1 audit proved the Census corpus is complete and correct AS PUBLISHED GEOGRAPHY:
 * 57 states, 3,235 county equivalents matching published counts exactly, 32,188 places, 33,791
 * ZCTAs, zero orphans. It also found the one thing government geography structurally cannot
 * supply — Clearwater Beach. That is a barrier-island neighbourhood inside the City of
 * Clearwater, and the Census publishes neither an incorporated place nor a CDP for it, because
 * it is not a unit of government. It is, however, one of the most searched real-estate
 * locations in Pinellas County.
 *
 * The gap is not an import defect and it is not fixable by re-importing. Nationwide, 6,911 of
 * the 7,088 legacy `us_cities` names that fail to match the corpus are exactly this class of
 * value: USPS/community place names for somewhere real that has no government of its own.
 *
 * So the corpus is left ALONE. Writing supplemental rows into `census_places` would destroy the
 * property that makes it trustworthy — that every row in it is published data whose counts can
 * be re-verified against the Census Bureau by `census:verify-geography`. This table carries the
 * additions instead, and `source` records where each row came from.
 *
 * `state_geoid` / `county_geoid`, NOT `state_id` / `county_id`
 * ------------------------------------------------------------
 * These hold Census GEOIDs — `12`, `12103` — which are zero-padded fixed-width STRINGS. Naming
 * them `*_id` invites exactly the coercion {@see App\Services\LocationDna\Criteria\CensusCriteriaGeographyRepository}
 * exists to refuse: `(int) '12103'` is a plausible-looking `12103`, but `(int) '01001'` is
 * `1001` — a real, different county. The name states the type so a caller cannot reach for the
 * wrong one by habit. `parent_place_id` IS a surrogate key and keeps the `_id` name.
 *
 * `name_key` IS THE MATCH SURFACE
 * -------------------------------
 * Stored, indexed, and written by the same normalisation the hydrator uses — lowercased,
 * whitespace collapsed, leading `Saint` folded to `St`. Resolution is then an indexed equality
 * rather than a scan over 39,000 rows with PHP normalisation applied per row. It is a derived
 * column and is rebuilt with the row; nothing outside the builder writes it.
 *
 * THREE SOURCES, ALL OF THEM REGENERABLE
 * --------------------------------------
 *   census        — projected from `census_places`, one row per published place.
 *   supplemental  — derived from the legacy USPS corpus where it names somewhere the Census
 *                   does not: ~6,900 unincorporated communities and postal localities.
 *   curated       — neighbourhoods, and the parent links that make a neighbourhood belong to a
 *                   city. Read from `config/location_places.php`.
 *
 * NOTHING IS HAND-INSERTED INTO THIS TABLE. Curated data is the only part a human authors, and
 * it is authored in a version-controlled config file rather than here, so `location:build-places`
 * can drop and rebuild the whole table in any environment and get the same result. A curated
 * mistake is fixed by editing a file and re-running — never by writing SQL against production.
 *
 * The corollary matters as much: an admin surface that let users write rows directly would break
 * this property, and would need a fourth source value and an explicit exclusion from the purge
 * before it could be added.
 */
class CreateLocationPlacesTable extends Migration
{
    public function up()
    {
        Schema::create('location_places', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('name_key', 150);            // normalised comparison form
            $table->string('type', 16);                 // city|town|village|borough|cdp|neighborhood|community

            $table->char('state_geoid', 2);
            $table->char('county_geoid', 5)->nullable();

            // A neighbourhood's city. Nullable because most rows ARE the city — and because a
            // community with no containing municipality is the normal unincorporated case, not
            // an error. Restricted on delete: silently orphaning a neighbourhood would leave a
            // selectable place whose parent no longer names anything.
            $table->unsignedBigInteger('parent_place_id')->nullable();

            // Link back to the foundation for `source = census` rows. Unique, so a rebuild can
            // never project the same published place twice.
            $table->char('census_place_geoid', 7)->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();

            $table->string('source', 16);               // census|supplemental|curated
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->foreign('parent_place_id')
                ->references('id')->on('location_places')
                ->restrictOnDelete();

            $table->unique('census_place_geoid', 'location_places_census_geoid_unique');

            // The cascade's question: "what can be selected under these counties?"
            $table->index(['county_geoid', 'active'], 'location_places_county_active_index');
            // The resolver's question: "what is this label, in this state?"
            $table->index(['state_geoid', 'name_key'], 'location_places_state_key_index');
            $table->index(['state_geoid', 'type'], 'location_places_state_type_index');
            $table->index('parent_place_id', 'location_places_parent_index');
            $table->index('source', 'location_places_source_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('location_places');
    }
}
