<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// class User extends Authenticatable implements MustVerifyEmail

class User extends Authenticatable

{
    use HasApiTokens, HasFactory, Notifiable;
    // protected $attributes = ['avatar_url', 'cover_url'];
    // protected $appends = ['avatar_url', 'cover_url'];


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'name',
        'short_id',
        'first_name',
        'middle_name',
        'last_name',
        'user_name',
        'email',
        'password',
        'user_type',
        'mls_id',
        'phone',
        'phone_number',
        'website',
        'description',
        'search_preferences',
        'language',
        'country_id',
        'state_id',
        'address1',
        'address2',
        'city_id',
        'zip',
        'avatar',
        'cover_photo',
        'is_approved',
        'is_deleted',
        'email_verified_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     *
     * The attributes that should be cast.
     * @var array<string, string>
     *
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = ["get"];
    protected $with = ['meta'];

    public function agents()
    {
        return $this->hasMany(UserAgent::class);
    }

    public function chat_tokens()
    {
        return $this->hasMany(AuctionChatUser::class);
    }

    public function meta()
    {
        return $this->hasMany(UserMeta::class);
    }

    public function saveMeta($key, $val)
    {
        return $this->meta()->updateOrCreate(["meta_key" => $key], ["meta_value" => $val]);
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

    public function getGetAttribute()
    {
        $data = [];
        $metas = $this->meta;
        foreach ($metas as $row) {
            if (gettype(json_decode($row->meta_value)) == 'array') {
                $value = json_decode($row->meta_value);
            } else {
                $value = $row->meta_value;
            }
            $data[$row->meta_key] = $value;
        }
        $collection = new Collection();
        $collection->push((object) $data);
        return $collection->first();
    }

    public function getBrokerageAttribute($value)
    {
        if ($this->info('brokerage') == "") {
            return $value;
        } else {
            return $this->info('brokerage');
        }
    }

    public function getLicenseNoAttribute($value)
    {
        if ($this->info('license_no') == "") {
            return $value;
        } else {
            return $this->info('license_no');
        }
    }

    public function getPhoneAttribute($value)
    {
        if ($this->info('phone') == "") {
            return $value;
        } else {
            return $this->info('phone');
        }
    }

    public function getMlsIdAttribute($value)
    {
        if ($this->info('mls_id') == "") {
            return $value;
        } else {
            return $this->info('mls_id');
        }
    }

    public function seller_agents()
    {
        return $this->hasMany(UserAgent::class)->where('type', 'seller');
    }

    public function buyer_agents()
    {
        return $this->hasMany(UserAgent::class)->where('type', 'buyer');
    }

    public function users()
    {
        return $this->hasMany(UserAgent::class, 'agent_id', 'id');
    }

    public function buyers()
    {
        return $this->hasMany(UserAgent::class, 'agent_id', 'id')->where('type', 'buyer');
    }

    public function sellers()
    {
        return $this->hasMany(UserAgent::class, 'agent_id', 'id')->where('type', 'seller');
    }



    public function seller_properties()
    {
        return $this->hasMany(PropertyAuction::class, 'user_id', 'id');
    }

    public function property_auctions()
    {
        return $this->hasMany(PropertyAuction::class, 'user_id', 'id');
    }

    public function landlord_agent_auctions()
    {
        return $this->hasMany(LandlordAgentAuction::class, 'user_id', 'id');
    }
    public function seller_agent_auctions()
    {
        return $this->hasMany(SellerAgentAuction::class, 'user_id', 'id');
    }
    public function tenant_agent_auctions()
    {
        return $this->hasMany(TenantAgentAuction::class, 'user_id', 'id');
    }
    public function tenant_agent_auction_bid()
    {
        return $this->hasMany(TenantAgentAuctionBid::class, 'user_id', 'id');
    }
    public function landlord_agent_auction_bid()
    {
        return $this->hasMany(LandlordAgentAuctionBid::class, 'user_id', 'id');
    }
    public function buyer_agent_auction_bid()
    {
        return $this->hasMany(BuyerAgentAuctionBid::class, 'user_id', 'id');
    }
    public function seller_agent_auction_bid()
    {
        return $this->hasMany(SellerAgentAuctionBid::class, 'user_id', 'id');
    }

    public function buyer_agent_auctions()
    {
        return $this->hasMany(BuyerAgentAuction::class, 'user_id', 'id');
    }

    public function agent_service_auctions()
    {
        return $this->hasMany(AgentServiceAuction::class, 'user_id', 'id');
    }

    public function seller_service_auctions()
    {
        return $this->hasMany(SellerServiceAuction::class, 'user_id', 'id');
    }

    public function criteria_auctions()
    {
        return $this->hasMany(BuyerCriteriaAuction::class, 'user_id', 'id');
    }

    public function buyer_criteria_auctions()
    {
        return $this->hasMany(BuyerCriteriaAuction::class, 'buyer_id', 'id');
    }

    public function getAvatarUrlAttribute()
    {
        return url('images/avatar/' . $this->avatar);
    }

    public function getCoverUrlAttribute()
    {
        return url('images/cover/' . $this->cover_photo);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function county()
    {
        return $this->belongsTo(County::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
