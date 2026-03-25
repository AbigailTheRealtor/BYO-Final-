<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentDefaultProfile extends Model
{
    protected $table = 'agent_default_profiles';

    protected $fillable = [
        'user_id',
        'role_type',
        'property_type',
        'profile_data',
    ];

    protected $casts = [
        'profile_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function findForAgent(int $userId, string $roleType, string $propertyType): ?self
    {
        return static::where('user_id', $userId)
            ->where('role_type', $roleType)
            ->where('property_type', $propertyType)
            ->first();
    }

    public static function upsertForAgent(int $userId, string $roleType, string $propertyType, array $data): self
    {
        $profile = static::firstOrNew([
            'user_id'       => $userId,
            'role_type'     => $roleType,
            'property_type' => $propertyType,
        ]);
        $profile->profile_data = $data;
        $profile->save();
        return $profile;
    }

    public function getData(string $key, $default = null)
    {
        return $this->profile_data[$key] ?? $default;
    }

    public static function roleLabel(string $role): string
    {
        return [
            'tenant'   => 'Hire a Tenant Agent',
            'landlord' => 'Hire a Landlord Agent',
            'seller'   => 'Hire a Seller Agent',
            'buyer'    => 'Hire a Buyer Agent',
        ][$role] ?? ucfirst($role);
    }

    public static function propertyLabel(string $property): string
    {
        return [
            'residential' => 'Residential',
            'income'      => 'Income',
            'commercial'  => 'Commercial',
            'business'    => 'Business',
            'vacant_land' => 'Vacant Land',
        ][$property] ?? ucfirst(str_replace('_', ' ', $property));
    }
}
