<?php

namespace App\Models;

use App\Models\Concerns\ScopesListingWorkflow;
use App\Traits\HasListingId;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerAgentAuction extends Model
{
    use HasFactory, HasListingId;

    // Product-scoped query narrowing for the Hire Agent / Offer Listing split that
    // shares this table. See ScopesListingWorkflow — it is a PRE-filter, not the
    // whole rule; ListingWorkflowResolver decides.
    use ScopesListingWorkflow;
    protected $guarded = [];
    protected $appends = ["get", "status"];
    protected $with = ['meta'];
    
    protected $casts = [
        'is_draft'    => 'boolean',
        'is_approved' => 'boolean',
    ];

    protected $attributes = [
        'is_approved' => true,
        'is_draft'    => false,
        'is_sold'     => false,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isCreatedByAgent(): bool
    {
        return optional($this->user)->user_type === 'agent';
    }

    public function bot_questions()
    {
        return $this->morphMany(BotQuestion::class, 'auction');
    }


    public function unanswered_bot_questions()
    {
        return $this->morphMany(UnansweredBotQuestion::class, 'auction');
    }

    public function chat_tokens()
    {
        return $this->morphMany(AuctionChatToken::class, 'auction');
    }

    public function bids()
    {
        return $this->hasMany(SellerAgentAuctionBid::class);
    }

    public function meta()
    {
        return $this->hasMany(SellerAgentAuctionMeta::class);
    }

    public function saveMeta($key, $val)
    {
        if (is_array($val) || is_object($val)) {
            $val = json_encode($val);
        }
        return $this->meta()->updateOrCreate(["meta_key" => $key], ["meta_value" => $val]);
    }
public function deleteMeta($key)
    {
        return $this->meta()->where('meta_key', $key)->delete();
    }
    public function info($key)
    {
        $data = $this->meta->where('meta_key', $key);
        if ($data->count() > 0) {
            return $data->first()->meta_value;
        } else {
            return false;
        }
    }

    public function getStatusAttribute()
    {
        $isSold = \App\Support\Listing\ListingFlag::isTrue($this->is_sold);
        if ($isSold) {
            return 'Hired Agent';
        }
        // An MLS-linked listing's market status is Stellar's, not ours.
        //
        // Checked before `listing_status` and before `expiration_date` because
        // those are precisely the two BidYourOffer mechanisms the live-sync
        // contract forbids from overriding the feed. A property Stellar still
        // lists as Active must not read 'Expired' here because of a date the
        // seller typed into a form weeks ago.
        //
        // Returns null for a manual listing, and for an MLS listing whose source
        // status has never been stored — both then fall through to the platform
        // logic below, unchanged.
        $mlsStatus = \App\Support\Listing\MlsLinkedListingStatus::marketStatus($this->get->toArray());
        if ($mlsStatus !== null) {
            return $mlsStatus;
        }

        $metaStatus = $this->info('listing_status');
        if ($metaStatus === 'Hired Agent') {
            return 'Hired Agent';
        }
        if ($metaStatus === 'Pending') {
            return 'Pending';
        }
        $expirationDate = $this->info('expiration_date');
        if ($expirationDate && \Carbon\Carbon::now()->gte(\Carbon\Carbon::parse($expirationDate))) {
            return 'Expired';
        }
        return 'Active';
    }

    public function getGetAttribute()
    {
        $data = [];
        $metas = $this->meta;
        foreach ($metas as $row) {
            if ($row->meta_value === null) {
                $data[$row->meta_key] = null;
                continue;
            }
            $decoded = json_decode($row->meta_value, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                $value = $decoded;
            } else {
                $value = $row->meta_value;
            }
            $data[$row->meta_key] = $value;
        }
        return new class($data) {
            private $data;
            public function __construct($data) { $this->data = $data; }
            public function __get($name) { return $this->data[$name] ?? null; }
            public function __isset($name) { return isset($this->data[$name]); }
            public function toArray(): array { return $this->data; }
        };
    }
}
