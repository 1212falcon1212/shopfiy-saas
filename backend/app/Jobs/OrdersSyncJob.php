<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrdersSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        Log::info("Eski Siparişleri Çekme Başladı: {$this->user->name}");

        // Shopify'dan son 250 siparişi iste
        $response = $this->user->api()->rest('GET', '/admin/api/2024-04/orders.json', [
            'status' => 'any', // Açık/Kapalı tüm siparişler
            'limit' => 250
        ]);

        if ($response['errors']) {
            Log::error("Siparişler çekilemedi:", $response['body']);
            return;
        }

        $orders = $response['body']['orders'];
        Log::info("Bulunan sipariş sayısı: " . count($orders));

        foreach ($orders as $orderData) {
            // Veritabanına kaydet (Webhook mantığının aynısı)
            Order::updateOrCreate(
                ['shopify_order_id' => $orderData['id']],
                [
                    'user_id' => $this->user->id,
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

        Log::info("Eski siparişler başarıyla senkronize edildi.");
    }
}
