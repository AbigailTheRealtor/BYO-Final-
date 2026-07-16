<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\ReferralLinkService;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
            // Whitelist self-registerable roles. 'admin' (and internal roles
            // like seller_agent/buyer_agent) must never be assignable from a
            // public registration request — admin gating trusts user_type.
            'user_type' => ['required', 'string', Rule::in(['seller', 'buyer', 'landlord', 'tenant', 'agent'])],
            'terms' => ['required'],
        ], [
            'terms.required' => 'Please accept terms and conditions!',
            'user_type.in' => 'Please choose a valid account type.',
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
        // Phase 5 — persist referral attribution if session contains one.
        ReferralLinkService::persistSignup($user->id);

        // dd($user);
        // event(new Registered($user));
        Auth::login($user);
        return redirect(RouteServiceProvider::HOME);
    }
}
