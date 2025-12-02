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

class OrdersCreateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $shopDomain;
    public $data;

    /**
     * Webhook'tan gelen veriyi alıyoruz
     */
    public function __construct(string $shopDomain, object $data)
    {
        $this->shopDomain = $shopDomain; // Örn: test-magaza.myshopify.com
        $this->data = $data; // Sipariş JSON verisi
    }

    public function handle(): void
    {
        // 1. Mağazayı Bul (Basit Laravel Modeli ile)
        // name sütunu Shopify domain'ini tutar.
        $user = User::where('name', $this->shopDomain)->first();

        if (!$user) {
            Log::error("Sipariş geldi ama mağaza bulunamadı: {$this->shopDomain}");
            return;
        }

        // 2. Sipariş Verilerini Al
        $orderData = $this->data;

        Log::info("Yeni Sipariş Yakalandı: " . ($orderData->name ?? 'İsimsiz'));

        // 3. Veritabanına Kaydet veya Güncelle
        Order::updateOrCreate(
            ['shopify_order_id' => $orderData->id],
            [
                'user_id' => $user->id,
                'order_number' => $orderData->name ?? '',
                'customer_name' => isset($orderData->customer)
                    ? ($orderData->customer->first_name ?? '') . ' ' . ($orderData->customer->last_name ?? '')
                    : 'Misafir',
                'customer_email' => $orderData->email ?? null,
                'shipping_address' => $orderData->shipping_address ?? [],
                'total_price' => $orderData->total_price ?? 0,
                'currency' => $orderData->currency ?? 'TRY',
                'financial_status' => $orderData->financial_status ?? 'pending',
                'fulfillment_status' => $orderData->fulfillment_status ?? 'unfulfilled',
                'line_items' => $orderData->line_items ?? [],
                'shipping_lines' => $orderData->shipping_lines ?? []
            ]
        );

        // 4. Fatura Oluştur
        CreateInvoiceJob::dispatch($order->fresh());
    }
}
