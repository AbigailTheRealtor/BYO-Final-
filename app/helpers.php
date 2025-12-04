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
}
