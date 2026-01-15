<?php

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use \SimpleSoftwareIO\QrCode\Facades\QrCode;

if (!function_exists('db_time')) {
    function db_time()
    {
        $dt = DB::select(DB::raw("SELECT NOW() as curDate"));
        return $dt[0]->curDate;
    }

    function get_setting($key)
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting) {
            return $setting->value;
        } else {
            return false;
        }
    }

    function selected($val, $matched, $selected = "selected")
    {
        if ($val == $matched) {
            return $selected;
        } else {
            return false;
        }
    }

    function selected_in($val, $array, $selected = "selected")
    {
        if (is_string($array)) {
            $array = json_decode($array, true) ?? [];
        }
        if (!is_array($array)) {
            $array = [];
        }
        if (in_array($val, $array)) {
            return $selected;
        } else {
            return false;
        }
    }

    function is_hidden($val, $class = "d-none")
    {
        if ($val == "" || $val == "null") {
            return $class;
        } else {
            return "";
        }
    }


    function checked($val, $matched, $selected = "checked")
    {
        if ($val == $matched) {
            return $selected;
        } else {
            return false;
        }
    }

    function checked_in($val, $array, $selected = "checked")
    {
        if (in_array($val, $array)) {
            return $selected;
        } else {
            return false;
        }
    }

    function qr_code($uri, $size = 150)
    {
        return QrCode::size($size)->generate($uri);
    }

    /**
     * Normalize service text for display - applies fallback mapping for legacy data.
     * This function translates old service text to new standardized text.
     * For Commercial Tenant Agent services only.
     * 
     * @param string $service The service text from database
     * @return string Normalized service text for display
     */
    function normalize_service_text($service)
    {
        // Mapping of old service text to new standardized text
        $serviceMappings = [
            // Commercial Tenant Agent - Property Search service text update
            "Send listing alerts from commercial platforms (e.g., LoopNet, Crexi, CoStar, or local MLS) that match the Tenant's leasing criteria" 
                => "Send listing alerts from real estate platforms that match the Tenant's leasing criteria.",
            // Buyer Commercial - Property Search service text update
            "Send property alerts that match the Buyer's purchase criteria from the MLS or commercial listing platforms"
                => "Send listing alerts from real estate platforms that match the Buyer's purchase criteria.",
            // Buyer Business Opportunity - Business Search service text update
            "Send alerts for businesses that match the Buyer's acquisition criteria from MLS, BizBuySell, or other listing platforms"
                => "Send alerts for businesses that match the Buyer's acquisition criteria from available business listing sources.",
            // Buyer Vacant Land - Property Search service text update
            "Send property alerts for land listings that match the Buyer's goals from MLS and land-specific platforms"
                => "Send property alerts for land listings that match the Buyer's goals from relevant real estate and land-specific platforms.",
        ];
        
        // Check if the service matches any old text (case-insensitive normalized comparison)
        foreach ($serviceMappings as $oldText => $newText) {
            // Normalize both strings for comparison (handle smart quotes vs regular quotes)
            $normalizedService = preg_replace('/[\x{2018}\x{2019}]/u', "'", $service);
            $normalizedOld = preg_replace('/[\x{2018}\x{2019}]/u', "'", $oldText);
            
            if (strcasecmp(trim($normalizedService), trim($normalizedOld)) === 0) {
                return $newText;
            }
        }
        
        return $service;
    }

    /**
     * Normalize an array of services for display.
     * Applies fallback mapping for legacy data.
     * 
     * @param array $services Array of service texts
     * @return array Normalized service texts
     */
    function normalize_services_array($services)
    {
        if (!is_array($services)) {
            return $services;
        }
        
        return array_map('normalize_service_text', $services);
    }
}
