<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentDefaultProfile extends Model
{
    const ROLE_DEFAULT = '__default__';

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

    public static function findRoleDefault(int $userId, string $roleType): ?self
    {
        return static::findForAgent($userId, $roleType, static::ROLE_DEFAULT);
    }

    public static function findForAgentWithFallback(int $userId, string $roleType, string $propertyType): ?self
    {
        $profile = static::findForAgent($userId, $roleType, $propertyType);
        if ($profile) {
            return $profile;
        }
        return static::findRoleDefault($userId, $roleType);
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

    public static function upsertRoleDefault(int $userId, string $roleType, array $data): self
    {
        return static::upsertForAgent($userId, $roleType, static::ROLE_DEFAULT, $data);
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
        if ($property === static::ROLE_DEFAULT) {
            return 'All Property Types (Role Default)';
        }
        return [
            'residential' => 'Residential',
            'income'      => 'Income',
            'commercial'  => 'Commercial',
            'business'    => 'Business',
            'vacant_land' => 'Vacant Land',
        ][$property] ?? ucfirst(str_replace('_', ' ', $property));
    }
}
