<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FigmaService
{
    protected $baseUrl;
    protected $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.figma.base_url');
        $this->accessToken = config('services.figma.access_token');

        // DEBUG: Token değerini kontrol et (Security Warning: Production'da bunu yapma!)
        Log::info('Figma Service Init - Token: ' . ($this->accessToken ? 'Var (' . substr($this->accessToken, 0, 5) . '...)' : 'YOK'));

        if (!$this->accessToken) {
            // Token yoksa log düş, ama constructor'da exception fırlatmak bazen sorun olabilir.
            Log::warning('Figma Access Token is missing in configuration.');
        }
    }

    /**
     * Figma dosyasının JSON verisini çeker.
     *
     * @param string $fileKey Dosya ID'si (URL'deki /file/xxxxx/ kısmı)
     * @return array
     * @throws Exception
     */
    public function getFile($fileKey)
    {
        if (!$this->accessToken) {
            throw new Exception('Figma Access Token is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'X-Figma-Token' => $this->accessToken,
            ])->get("{$this->baseUrl}/files/{$fileKey}");

            if ($response->failed()) {
                Log::error('Figma API Error: ' . $response->body());
                throw new Exception('Failed to fetch Figma file: ' . $response->status());
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('Figma Service Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Figma dosyasındaki görselleri (Image Fills) URL olarak çeker.
     *
     * @param string $fileKey
     * @return array
     */
    public function getImages($fileKey)
    {
        // Figma API'de image fill'leri çekmek için ayrı endpoint var
        // GET /v1/files/:key/images
        try {
             $response = Http::withHeaders([
                'X-Figma-Token' => $this->accessToken,
            ])->get("{$this->baseUrl}/files/{$fileKey}/images");

             if ($response->failed()) {
                 return [];
             }

             return $response->json()['meta']['images'] ?? [];

        } catch (Exception $e) {
            Log::error('Figma Images Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Dosyadaki ana renkleri ve stilleri analiz eder (Basit Analiz).
     *
     * @param array $fileData getFile() metodundan dönen data
     * @return array
     */
    public function extractDesignTokens($fileData)
    {
        $tokens = [
            'colors' => [],
            'fonts' => [],
        ];

        // Bu kısım, Figma dosyasının derinliklerine inip
        // kullanılan renkleri ve fontları bulmak için recursive bir gezinti gerektirir.
        // Şimdilik basitçe "document" düğümünden başlıyoruz.
        
        if (isset($fileData['document'])) {
            $this->traverseNodes($fileData['document'], $tokens);
        }

        // Tekrarlananları temizle
        $tokens['colors'] = array_unique($tokens['colors']);
        $tokens['fonts'] = array_unique($tokens['fonts']);

        return $tokens;
    }

    /**
     * Node ağacını gezer ve stilleri toplar.
     */
    private function traverseNodes($node, &$tokens)
    {
        // Renkleri Bul (Fills)
        if (isset($node['fills'])) {
            foreach ($node['fills'] as $fill) {
                if ($fill['type'] === 'SOLID' && isset($fill['color'])) {
                    $hex = $this->rgbToHex($fill['color']);
                    $tokens['colors'][] = $hex;
                }
            }
        }

        // Fontları Bul (Style -> Text)
        // Figma API'de text style property'leri node üzerinde direct olabilir
        if (isset($node['style']) && isset($node['style']['fontFamily'])) {
            $tokens['fonts'][] = $node['style']['fontFamily'];
        }

        // Çocukları Gez
        if (isset($node['children'])) {
            foreach ($node['children'] as $child) {
                $this->traverseNodes($child, $tokens);
            }
        }
    }

    private function rgbToHex($color)
    {
        $r = round($color['r'] * 255);
        $g = round($color['g'] * 255);
        $b = round($color['b'] * 255);
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}

