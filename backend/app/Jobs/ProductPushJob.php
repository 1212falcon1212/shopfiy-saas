<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $product;
    protected $user;

    public function __construct(Product $product, User $user)
    {
        $this->product = $product;
        $this->user = $user;
    }

    public function handle(): void
    {
        Log::info("Ürün Push Başladı: ID {$this->product->id}");

        try {
            // Varyasyon Kontrolü
            $hasVariants = false;
            $optionName = 'Seçenek'; // Varsayılan

            if (!empty($this->product->variants) && is_array($this->product->variants)) {
                foreach ($this->product->variants as $v) {
                    if (isset($v['option1']) && !empty($v['option1'])) {
                        $hasVariants = true;
                        // Frontend'den gelen 'name' (Örn: Renk) bilgisini al
                        if (isset($v['name'])) {
                            $optionName = $v['name'];
                        }
                        break;
                    }
                }
            }

            // 1. Resim İşleme (Local vs Remote)
            $imagePayload = [];
            if ($this->product->image_src) {
                // Eğer URL yerel ise (localhost veya kendi domainimiz)
                // Bu basit bir kontrol, prodüksiyonda APP_URL ile kontrol edilebilir.
                if (Str::contains($this->product->image_src, ['localhost', '127.0.0.1'])) {
                    
                    // URL'den dosya yolunu çıkaralım
                    // Örn: http://localhost:8000/storage/uploads/xyz.jpg -> uploads/xyz.jpg
                    $path = str_replace(asset('storage/'), '', $this->product->image_src);
                    
                    if (Storage::disk('public')->exists($path)) {
                        $fileContent = Storage::disk('public')->get($path);
                        $base64 = base64_encode($fileContent);
                        $imagePayload = [['attachment' => $base64]];
                    } else {
                        Log::warning("Resim dosyası bulunamadı: $path");
                    }
                } else {
                    // Uzak URL (Amazon S3, başka site vb.) -> Doğrudan src kullan
                    $imagePayload = [['src' => $this->product->image_src]];
                }
            }

            // Shopify Payload
            $productData = [
                'title' => $this->product->title,
                'body_html' => $this->product->body_html,
                'vendor' => $this->product->vendor,
                'product_type' => $this->product->product_type,
                'status' => $this->product->status ?? 'active',
                'images' => $imagePayload,
            ];

            if ($hasVariants) {
                // Varyasyonlu Ürün
                $productData['options'] = [
                    ['name' => $optionName] // Kullanıcının girdiği seçenek adı (Renk, Beden vb.)
                ];
                
                // Variants array'ini Shopify formatına uyarla (name alanını temizle, sadece option1 kalsın)
                $cleanVariants = [];
                foreach ($this->product->variants as $v) {
                    $cleanVariants[] = [
                        'option1' => $v['option1'],
                        'price' => $v['price'],
                        'sku' => $v['sku'] ?? '',
                        'inventory_quantity' => $v['inventory_quantity'] ?? 0,
                    ];
                }
                $productData['variants'] = $cleanVariants;
            } else {
                // Tekil Ürün
                $productData['variants'] = $this->product->variants;
            }

            $shopifyPayload = ['product' => $productData];

            // 1. Ürünü Oluştur/Güncelle
            if ($this->product->shopify_product_id) {
                $endpoint = "/admin/api/2024-04/products/{$this->product->shopify_product_id}.json";
                $method = 'PUT';
            } else {
                $endpoint = '/admin/api/2024-04/products.json';
                $method = 'POST';
            }

            $response = $this->user->api()->rest($method, $endpoint, $shopifyPayload);

            if ($response['errors']) {
                Log::error("Shopify Push Hatası:", $response['body']->container);
                return;
            }

            $shopifyProduct = $response['body']->container['product'];
            $shopifyProductId = $shopifyProduct['id'];

            // Başarılı ise güncelle
            if ($method === 'POST') {
                $this->product->update([
                    'shopify_product_id' => $shopifyProductId,
                    'status' => 'active'
                ]);
                Log::info("Ürün Başarıyla Eklendi: {$shopifyProductId}");
            }

            // 2. Koleksiyon (Category) Eşleştirmesi
            // Kullanıcının girdiği Kategori (product_type) isminde bir Custom Collection var mı bakalım.
            // Yoksa oluşturalım ve ürünü oraya ekleyelim.
            if ($this->product->product_type) {
                $categoryName = $this->product->product_type;
                $collectionId = null;

                // A. Koleksiyonu Ara
                $findCollection = $this->user->api()->rest('GET', '/admin/api/2024-04/custom_collections.json', [
                    'title' => $categoryName
                ]);

                // Hata Ayıklama ve Güvenli Erişim
                $foundCollections = [];
                if (!$findCollection['errors']) {
                    // ResponseAccess object'i array'e dönüştürmek veya container'ı kullanmak en güvenlisi
                    $body = $findCollection['body']->container ?? $findCollection['body'];
                    $foundCollections = $body['custom_collections'] ?? [];
                }

                if (!empty($foundCollections)) {
                    // Bulundu
                    $collectionId = $foundCollections[0]['id'];
                } else {
                    // B. Bulunamadı -> Yeni Oluştur
                    $createCollection = $this->user->api()->rest('POST', '/admin/api/2024-04/custom_collections.json', [
                        'custom_collection' => [
                            'title' => $categoryName
                        ]
                    ]);

                    if (!$createCollection['errors']) {
                        $body = $createCollection['body']->container ?? $createCollection['body'];
                        $collectionId = $body['custom_collection']['id'];
                        Log::info("Yeni Koleksiyon Oluşturuldu: $categoryName ($collectionId)");
                    } else {
                        Log::error("Koleksiyon Oluşturma Hatası ($categoryName):", $createCollection['body']->container);
                    }
                }

                // C. Ürünü Koleksiyona Ekle
                if ($collectionId) {
                    $collectPayload = [
                        'collect' => [
                            'product_id' => $shopifyProductId,
                            'collection_id' => $collectionId
                        ]
                    ];
                    
                    $collectResponse = $this->user->api()->rest('POST', '/admin/api/2024-04/collects.json', $collectPayload);
                    
                    if (!$collectResponse['errors']) {
                        Log::info("Ürün '$categoryName' Koleksiyonuna Eklendi.");
                    } else {
                        // Zaten ekli hatası olabilir, loglayıp geçelim
                        Log::warning("Koleksiyon Eşleştirme Uyarısı:", $collectResponse['body']->container);
                    }
                }
            }

            // (Eski) Manuel ID ile eşleştirme (Yedek)
            if (isset($this->product->collection_id_to_sync) && $this->product->collection_id_to_sync) {
                $collectPayload = [
                    'collect' => [
                        'product_id' => $shopifyProductId,
                        'collection_id' => $this->product->collection_id_to_sync
                    ]
                ];
                
                $collectResponse = $this->user->api()->rest('POST', '/admin/api/2024-04/collects.json', $collectPayload);
                
                if (!$collectResponse['errors']) {
                    Log::info("Ürün Koleksiyona Eklendi: {$this->product->collection_id_to_sync}");
                } else {
                    Log::error("Koleksiyon Eşleştirme Hatası:", $collectResponse['body']->container);
                }
            }

        } catch (\Exception $e) {
            Log::error("ProductPushJob Exception: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
