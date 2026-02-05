<?php

namespace App\Support;

class CompensationFormatter
{
    public static function formatRetainerFeeApplication($rawValue): string
    {
        if (empty($rawValue)) {
            return '';
        }

        $normalized = strtolower(trim($rawValue));

        $appliedValues = [
            'applied',
            'apply_to_final',
            'credited',
            'credited_to_final',
            'applied toward final compensation',
        ];

        $additionalValues = [
            'additional',
            'in_addition',
            'charged_additional',
            'charged in addition to final compensation',
        ];

        if (in_array($normalized, $appliedValues, true)) {
            return 'Applied toward final compensation';
        }

        if (in_array($normalized, $additionalValues, true)) {
            return 'Charged in addition to final compensation';
        }

        return '';
    }
}
