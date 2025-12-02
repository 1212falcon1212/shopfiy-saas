<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CollectionController extends Controller
{
    /**
     * Shopify'daki Koleksiyonları (Kategorileri) Listele
     */
    public function index()
    {
        $user = User::first();

        if (!$user) {
            return response()->json(['error' => 'Mağaza bulunamadı'], 404);
        }

        try {
            // Önce Cache'e bak
            $collections = \Illuminate\Support\Facades\Cache::get("collections_{$user->id}");

            // Cache boşsa, Job'ı tetikle ama kullanıcıyı bekletmemek için
            // anlık olarak API'den çekip dönelim (ve Cache'i dolduralım)
            if (!$collections) {
                \App\Jobs\CollectionsSyncJob::dispatchSync($user);
                $collections = \Illuminate\Support\Facades\Cache::get("collections_{$user->id}");
            }

            return response()->json($collections);

        } catch (\Exception $e) {
            Log::error("Koleksiyon Çekme Hatası: " . $e->getMessage());
            return response()->json(['error' => 'Koleksiyonlar çekilemedi'], 500);
        }
    }
}
