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
        
        // Eğer hiç mağaza yoksa, ana hesabı bir "Store" gibi dönüştürüp listeye ekleyebiliriz
        // veya otomatik oluşturabiliriz. Şimdilik boş dönelim.
        $stores = $user->stores;

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
        ]);

        $user = Auth::user();

        $store = $user->stores()->create($request->all());

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
     * Mağaza ayarlarını günceller (KolaySoft dahil).
     */
    public function update(Request $request, $id)
    {
        $store = Auth::user()->stores()->findOrFail($id);

        // Hassas verileri şifrelemek gerekebilir ama şimdilik düz tutuyoruz
        $store->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Ayarlar güncellendi.',
            'store' => $store
        ]);
    }

    /**
     * Ana hesabın KolaySoft ayarlarını kaydetmek için (Geçici Çözüm)
     * SaaS dönüşümünde User -> Store geçişi tamamlanana kadar, User tablosunda veya
     * User'a bağlı "Default Store"da bu ayarları tutmak gerekir.
     */
    public function updateDefaultSettings(Request $request)
    {
        $user = Auth::user();
        
        // Kullanıcının varsayılan bir mağazası var mı? Yoksa oluşturalım.
        $store = $user->stores()->first();
        
        if (!$store) {
            $store = $user->stores()->create([
                'domain' => $user->name, // User name usually holds the domain in laravel-shopify
                'is_active' => true
            ]);
        }

        $store->update($request->only([
            'kolaysoft_username',
            'kolaysoft_password',
            'kolaysoft_vkn_tckn',
            'kolaysoft_supplier_name'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Entegrasyon ayarları kaydedildi.',
            'store' => $store
        ]);
    }
    
    /**
     * Varsayılan ayarları getirir.
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

