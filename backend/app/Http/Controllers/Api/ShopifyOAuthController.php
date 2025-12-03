<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyOAuthController extends Controller
{
    /**
     * Redirect URL'i döner (Custom App oluştururken kullanılacak)
     */
    public function getRedirectUrl()
    {
        $backendUrl = config('app.url');
        $redirectUri = rtrim($backendUrl, '/') . '/api/shopify/callback';
        
        return response()->json([
            'redirect_uri' => $redirectUri,
            'instructions' => 'Custom App oluştururken "Allowed redirection URL(s)" veya "Redirect URL" alanına bu URL\'yi girin.',
        ]);
    }

    /**
     * Shopify OAuth başlatma endpoint'i
     * Custom App kullanarak OAuth başlatır (env'e bağlı değil)
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'shop' => 'required|string', // shop.myshopify.com
            'client_id' => 'required|string', // Custom App Client ID
            'client_secret' => 'required|string', // Custom App Client Secret
        ]);

        $shop = $request->shop;
        // Domain formatını temizle
        $shop = preg_replace('#^https?://#', '', $shop);
        $shop = rtrim($shop, '/');
        
        // .myshopify.com eklenmemişse ekle
        if (!str_ends_with($shop, '.myshopify.com')) {
            $shop .= '.myshopify.com';
        }

        $clientId = $request->client_id;
        $clientSecret = $request->client_secret;
        $scopes = config('shopify-app.api_scopes', 'read_products,write_products,read_orders,write_orders,read_custom_collections,write_custom_collections,write_themes');
        
        // Backend URL'den redirect URI oluştur (env'den alınır ama her kullanıcı için aynı)
        $backendUrl = config('app.url');
        $redirectUri = rtrim($backendUrl, '/') . '/api/shopify/callback';

        // Geçici olarak store oluştur veya güncelle (client_id ve client_secret'ı kaydet)
        $user = Auth::user();
        $store = Store::updateOrCreate(
            [
                'domain' => $shop,
                'user_id' => $user->id,
            ],
            [
                'shopify_client_id' => $clientId,
                'shopify_client_secret' => $clientSecret,
                'is_active' => false, // OAuth tamamlanana kadar false
            ]
        );

        // State olarak store_id ve user_id'yi gönder (güvenlik için encode edilmiş)
        $state = base64_encode(json_encode([
            'store_id' => $store->id,
            'user_id' => $user->id,
        ]));

        Log::info('Shopify OAuth başlatılıyor (Custom App)', [
            'shop' => $shop,
            'store_id' => $store->id,
            'redirect_uri' => $redirectUri,
        ]);

        $authUrl = "https://{$shop}/admin/oauth/authorize?" . http_build_query([
            'client_id' => $clientId,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return response()->json([
            'auth_url' => $authUrl,
            'redirect_uri' => $redirectUri,
        ]);
    }

    /**
     * Shopify OAuth callback endpoint'i
     * Custom App kullanarak OAuth callback'i işler
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $shop = $request->query('shop');
        $state = $request->query('state');
        $hmac = $request->query('hmac');

        if (!$code || !$shop || !$state) {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect($frontendUrl . '/stores?error=invalid_request');
        }

        // State'ten store_id ve user_id'yi al
        try {
            $stateData = json_decode(base64_decode($state), true);
            $storeId = $stateData['store_id'] ?? null;
            $userId = $stateData['user_id'] ?? null;

            if (!$storeId || !$userId) {
                throw new \Exception('Invalid state data');
            }
        } catch (\Exception $e) {
            Log::error('Shopify OAuth state decode hatası', [
                'state' => $state,
                'error' => $e->getMessage(),
            ]);
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect($frontendUrl . '/stores?error=invalid_state');
        }

        // Store'u bul
        $store = Store::where('id', $storeId)
            ->where('user_id', $userId)
            ->first();

        if (!$store) {
            Log::error('Shopify OAuth store bulunamadı', [
                'store_id' => $storeId,
                'user_id' => $userId,
            ]);
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect($frontendUrl . '/stores?error=store_not_found');
        }

        // ÖNEMLİ: Callback'teki shop ile store'daki domain farklı olabilir (alias/redirect durumu)
        // Shopify'da shop alias'ları olabilir, bu durumda callback'teki shop'u kullanmalıyız
        // Store'daki domain'i callback'teki shop ile güncelle (eğer farklıysa)
        if ($shop !== $store->domain) {
            Log::info('Shopify OAuth shop uyumsuzluğu tespit edildi - store domain güncelleniyor', [
                'callback_shop' => $shop,
                'old_store_domain' => $store->domain,
                'store_id' => $storeId,
            ]);
            
            // Bu domainde başka bir mağaza var mı kontrol et (Unique constraint hatasını önlemek için)
            $existingStore = Store::where('domain', $shop)
                ->where('id', '!=', $storeId)
                ->withTrashed() // Silinmiş kayıtları da kontrol et
                ->first();

            if ($existingStore) {
                // Eğer aynı kullanıcıya aitse, eski kaydı tamamen sil (force delete)
                if ($existingStore->user_id == $userId) {
                    Log::warning('Aynı domainde eski mağaza kaydı bulundu, siliniyor', [
                        'existing_store_id' => $existingStore->id,
                        'domain' => $shop
                    ]);
                    $existingStore->forceDelete();
                } else {
                    // Başka kullanıcıya aitse hata ver
                    Log::error('Bu domain başka bir kullanıcıya ait', [
                        'domain' => $shop,
                        'current_user_id' => $userId,
                        'owner_user_id' => $existingStore->user_id
                    ]);
                    $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
                    return redirect($frontendUrl . '/stores?error=domain_taken');
                }
            }

            // Store domain'ini callback'teki shop ile güncelle
            $store->domain = $shop;
            $store->save();
            
            Log::info('Store domain güncellendi', [
                'new_domain' => $shop,
                'store_id' => $storeId,
            ]);
        }

        // HMAC doğrulaması (Custom App'in client_secret'ı ile)
        // Shopify Custom App OAuth callback'inde HMAC doğrulaması:
        // ÖNEMLİ: Laravel query parametrelerini otomatik decode ediyor
        // HMAC için orijinal URL encoded query string'i kullanmalıyız!
        
        $params = $request->query->all();
        $receivedHmac = $params['hmac'] ?? null;
        
        // Orijinal query string'i al
        $originalQueryString = $request->server('QUERY_STRING') ?? '';
        
        // Parse et (manuel olarak, decode etmeden)
        $queryParams = [];
        if (!empty($originalQueryString)) {
            $pairs = explode('&', $originalQueryString);
            foreach ($pairs as $pair) {
                if (empty($pair)) continue;
                $parts = explode('=', $pair, 2);
                $key = $parts[0];
                $value = isset($parts[1]) ? $parts[1] : '';
                $queryParams[$key] = $value;
            }
        } else {
            // Fallback: Laravel params (rawurlencode ile tekrar encode et)
            foreach ($params as $key => $value) {
                $queryParams[rawurlencode($key)] = rawurlencode($value);
            }
        }

        // Yöntem 1: Host DAHİL (Şu anki standart)
        $paramsWithHost = $queryParams;
        unset($paramsWithHost['hmac']);
        unset($paramsWithHost['signature']);
        ksort($paramsWithHost);
        
        $queryPartsWithHost = [];
        foreach ($paramsWithHost as $key => $value) {
            $queryPartsWithHost[] = $key . '=' . $value;
        }
        $stringWithHost = implode('&', $queryPartsWithHost);
        $hmacWithHost = hash_hmac('sha256', $stringWithHost, $store->shopify_client_secret);

        // Yöntem 2: Host HARİÇ (Eski standart / Bazı durumlar)
        $paramsWithoutHost = $queryParams;
        unset($paramsWithoutHost['hmac']);
        unset($paramsWithoutHost['signature']);
        unset($paramsWithoutHost['host']);
        ksort($paramsWithoutHost);
        
        $queryPartsWithoutHost = [];
        foreach ($paramsWithoutHost as $key => $value) {
            $queryPartsWithoutHost[] = $key . '=' . $value;
        }
        $stringWithoutHost = implode('&', $queryPartsWithoutHost);
        $hmacWithoutHost = hash_hmac('sha256', $stringWithoutHost, $store->shopify_client_secret);

        // Debug log
        Log::info('Shopify OAuth HMAC Çoklu Kontrol', [
            'received_hmac' => $receivedHmac,
            'calc_with_host' => $hmacWithHost,
            'calc_no_host' => $hmacWithoutHost,
            'match_with_host' => hash_equals($hmacWithHost, $receivedHmac),
            'match_no_host' => hash_equals($hmacWithoutHost, $receivedHmac),
            'string_with_host' => $stringWithHost,
            'string_no_host' => $stringWithoutHost,
        ]);

        // Doğrulama Kontrolü
        if (!hash_equals($hmacWithHost, $receivedHmac) && !hash_equals($hmacWithoutHost, $receivedHmac)) {
            // Test modu kontrolü
            if (env('SHOPIFY_SKIP_HMAC_VERIFICATION', false) === true) {
                Log::warning('Shopify OAuth HMAC doğrulaması atlandı (test modu aktif)');
            } else {
                Log::error('Shopify OAuth HMAC doğrulaması başarısız (İki yöntem de denendi)', [
                    'shop' => $shop,
                    'store_id' => $storeId,
                ]);
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
                return redirect($frontendUrl . '/stores?error=invalid_hmac');
            }
        }

        // Access token al (Custom App'in client_id ve client_secret'ı ile)
        try {
            $response = Http::post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => $store->shopify_client_id,
                'client_secret' => $store->shopify_client_secret,
                'code' => $code,
            ]);

            if (!$response->successful()) {
                Log::error('Shopify OAuth token alınamadı', [
                    'shop' => $shop,
                    'response' => $response->body(),
                ]);
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
                return redirect($frontendUrl . '/stores?error=token_failed');
            }

            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'] ?? null;

            if (!$accessToken) {
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
                return redirect($frontendUrl . '/stores?error=no_token');
            }

            // Mağaza bilgilerini Shopify'dan al
            $shopResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
            ])->get("https://{$shop}/admin/api/2024-01/shop.json");

            if (!$shopResponse->successful()) {
                Log::error('Shopify shop bilgileri alınamadı', [
                    'shop' => $shop,
                    'response' => $shopResponse->body(),
                ]);
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
                return redirect($frontendUrl . '/stores?error=shop_info_failed');
            }

            $shopData = $shopResponse->json('shop');

            // Store'u güncelle (access token ve mağaza bilgileri)
            $store->update([
                'access_token' => $accessToken,
                'shop_owner' => $shopData['shop_owner'] ?? null,
                'email' => $shopData['email'] ?? null,
                'webhook_base_url' => config('app.url'),
                'is_active' => true,
            ]);

            Log::info('Shopify mağazası başarıyla eklendi', [
                'store_id' => $store->id,
                'shop' => $shop,
            ]);

            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect($frontendUrl . '/stores?success=store_added&store_id=' . $store->id);
        } catch (\Exception $e) {
            Log::error('Shopify OAuth callback hatası', [
                'shop' => $shop,
                'error' => $e->getMessage(),
            ]);
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect($frontendUrl . '/stores?error=exception');
        }
    }
}

