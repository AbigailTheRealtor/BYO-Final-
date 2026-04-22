<?php

namespace App\Http\Controllers;

use App\Services\ReferralLinkService;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class SocialAuth extends Controller
{
    public function facebookIndex(){
        return Socialite::driver('facebook')->stateless()->redirect();
    }

    public function facebookCallback(){
        $facebookUser = Socialite::driver('facebook')->stateless()->user();

        $user = User::where('email', $facebookUser->getEmail())->first();

        $isNewUser = false;
        if (!$user) {
            $user = User::create([
                'name' => $facebookUser->getName(),
                'email' => $facebookUser->getEmail(),
                'password' => bcrypt(uniqid()), // Create a random password
            ]);
            $isNewUser = true;
        }

        // Phase 5 — persist referral attribution for brand-new social signups.
        if ($isNewUser) {
            ReferralLinkService::persistSignup($user->id);
        }

        Auth::login($user, true);

        return redirect()->intended('/');
    }

    public function googleIndex()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function googleCallback(){
        
        $googleUser = Socialite::driver('google')->stateless()->user();
        
        $user = User::where('email', $googleUser->getEmail())->first();

        $isNewUser = false;
        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'password' => bcrypt(uniqid()), // Create a random password
            ]);
            $isNewUser = true;
        }

        // Phase 5 — persist referral attribution for brand-new social signups.
        if ($isNewUser) {
            ReferralLinkService::persistSignup($user->id);
        }

        Auth::login($user, true);

        return redirect()->intended('/');
    }
}
