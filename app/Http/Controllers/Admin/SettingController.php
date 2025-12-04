<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['title' => 'required']);
        $allowedPhotos=['jpg','png','jpeg','gif','svg'];
        // print_r($request->input());

        $setting = new Setting();
        if(Setting::where('key','title')->count() > 0)
        {
            $setting = Setting::where('key','title')->first();
        }
        $setting->key = 'title';
        $setting->value = $request->title;
        $setting->save();

        $setting = new Setting();
        if(Setting::where('key','footer_text')->count() > 0)
        {
            $setting = Setting::where('key','footer_text')->first();
        }
        $setting->key = 'footer_text';
        $setting->value = $request->footer_text;
        $setting->save();


        if ($request->hasFile('logo')) {
            $logo = $request->logo;
            $extension = $logo->getClientOriginalExtension();
            $check = in_array($extension, $allowedPhotos);
            if ($check) {
                $uuid = (string) Str::uuid();
                $logoName = $uuid . '.' . $extension;
                $logo->move(public_path('images'), $logoName);

                $setting = new Setting();
                if(Setting::where('key','logo')->count() > 0)
                {
                    $setting = Setting::where('key','logo')->first();
                }
                $setting->key = 'logo';
                $setting->value = '/images/'.$logoName;
                $setting->save();
            }
        }
        if($request->hasFile('favicon') )
        {
            $favicon = $request->favicon;
            $extension = $favicon->getClientOriginalExtension();
            $check = in_array($extension, $allowedPhotos);
            if ($check) {
                $uuid = (string) Str::uuid();
                $faviconName = $uuid . '.' . $extension;
                $favicon->move(public_path('images'), $faviconName);

                $setting = new Setting();
                if(Setting::where('key','favicon')->count() > 0)
                {
                    $setting = Setting::where('key','favicon')->first();
                }
                $setting->key = 'favicon';
                $setting->value = '/images/'.$faviconName;
                $setting->save();
            }
        }

        return redirect()->back()->with('success', 'Settings saved successfully!');

    }
}
