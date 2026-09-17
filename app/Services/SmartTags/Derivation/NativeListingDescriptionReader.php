<?php

namespace App\Services\SmartTags\Derivation;

use App\Support\OfferListing\LandlordProviderTextPolicy;
use App\Support\SmartTags\NativeMetaValueReader;
use App\Support\SmartTags\SmartTagListingType;

/**
 * Returns the ONE public listing description a native Offer Listing publishes —
 * and nothing else.
 *
 * Verified field per listing type (see config/smart_tag_sources.php):
 *
 *   seller_agent    meta additional_details — "Property Description", every
 *                   Seller property type.
 *   landlord_agent  meta additional_details — "Rental Description", both Landlord
 *                   property types. Governed by LandlordProviderTextPolicy, and
 *                   read ONLY through its displayValue(): if the policy withholds
 *                   the text from the public page, Smart Tags parses nothing.
 *
 * No other free-text field can reach the parser through this class: it reads a
 * single configured meta key, and refuses a key on the forbidden list.
 */
final class NativeListingDescriptionReader
{
    public function read(SmartTagListingType $type, NativeMetaValueReader $meta): ?string
    {
        return $this->describe($type, $meta)->text;
    }

    /**
     * The same decision as read(), keeping WHY there is no text.
     *
     * Phase 2 needs the distinction for one log line only — a listing with no
     * description and a listing whose description the policy withholds are both
     * "nothing to parse", but only the second is a suppression an operator may
     * want to see. Derivation itself still treats them identically.
     */
    public function describe(SmartTagListingType $type, NativeMetaValueReader $meta): NativeDescription
    {
        if (! $type->isNative()) {
            return NativeDescription::absent();
        }

        $field = SmartTagSourceRules::descriptionField($type);
        $key = (string) ($field['meta_key'] ?? '');

        if ($key === '' || SmartTagSourceRules::isForbiddenKey($key)) {
            return NativeDescription::absent();
        }

        $raw = $meta->raw($key);
        if (! is_string($raw) || trim($raw) === '') {
            return NativeDescription::absent();
        }

        if (($field['gate'] ?? null) === 'landlord_provider_text') {
            $shown = LandlordProviderTextPolicy::displayValue($key, $raw);

            // Stored prose the public page does not publish. Smart Tags parses
            // exactly what the page shows, so this is a suppression, not an
            // absence — and it is still null to every caller that reads text.
            return ($shown === null || trim($shown) === '')
                ? NativeDescription::suppressed()
                : NativeDescription::published($shown);
        }

        return NativeDescription::published(trim($raw));
    }
}
