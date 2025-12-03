<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Osiset\ShopifyApp\Contracts\ShopModel as IShopModel;
use Osiset\ShopifyApp\Objects\Values\ShopId;
use Osiset\ShopifyApp\Objects\Values\NullShopDomain;
use Osiset\ShopifyApp\Objects\Values\NullAccessToken;
use Osiset\ShopifyApp\Contracts\ApiHelper as IApiHelper;
use Gnikyt\BasicShopifyAPI\BasicShopifyAPI;
use Osiset\ShopifyApp\Objects\Values\SessionContext;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable implements IShopModel
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Kullanıcının yönettiği mağazalar.
     */
    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Kullanıcının abonelikleri.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Aktif abonelik.
     */
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
            })
            ->latestOfMany();
    }

    /**
     * Kullanıcının ödemeleri.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Kullanıcı admin mi kontrol eder.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * ShopModel Interface Implementation (laravel-shopify paketi için)
     * User modeli Shopify mağazası değil, bu metodlar boş/null döndürür.
     */
    public function getId(): ShopId
    {
        return new ShopId($this->id ?? 0);
    }

    public function getDomain(): \Osiset\ShopifyApp\Contracts\Objects\Values\ShopDomain
    {
        return NullShopDomain::fromNative(null);
    }

    public function getName()
    {
        return $this->name; // User'ın adı
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getAccessToken(): \Osiset\ShopifyApp\Contracts\Objects\Values\AccessToken
    {
        return NullAccessToken::fromNative(null);
    }

    public function charges(): HasMany
    {
        // User'ın charges'ı yok, Shopify paketinin Charge modelini kullan
        $chargeClass = \Osiset\ShopifyApp\Storage\Models\Charge::class;
        return $this->hasMany($chargeClass)->whereRaw('1 = 0');
    }

    public function plan(): BelongsTo
    {
        // User'ın plan'ı yok, boş relation döndür
        return $this->belongsTo(\App\Models\Plan::class)->whereRaw('1 = 0');
    }

    public function isGrandfathered(): bool
    {
        return false;
    }

    public function isFreemium(): bool
    {
        return false;
    }

    public function hasOfflineAccess(): bool
    {
        return false;
    }

    public function apiHelper(): IApiHelper
    {
        return app(IApiHelper::class);
    }

    public function api(): BasicShopifyAPI
    {
        return $this->apiHelper()->make();
    }

    public function setSessionContext(SessionContext $session): void
    {
        // User için session context yok
    }

    public function getSessionContext(): ?SessionContext
    {
        return null;
    }
}
