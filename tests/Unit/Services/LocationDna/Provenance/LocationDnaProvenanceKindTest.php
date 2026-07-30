<?php

namespace Tests\Unit\Services\LocationDna\Provenance;

use App\Services\LocationDna\Provenance\LocationDnaProvenanceKind as Kind;
use App\Services\LocationDna\Provenance\ProvenanceAuthority as Authority;
use PHPUnit\Framework\TestCase;

/**
 * G1e — provenance kinds and their authority classification.
 *
 * The property this suite protects is that authority is TOTAL and derived: every kind has exactly
 * one classification, and no kind can drift into being authoritative by accident.
 */
class LocationDnaProvenanceKindTest extends TestCase
{
    public function test_the_vocabulary_is_the_required_closed_set(): void
    {
        $this->assertSame([
            'owner_authored', 'owner_cleared', 'legacy_fallback', 'legacy_repaired',
            'inherited', 'derived', 'imported', 'snapshot_retained', 'unknown',
        ], array_map(fn (Kind $k): string => $k->value, Kind::all()));
    }

    public function test_every_kind_has_exactly_one_explicit_authority(): void
    {
        foreach (Kind::all() as $kind) {
            $this->assertInstanceOf(Authority::class, $kind->authority(), "{$kind->value} needs an authority");
        }
    }

    // ── the required authority mapping ───────────────────────────────────────

    public function test_owner_authored_is_authoritative(): void
    {
        $this->assertSame(Authority::Authoritative, Kind::OwnerAuthored->authority());
        $this->assertTrue(Kind::OwnerAuthored->isAuthoritative());
    }

    public function test_owner_cleared_is_authoritative_and_blocks_resurrection(): void
    {
        $this->assertSame(Authority::Authoritative, Kind::OwnerCleared->authority());
        $this->assertTrue(Kind::OwnerCleared->isAuthoritative());
        $this->assertTrue(Kind::OwnerCleared->blocksFallbackResurrection());
    }

    public function test_only_owner_cleared_blocks_resurrection(): void
    {
        foreach (Kind::all() as $kind) {
            if ($kind === Kind::OwnerCleared) {
                continue;
            }

            $this->assertFalse($kind->blocksFallbackResurrection(), "{$kind->value} must not claim to block");
        }
    }

    public function test_legacy_fallback_is_non_authoritative(): void
    {
        $this->assertSame(Authority::NonAuthoritative, Kind::LegacyFallback->authority());
        $this->assertFalse(Kind::LegacyFallback->isAuthoritative());
    }

    public function test_legacy_repaired_is_canonical_storage_but_not_owner_authored(): void
    {
        $this->assertSame(Authority::NonAuthoritative, Kind::LegacyRepaired->authority());
        $this->assertTrue(Kind::LegacyRepaired->isCanonicalStorage(), 'repair writes canonical storage');
        $this->assertFalse(Kind::LegacyRepaired->isOwnerStated(), 'but it is not authorship');
        $this->assertNotSame(Kind::OwnerAuthored, Kind::LegacyRepaired);
    }

    public function test_inherited_and_derived_are_distinct_and_both_non_authoritative(): void
    {
        $this->assertNotSame(Kind::Inherited, Kind::Derived);
        $this->assertSame(Authority::NonAuthoritative, Kind::Inherited->authority());
        $this->assertSame(Authority::NonAuthoritative, Kind::Derived->authority());
    }

    public function test_imported_is_conditionally_authoritative(): void
    {
        // §8.2 rule 2 gives an import standing over an absent dimension and none over an authored
        // one; the conditionality is recorded rather than flattened.
        $this->assertSame(Authority::ConditionallyAuthoritative, Kind::Imported->authority());
        $this->assertFalse(Kind::Imported->isAuthoritative(), 'conditional is not unqualified');
    }

    public function test_snapshot_is_forbidden_as_a_restoration_source(): void
    {
        $this->assertSame(Authority::ForbiddenAsRestorationSource, Kind::SnapshotRetained->authority());
        $this->assertFalse(Kind::SnapshotRetained->authority()->mayBeAutomaticRestorationSource());
    }

    public function test_unknown_is_default_safe_and_never_authoritative(): void
    {
        $this->assertSame(Authority::ForbiddenAsRestorationSource, Kind::Unknown->authority());
        $this->assertFalse(Kind::Unknown->isAuthoritative());
        $this->assertFalse(Kind::Unknown->authority()->mayBeAutomaticRestorationSource());
        $this->assertFalse(Kind::Unknown->authority()->mayBeOverwrittenAutomatically());
    }

    public function test_no_authority_permits_automatic_restoration(): void
    {
        foreach (Authority::cases() as $authority) {
            $this->assertFalse(
                $authority->mayBeAutomaticRestorationSource(),
                "{$authority->value}: automatic restoration is never permitted by authority alone",
            );
        }
    }

    public function test_only_non_authoritative_may_be_overwritten_automatically(): void
    {
        $this->assertTrue(Authority::NonAuthoritative->mayBeOverwrittenAutomatically());

        foreach ([Authority::Authoritative, Authority::ConditionallyAuthoritative,
                  Authority::ForbiddenAsRestorationSource] as $authority) {
            $this->assertFalse($authority->mayBeOverwrittenAutomatically(), $authority->value);
        }
    }

    // ── owner-stated and storage classification ──────────────────────────────

    public function test_only_the_two_owner_kinds_are_owner_stated(): void
    {
        $stated = array_values(array_filter(Kind::all(), fn (Kind $k): bool => $k->isOwnerStated()));

        $this->assertSame([Kind::OwnerAuthored, Kind::OwnerCleared], $stated);
    }

    public function test_read_through_kinds_are_not_canonical_storage(): void
    {
        $this->assertFalse(Kind::LegacyFallback->isCanonicalStorage(), 'fallback is read through');
        $this->assertFalse(Kind::SnapshotRetained->isCanonicalStorage());
        $this->assertFalse(Kind::Unknown->isCanonicalStorage());
    }

    // ── parsing is default-safe ──────────────────────────────────────────────

    public function test_unrecognised_names_parse_to_unknown_not_to_something_authoritative(): void
    {
        foreach (['owner', 'authored', 'trusted', '', 'OWNER_AUTHORED_BUT_TYPOED'] as $name) {
            $this->assertSame(Kind::Unknown, Kind::fromNameOrUnknown($name), "`{$name}`");
        }

        $this->assertSame(Kind::Unknown, Kind::fromNameOrUnknown(null));
    }

    public function test_known_names_parse_case_insensitively(): void
    {
        $this->assertSame(Kind::OwnerCleared, Kind::fromNameOrUnknown(' Owner_Cleared '));
    }

    public function test_the_model_needs_no_framework_boot(): void
    {
        // Extends PHPUnit's TestCase, never boots the app. If a framework dependency appears, this
        // suite stops running.
        $this->assertTrue(Kind::OwnerAuthored->isAuthoritative());
    }
}
