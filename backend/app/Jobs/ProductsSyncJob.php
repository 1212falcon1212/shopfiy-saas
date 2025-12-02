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

class ProductsSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        Log::info("Ürün Senkronizasyonu Başladı (Stok/Fiyat Güncelleme): {$this->user->name}");

        // Tüm ürünleri çekelim (Pagination gerekebilir, şimdilik 250 limit)
        $response = $this->user->api()->rest('GET', '/admin/api/2024-04/products.json', ['limit' => 250]);

        if ($response['errors']) {
            Log::error("Ürünler çekilemedi:", $response['body']);
            return;
        }

        $products = $response['body']['products'];

        foreach ($products as $shopifyProduct) {
            $localProduct = Product::where('shopify_product_id', $shopifyProduct['id'])->first();

            if ($localProduct) {
                // Sadece stok ve fiyat güncelle (Mevcut ürünse)
                // Varyasyonlar JSON olarak tutulduğu için tüm variants dizisini güncelliyoruz
                // Ancak başlık, açıklama, resim gibi alanlara dokunmuyoruz.
                
                $localProduct->update([
                    'total_inventory' => $this->calculateTotalInventory($shopifyProduct['variants']),
                    'variants' => $shopifyProduct['variants']
                ]);
            } else {
                // Yeni ürünse oluştur
                Product::create([
                    'user_id' => $this->user->id,
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

        Log::info(count($products) . " adet ürün senkronize edildi.");
    }

    private function calculateTotalInventory($variants) {
        $total = 0;
        foreach ($variants as $variant) {
            $total += $variant['inventory_quantity'] ?? 0;
        }
        return $total;
    }
}
