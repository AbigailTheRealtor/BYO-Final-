<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function creating(User $user)
    {
        if (empty($user->short_id)) {
            $user->short_id = static::generateUniqueShortId();
        }

        if (empty($user->user_name)) {
            $user->user_name = $user->email ?? uniqid('user_');
        }

        if (empty($user->phone_number)) {
            $user->phone_number = $user->phone ?? '';
        }
    }

    public function retrieved(User $user)
    {
        if (empty($user->short_id)) {
            $user->short_id = static::generateUniqueShortId();
            $user->save();
        }
    }

    /**
     * Generate a hex short_id guaranteed to be unique in the users table.
     * Uses bin2hex(random_bytes(6)) for cryptographic randomness (no timestamp bias).
     * Loops until a non-colliding value is found (virtually instant in practice).
     */
    protected static function generateUniqueShortId(): string
    {
        do {
            $shortId = bin2hex(random_bytes(6));
        } while (User::where('short_id', $shortId)->exists());

        return $shortId;
    }
}
