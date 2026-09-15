<?php

namespace App\Services\SmartTags\Derivation;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSource;

/**
 * Bridge/MLS structured fields → structured_mls evidence.
 *
 * Reads ONLY structured fields: native bridge_properties columns and the RESO
 * fields named in config/smart_tag_sources.php. It never reads PublicRemarks
 * (that is the description parser's job, and it is not approved for
 * production), and it never reads ListingTerms (its display classification is
 * unresolved). Pure: no database, no network.
 */
final class BridgeStructuredTagDeriver
{
    public function contextFor(BridgeRecordAccessor $record): ?SmartTagContext
    {
        $type = $record->column('property_type') ?? ($record->raw()['PropertyType'] ?? null);

        return SmartTagContextResolver::forBridge(is_string($type) ? $type : null);
    }

    /**
     * @return TagEvidence[] keyed by tag
     */
    public function derive(BridgeRecordAccessor $record, SmartTagContext $context): array
    {
        return StructuredRuleEngine::evaluate(
            SmartTagSourceRules::bridgeRules(),
            $record,
            $context,
            SmartTagListingType::Bridge,
            SmartTagSource::StructuredMls,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function structuredInputs(BridgeRecordAccessor $record): array
    {
        return $record->inputsFor(SmartTagSourceRules::bridgeRules());
    }
}
