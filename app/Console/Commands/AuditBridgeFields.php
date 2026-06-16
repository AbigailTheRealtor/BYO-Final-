<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Bridge\BridgeApiService;

class AuditBridgeFields extends Command
{
    protected $signature = 'bridge:audit-fields {--limit=25 : Number of property records to sample}';

    protected $description = 'Fetch sample Bridge API records and produce a field audit report at docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md';

    /**
     * Pattern-based compliance detection: keys matching any pattern are classified
     * as Compliance/Restricted unless they appear in the allow-list below.
     */
    private const COMPLIANCE_PATTERNS = [
        '/Phone/i',                             // agent/tenant/call-center phone numbers
        '/Email/i',                             // email addresses
        '/License/i',                           // agent/builder license numbers
        '/LockBox/i',                           // lockbox type/location/serial
        '/PrivateRemarks/i',                    // agent-only private remarks
        '/ContactPreferred/i',                  // preferred contact method/value fields
        '/Contact(?:Name|Phone|Type|Info|Number|Method)/i', // explicit contact sub-fields
        '/ShowingInstruction/i',                // access instructions
        '/ShowingContact/i',                    // contact data for showings
        '/ShowingRequirement/i',                // entry requirement details
        '/ShowingConsideration/i',              // showing-specific private notes
        '/^OwnerName$/i',                       // owner PII
        '/^OwnerPhone$/i',                      // owner PII
    ];

    /**
     * Fields that match a compliance pattern but are NOT compliance-sensitive.
     * These are public property characteristics or legitimate operational data.
     */
    private const COMPLIANCE_ALLOW_LIST = [
        'PoolPrivateYN',                       // describes pool privacy (a property feature)
        'PublicRemarks',                       // public-facing listing description
        'STELLAR_PublicRemarksAgent',          // public description copy
        'STELLAR_PublicRemarksRequired',       // MLS public remarks field
        'STELLAR_PublicRemarksSpanishReq',     // Spanish public remarks
        'Ownership',                           // property ownership structure (e.g. "Fee Simple")
        'OwnerPays',                           // landlord/tenant terms — financial, not PII
        'STELLAR_SolarPanelOwnership',         // property feature (owned/leased panels)
        'STELLAR_YrsOfOwnerPriorToLeasingReqYN', // leasing restriction type
        'AttachedGarageYN',                    // garage attachment — not compliance
        'STELLAR_ShowingTime',                 // general showing hours window (scheduling)
        'AssociationPhone',                    // HOA office phone (publicly listed)
        'AssociationPhone2',                   // secondary HOA office phone (publicly listed)
        'STELLAR_AssociationEmail',            // HOA contact email (publicly listed)
    ];

    private const CATEGORIES = [
        'Location'             => ['City', 'CountyOrParish', 'PostalCode', 'StateOrProvince', 'Country',
                                   'Latitude', 'Longitude', 'UnparsedAddress', 'StreetNumber', 'StreetName',
                                   'StreetSuffix', 'UnitNumber', 'MLSAreaMajor', 'MLSAreaMinor',
                                   'SubdivisionName', 'Township', 'GeoLat', 'GeoLon', 'Directions',
                                   'CrossStreet', 'MapCoordinate', 'ParcelNumber', 'TaxLegalDescription',
                                   'PublicSurveySection', 'PublicSurveyTownship', 'PublicSurveyRange',
                                   'StreetDirPrefix', 'StreetSuffixModifier', 'StreetAdditionalInfo'],
        'Price'                => ['ListPrice', 'OriginalListPrice', 'ClosePrice', 'PreviousListPrice',
                                   'ListPriceLow', 'PriceChangeTimestamp', 'LeaseAmountFrequency',
                                   'LeaseAmount', 'RentIncludes', 'AssociationFee', 'AssociationFee2',
                                   'AssociationFeeFrequency', 'AssociationFee2Frequency',
                                   'TaxAnnualAmount', 'TaxYear'],
        'Property Basics'      => ['PropertyType', 'PropertySubType', 'StandardStatus', 'ListingKey',
                                   'ListingId', 'MlsStatus', 'ListingContractDate', 'OnMarketDate',
                                   'OffMarketDate', 'CloseDate', 'DaysOnMarket', 'CumulativeDaysOnMarket',
                                   'ExpirationDate', 'WithdrawnDate', 'ModificationTimestamp',
                                   'OriginalEntryTimestamp', 'StatusChangeTimestamp',
                                   'PurchaseContractDate', 'ContingentDate', 'BridgeModificationTimestamp'],
        'Interior Features'    => ['BedroomsTotal', 'BathroomsTotalInteger', 'BathroomsFull',
                                   'BathroomsHalf', 'BathroomsOneQuarter', 'BathroomsThreeQuarter',
                                   'BathroomsTotalDecimal', 'LivingArea', 'LivingAreaUnits',
                                   'BuildingAreaTotal', 'Levels', 'StoriesTotal', 'Stories',
                                   'NumberOfUnitsTotal', 'RoomCount', 'InteriorFeatures', 'Flooring',
                                   'Appliances', 'LaundryFeatures', 'Cooling', 'CoolingYN', 'Heating',
                                   'HeatingYN', 'WindowFeatures', 'FireplacesTotal', 'FireplaceFeatures',
                                   'FireplaceYN', 'BasementYN', 'BelowGradeFinishedArea',
                                   'AboveGradeFinishedArea', 'AccessibilityFeatures', 'BuildingAreaUnits'],
        'Exterior/Lot'         => ['LotSizeAcres', 'LotSizeSquareFeet', 'LotSizeArea', 'LotSizeUnits',
                                   'LotFeatures', 'LotDimensions', 'PoolPrivateYN', 'PoolFeatures',
                                   'SpaYN', 'SpaFeatures', 'WaterfrontYN', 'WaterfrontFeatures',
                                   'ViewYN', 'View', 'ExteriorFeatures', 'ArchitecturalStyle',
                                   'ConstructionMaterials', 'RoofType', 'Roof', 'Fencing',
                                   'GarageYN', 'GarageSpaces', 'AttachedGarageYN', 'ParkingFeatures',
                                   'ParkingTotal', 'OpenParkingYN', 'CarportYN', 'CarportSpaces',
                                   'PatioAndPorchFeatures', 'YearBuilt', 'YearBuiltEffective',
                                   'YearBuiltSource', 'PropertyCondition', 'FoundationDetails',
                                   'Sewer', 'WaterSource', 'Electric', 'Utilities', 'OtherStructures',
                                   'NewConstructionYN', 'Topography', 'WaterBodyName'],
        'HOA/Fees'             => ['AssociationYN', 'AssociationName', 'AssociationName2',
                                   'AssociationPhone', 'AssociationPhone2', 'AssociationFee',
                                   'AssociationFeeFrequency', 'AssociationFeeIncludes',
                                   'AssociationAmenities', 'CommunityFeatures', 'SeniorCommunityYN',
                                   'AssociationFee2', 'AssociationFee2Frequency',
                                   'STELLAR_AssociationEmail', 'STELLAR_MonthlyHOAAmount'],
        'Lease/Rental'         => ['LeaseConsideredYN', 'LeaseAmount', 'LeaseAmountFrequency',
                                   'LeaseTerm', 'LeaseRenewalOptionYN', 'PetsAllowed', 'TenantPays',
                                   'LandlordPays', 'AvailabilityDate', 'FurnishedYN', 'Furnished',
                                   'RentIncludes', 'NumberOfUnitsVacant', 'NumberOfUnitsLeased',
                                   'NumberOfUnitsMoMo', 'STELLAR_YrsOfOwnerPriorToLeasingReqYN'],
        'Financial/Investment' => ['GrossScheduledIncome', 'NetOperatingIncome', 'OperatingExpense',
                                   'CapRate', 'TaxAssessedValue', 'TaxBlock', 'TaxLot',
                                   'TaxMapNumber', 'Zoning', 'ZoningDescription', 'TotalActualRent'],
        'Media'                => ['Media', 'PhotosCount', 'PhotosChangeTimestamp', 'VideosCount',
                                   'VirtualTourURLUnbranded', 'VirtualTourURLBranded',
                                   'VirtualTourURLZillow', 'DocumentsCount'],
        'Agent/Brokerage'      => ['ListAgentFullName', 'ListAgentKey', 'ListAgentMlsId',
                                   'ListOfficeName', 'ListOfficeKey', 'ListOfficeMlsId',
                                   'CoListAgentFullName', 'CoListAgentKey', 'CoListOfficeName',
                                   'BuyerAgentFullName', 'BuyerAgentKey', 'BuyerOfficeName',
                                   'CoBuyerAgentFullName', 'OriginatingSystemID',
                                   'OriginatingSystemName', 'OriginatingSystemKey',
                                   'SourceSystemName'],
    ];

    private const MATCHING_USEFUL = [
        'City', 'CountyOrParish', 'PostalCode', 'StateOrProvince', 'MLSAreaMajor', 'MLSAreaMinor',
        'SubdivisionName', 'Latitude', 'Longitude', 'ListPrice', 'OriginalListPrice', 'LeaseAmount',
        'BedroomsTotal', 'BathroomsTotalInteger', 'BathroomsFull', 'BathroomsHalf', 'LivingArea',
        'PropertyType', 'PropertySubType', 'StandardStatus', 'MlsStatus', 'YearBuilt',
        'LotSizeAcres', 'LotSizeSquareFeet', 'PoolPrivateYN', 'WaterfrontYN', 'ViewYN', 'View',
        'AssociationYN', 'AssociationFee', 'AssociationAmenities', 'CommunityFeatures', 'SeniorCommunityYN',
        'LeaseConsideredYN', 'PetsAllowed', 'Furnished', 'FurnishedYN', 'LeaseTerm',
        'GarageYN', 'GarageSpaces', 'ParkingFeatures', 'Cooling', 'Heating',
        'Appliances', 'InteriorFeatures', 'ExteriorFeatures', 'LotFeatures',
        'Zoning', 'ZoningDescription', 'NewConstructionYN', 'ConstructionMaterials',
        'TaxAnnualAmount', 'GrossScheduledIncome', 'NetOperatingIncome', 'CapRate',
        'PhotosCount', 'VirtualTourURLUnbranded', 'Levels', 'StoriesTotal',
        'ArchitecturalStyle', 'PropertyCondition', 'Sewer', 'WaterSource', 'Utilities',
    ];

    public function handle(BridgeApiService $service): int
    {
        $limit   = (int) $this->option('limit');
        $this->info("Fetching up to {$limit} property records from Bridge API...");

        $records = $service->fetchProperties($limit);
        $actual  = count($records);

        if ($actual === 0) {
            $this->error('No records returned. Check bridge.dataset / bridge.token config and logs.');
            return self::FAILURE;
        }

        $this->info("Received {$actual} record(s). Analysing fields...");

        $fieldData = [];

        foreach ($records as $record) {
            foreach ($record as $key => $value) {
                if (!isset($fieldData[$key])) {
                    $fieldData[$key] = ['count' => 0, 'example' => null];
                }
                $isSet = !is_null($value) && $value !== '' && $value !== [];
                if ($isSet) {
                    $fieldData[$key]['count']++;
                    if ($fieldData[$key]['example'] === null) {
                        $fieldData[$key]['example'] = $value;
                    }
                }
            }
        }

        ksort($fieldData);

        $totalFields = count($fieldData);
        $this->info("Found {$totalFields} unique field keys.");

        $report = $this->buildReport($fieldData, $actual, $limit);

        $outputDir  = base_path('docs/audits');
        $outputPath = $outputDir . '/STELLAR_BRIDGE_FIELD_AUDIT.md';

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputPath, $report);

        $this->info("Report written to: docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md");
        return self::SUCCESS;
    }

    private function isComplianceSensitive(string $key, mixed $exampleValue = null): bool
    {
        if (in_array($key, self::COMPLIANCE_ALLOW_LIST, true)) {
            return false;
        }
        foreach (self::COMPLIANCE_PATTERNS as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }
        // Value-based backstop: flag any string field whose example value looks like a
        // phone number or email address, even when the key name is ambiguous.
        if (is_string($exampleValue) && $exampleValue !== '') {
            $trimmed = trim($exampleValue);
            // US phone: (NXX) NXX-XXXX or NXX-NXX-XXXX or NXX.NXX.XXXX etc.
            if (preg_match('/^\+?1?[-.\s]?\(?\d{3}\)?[-.\s]\d{3}[-.\s]\d{4}$/', $trimmed)) {
                return true;
            }
            // Email address
            if (preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $trimmed)) {
                return true;
            }
        }
        return false;
    }

    private function buildReport(array $fieldData, int $actual, int $requested): string
    {
        $now = date('Y-m-d H:i:s T');

        $populated     = array_filter($fieldData, fn($d) => $d['count'] >= ceil($actual * 0.5));
        $sparse        = array_filter($fieldData, fn($d) => $d['count'] > 0 && $d['count'] < ceil($actual * 0.5));
        $empty         = array_filter($fieldData, fn($d) => $d['count'] === 0);
        $complianceFound = array_filter(array_keys($fieldData), fn($k) => $this->isComplianceSensitive($k, $fieldData[$k]['example']));

        $lines   = [];
        $lines[] = "# Stellar Bridge API Field Audit";
        $lines[] = "";
        $lines[] = "> Generated: {$now}  ";
        $lines[] = "> Sample size: **{$actual}** record(s) fetched (requested {$requested})  ";
        $lines[] = "> Dataset: `" . config('bridge.dataset', 'stellar') . "`  ";
        $lines[] = "> Command: `php artisan bridge:audit-fields --limit={$requested}`";
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";
        $lines[] = "## Executive Summary";
        $lines[] = "";
        $lines[] = "- **Total unique field keys found:** " . count($fieldData);
        $lines[] = "- **Reliably populated (≥50 % of records):** " . count($populated);
        $lines[] = "- **Sparsely populated (<50 %):** " . count($sparse);
        $lines[] = "- **Always empty / null in this sample:** " . count($empty);
        $lines[] = "- **Compliance-sensitive fields (excluded from matching):** " . count($complianceFound);
        $lines[] = "";
        $lines[] = "> **Note on compliance fields:** Fields flagged as `Compliance/Restricted` contain personal contact";
        $lines[] = "> information (phone numbers, email addresses), agent license numbers, lockbox/access details, or";
        $lines[] = "> private showing instructions. These must not be used in matching, Ask AI context, or any";
        $lines[] = "> public-facing feature without legal review. Example values for these fields are redacted below.";
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";
        $lines[] = "## Matching Question Answers";
        $lines[] = "";
        $lines[] = "| Question | Available? | Fields |";
        $lines[] = "|---|---|---|";

        $this->addMatchingQA($lines, $fieldData, "City / County / ZIP",
            ['City', 'CountyOrParish', 'PostalCode', 'StateOrProvince']);
        $this->addMatchingQA($lines, $fieldData, "List price / rent amount",
            ['ListPrice', 'LeaseAmount', 'LeaseAmountFrequency', 'OriginalListPrice']);
        $this->addMatchingQA($lines, $fieldData, "Beds / baths / sqft",
            ['BedroomsTotal', 'BathroomsTotalInteger', 'BathroomsFull', 'BathroomsHalf', 'LivingArea']);
        $this->addMatchingQA($lines, $fieldData, "Property type / subtype",
            ['PropertyType', 'PropertySubType']);
        $this->addMatchingQA($lines, $fieldData, "Amenities (pool, garage, waterfront, view)",
            ['PoolPrivateYN', 'GarageYN', 'WaterfrontYN', 'ViewYN', 'View', 'CommunityFeatures', 'AssociationAmenities']);
        $this->addMatchingQA($lines, $fieldData, "HOA / CDD / flood zone",
            ['AssociationYN', 'AssociationFee', 'CddStatusYN', 'FloodZone', 'FloodZoneCode', 'FloodZoneStableId']);
        $this->addMatchingQA($lines, $fieldData, "Rental vs sale separation",
            ['LeaseConsideredYN', 'LeaseAmount', 'StandardStatus', 'MlsStatus', 'PropertyType']);
        $this->addMatchingQA($lines, $fieldData, "Pet policy",
            ['PetsAllowed']);
        $this->addMatchingQA($lines, $fieldData, "Year built / condition",
            ['YearBuilt', 'PropertyCondition', 'NewConstructionYN']);
        $this->addMatchingQA($lines, $fieldData, "Lot size",
            ['LotSizeAcres', 'LotSizeSquareFeet', 'LotSizeArea']);
        $this->addMatchingQA($lines, $fieldData, "Zoning",
            ['Zoning', 'ZoningDescription']);

        $lines[] = "";

        $gapCandidates = ['SchoolDistrict', 'SchoolElementary', 'SchoolMiddleOrJunior', 'SchoolHigh',
                          'FloodZone', 'FloodZoneCode', 'WalkScore', 'TransitScore'];
        $gaps = array_filter($gapCandidates, fn($g) => !isset($fieldData[$g]));
        if (!empty($gaps)) {
            $lines[] = "**Gaps requiring manual input or supplemental data source:** `" . implode('`, `', array_values($gaps)) . "`";
            $lines[] = "";
        }

        $lines[] = "---";
        $lines[] = "";
        $lines[] = "## Full Field Inventory";
        $lines[] = "";
        $lines[] = "| Field Key | Category | Data Type | Population | Matching Useful? | Example Value |";
        $lines[] = "|---|---|---|---|---|---|";

        foreach ($fieldData as $key => $data) {
            $isCompliance = $this->isComplianceSensitive($key, $data['example']);
            $category     = $isCompliance ? 'Compliance/Restricted' : $this->categorize($key);
            $type         = $this->inferType($data['example']);
            $pop          = $data['count'] . '/' . $actual;
            $useful       = ($isCompliance) ? 'No (restricted)' : (in_array($key, self::MATCHING_USEFUL, true) ? 'Yes' : 'No');
            $example      = $isCompliance ? '`[REDACTED]`' : $this->formatExample($data['example']);

            $lines[] = "| `{$key}` | {$category} | {$type} | {$pop} | {$useful} | {$example} |";
        }

        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";
        $lines[] = "## Empty / Unreliable Fields (0 populated in sample)";
        $lines[] = "";
        if (empty($empty)) {
            $lines[] = "_All returned fields had at least one non-null value in this sample._";
        } else {
            $lines[] = "| Field Key | Category |";
            $lines[] = "|---|---|";
            foreach (array_keys($empty) as $key) {
                $cat = $this->isComplianceSensitive($key, null) ? 'Compliance/Restricted' : $this->categorize($key);
                $lines[] = "| `{$key}` | {$cat} |";
            }
        }

        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";
        $lines[] = "## Compliance-Sensitive Exclusions";
        $lines[] = "";
        $lines[] = "The following fields were found in the dataset but **must not be used in matching, Ask AI, or";
        $lines[] = "public-facing features** without legal review. Example values are redacted throughout this report.";
        $lines[] = "";
        $lines[] = "Detection rules (key-pattern layer):";
        $lines[] = "- Key contains `Phone` → agent/tenant/call-center phone numbers";
        $lines[] = "- Key contains `Email` → email addresses (unless HOA/Association on allow-list)";
        $lines[] = "- Key contains `License` → agent or builder license numbers";
        $lines[] = "- Key starts with or contains `LockBox` → lockbox type/location/serial";
        $lines[] = "- Key contains `PrivateRemarks` → agent-only private notes";
        $lines[] = "- Key contains `ContactPreferred` or `Contact{Name|Phone|Type|Info|Number|Method}` → contact data fields";
        $lines[] = "- Key contains `ShowingInstruction|ShowingContact|ShowingRequirement|ShowingConsideration` → private access details";
        $lines[] = "";
        $lines[] = "Value-based backstop (catches ambiguous key names):";
        $lines[] = "- Field's example value matches US phone number pattern → flagged as restricted";
        $lines[] = "- Field's example value matches email address pattern → flagged as restricted";
        $lines[] = "";
        if (empty($complianceFound)) {
            $lines[] = "_None of the pre-flagged compliance fields appeared in this sample._";
        } else {
            $lines[] = "| Field Key | Reason |";
            $lines[] = "|---|---|";
            foreach ($complianceFound as $key) {
                $lines[] = "| `{$key}` | " . $this->complianceReason($key, $fieldData[$key]['example']) . " |";
            }
        }

        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";
        $lines[] = "## Proof Block";
        $lines[] = "";
        $lines[] = "| Item | Value |";
        $lines[] = "|---|---|";
        $lines[] = "| Command run | `php artisan bridge:audit-fields --limit={$requested}` |";
        $lines[] = "| Records received | {$actual} |";
        $lines[] = "| Total unique field keys | " . count($fieldData) . " |";
        $lines[] = "| Reliably populated fields (≥50%) | " . count($populated) . " |";
        $lines[] = "| Sparse fields (<50%) | " . count($sparse) . " |";
        $lines[] = "| Always-empty fields | " . count($empty) . " |";
        $lines[] = "| Compliance-flagged fields found | " . count($complianceFound) . " |";
        $lines[] = "| Existing tables modified | **None** |";
        $lines[] = "| Migrations created | **None** |";
        $lines[] = "";

        $topMatching = array_filter(array_keys($fieldData), fn($k) => in_array($k, self::MATCHING_USEFUL, true));
        $lines[] = "**Top matching-ready fields found in sample:**";
        $lines[] = "";
        $lines[] = implode(', ', array_map(fn($k) => "`{$k}`", array_values($topMatching)));
        $lines[] = "";

        return implode("\n", $lines) . "\n";
    }

    private function addMatchingQA(array &$lines, array $fieldData, string $question, array $candidates): void
    {
        $found = [];
        foreach ($candidates as $c) {
            if (isset($fieldData[$c]) && !$this->isComplianceSensitive($c, $fieldData[$c]['example'])) {
                $found[] = "`{$c}`";
            }
        }
        $available = empty($found) ? '❌ No' : '✅ Yes';
        $fields    = empty($found) ? '—' : implode(', ', $found);
        $lines[] = "| {$question} | {$available} | {$fields} |";
    }

    private function categorize(string $key): string
    {
        foreach (self::CATEGORIES as $cat => $keys) {
            if (in_array($key, $keys, true)) {
                return $cat;
            }
        }
        foreach (self::CATEGORIES as $cat => $keys) {
            foreach ($keys as $k) {
                if (stripos($key, $k) !== false || stripos($k, $key) !== false) {
                    return $cat;
                }
            }
        }
        return 'Unknown';
    }

    private function complianceReason(string $key, mixed $exampleValue = null): string
    {
        if (preg_match('/Phone/i', $key))                    return 'Phone number — personal contact data';
        if (preg_match('/Email/i', $key))                    return 'Email address — personal contact data';
        if (preg_match('/License/i', $key))                  return 'License number — regulatory identifier';
        if (preg_match('/LockBox/i', $key))                  return 'Lockbox details — private property access';
        if (preg_match('/PrivateRemarks/i', $key))           return 'Private remarks — agent-only notes';
        if (preg_match('/ContactPreferred/i', $key))         return 'Preferred contact field — stores phone/email value';
        if (preg_match('/Contact(?:Name|Phone|Type|Info|Number|Method)/i', $key)) return 'Contact sub-field — personal contact data';
        if (preg_match('/ShowingInstruction/i', $key))       return 'Showing instructions — private access details';
        if (preg_match('/ShowingContact/i', $key))           return 'Showing contact — personal contact data';
        if (preg_match('/ShowingRequirement/i', $key))       return 'Showing requirements — private access details';
        if (preg_match('/ShowingConsideration/i', $key))     return 'Showing considerations — private notes';
        if (preg_match('/^OwnerName$/i', $key))              return 'Owner name — PII';
        if (preg_match('/^OwnerPhone$/i', $key))             return 'Owner phone — PII';
        // Value-based detection
        if (is_string($exampleValue)) {
            $trimmed = trim($exampleValue);
            if (preg_match('/^\+?1?[-.\s]?\(?\d{3}\)?[-.\s]\d{3}[-.\s]\d{4}$/', $trimmed)) {
                return 'Value is a phone number — personal contact data detected via value heuristic';
            }
            if (preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $trimmed)) {
                return 'Value is an email address — personal contact data detected via value heuristic';
            }
        }
        return 'Compliance-sensitive field';
    }

    private function inferType(mixed $value): string
    {
        if (is_null($value))    return 'null';
        if (is_bool($value))    return 'boolean';
        if (is_int($value))     return 'integer';
        if (is_float($value))   return 'float';
        if (is_array($value))   return 'array';
        if (is_string($value)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) return 'datetime';
            if (is_numeric($value)) return 'numeric string';
            return 'string';
        }
        return gettype($value);
    }

    private function formatExample(mixed $value): string
    {
        if (is_null($value))  return '—';
        if (is_bool($value))  return $value ? 'true' : 'false';
        if (is_array($value)) {
            $str = json_encode(array_slice($value, 0, 2));
            return '`' . addslashes(mb_substr($str, 0, 60)) . (strlen($str) > 60 ? '…' : '') . '`';
        }
        $str = (string) $value;
        $str = str_replace(['|', "\n", "\r"], ['\|', ' ', ' '], $str);
        $str = mb_substr($str, 0, 70);
        return '`' . $str . '`';
    }
}
