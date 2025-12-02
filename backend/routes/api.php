<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\XmlController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CollectionController; // YENİ
use App\Http\Controllers\Api\UploadController; // YENİ
use App\Jobs\ProductsSyncJob;
use App\Http\Controllers\Api\DashboardController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json(['message' => 'Merhaba! Laravel ve Redis çalışıyor.']);
});

// XML Entegrasyon
Route::get('/xml/integrations', [XmlController::class, 'index']);
Route::get('/xml/integrations/{id}', [XmlController::class, 'show']); // YENİ
Route::put('/xml/integrations/{id}', [XmlController::class, 'update']); // YENİ
Route::post('/xml/preview', [XmlController::class, 'preview']);
Route::post('/xml/store', [XmlController::class, 'store']);
Route::delete('/xml/integrations/{id}', [XmlController::class, 'destroy']);
Route::post('/xml/integrations/{id}/sync', [XmlController::class, 'sync']);

// Siparişler
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::get('/orders/{id}/invoice', [OrderController::class, 'invoice']);

// Ürünler
Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::post('/products/bulk-push', [ProductController::class, 'bulkPush']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/products/sync', function (Request $request) {
    $user = \App\Models\User::first();
    \App\Jobs\ProductsSyncJob::dispatch($user);
    return response()->json(['message' => 'Ürünler arka planda eşitleniyor...']);
});

// Billing (Para Kazanma)
Route::get('/billing', [\App\Http\Controllers\Api\BillingController::class, 'index'])->name('billing');
Route::get('/billing/process', [\App\Http\Controllers\Api\BillingController::class, 'process'])->name('billing.process');

// Koleksiyonlar (Kategoriler) - YENİ
Route::get('/collections', [CollectionController::class, 'index']);
Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']); // YENİ: Yerel Kategoriler

// Dosya Yükleme - YENİ
Route::post('/upload', [UploadController::class, 'store']);

Route::get('/dashboard', [DashboardController::class, 'index']);
