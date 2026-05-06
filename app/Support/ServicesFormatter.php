<?php

namespace App\Support;

class ServicesFormatter
{
    public static function orderSelectedServices($selectedServices, string $flowKey): array
    {
        $normalized = self::normalizeServices($selectedServices);
        
        if (empty($normalized)) {
            return [];
        }

        $canonicalOrder = self::getCanonicalOrder($flowKey);
        
        if (empty($canonicalOrder)) {
            return $normalized;
        }

        $orderedResult = [];
        $matched = [];

        foreach ($canonicalOrder as $category => $items) {
            if ($category === '✍️ Additional Services') {
                continue;
            }

            $categoryServices = [];
            foreach ($items as $item) {
                $normalizedItem = self::normalizeString($item);
                foreach ($normalized as $selected) {
                    $normalizedSelected = self::normalizeString($selected);
                    if ($normalizedItem === $normalizedSelected) {
                        $categoryServices[] = $selected;
                        $matched[] = $normalizedSelected;
                        break;
                    }
                }
            }

            if (!empty($categoryServices)) {
                $orderedResult[$category] = $categoryServices;
            }
        }

        $additionalServices = [];
        foreach ($normalized as $selected) {
            $normalizedSelected = self::normalizeString($selected);
            if (!in_array($normalizedSelected, $matched)) {
                $additionalServices[] = $selected;
            }
        }

        if (!empty($additionalServices)) {
            $orderedResult['✍️ Additional Services'] = $additionalServices;
        }

        return $orderedResult;
    }

    public static function getFlatOrderedServices($selectedServices, string $flowKey): array
    {
        $grouped = self::orderSelectedServices($selectedServices, $flowKey);
        $flat = [];
        
        foreach ($grouped as $category => $items) {
            foreach ($items as $item) {
                $flat[] = $item;
            }
        }
        
        return $flat;
    }

    public static function keyForBuyerAgent(string $propertyType): string
    {
        $map = [
            'Residential' => 'buyer_agent.residential',
            'Income' => 'buyer_agent.income',
            'Commercial' => 'buyer_agent.commercial',
            'Business' => 'buyer_agent.business',
            'Vacant Land' => 'buyer_agent.vacant_land',
        ];

        return $map[$propertyType] ?? 'buyer_agent.residential';
    }

    public static function keyForSellerAgent(string $propertyType): string
    {
        $map = [
            'Residential' => 'seller_agent.residential',
            'Income' => 'seller_agent.income',
            'Commercial' => 'seller_agent.commercial',
            'Business' => 'seller_agent.business',
            'Vacant Land' => 'seller_agent.vacant_land',
        ];

        return $map[$propertyType] ?? 'seller_agent.residential';
    }

    public static function keyForLandlordAgent(string $propertyType): string
    {
        $map = [
            'Residential'          => 'landlord_agent.residential',
            'Residential Property' => 'landlord_agent.residential',
            'Commercial'           => 'landlord_agent.commercial',
            'Commercial Property'  => 'landlord_agent.commercial',
        ];

        return $map[$propertyType] ?? 'landlord_agent.residential';
    }

    public static function keyForTenantAgent(string $propertyType): string
    {
        $map = [
            'Residential'          => 'tenant_agent.residential',
            'Residential Property' => 'tenant_agent.residential',
            'Commercial'           => 'tenant_agent.commercial',
            'Commercial Property'  => 'tenant_agent.commercial',
        ];

        return $map[$propertyType] ?? 'tenant_agent.residential';
    }

    /**
     * Return the full category => [services] catalog for the given flow key.
     * Used by the preset editor to render grouped checkboxes.
     */
    public static function groupedCatalog(string $flowKey): array
    {
        return self::getCanonicalOrder($flowKey);
    }

    protected static function getCanonicalOrder(string $flowKey): array
    {
        $parts = explode('.', $flowKey);
        if (count($parts) !== 2) {
            return [];
        }

        $configMap = [
            'buyer_agent'    => 'buyer_services_order',
            'seller_agent'   => 'seller_services_order',
            'landlord_agent' => 'landlord_services_order',
            'tenant_agent'   => 'tenant_services_order',
        ];

        $configName = $configMap[$parts[0]] ?? null;
        if (!$configName) {
            return [];
        }

        $config = config($configName);

        if (!$config) {
            return [];
        }

        return $config[$parts[0]][$parts[1]] ?? [];
    }

    protected static function normalizeServices($services): array
    {
        if (empty($services)) {
            return [];
        }

        if (is_string($services)) {
            $decoded = json_decode($services, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $services = $decoded;
            } else {
                $services = [$services];
            }
        }

        if (is_object($services)) {
            $services = (array) $services;
        }

        if (!is_array($services)) {
            return [];
        }

        $flat = [];
        foreach ($services as $item) {
            if (is_array($item)) {
                foreach ($item as $subItem) {
                    if (is_string($subItem) && trim($subItem) !== '') {
                        $flat[] = self::decodeServiceLabel(trim($subItem));
                    }
                }
            } elseif (is_string($item) && trim($item) !== '') {
                $flat[] = self::decodeServiceLabel(trim($item));
            }
        }

        return array_values(array_unique($flat));
    }

    protected static function normalizeString(string $str): string
    {
        // If the string contains literal JSON unicode escape sequences (e.g. \u2019
        // stored as the six characters \, u, 2, 0, 1, 9) rather than the decoded
        // Unicode character, decode them first so comparisons always work correctly.
        if (strpos($str, '\u') !== false || strpos($str, '\U') !== false) {
            $decoded = json_decode('"' . str_replace('"', '\\"', $str) . '"');
            if ($decoded !== null && is_string($decoded)) {
                $str = $decoded;
            }
        }

        $str = preg_replace('/[\x{2018}\x{2019}]/u', "'", $str);
        $str = preg_replace('/[\x{201C}\x{201D}]/u', '"', $str);
        $str = preg_replace('/[\x{2013}\x{2014}]/u', '-', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        $str = trim($str);
        return mb_strtolower($str, 'UTF-8');
    }

    /**
     * Decode any literal JSON unicode escape sequences (e.g. \u2019 stored as
     * six raw characters) in a service label string so they render as the
     * correct UTF-8 character rather than as raw escape text.
     *
     * Safe to call on already-correct UTF-8 strings — no double-decoding occurs.
     */
    public static function decodeServiceLabel(string $str): string
    {
        if (strpos($str, '\u') === false && strpos($str, '\U') === false) {
            return $str;
        }
        $decoded = json_decode('"' . str_replace('"', '\\"', $str) . '"');
        return ($decoded !== null && is_string($decoded)) ? $decoded : $str;
    }
}
