<?php

namespace App\Jobs;

use App\Models\Theme;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ThemeInstallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $theme;
    protected $user;

    const API_VERSION = '2024-01';

    public function __construct(User $user, Theme $theme)
    {
        $this->user = $user;
        $this->theme = $theme;
    }

    public function handle(): void
    {
        Log::info("🚀 TEMA YÜKLEME BAŞLADI: {$this->theme->name}");

        try {
            // 1. ZIP OLUŞTURMA
            $themePath = storage_path('app/' . $this->theme->folder_path);
            
            if (!File::exists($themePath)) {
                Log::error("❌ Tema klasörü bulunamadı: {$themePath}");
                return;
            }

            $zipFileName = 'theme-' . time() . '-' . rand(1000, 9999) . '.zip';
            $zipPath = storage_path('app/public/' . $zipFileName);

            // Public klasörünü oluştur
            if (!File::exists(storage_path('app/public'))) {
                File::makeDirectory(storage_path('app/public'), 0755, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                Log::error("❌ ZIP dosyası oluşturulamadı: {$zipPath}");
                return;
            }

            // Tema dosyalarını ZIP'e ekle
            $files = File::allFiles($themePath);
            foreach ($files as $file) {
                if (str_starts_with($file->getFilename(), '.')) continue;
                
                $relativePath = $file->getRelativePathname();
                $zip->addFile($file->getRealPath(), $relativePath);
            }

            $zip->close();
            Log::info("✅ ZIP oluşturuldu: {$zipFileName}");

            // 2. PUBLIC URL OLUŞTURMA
            // Not: Production'da bu ZIP'i S3 veya başka bir CDN'e yüklemek gerekir
            // Şimdilik local storage kullanıyoruz, ama public symlink olmalı
            $publicUrl = url('storage/' . $zipFileName);
            
            // Eğer APP_URL .env'de tanımlı değilse, manuel oluştur
            if (!config('app.url')) {
                $publicUrl = 'http://localhost:8000/storage/' . $zipFileName;
            }

            Log::info("📦 ZIP URL: {$publicUrl}");

            // 3. SHOPIFY'A ZIP URL'İNİ GÖNDERME
            $shopDomain = preg_replace('#^https?://#', '', $this->user->name);
            $shopDomain = rtrim($shopDomain, '/');
            $accessToken = $this->user->password;

            $themePayload = [
                'theme' => [
                    'name' => $this->theme->name . ' (SaaS ' . rand(100,999) . ')',
                    'src' => $publicUrl,
                    'role' => 'unpublished'
                ]
            ];

            $url = "https://{$shopDomain}/admin/api/" . self::API_VERSION . "/themes.json";
            
            $client = new \GuzzleHttp\Client();
            
            try {
                $response = $client->request('POST', $url, [
                    'headers' => [
                        'X-Shopify-Access-Token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $themePayload
                ]);

                $responseBody = json_decode($response->getBody()->getContents(), true);
                
                if (isset($responseBody['theme']['id'])) {
                    $themeId = $responseBody['theme']['id'];
                    Log::info("✅ Tema başarıyla oluşturuldu! ID: {$themeId}");
                    Log::info("⏳ Shopify ZIP'i işliyor... Bu birkaç dakika sürebilir.");
                } else {
                    Log::error("❌ Tema oluşturulamadı:", $responseBody);
                }

            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                
                Log::error("❌ Shopify API Hatası:", [
                    'status' => $statusCode,
                    'body' => $responseBody
                ]);
            }

            // 4. TEMİZLİK (Opsiyonel - ZIP'i silmek isterseniz)
            // File::delete($zipPath);

        } catch (\Exception $e) {
            Log::error("🔥 Kritik Hata: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
        }
    }
}
