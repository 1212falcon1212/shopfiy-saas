<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    /**
     * Kullanıcının mağazalarını listeler.
     */
    public function index()
    {
        $user = Auth::user();
        $stores = $user->stores()->orderBy('created_at', 'desc')->get();
        return response()->json($stores);
    }

    /**
     * Yeni mağaza ekler.
     */
    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|unique:stores,domain',
            'access_token' => 'nullable|string',
            'shopify_client_id' => 'nullable|string',
            'shopify_client_secret' => 'nullable|string',
            'shop_owner' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $user = Auth::user();

        // Domain formatını temizle (https:// kaldır, sonundaki / kaldır)
        $domain = preg_replace('#^https?://#', '', $request->domain);
        $domain = rtrim($domain, '/');

        $store = $user->stores()->create(array_merge(
            $request->all(),
            ['domain' => $domain]
        ));

        return response()->json($store, 201);
    }

    /**
     * Mağaza detaylarını getirir.
     */
    public function show($id)
    {
        $store = Auth::user()->stores()->findOrFail($id);
        return response()->json($store);
    }

    /**
     * Mağaza bilgilerini günceller.
     */
    public function update(Request $request, $id)
    {
        $store = Auth::user()->stores()->findOrFail($id);

        $request->validate([
            'domain' => 'sometimes|required|string|unique:stores,domain,' . $id,
            'email' => 'nullable|email',
        ]);

        $store->update($request->only([
            'domain',
            'access_token',
            'shopify_client_id',
            'shopify_client_secret',
            'shop_owner',
            'email',
            'webhook_base_url',
            'currency',
            'locale',
            'is_active'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Mağaza güncellendi.',
            'store' => $store
        ]);
    }

    /**
     * KolaySoft ayarlarını günceller.
     */
    public function updateKolaysoftSettings(Request $request, $id)
    {
        $store = Auth::user()->stores()->findOrFail($id);

        $request->validate([
            'kolaysoft_username' => 'nullable|string',
            'kolaysoft_password' => 'nullable|string',
            'kolaysoft_vkn_tckn' => 'nullable|string',
            'kolaysoft_supplier_name' => 'nullable|string',
        ]);

        $store->update($request->only([
            'kolaysoft_username',
            'kolaysoft_password',
            'kolaysoft_vkn_tckn',
            'kolaysoft_supplier_name'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Entegrasyon ayarları güncellendi.',
            'store' => $store
        ]);
    }

    /**
     * Mağazayı siler.
     */
    public function destroy($id)
    {
        $store = Auth::user()->stores()->findOrFail($id);
        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mağaza silindi.'
        ]);
    }

    /**
     * Varsayılan (ilk) mağazanın ayarlarını günceller (Eski metod uyumluluğu için)
     */
    public function updateDefaultSettings(Request $request)
    {
        $user = Auth::user();
        $store = $user->stores()->first();
        
        if (!$store) {
            // Mağaza yoksa oluştur (Eski kullanıcılar için migration)
            $store = $user->stores()->create([
                'domain' => $user->name ?? 'default-store', 
                'is_active' => true
            ]);
        }

        return $this->updateKolaysoftSettings($request, $store->id);
    }
    
    /**
     * Varsayılan (ilk) mağazanın ayarlarını getirir (Eski metod uyumluluğu için)
     */
    public function getDefaultSettings()
    {
        $user = Auth::user();
        $store = $user->stores()->first();
        
        if (!$store) {
            return response()->json([
                'kolaysoft_username' => '',
                'kolaysoft_password' => '',
                'kolaysoft_vkn_tckn' => '',
                'kolaysoft_supplier_name' => '',
            ]);
        }
        
        return response()->json($store);
    }
}
