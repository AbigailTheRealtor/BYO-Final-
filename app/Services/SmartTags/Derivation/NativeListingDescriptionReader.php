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
        if (! $type->isNative()) {
            return null;
        }

        $field = SmartTagSourceRules::descriptionField($type);
        $key = (string) ($field['meta_key'] ?? '');

        if ($key === '' || SmartTagSourceRules::isForbiddenKey($key)) {
            return null;
        }

        $raw = $meta->raw($key);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        if (($field['gate'] ?? null) === 'landlord_provider_text') {
            $shown = LandlordProviderTextPolicy::displayValue($key, $raw);

            return ($shown === null || trim($shown) === '') ? null : $shown;
        }

        return trim($raw);
    }
}
