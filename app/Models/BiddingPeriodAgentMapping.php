<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiddingPeriodAgentMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'auction_type',
        'agent_user_id',
        'anonymous_number',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public static function getOrCreateMapping($auctionId, $auctionType, $agentUserId)
    {
        $existing = self::where('auction_id', $auctionId)
            ->where('auction_type', $auctionType)
            ->where('agent_user_id', $agentUserId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $usedNumbers = self::where('auction_id', $auctionId)
            ->where('auction_type', $auctionType)
            ->pluck('anonymous_number')
            ->toArray();

        $maxAttempts = 100;
        $newNumber = null;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidate = random_int(1, 999);
            if (!in_array($candidate, $usedNumbers)) {
                $newNumber = $candidate;
                break;
            }
        }
        
        if ($newNumber === null) {
            $newNumber = max($usedNumbers) + random_int(1, 50);
        }

        return self::create([
            'auction_id' => $auctionId,
            'auction_type' => $auctionType,
            'agent_user_id' => $agentUserId,
            'anonymous_number' => $newNumber,
        ]);
    }

    public static function getAnonymousLabel($auctionId, $auctionType, $agentUserId)
    {
        $mapping = self::getOrCreateMapping($auctionId, $auctionType, $agentUserId);
        return 'Agent ' . $mapping->anonymous_number;
    }
}
