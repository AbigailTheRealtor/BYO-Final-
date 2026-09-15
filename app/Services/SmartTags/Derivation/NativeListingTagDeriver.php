<?php

namespace App\Services\SmartTags\Derivation;

use App\Support\SmartTags\NativeMetaValueReader;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSource;
use InvalidArgumentException;
use LogicException;

/**
 * Native Seller/Landlord Offer Listing structured fields → structured_native_listing evidence.
 *
 * If the listing's own form already answers a characteristic (Waterfront = Yes,
 * Pool Type = Private, Interior Features includes Quartz Counters), the tag is
 * derived here and the owner is never asked twice.
 *
 * Reads only the meta keys named in config/smart_tag_sources.php, and refuses to
 * run at all if any rule names a forbidden free-text key — screening,
 * qualification, provider prose, pet/breed text, clientele, compatibility
 * preferences, "other" boxes.
 */
final class NativeListingTagDeriver
{
    public function __construct()
    {
        foreach ([SmartTagListingType::SellerAgent, SmartTagListingType::LandlordAgent] as $type) {
            foreach (SmartTagSourceRules::nativeRules($type) as $rule) {
                $field = (string) ($rule['field'] ?? '');
                if (SmartTagSourceRules::isForbiddenKey($field)) {
                    throw new LogicException("Smart Tag rule {$rule['id']} reads forbidden source key {$field}.");
                }
            }
        }
    }

    public function contextFor(SmartTagListingType $type, NativeMetaValueReader $meta): ?SmartTagContext
    {
        self::assertNative($type);

        return SmartTagContextResolver::forListingType($type, $meta->scalar(SmartTagSourceRules::nativePropertyTypeField()));
    }

    /**
     * @return TagEvidence[] keyed by tag
     */
    public function derive(SmartTagListingType $type, NativeMetaValueReader $meta, SmartTagContext $context): array
    {
        self::assertNative($type);

        if (! in_array($context, $type->possibleContexts(), true)) {
            return [];
        }

        return StructuredRuleEngine::evaluate(
            SmartTagSourceRules::nativeRules($type),
            new NativeRecordAccessor($meta),
            $context,
            $type,
            SmartTagSource::StructuredNativeListing,
        );
    }

    /**
     * Keys this listing's own Yes/No-style fields already answer. The owner edits
     * those fields, not a manual tag.
     *
     * @return string[]
     */
    public function answeredKeys(SmartTagListingType $type, NativeMetaValueReader $meta, SmartTagContext $context): array
    {
        $answered = [];
        foreach ($this->derive($type, $meta, $context) as $evidence) {
            if ($evidence->authoritative) {
                $answered[] = $evidence->tagKey;
            }
        }

        return $answered;
    }

    /**
     * @return array<string, mixed>
     */
    public function structuredInputs(SmartTagListingType $type, NativeMetaValueReader $meta): array
    {
        self::assertNative($type);

        $inputs = (new NativeRecordAccessor($meta))->inputsFor(SmartTagSourceRules::nativeRules($type));
        $inputs['__property_type'] = $meta->raw(SmartTagSourceRules::nativePropertyTypeField());

        return $inputs;
    }

    private static function assertNative(SmartTagListingType $type): void
    {
        if (! $type->isNative()) {
            throw new InvalidArgumentException('NativeListingTagDeriver only reads native Offer Listings.');
        }
    }
}
