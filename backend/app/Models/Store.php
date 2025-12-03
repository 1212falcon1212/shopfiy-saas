<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Osiset\ShopifyApp\Contracts\ApiHelper as IApiHelper;
use Gnikyt\BasicShopifyAPI\BasicShopifyAPI;
use Gnikyt\BasicShopifyAPI\Session; // Doğru Session sınıfı
use Osiset\ShopifyApp\Objects\Values\SessionContext;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Objects\Values\AccessToken;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Osiset\ShopifyApp\Contracts\ShopModel as IShopModel;
use Osiset\ShopifyApp\Traits\ShopModel;

class Store extends Authenticatable implements IShopModel
{
    use HasFactory, ShopModel, SoftDeletes;

    protected $fillable = [
        'user_id',
        'domain',
        'shopify_client_id', // Custom App Client ID
        'shopify_client_secret', // Custom App Client Secret
        'access_token',
        'shopify_namespace', // ShopModel trait için
        'shopify_grandfathered', // ShopModel trait için
        'shopify_freemium', // ShopModel trait için
        'shop_owner',
        'email',
        'webhook_base_url', // Webhook'lar için base URL (ngrok veya production URL)
        'kolaysoft_username',
        'kolaysoft_password',
        'kolaysoft_vkn_tckn',
        'kolaysoft_supplier_name',
        'currency',
        'locale',
        'is_active'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'shopify_grandfathered' => 'boolean',
        'shopify_freemium' => 'boolean',
    ];

    /**
     * Mağazanın sahibi olan kullanıcı.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // IShopModel Metodları
    
    public function getId(): \Osiset\ShopifyApp\Objects\Values\ShopId
    {
        return new \Osiset\ShopifyApp\Objects\Values\ShopId($this->id);
    }

    /**
     * ShopModel trait override: Domain kolonunu kullan
     */
    public function getDomain(): ShopDomain
    {
        return ShopDomain::fromNative($this->domain ?? '');
    }

    /**
     * ShopModel trait override: access_token kolonunu kullan (password değil)
     */
    public function getAccessToken(): AccessToken
    {
        return AccessToken::fromNative($this->access_token ?? '');
    }
    
    public function charges(): HasMany
    {
        $chargeClass = \Osiset\ShopifyApp\Storage\Models\Charge::class;
        return $this->hasMany($chargeClass, 'shopify_id', 'id');
    }
    
    public function plan(): BelongsTo
    {
        // SaasPlan modeline referans
        return $this->belongsTo(Plan::class, 'plan_id');
    }
    
    public function isGrandfathered(): bool
    {
        return (bool) $this->shopify_grandfathered;
    }
    
    public function isFreemium(): bool
    {
        return (bool) $this->shopify_freemium;
    }
    
    public function hasOfflineAccess(): bool
    {
        return (bool) $this->access_token;
    }
    
    public function apiHelper(): IApiHelper
    {
        return app(IApiHelper::class);
    }

    public function api(): BasicShopifyAPI
    {
        // Partner App Modeli:
        // Merkezi API Credentials (.env) kullanılır.
        // Access Token ise her mağaza için veritabanından ($this->access_token) alınır.
        
        $helper = $this->apiHelper();
        $helper->make(); // Env'deki credentials ile başlatır

        // Access token ve domain oturum olarak ayarlanır
        if ($this->access_token) {
            $api = $helper->getApi();
            $api->setSession(
                new Session(
                    $this->domain,
                    $this->access_token
                )
            );
            return $api;
        }

        return $helper->getApi();
    }
    
    public function setSessionContext(SessionContext $session): void
    {
        // Gerekirse session context set edilebilir
    }
    
    public function getSessionContext(): ?SessionContext
    {
        return null;
    }

    /**
     * Authenticatable override: Store modelinde password yok
     */
    public function getAuthPassword()
    {
        return null;
    }
}
