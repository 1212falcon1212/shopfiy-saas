<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\XmlController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\UploadController;
use App\Jobs\ProductsSyncJob;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FigmaController;
use App\Http\Controllers\Api\ThemeController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AuthController; // YENİ

// Authentication
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Shopify OAuth Callback (Public - web route olarak tanımlanmalı)
// Bu route web.php'de tanımlanacak çünkü redirect yapıyor

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json(['message' => 'Merhaba! Laravel ve Redis çalışıyor.']);
});

// Shopify OAuth - Redirect URL (Public - Custom App oluştururken gerekli)
Route::get('/shopify/redirect-url', [\App\Http\Controllers\Api\ShopifyOAuthController::class, 'getRedirectUrl']);

// KORUMALI ROTALAR (Auth Required)
Route::middleware('auth:sanctum')->group(function () {
    // Shopify OAuth
    Route::post('/shopify/initiate', [\App\Http\Controllers\Api\ShopifyOAuthController::class, 'initiate']);
    
    // XML Entegrasyon
    Route::get('/xml/integrations', [XmlController::class, 'index']);
    Route::get('/xml/integrations/{id}', [XmlController::class, 'show']);
    Route::put('/xml/integrations/{id}', [XmlController::class, 'update']);
    Route::post('/xml/preview', [XmlController::class, 'preview']);
    Route::post('/xml/store', [XmlController::class, 'store']);
    Route::delete('/xml/integrations/{id}', [XmlController::class, 'destroy']);
    Route::post('/xml/integrations/{id}/sync', [XmlController::class, 'sync']);

    // Siparişler
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/{id}/invoice', [OrderController::class, 'invoice']);
    Route::post('/orders/{id}/create-invoice', [OrderController::class, 'createInvoice']);
    Route::post('/orders/sync', function (Request $request) {
        $request->validate(['store_id' => 'required|exists:stores,id']);
        
        $user = $request->user();
        $store = \App\Models\Store::where('id', $request->store_id)
            ->where('user_id', $user->id)
            ->firstOrFail();
            
        \App\Jobs\OrdersSyncJob::dispatch($store);
        
        return response()->json(['message' => 'Siparişler arka planda eşitleniyor...', 'store_domain' => $store->domain]);
    });

    // Ürünler
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::post('/products/bulk-push', [ProductController::class, 'bulkPush']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products/sync', function (Request $request) {
        $request->validate(['store_id' => 'required|exists:stores,id']);
        
        $user = $request->user();
        $store = \App\Models\Store::where('id', $request->store_id)
            ->where('user_id', $user->id)
            ->firstOrFail();
            
        \App\Jobs\ProductsSyncJob::dispatch($store);
        
        return response()->json(['message' => 'Ürünler arka planda eşitleniyor...', 'store_domain' => $store->domain]);
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // SaaS Planları
    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/plans/{id}', [PlanController::class, 'show']);

    // PayTR Ödeme
    Route::post('/payment/init', [PaymentController::class, 'init']);

    // Koleksiyonlar
    Route::get('/collections', [CollectionController::class, 'index']);
    Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);

    Route::post('/upload', [UploadController::class, 'store']);

    // Figma Entegrasyonu
    Route::post('/figma/analyze', [FigmaController::class, 'analyze']);

    // Tema Yönetimi
    Route::get('/themes', [ThemeController::class, 'index']);
    Route::post('/themes/{id}/install', [ThemeController::class, 'install']);
    Route::post('/themes/upload', [ThemeController::class, 'upload']);

    // Mağaza Yönetimi
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/stores/default', [StoreController::class, 'getDefaultSettings']);
    Route::post('/stores/default', [StoreController::class, 'updateDefaultSettings']);
    Route::post('/stores', [StoreController::class, 'store']);
    Route::get('/stores/{id}', [StoreController::class, 'show']);
    Route::put('/stores/{id}', [StoreController::class, 'update']);
    Route::put('/stores/{id}/kolaysoft-settings', [StoreController::class, 'updateKolaysoftSettings']);
    Route::delete('/stores/{id}', [StoreController::class, 'destroy']);
});

// Public Routes (Geri aramalar vb.)
Route::post('/payment/callback', [PaymentController::class, 'callback']); // PayTR Webhook
