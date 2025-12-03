<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Store; // Store modelini kullan
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrdersSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $store; // Store değişkeni

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function handle(): void
    {
        Log::info("Siparişleri Çekme Başladı (Mağaza: {$this->store->domain})");

        try {
            // Shopify'dan son 250 siparişi iste
            $response = $this->store->api()->rest('GET', '/admin/api/2024-01/orders.json', [
                'status' => 'any', // Açık/Kapalı tüm siparişler
                'limit' => 250
            ]);

            if ($response['errors']) {
                Log::error("Siparişler çekilemedi:", ['body' => $response['body'], 'store_id' => $this->store->id]);
                return;
            }

            $orders = $response['body']['orders'];
            Log::info("Bulunan sipariş sayısı: " . count($orders));

            foreach ($orders as $orderData) {
                // Veritabanına kaydet (store_id ile birlikte)
                Order::updateOrCreate(
                    ['shopify_order_id' => $orderData['id']],
                    [
                        'user_id' => $this->store->user_id,
                        'store_id' => $this->store->id, // store_id ekle
                        'order_number' => $orderData['name'],
                        'customer_name' => isset($orderData['customer'])
                            ? ($orderData['customer']['first_name'] ?? '') . ' ' . ($orderData['customer']['last_name'] ?? '')
                            : 'Misafir',
                        'customer_email' => $orderData['email'] ?? null,
                        'shipping_address' => $orderData['shipping_address'] ?? [],
                        'total_price' => $orderData['total_price'],
                        'currency' => $orderData['currency'],
                        'financial_status' => $orderData['financial_status'] ?? 'pending',
                        'fulfillment_status' => $orderData['fulfillment_status'] ?? 'unfulfilled',
                        'line_items' => $orderData['line_items']
                    ]
                );
            }

            Log::info("Eski siparişler başarıyla senkronize edildi. (Mağaza: {$this->store->domain})");

        } catch (\Exception $e) {
            Log::error("Sipariş senkronizasyonu hatası:", ['error' => $e->getMessage(), 'store_id' => $this->store->id]);
        }
    }
}
