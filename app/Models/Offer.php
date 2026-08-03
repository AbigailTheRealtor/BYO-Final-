<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'offer_auction_id',
        'parent_offer_id',
        'role',
        'listing_snapshot',
        'status',
        'submitted_at',
        'expires_at',
    ];

    protected $casts = [
        'listing_snapshot' => 'array',
        'submitted_at'     => 'datetime',
        'expires_at'       => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function offerAuction()
    {
        return $this->belongsTo(OfferAuction::class, 'offer_auction_id');
    }

    public function parentOffer()
    {
        return $this->belongsTo(Offer::class, 'parent_offer_id');
    }

    public function childOffers()
    {
        return $this->hasMany(Offer::class, 'parent_offer_id');
    }

    /**
     * Is this offer a counteroffer rather than an original submission?
     *
     * The parent link is the single source of truth. OfferCounterService writes
     * `parent_offer_id` on the child it creates and on nothing else, so a row
     * carrying one is a counter and a row without one is an original.
     *
     * Status is NOT a discriminator. When A counters B, the SERVICE sets B's own
     * status to 'countered' while leaving B's parent_offer_id null — B remains an
     * original that happens to have been countered. Reading 'countered' as "this
     * is a counteroffer" mislabels every such original.
     */
    public function isCounterOffer(): bool
    {
        return $this->parent_offer_id !== null;
    }

    public function metas()
    {
        return $this->hasMany(OfferMeta::class);
    }

    public function eventLogs()
    {
        return $this->hasMany(OfferEventLog::class);
    }

    public function saveMeta(string $key, mixed $value): void
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        $this->metas()->updateOrCreate(['meta_key' => $key], ['meta_value' => $value]);
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        $meta = $this->metas->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : $default;
    }
}
