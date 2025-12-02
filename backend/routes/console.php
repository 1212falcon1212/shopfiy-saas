<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\XmlIntegration;
use App\Jobs\XmlImportJob;
use App\Models\User;
use App\Jobs\CollectionsSyncJob;
use App\Jobs\OrdersSyncJob;
use App\Jobs\ProductsSyncJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Zamanlanmış Görevler
Schedule::call(function () {
    $users = User::all();
    foreach ($users as $user) {
        
        // 1. Koleksiyonları her gece 03:00'te çek (cron expression: 0 3 * * *)
        // Buradaki call() her dakika çalışır, o yüzden dailyAt gibi metodlar Schedule::job() ile daha temiz olur.
        // Ancak Schedule::call içinde dispatch yapmak daha kontrollü.
        
        // Bu blok sadece saat başı çalışırsa logic karışabilir, en doğrusu Laravel'in scheduler'ına emanet etmek.
        // Aşağıdaki blok Schedule::command veya job ile yapılmalı.
    }
})->hourly();


// DOĞRU KULLANIM:

// 1. Koleksiyonları Her Gece 03:00'te Çek
Schedule::call(function () {
    $users = User::all();
    foreach ($users as $user) {
        CollectionsSyncJob::dispatch($user);
    }
})->dailyAt('03:00');

// 2. Siparişleri Her 5 Dakikada Bir Çek
Schedule::call(function () {
    $users = User::all();
    foreach ($users as $user) {
        OrdersSyncJob::dispatch($user);
    }
})->everyFiveMinutes();

// 3. Ürünleri Saat Başı Çek (Sadece Stok ve Fiyat Güncellemesi için)
Schedule::call(function () {
    $users = User::all();
    foreach ($users as $user) {
        ProductsSyncJob::dispatch($user);
    }
})->hourly();

// 4. XML Entegrasyonlarını Saat Başı Çalıştır
Schedule::call(function () {
    $integrations = XmlIntegration::where('is_active', true)->get();
    foreach ($integrations as $integration) {
        XmlImportJob::dispatch($integration);
    }
})->hourly();
