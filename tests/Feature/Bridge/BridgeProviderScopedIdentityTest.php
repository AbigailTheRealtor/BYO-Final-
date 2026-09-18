<?php

namespace Tests\Feature\Bridge;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Support\Listing\MlsProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P0-2 — native MLS identity is `(provider, listing_key)`.
 *
 * THE DEFECT THIS FILE EXISTS TO PREVENT
 * --------------------------------------
 * Provider B sends `ListingKey 12345`; Stellar already has a `12345`; the upsert
 * matches on `listing_key` alone, finds Stellar's row, and OVERWRITES it. Same
 * primary key, same preferences and tags pointing at it, a different house.
 * {@see provider_b_cannot_overwrite_stellars_row()} is that scenario, written
 * out literally.
 *
 * WHY A SECOND PROVIDER IS SIMULATED WITH A RAW VALUE
 * --------------------------------------------------
 * `MlsProvider` has exactly one case, and adding a speculative second one to
 * make a test pass would put a provider in the application's vocabulary that no
 * adapter, credential or configuration backs — the enum would then assert the
 * platform supports something it does not. So these tests write the foreign
 * provider straight to the column, which is what the DATABASE constraint is
 * being tested on anyway. No production credential, endpoint or config for a
 * second provider is created anywhere.
 */
class BridgeProviderScopedIdentityTest extends TestCase
{
    use DatabaseTransactions;

    /** A stand-in for a second MLS. Deliberately not an MlsProvider case. */
    private const OTHER_PROVIDER = 'other_mls_for_test';

    private function normalizer(): BridgePropertyNormalizer
    {
        return new BridgePropertyNormalizer();
    }

    private function apiRecord(string $listingKey, array $overrides = []): array
    {
        return array_merge([
            'ListingKey'            => $listingKey,
            'ListingId'             => 'MLS-' . $listingKey,
            'StandardStatus'        => 'Active',
            'PropertyType'          => 'Residential',
            'ListPrice'             => 450000,
            'UnparsedAddress'       => '123 Main Street, St. Petersburg, FL 33701',
            'City'                  => 'St. Petersburg',
            'StateOrProvince'       => 'FL',
            'PostalCode'            => '33701',
            'BedroomsTotal'         => 3,
            'BathroomsTotalInteger' => 2,
            'LivingArea'            => 1800,
            'ModificationTimestamp' => '2026-09-01T12:00:00Z',
        ], $overrides);
    }

    /** Insert a row as though a second MLS had supplied it. */
    private function seedForeignRow(string $listingKey, array $overrides = []): int
    {
        return DB::table('bridge_properties')->insertGetId(array_merge([
            'provider'         => self::OTHER_PROVIDER,
            'listing_key'      => $listingKey,
            'listing_id'       => 'OTHER-' . $listingKey,
            'standard_status'  => 'Active',
            'property_type'    => 'Residential',
            'unparsed_address' => '999 Other Avenue, Tampa, FL 33602',
            'city'             => 'Tampa',
            'state_or_province' => 'FL',
            'postal_code'      => '33602',
            'raw_json'         => json_encode(['ListingKey' => $listingKey]),
            'imported_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ], $overrides));
    }

    // ─── 1 & 2: one pair, one row; two providers, two rows ───────────────────

    /** @test */
    public function the_same_provider_and_listing_key_resolve_to_one_record(): void
    {
        $this->normalizer()->upsert($this->apiRecord('DUP-1'));
        $this->normalizer()->upsert($this->apiRecord('DUP-1', ['ListPrice' => 500000]));

        $rows = BridgeProperty::forNativeKey(MlsProvider::current(), 'DUP-1')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('500000.00', (string) $rows->first()->list_price);
    }

    /** @test */
    public function two_providers_may_hold_the_same_listing_key(): void
    {
        $this->normalizer()->upsert($this->apiRecord('SHARED-KEY-1'));
        $foreignId = $this->seedForeignRow('SHARED-KEY-1');

        $all = BridgeProperty::query()->where('listing_key', 'SHARED-KEY-1')->get();

        $this->assertCount(2, $all, 'the same native key must be able to exist once per provider');
        $this->assertEqualsCanonicalizing(
            ['stellar_bridge', self::OTHER_PROVIDER],
            $all->pluck('provider')->all()
        );
        $this->assertTrue($all->pluck('id')->contains($foreignId));
    }

    // ─── 3: the named defect ─────────────────────────────────────────────────

    /**
     * Provider B sends a key Stellar already uses. Stellar's row must be
     * untouched, and a NEW row must exist for the other provider.
     *
     * @test
     */
    public function provider_b_cannot_overwrite_stellars_row(): void
    {
        $stellar = $this->normalizer()->upsert($this->apiRecord('COLLIDE-1'))->model;

        $stellarId      = $stellar->id;
        $stellarAddress = $stellar->unparsed_address;
        $stellarPrice   = (string) $stellar->fresh()->list_price;

        $foreignId = $this->seedForeignRow('COLLIDE-1');

        $this->assertNotSame($stellarId, $foreignId, 'the second provider must get its own row');

        $stellarAfter = BridgeProperty::query()->find($stellarId);

        $this->assertSame('stellar_bridge', $stellarAfter->provider);
        $this->assertSame($stellarAddress, $stellarAfter->unparsed_address, 'Stellar\'s address was overwritten');
        $this->assertSame($stellarPrice, (string) $stellarAfter->list_price, 'Stellar\'s price was overwritten');
        $this->assertSame('MLS-COLLIDE-1', $stellarAfter->listing_id);
    }

    /**
     * And the reverse direction: a Stellar import lands on Stellar's row even
     * when another provider already holds that key.
     *
     * @test
     */
    public function a_stellar_upsert_never_touches_another_providers_row(): void
    {
        $foreignId = $this->seedForeignRow('COLLIDE-2');

        $result = $this->normalizer()->upsert($this->apiRecord('COLLIDE-2'));

        $this->assertTrue($result->isNew, 'the foreign row must not be mistaken for ours');
        $this->assertNotSame($foreignId, $result->model->id);

        $foreignAfter = DB::table('bridge_properties')->where('id', $foreignId)->first();

        $this->assertSame(self::OTHER_PROVIDER, $foreignAfter->provider);
        $this->assertSame('999 Other Avenue, Tampa, FL 33602', $foreignAfter->unparsed_address);
        $this->assertSame('OTHER-COLLIDE-2', $foreignAfter->listing_id);
    }

    // ─── 4: an upsert updates only its own pair ──────────────────────────────

    /** @test */
    public function an_upsert_updates_only_the_matching_provider_and_key_pair(): void
    {
        $this->seedForeignRow('COLLIDE-3');
        $mine = $this->normalizer()->upsert($this->apiRecord('COLLIDE-3'))->model;

        $this->normalizer()->upsert($this->apiRecord('COLLIDE-3', [
            'ListPrice'       => 999000,
            'UnparsedAddress' => '77 Updated Way, St. Petersburg, FL 33701',
        ]));

        $this->assertSame(2, BridgeProperty::query()->where('listing_key', 'COLLIDE-3')->count());

        $ours = BridgeProperty::forNativeKey(MlsProvider::current(), 'COLLIDE-3')->first();
        $this->assertSame($mine->id, $ours->id);
        $this->assertSame('999000.00', (string) $ours->list_price);

        $theirs = BridgeProperty::query()
            ->where('provider', self::OTHER_PROVIDER)
            ->where('listing_key', 'COLLIDE-3')
            ->first();

        $this->assertSame('999 Other Avenue, Tampa, FL 33602', $theirs->unparsed_address);
    }

    // ─── 12: the database actually enforces it ───────────────────────────────

    /** @test */
    public function the_database_rejects_a_duplicate_provider_and_listing_key_pair(): void
    {
        $this->normalizer()->upsert($this->apiRecord('UNIQ-1'));

        $this->expectException(QueryException::class);

        // Same provider, same key, inserted behind the model's back.
        DB::table('bridge_properties')->insert([
            'provider'    => 'stellar_bridge',
            'listing_key' => 'UNIQ-1',
            'raw_json'    => '{}',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * The old global rule is gone: the same key under a DIFFERENT provider is
     * now accepted by the database itself, not merely by application code.
     *
     * @test
     */
    public function the_database_accepts_the_same_key_under_a_different_provider(): void
    {
        $this->normalizer()->upsert($this->apiRecord('UNIQ-2'));

        $foreignId = $this->seedForeignRow('UNIQ-2');

        $this->assertIsInt($foreignId);
        $this->assertSame(2, BridgeProperty::query()->where('listing_key', 'UNIQ-2')->count());
    }

    // ─── 5 & 6: existing Stellar behaviour is unchanged ──────────────────────

    /** @test */
    public function stellar_ingestion_still_creates_updates_and_reports_as_before(): void
    {
        $first = $this->normalizer()->upsert($this->apiRecord('BEHAVE-1'));
        $this->assertTrue($first->isNew);
        $this->assertFalse($first->addressChanged);

        $same = $this->normalizer()->upsert($this->apiRecord('BEHAVE-1'));
        $this->assertFalse($same->isNew);
        $this->assertFalse($same->addressChanged);

        $moved = $this->normalizer()->upsert(
            $this->apiRecord('BEHAVE-1', ['UnparsedAddress' => '9 Other Road, Tampa, FL 33602'])
        );
        $this->assertFalse($moved->isNew);
        $this->assertTrue($moved->addressChanged);

        $this->assertSame(1, BridgeProperty::query()->where('listing_key', 'BEHAVE-1')->count());
    }

    /** @test */
    public function the_governed_lookup_returns_the_same_listing_a_bare_key_lookup_used_to(): void
    {
        $expected = $this->normalizer()->upsert($this->apiRecord('LOOKUP-1'))->model;

        $found = BridgeProperty::forNativeKey(MlsProvider::current(), 'LOOKUP-1')->first();

        $this->assertNotNull($found);
        $this->assertSame($expected->id, $found->id);
    }

    /** @test */
    public function the_governed_mls_number_lookup_is_also_provider_scoped(): void
    {
        $mine = $this->normalizer()->upsert($this->apiRecord('NUMBER-1'))->model;
        $this->seedForeignRow('NUMBER-1-FOREIGN', ['listing_id' => 'MLS-NUMBER-1']);

        $found = BridgeProperty::forNativeMlsNumber(MlsProvider::current(), 'MLS-NUMBER-1')->get();

        $this->assertCount(1, $found, 'an MLS number is unique only within its own system');
        $this->assertSame($mine->id, $found->first()->id);
    }

    /** @test */
    public function the_provider_scope_narrows_an_existing_query(): void
    {
        $this->normalizer()->upsert($this->apiRecord('SCOPE-1'));
        $this->seedForeignRow('SCOPE-1');

        $this->assertSame(
            1,
            BridgeProperty::query()->forProvider(MlsProvider::current())->where('listing_key', 'SCOPE-1')->count()
        );
    }
}
