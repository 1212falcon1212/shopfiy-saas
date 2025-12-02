<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\User;
use App\Models\XmlIntegration;
use App\Services\XmlParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class XmlImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $integration;
    protected $userId;

    public function __construct(XmlIntegration $integration)
    {
        $this->integration = $integration;
        $this->userId = $integration->user_id;
    }

    public function handle(XmlParserService $xmlService)
    {
        Log::info("XML Import Başladı: " . $this->integration->name);

        $parsedData = $xmlService->preview($this->integration->xml_url);

        if (isset($parsedData['error'])) {
            Log::error("XML Okuma Hatası: " . $parsedData['message']);
            return;
        }

        $items = $parsedData['items'] ?? [];
        $mapping = $this->integration->field_mapping; 
        $user = User::find($this->userId);

        Log::info("Toplam " . count($items) . " adet ürün bulundu. İşleniyor...");

        foreach ($items as $item) {
            $this->processItem($item, $mapping, $user);
        }
        
        Log::info("XML Import Tamamlandı.");
    }

    private function processItem($item, $mapping, $user)
    {
        $title = $this->getMappedValue($item, $mapping, 'title', 'İsimsiz Ürün');
        $sku = $this->getMappedValue($item, $mapping, 'sku', 'XML-' . uniqid());
        $price = $this->cleanPrice($this->getMappedValue($item, $mapping, 'price', '0'));
        $stock = (int)$this->getMappedValue($item, $mapping, 'stock', 10);
        $imageSrc = $this->getMappedValue($item, $mapping, 'image');
        $categoryName = $this->getMappedValue($item, $mapping, 'category'); // Kategori

        // 1. Ürün Daha Önce Eklenmiş mi? (Başlığa göre kontrol - Basit Duplicate Önleme)
        $localProduct = Product::where('user_id', $user->id)
            ->where('title', $title)
            ->first();

        $productData = [
            'title' => $title,
            'body_html' => $this->getMappedValue($item, $mapping, 'description', ''),
            'vendor' => 'XML Entegrasyon',
            'product_type' => $categoryName ?? 'Imported', // Kategori buraya
            'status' => 'active',
            'variants' => [
                [
                    'price' => $price,
                    'sku' => $sku,
                    'inventory_quantity' => $stock,
                ]
            ],
            'images' => $imageSrc ? [['src' => $imageSrc]] : []
        ];

        // Eğer ürün güncelleniyorsa, resimleri tekrar yüklemeyelim (opsiyonel, trafik tasarrufu)
        if ($localProduct) {
            unset($productData['images']); 
        }

        $shopifyPayload = ['product' => $productData];
        $method = 'POST';
        $endpoint = '/admin/api/2024-04/products.json';
        $shopifyProductId = null;

        if ($localProduct && $localProduct->shopify_product_id) {
            $method = 'PUT';
            $endpoint = "/admin/api/2024-04/products/{$localProduct->shopify_product_id}.json";
        }

        try {
            // 2. Shopify API İsteği
            $response = $user->api()->rest($method, $endpoint, $shopifyPayload);

            if ($response['errors']) {
                Log::error('Shopify Yükleme Başarısız:', $response['body']->container);
            } else {
                $shopifyProduct = $response['body']->container['product'];
                $shopifyProductId = $shopifyProduct['id'];
                
                // 3. Panel Veritabanını Güncelle
                Product::updateOrCreate(
                    ['id' => $localProduct ? $localProduct->id : null],
                    [
                        'user_id' => $user->id,
                        'shopify_product_id' => $shopifyProductId,
                        'title' => $shopifyProduct['title'],
                        'body_html' => $shopifyProduct['body_html'],
                        'vendor' => $shopifyProduct['vendor'],
                        'product_type' => $shopifyProduct['product_type'],
                        'status' => $shopifyProduct['status'],
                        'total_inventory' => $shopifyProduct['variants'][0]['inventory_quantity'] ?? 0,
                        'image_src' => isset($shopifyProduct['images'][0]) ? $shopifyProduct['images'][0]['src'] : ($localProduct->image_src ?? null),
                        'variants' => $shopifyProduct['variants']
                    ]
                );

                // 4. Kategori (Koleksiyon) Eşleştirmesi
                if ($categoryName) {
                    $this->syncCollection($user, $shopifyProductId, $categoryName);
                }
            }

        } catch (\Exception $e) {
            Log::error("API İstek Hatası (Exception): " . $e->getMessage());
        }
    }

    private function syncCollection($user, $productId, $categoryName)
    {
        // Koleksiyonu Ara
        $findCollection = $user->api()->rest('GET', '/admin/api/2024-04/custom_collections.json', [
            'title' => $categoryName
        ]);
        
        $collectionId = null;
        $foundCollections = [];

        if (!$findCollection['errors']) {
            $body = $findCollection['body']->container ?? $findCollection['body'];
            $foundCollections = $body['custom_collections'] ?? [];
        }

        if (!empty($foundCollections)) {
            $collectionId = $foundCollections[0]['id'];
        } else {
            // Yeni Koleksiyon Oluştur
            $createCollection = $user->api()->rest('POST', '/admin/api/2024-04/custom_collections.json', [
                'custom_collection' => ['title' => $categoryName]
            ]);

            if (!$createCollection['errors']) {
                $body = $createCollection['body']->container ?? $createCollection['body'];
                $collectionId = $body['custom_collection']['id'];
            }
        }

        // Ürünü Koleksiyona Ekle
        if ($collectionId) {
            $user->api()->rest('POST', '/admin/api/2024-04/collects.json', [
                'collect' => [
                    'product_id' => $productId,
                    'collection_id' => $collectionId
                ]
            ]);
        }
    }

    private function getMappedValue($item, $mapping, $shopifyField, $default = null)
    {
        if (isset($mapping[$shopifyField]) && !empty($mapping[$shopifyField])) {
            $xmlKey = $mapping[$shopifyField];
            return $item[$xmlKey] ?? $default; 
        }
        return $default;
    }

    private function cleanPrice($price)
    {
        return preg_replace('/[^0-9.]/', '', $price);
    }
}
