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

    protected static function getCanonicalOrder(string $flowKey): array
    {
        $parts = explode('.', $flowKey);
        if (count($parts) !== 2) {
            return [];
        }

        $config = config('buyer_services_order');
        
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
                        $flat[] = trim($subItem);
                    }
                }
            } elseif (is_string($item) && trim($item) !== '') {
                $flat[] = trim($item);
            }
        }

        return array_values(array_unique($flat));
    }

    protected static function normalizeString(string $str): string
    {
        $str = preg_replace('/[\x{2018}\x{2019}]/u', "'", $str);
        $str = preg_replace('/[\x{201C}\x{201D}]/u', '"', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        $str = trim($str);
        return mb_strtolower($str, 'UTF-8');
    }
}
