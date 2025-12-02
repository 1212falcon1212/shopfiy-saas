<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class XmlParserService
{
    /**
     * XML URL'sini alır, analiz eder ve verileri döner.
     */
    public function preview($url)
    {
        try {
            $response = Http::timeout(60)->get($url); // Timeout artırıldı

            if ($response->failed()) {
                throw new Exception("XML dosyasına erişilemedi. Hata kodu: " . $response->status());
            }

            $xmlContent = $response->body();
            
            $xmlObject = @simplexml_load_string($xmlContent, "SimpleXMLElement", LIBXML_NOCDATA);

            if ($xmlObject === false) {
                throw new Exception("XML formatı bozuk veya okunamadı.");
            }

            $json = json_encode($xmlObject);
            $data = json_decode($json, true);

            $rootNode = array_key_first($data); 
            $items = $data;

            if (count($data) === 1 && is_array(reset($data))) {
                $items = reset($data);
            }

            $foundItems = null;

            foreach ($items as $key => $value) {
                if (is_array($value) && isset($value[0])) {
                    $foundItems = $value;
                    $rootNode = $key;
                    break;
                }
            }

            if (!$foundItems && isset($items[0])) {
                $foundItems = $items;
                $rootNode = 'root';
            }

            if (!$foundItems) {
                if (is_array($items) && count($items) > 0) {
                     $foundItems = [$items];
                     $rootNode = 'single_item';
                } else {
                    throw new Exception("XML içinde ürün listesi tespit edilemedi.");
                }
            }

            // Örnek item (İlk ürün)
            $sampleItem = $foundItems[0];
            
            // Tüm anahtarları (fields) çıkar
            $fields = array_keys($sampleItem);

            return [
                'root_node' => $rootNode,
                'sample_item' => $sampleItem,
                'items' => $foundItems, // TÜM ÜRÜNLERİ DÖNÜYORUZ
                'fields' => $fields, 
                'total_count' => count($foundItems)
            ];

        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
}
