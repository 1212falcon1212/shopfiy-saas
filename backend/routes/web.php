<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\XmlIntegration;
use App\Jobs\XmlImportJob;
use App\Models\Theme;
use App\Jobs\ThemeInstallJob;
use App\Models\User;
use App\Jobs\OrdersCreateJob;
use App\Jobs\OrdersSyncJob;
use App\Jobs\ProductsSyncJob;


// Shopify'dan giriş yapan kullanıcıları buraya yönlendirir
Route::get('/billing', [\App\Http\Controllers\Api\BillingController::class, 'index'])->middleware('verify.shopify')->name('billing');
Route::get('/billing/process', [\App\Http\Controllers\Api\BillingController::class, 'process'])->middleware('verify.shopify')->name('billing.process');

// Frontend'e yönlendirme
Route::get('/{any?}', function () {
    // Bu route tüm React Router isteklerini yakalar ve ana Next.js sayfasına yönlendirir.
    // Shopify App Bridge'in doğru çalışması için gereklidir.
    return view('welcome');
})->where('any', '.*')->middleware('verify.shopify');


// API test rotası (Next.js için)
Route::get('/api/test', function () {
    return response()->json(['message' => 'API Çalışıyor']);
});

Route::get('/test-import', function () {
    $integration = XmlIntegration::first();

    if (!$integration) {
        return "Hiç entegrasyon bulunamadı! Önce panelden ekle.";
    }

    // İşi kuyruğa at (Dispatch)
    XmlImportJob::dispatch($integration);

    return "İşlem kuyruğa alındı! Terminalden 'php artisan queue:work' çalıştırmayı unutma.";
});

Route::get('/test-theme-install', function () {
    // 1. Veritabanındaki ilk kullanıcıyı (Mağazayı) al
    // NOT: ID'nin senin veritabanındaki geçerli mağaza ID'si olduğundan emin ol!
    $user = User::first();

    // 2. İlk temayı al
    $theme = Theme::first();

    if (!$user || !$theme) {
        return "Kullanıcı veya Tema bulunamadı!";
    }

    // 3. İşi başlat
    ThemeInstallJob::dispatch($user, $theme);

    return "Tema yükleme işlemi başlatıldı! ({$theme->name} -> {$user->name}) <br> Kuyruğu izle...";
});



Route::get('/test-order-webhook', function () {
    $user = User::first();

    if (!$user) return "Kullanıcı yok!";

    $fakeOrder = (object) [
        'id' => 987654321,
        'name' => '#TEST-1001',
        'email' => 'musteri@mail.com',
        'customer' => (object) ['first_name' => 'Ali', 'last_name' => 'Veli'],
        'shipping_address' => ['address1' => 'Test Mah.'],
        'total_price' => '150.00',
        'currency' => 'TRY',
        'financial_status' => 'paid',
        'fulfillment_status' => null,
        'line_items' => [
            ['title' => 'Kırmızı Kazak', 'quantity' => 1, 'price' => '150.00']
        ]
    ];

    // Parametresiz handle() çağırıyoruz
    $job = new OrdersCreateJob($user->name, $fakeOrder);
    $job->handle();

    return "Sipariş test edildi! Veritabanını (orders tablosunu) kontrol et.";
});

Route::get('/force-register-webhook', function () {
    $user = User::first(); // Mağazanı bul

    if (!$user) return "Mağaza bulunamadı!";

    $shopDomain = preg_replace('#^https?://#', '', $user->name);
    $shopDomain = rtrim($shopDomain, '/');

    // Config dosyasındaki webhook adresini alıyoruz
    // DİKKAT: .env dosyasındaki APP_URL'in doğru (ngrok) olduğundan emin ol!
    $webhookUrl = env('APP_URL') . '/webhook/orders-create';

    // API İsteği
    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $user->password,
        'Content-Type' => 'application/json'
    ])->post("https://{$shopDomain}/admin/api/2024-04/webhooks.json", [
        'webhook' => [
            'topic' => 'orders/create',
            'address' => $webhookUrl,
            'format' => 'json'
        ]
    ]);

    return response()->json([
        'sent_url' => $webhookUrl,
        'shopify_response' => $response->json()
    ]);
});

Route::get('/sync-old-orders', function () {
    $user = User::first();
    if (!$user) return "Mağaza yok!";

    // İşçiyi çalıştır
    OrdersSyncJob::dispatch($user);

    return "Geçmiş siparişleri çekme işlemi kuyruğa alındı! Terminali izle.";
});


Route::get('/sync-products', function () {
    $user = \App\Models\User::first();
    if (!$user) return "Mağaza yok!";

    // İşçiyi çalıştır
    ProductsSyncJob::dispatch($user);

    return "Ürünleri çekme işlemi kuyruğa alındı! Terminali izle.";
});
