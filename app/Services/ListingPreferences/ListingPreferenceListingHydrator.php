<?php

namespace App\Services\ListingPreferences;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Services\ListingImport\Mls\MlsDisplayPermissions;
use App\Services\ListingImport\Mls\MlsListingDetailsReader;
use App\Support\Listing\MlsProvider;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;

/**
 * Turns the listing references a customer's preferences point at into
 * customer-safe property cards — in batch.
 *
 * WHY THIS IS SEPARATE FROM THE PREFERENCE READ. A preference is the
 * customer's; the listing is the platform's, and the two have different
 * lifetimes. A listing can be withdrawn, archived, or lose its IDX permission
 * after somebody passed on it, and when that happens the customer's decision
 * and their reasons are still theirs to see and undo. Keeping hydration out of
 * the preference read is what lets a missing listing degrade to a card that
 * says so instead of an exception on their own account page.
 *
 * ONE QUERY PER LISTING TYPE. A page of twelve preferences is three queries at
 * most — the Bridge rows, the Seller auctions, the Landlord auctions — plus
 * one eager-loaded meta query per native type. Adding a card adds no query.
 *
 * IT REUSES THE EXISTING PERMISSION RULES AND ADDS NONE OF ITS OWN.
 * ----------------------------------------------------------------
 * A Bridge row passes through {@see MlsDisplayPermissions}, the same class
 * every other public MLS surface consults:
 *
 *   listingDisplayable() false  →  the card is UNAVAILABLE. Not "shown without
 *                                  an address" — the feed has said this listing
 *                                  may not be published at all.
 *   addressDisplayable() false  →  the street line is withheld and the city /
 *                                  state / ZIP still shown, which is exactly
 *                                  what an address-suppressed IDX listing is
 *                                  meant to look like.
 *
 * A BYO listing imported from the MLS carries the feed's permissions in its
 * meta, and {@see MlsListingDetailsReader::addressVisibleTo()} — the same call
 * the Seller and Landlord detail pages make — decides whether its street line
 * may be shown. Withheld, the card mirrors those pages: no street line, no
 * title that falls back to the address (quick import seeds `title` FROM it),
 * locality kept. Only the listing's owner sees their own address.
 *
 * NO IDENTITY IS INFERRED. Cards are looked up by the stored
 * (listing_type, listing_id) pair and nothing else. No address matching, no
 * parcel grouping, no coordinate proximity — `subject_key` already decided
 * which listings are the same property, and re-deciding it here with a weaker
 * rule is how one customer's Pass lands on somebody else's house.
 *
 * NO PHOTOGRAPHS IN PHASE 3B, deliberately. MLS media carries its own licence
 * flags and its own extraction path; a compact management list does not need
 * it, and adding a second media consumer is a larger decision than this page
 * justifies. `photoUrl` stays null and the card renders without one.
 */
class ListingPreferenceListingHydrator
{
    public function __construct(private readonly MlsListingDetailsReader $mlsReader)
    {
    }

    /** The viewer, so a listing's owner still sees their own withheld address. */
    private ?int $viewerId = null;

    /**
     * @param  list<SmartTagListingRef> $refs
     * @param  int|null                 $viewerId the signed-in viewer; null reads as "not the owner"
     * @return array<string, ListingPreferenceListingCard> keyed "<type>:<id>"
     */
    public function hydrate(array $refs, ?int $viewerId = null): array
    {
        $this->viewerId = $viewerId !== null && $viewerId > 0 ? $viewerId : null;

        /** @var array<string, list<int>> $idsByType */
        $idsByType = [];

        foreach ($refs as $ref) {
            $idsByType[$ref->type->value][$ref->id] = $ref->id;
        }

        $cards = [];

        foreach ($idsByType as $typeValue => $ids) {
            $type = SmartTagListingType::tryFrom($typeValue);

            if ($type === null) {
                continue;
            }

            $ids = array_values($ids);

            $cards += match ($type) {
                SmartTagListingType::Bridge        => $this->bridgeCards($ids),
                SmartTagListingType::SellerAgent   => $this->sellerCards($ids),
                SmartTagListingType::LandlordAgent => $this->landlordCards($ids),
                default                            => [],
            };

            // Anything the query did not return is a listing that is gone.
            foreach ($ids as $id) {
                $ref = new SmartTagListingRef($type, $id);

                if (! array_key_exists($ref->type->value . ':' . $ref->id, $cards)) {
                    $cards[$ref->type->value . ':' . $ref->id] =
                        ListingPreferenceListingCard::unavailable($ref, $this->sourceLabel($type));
                }
            }
        }

        return $cards;
    }

    // ------------------------------------------------------------------ Bridge

    /**
     * @param  list<int> $ids
     * @return array<string, ListingPreferenceListingCard>
     */
    private function bridgeCards(array $ids): array
    {
        $cards = [];

        foreach (BridgeProperty::query()->whereIn('id', $ids)->get() as $row) {
            $ref   = new SmartTagListingRef(SmartTagListingType::Bridge, (int) $row->id);
            $label = $this->sourceLabel(SmartTagListingType::Bridge);

            $raw = is_string($row->raw_json) ? (json_decode($row->raw_json, true) ?: []) : [];
            $permissions = MlsDisplayPermissions::fromRecord(is_array($raw) ? $raw : []);

            // The feed's own instruction, and it is absolute: a listing it
            // refuses to publish is not published here either.
            if (! $permissions->listingDisplayable()) {
                $cards[$ref->type->value . ':' . $ref->id] =
                    ListingPreferenceListingCard::unavailable($ref, $label);
                continue;
            }

            $address = $permissions->addressDisplayable()
                ? ($row->unparsed_address ?: null)
                : null;

            // The Stellar detail route resolves its key within the current
            // provider, so only a row that provider issued may link there — a
            // key from another MLS would open a different property.
            $listingKey = $row->mlsProvider() === MlsProvider::current()
                && is_string($row->listing_key) && trim($row->listing_key) !== ''
                ? trim($row->listing_key)
                : null;

            $cards[$ref->type->value . ':' . $ref->id] = new ListingPreferenceListingCard(
                ref:          $ref,
                available:    true,
                sourceLabel:  $label,
                // An address-suppressed listing still needs a heading; its
                // locality is what it is allowed to be called.
                title:        $address ?: ($this->locationLine($row) ?: 'Listing'),
                addressLine:  $address,
                locationLine: $this->locationLine($row),
                priceDisplay: $this->money($row->list_price),
                url:          $listingKey === null ? null : route('stellar.property.show', ['listingKey' => $listingKey]),
                factsLine:    $this->facts(
                    $row->bedrooms_total,
                    $row->bathrooms_total_integer,
                    $row->living_area,
                ),
            );
        }

        return $cards;
    }

    // ------------------------------------------------------------ BYO Seller

    /**
     * @param  list<int> $ids
     * @return array<string, ListingPreferenceListingCard>
     */
    private function sellerCards(array $ids): array
    {
        $cards = [];

        $rows = SellerAgentAuction::query()->with('meta')->whereIn('id', $ids)->get();

        foreach ($rows as $row) {
            $ref   = new SmartTagListingRef(SmartTagListingType::SellerAgent, (int) $row->id);
            $label = $this->sourceLabel(SmartTagListingType::SellerAgent);
            $meta  = $this->metaOf($row);

            if ($this->nativeWithdrawn($row)) {
                $cards[$ref->type->value . ':' . $ref->id] =
                    ListingPreferenceListingCard::unavailable($ref, $label);
                continue;
            }

            $visible = $this->addressVisible($row, $meta);
            $address = $visible
                ? ($this->text($row->address) ?? $this->text($meta['address'] ?? null))
                : null;

            $cards[$ref->type->value . ':' . $ref->id] = new ListingPreferenceListingCard(
                ref:          $ref,
                available:    true,
                sourceLabel:  $label,
                // Same fallbacks as the seller detail page's header.
                title:        $visible
                    ? ($this->text($row->title) ?? $address ?? 'Seller Offer Listing')
                    : ($this->text($meta['listing_title'] ?? null) ?? 'Seller Offer Listing'),
                addressLine:  $address,
                locationLine: $this->nativeLocationLine($meta),
                priceDisplay: $this->money($meta['ideal_price'] ?? null),
                url:          route('offer.listing.seller.view', $row->id),
                factsLine:    $this->facts(
                    $meta['bedrooms'] ?? null,
                    $meta['bathrooms'] ?? null,
                    $meta['square_feet'] ?? null,
                ),
            );
        }

        return $cards;
    }

    // ---------------------------------------------------------- BYO Landlord

    /**
     * @param  list<int> $ids
     * @return array<string, ListingPreferenceListingCard>
     */
    private function landlordCards(array $ids): array
    {
        $cards = [];

        $rows = LandlordAgentAuction::query()->with('meta')->whereIn('id', $ids)->get();

        foreach ($rows as $row) {
            $ref   = new SmartTagListingRef(SmartTagListingType::LandlordAgent, (int) $row->id);
            $label = $this->sourceLabel(SmartTagListingType::LandlordAgent);
            $meta  = $this->metaOf($row);

            if ($this->nativeWithdrawn($row)) {
                $cards[$ref->type->value . ':' . $ref->id] =
                    ListingPreferenceListingCard::unavailable($ref, $label);
                continue;
            }

            // Landlord listings are EAV: there is no native address column.
            $visible = $this->addressVisible($row, $meta);
            $address = $visible ? $this->text($meta['address'] ?? null) : null;

            $cards[$ref->type->value . ':' . $ref->id] = new ListingPreferenceListingCard(
                ref:          $ref,
                available:    true,
                sourceLabel:  $label,
                // Withheld: never a title that could be the address — the
                // landlord detail page's rule.
                title:        $visible
                    ? ($this->text($meta['titleListing'] ?? null) ?? $address ?? 'Rental property')
                    : ($this->text($meta['listing_title'] ?? null) ?? 'Rental property'),
                addressLine:  $address,
                locationLine: $this->nativeLocationLine($meta),
                // Already a formatted string on this record; not re-formatted.
                priceDisplay: $this->text($meta['leaseAmount'] ?? null),
                url:          route('offer.listing.landlord.view', $row->id),
                factsLine:    $this->facts(
                    $meta['bedrooms'] ?? null,
                    $meta['bathrooms'] ?? null,
                    $meta['square_feet'] ?? null,
                ),
            );
        }

        return $cards;
    }

    // ----------------------------------------------------------------- shared

    /**
     * A native listing the platform is no longer publishing.
     *
     * Archived and draft are the two states the discovery queries already
     * exclude. The customer's preference survives; the property facts stop.
     */
    private function nativeWithdrawn(object $row): bool
    {
        return (bool) ($row->is_archived ?? false) || (bool) ($row->is_draft ?? false);
    }

    /**
     * The detail pages' own decision, not a second one: the owner always sees
     * their address; everyone else only where the feed permits it. A listing
     * that was never MLS-imported carries no restriction and reads as visible.
     *
     * @param array<string,mixed> $meta
     */
    private function addressVisible(object $row, array $meta): bool
    {
        $isOwner = $this->viewerId !== null && (int) ($row->user_id ?? 0) === $this->viewerId;

        return $this->mlsReader->addressVisibleTo($meta, $isOwner);
    }

    /** City, state ZIP from a native listing's meta — what a withheld listing may still show. @param array<string,mixed> $meta */
    private function nativeLocationLine(array $meta): ?string
    {
        $cityState = implode(', ', array_filter([
            $this->text($meta['property_city'] ?? null),
            $this->text($meta['property_state'] ?? null),
        ]));

        $zip  = $this->text($meta['property_zip'] ?? null) ?? $this->text($meta['zip_code'] ?? null);
        $line = trim($cityState . ' ' . (string) ($zip ?? ''));

        return $line === '' ? null : $line;
    }

    /** @return array<string,mixed> */
    private function metaOf(object $row): array
    {
        $out = [];

        // Decoded exactly as the Seller/Landlord detail controllers decode it:
        // `saveMeta()` JSON-encodes arrays, and the stored MLS display
        // permissions are one. Read raw, they would be a string, and
        // `MlsDisplayPermissions::fromStored()` reads a non-array as "no
        // restriction" — which would publish a withheld address.
        foreach ($row->meta ?? [] as $meta) {
            $value   = $meta->meta_value;
            $decoded = is_string($value) ? json_decode($value, true) : null;

            $out[(string) $meta->meta_key] = is_array($decoded) ? $decoded : $value;
        }

        return $out;
    }

    private function sourceLabel(SmartTagListingType $type): string
    {
        return match ($type) {
            SmartTagListingType::Bridge        => 'MLS listing',
            SmartTagListingType::SellerAgent   => 'Seller listing',
            SmartTagListingType::LandlordAgent => 'Rental listing',
            default                            => 'Listing',
        };
    }

    private function locationLine(BridgeProperty $row): ?string
    {
        $cityState = implode(', ', array_filter([
            $this->text($row->city),
            $this->text($row->state_or_province),
        ]));

        $line = trim($cityState . ' ' . (string) ($this->text($row->postal_code) ?? ''));

        return $line === '' ? null : $line;
    }

    private function facts(mixed $beds, mixed $baths, mixed $sqft): ?string
    {
        $parts = [];

        if (is_numeric($beds) && (int) $beds > 0)  { $parts[] = (int) $beds . ' bed'; }
        if (is_numeric($baths) && (int) $baths > 0) { $parts[] = (int) $baths . ' bath'; }
        if (is_numeric($sqft) && (int) $sqft > 0)  { $parts[] = number_format((int) $sqft) . ' sqft'; }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function money(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // A stored string that already reads as money is shown as written
        // rather than re-parsed into a number we might round differently.
        if (! is_numeric($value)) {
            return $this->text($value);
        }

        return '$' . number_format((float) $value, 0);
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
