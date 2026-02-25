<?php

namespace App\Exports;

class ListingPdfDataBuilder
{
    public static function build(object $meta, array $sections, array $otherPairs = [], array $normalizers = []): array
    {
        $out = [];

        foreach ($sections as $sectionTitle => $fields) {
            $rows = [];

            foreach ($fields as $label => $key) {
                $value = self::getValue($meta, $key);

                if (isset($normalizers[$key]) && is_callable($normalizers[$key])) {
                    $ref = new \ReflectionFunction($normalizers[$key]);
                    if ($ref->getNumberOfParameters() >= 2) {
                        $value = $normalizers[$key]($value, $meta);
                    } else {
                        $value = $normalizers[$key]($value);
                    }
                }

                if (isset($otherPairs[$key])) {
                    $otherValue = self::getValue($meta, $otherPairs[$key]);
                    $items = ListingExportFormatter::resolveOther($value, $otherValue);
                } else {
                    $items = ListingExportFormatter::toList($value);
                }

                if (count($items) === 0) continue;

                $rows[] = [
                    'label' => $label,
                    'items' => $items,
                ];
            }

            if (count($rows)) {
                $out[] = [
                    'title' => $sectionTitle,
                    'rows' => $rows,
                ];
            }
        }

        return $out;
    }

    protected static function getValue(object $meta, string $key)
    {
        return $meta->$key ?? null;
    }
}
