<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Store; // Store modelini ekledik
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProductsSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $store; // user yerine store

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function handle(): void
    {
        Log::info("Ürün Senkronizasyonu Başladı (Mağaza: {$this->store->domain})");

        // Tüm ürünleri çekelim (Pagination gerekebilir, şimdilik 250 limit)
        try {
            $response = $this->store->api()->rest('GET', '/admin/api/2024-01/products.json', ['limit' => 250]);

            if ($response['errors']) {
                Log::error("Ürünler çekilemedi:", ['body' => $response['body'], 'store_id' => $this->store->id]);
                return;
            }

            $products = $response['body']['products'];

            foreach ($products as $shopifyProduct) {
                // Hem shopify_product_id hem de store_id'ye göre kontrol et
                $localProduct = Product::where('shopify_product_id', $shopifyProduct['id'])
                    ->where('store_id', $this->store->id)
                    ->first();

                if ($localProduct) {
                    // Sadece stok ve fiyat güncelle (Mevcut ürünse)
                    $localProduct->update([
                        'total_inventory' => $this->calculateTotalInventory($shopifyProduct['variants']),
                        'variants' => $shopifyProduct['variants']
                    ]);
                } else {
                    // Yeni ürünse oluştur
                    Product::create([
                        'user_id' => $this->store->user_id, // Store'un sahibi olan user
                        'store_id' => $this->store->id,     // Hangi mağazaya ait olduğu
                        'shopify_product_id' => $shopifyProduct['id'],
                        'title' => $shopifyProduct['title'],
                        'body_html' => $shopifyProduct['body_html'],
                        'vendor' => $shopifyProduct['vendor'],
                        'product_type' => $shopifyProduct['product_type'],
                        'status' => $shopifyProduct['status'],
                        'total_inventory' => $this->calculateTotalInventory($shopifyProduct['variants']),
                        'image_src' => isset($shopifyProduct['images'][0]) ? $shopifyProduct['images'][0]['src'] : null,
                        'variants' => $shopifyProduct['variants']
                    ]);
                }
            }

            Log::info(count($products) . " adet ürün senkronize edildi. (Mağaza: {$this->store->domain})");

        } catch (\Exception $e) {
            Log::error("Ürün senkronizasyonu sırasında hata:", ['error' => $e->getMessage(), 'store_id' => $this->store->id]);
        }
    }

    private function calculateTotalInventory($variants) {
        $total = 0;
        foreach ($variants as $variant) {
            $total += $variant['inventory_quantity'] ?? 0;
        }
        return $total;
    }
}
