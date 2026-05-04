<?php

namespace App\Helpers;

class OfferListingViewHelper
{
    public static function row(string $label, $val, string $col = 'col-md-4'): void
    {
        if ($val === null || $val === '' || $val === false) {
            return;
        }
        echo '<div class="' . $col . '">'
            . '<div class="text-muted small mb-1">' . e($label) . '</div>'
            . '<div>' . e($val) . '</div>'
            . '</div>';
    }

    public static function badge(string $label, $val, string $col = 'col-md-4'): void
    {
        if ($val === null || $val === '' || $val === false) {
            return;
        }
        $cls = $val === 'Yes' ? 'bg-success' : ($val === 'No' ? 'bg-secondary' : 'bg-info text-dark');
        echo '<div class="' . $col . '">'
            . '<div class="text-muted small mb-1">' . e($label) . '</div>'
            . '<span class="badge ' . $cls . '">' . e($val) . '</span>'
            . '</div>';
    }

    public static function tags(string $label, array $arr, string $col = 'col-12'): void
    {
        $arr = array_filter($arr);
        if (empty($arr)) {
            return;
        }
        echo '<div class="' . $col . '"><div class="text-muted small mb-1">' . e($label) . '</div>'
            . '<div class="d-flex flex-wrap gap-1">';
        foreach ($arr as $item) {
            echo '<span class="badge bg-light text-dark border">' . e($item) . '</span>';
        }
        echo '</div></div>';
    }

    public static function section(string $icon, string $title): void
    {
        echo '<div class="card border-0 shadow-sm mb-4">'
            . '<div class="card-header bg-white fw-semibold py-3" style="border-bottom:1px solid #f0f0f0;">'
            . '<i class="' . $icon . ' me-2" style="color:#049399;"></i>' . e($title)
            . '</div><div class="card-body"><div class="row g-3">';
    }

    public static function sectionEnd(): void
    {
        echo '</div></div></div>';
    }
}
