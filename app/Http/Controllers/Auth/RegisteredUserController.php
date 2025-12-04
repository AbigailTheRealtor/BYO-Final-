<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.signup');
    }

    /**
     * Handle an incoming registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {

        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_number' =>  ['required'],
            'password' => ['required', 'string', Rules\Password::defaults()],
            'user_type' => ['required', 'string', 'max:255'],
            'terms' => ['required'],
        ], [
            'terms.required' => 'Please accept terms and conditions!'
        ]);

        // dd($request->user_name);

        // $names = explode(" ", $request->name);
        // $first_name = current($names);
        // $last_name = end($names);

        $fullNameParts = [
            $request->first_name,
            $request->middle_name,
            $request->last_name,
        ];

        // Filter out null or empty values
        $fullName = implode(' ', array_filter($fullNameParts));

        $user = User::create([
            'name' => $fullName,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'user_name' => $request->user_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type,
            'mls_id' => $request->mls_id,
            'email_verified_at' => Carbon::now(),
        ]);
        $user->saveMeta("name", $fullName);
        $user->saveMeta("first_name", $request->first_name);
        $user->saveMeta("last_name", $request->last_name);
        $user->saveMeta("user_type", $request->user_type);
        $user->saveMeta("license_no", $request->license_no);
        $user->saveMeta("license_date", $request->license_date);
        $user->saveMeta("nar_id", $request->nar_id);
        $user->saveMeta("brokerage", $request->brokerage);
        $user->saveMeta("office_building_no", $request->office_building_no);
        $user->saveMeta("office_suite_no", $request->office_suite_no);
        $user->saveMeta("office_zip", $request->office_zip);
        $user->saveMeta("total_transactions", $request->total_transactions);
        $user->saveMeta("sales_address", $request->sales_address);
        $user->saveMeta("sales_zip", $request->sales_zip);
        $user->saveMeta("sales_price", $request->sales_price);
        $user->saveMeta("realtor_profile", $request->realtor_profile);
        $user->saveMeta("email", $request->email);
        $user->saveMeta("phone_number", $request->phone_number);
        $user->saveMeta("user_name", $request->user_name);
        $user->saveMeta("mls_id", $request->mls_id);
        $user->saveMeta("office_address", $request->office_address);
        $user->saveMeta("city", $request->city);
        $user->saveMeta("county", $request->county);
        $user->saveMeta("state", $request->state);
        // dd($user);
        // event(new Registered($user));
        Auth::login($user);
        return redirect(RouteServiceProvider::HOME);
    }
}
