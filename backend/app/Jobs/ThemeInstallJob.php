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
use Illuminate\Support\Facades\Http;

class ThemeInstallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $theme;
    protected $user;

    const API_VERSION = '2024-04';

    public function __construct(User $user, Theme $theme)
    {
        $this->user = $user;
        $this->theme = $theme;
    }

    public function handle(): void
    {
        Log::info("🚀 TEMA YÜKLEME BAŞLADI: {$this->theme->name}");

        try {
            // 1. TEMA OLUŞTURMA
            $themePayload = [
                'theme' => [
                    'name' => $this->theme->name . ' (SaaS ' . rand(100,999) . ')',
                    'role' => 'unpublished'
                ]
            ];

            $response = $this->user->api()->rest('POST', 'admin/api/' . self::API_VERSION . '/themes.json', $themePayload);

            if ($response['errors'] || empty($response['body']['theme']['id'])) {
                Log::error('❌ Tema Oluşturulamadı:', ['err' => $response['body'] ?? 'Hata']);
                return;
            }

            $newThemeId = (string)$response['body']['theme']['id'];
            Log::info("✅ Tema Oluşturuldu ID: {$newThemeId}");

            Log::info("⏳ Hazırlık için 5 saniye bekleniyor...");
            sleep(5);

            // 2. DOSYALARI SIRALA (Config En Başa)
            $themePath = storage_path('app/' . $this->theme->folder_path);
            $files = File::allFiles($themePath);

            usort($files, function($a, $b) {
                $aPath = $a->getRelativePathname();
                $bPath = $b->getRelativePathname();
                if (str_contains($aPath, 'settings_schema.json')) return -1;
                if (str_contains($bPath, 'settings_schema.json')) return 1;
                return 0;
            });

            // 3. YÜKLEME
            $shopDomain = preg_replace('#^https?://#', '', $this->user->name);
            $shopDomain = rtrim($shopDomain, '/');
            $accessToken = $this->user->password;

            foreach ($files as $file) {
                if (str_starts_with($file->getFilename(), '.')) continue;

                $relativePath = $file->getRelativePathname();
                $shopifyKey = str_replace('\\', '/', $relativePath);
                $shopifyKey = ltrim($shopifyKey, '/');

                $content = File::get($file->getRealPath());
                $extension = strtolower($file->getExtension());

                // --- KRİTİK AYRIM: Text vs Image ---
                $isTextFile = in_array($extension, ['json', 'liquid', 'html', 'css', 'js', 'txt']);

                $assetData = [
                    'key' => $shopifyKey
                ];

                if ($isTextFile) {
                    // Metin dosyalarını doğrudan string olarak gönderiyoruz
                    $assetData['value'] = $content;
                } else {
                    // Resimleri base64 yapıyoruz
                    $assetData['attachment'] = base64_encode($content);
                }

                $body = ['asset' => $assetData];

                // URL Query Hack'ini kullanıyoruz çünkü PUT için en sağlamı bu
                $encodedKey = urlencode($shopifyKey);
                $url = "https://{$shopDomain}/admin/api/" . self::API_VERSION . "/themes/{$newThemeId}/assets.json?asset[key]={$encodedKey}";

                $uploaded = false;
                $tries = 0;

                while(!$uploaded && $tries < 3) {
                    $tries++;

                    // Guzzle ile saf istek (Laravel HTTP wrapper'ı bazen body formatını bozabiliyor)
                    // JSON encode işlemini manuel yapıyoruz
                    $assetResponse = Http::withOptions(['allow_redirects' => false])
                        ->withHeaders([
                            'X-Shopify-Access-Token' => $accessToken,
                            'Content-Type' => 'application/json'
                        ])
                        ->send('PUT', $url, [
                            'body' => json_encode($body)
                        ]);

                    if ($assetResponse->successful()) {
                        Log::info("✅ Yüklendi: {$shopifyKey}");
                        $uploaded = true;
                    } else {
                        Log::warning("⚠️ Hata ({$shopifyKey}) - Deneme {$tries}", [
                            'status' => $assetResponse->status(),
                            'body' => $assetResponse->body()
                        ]);
                        sleep(2);
                    }
                }

                usleep(300000);
            }

            Log::info("🎉 TEMA KURULUMU TAMAMLANDI!");

        } catch (\Exception $e) {
            Log::error("🔥 Kritik Hata: " . $e->getMessage());
        }
    }
}
