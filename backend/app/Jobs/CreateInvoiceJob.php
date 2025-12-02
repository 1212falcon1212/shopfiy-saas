<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\KolaySoftService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;

    /**
     * Job oluşturulurken sipariş modelini alıyoruz.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Job çalıştığında yapılacaklar.
     */
    public function handle(KolaySoftService $kolaySoftService)
    {
        // Eğer zaten fatura kesildiyse tekrar deneme
        if ($this->order->invoice_status === 'completed') {
            return;
        }

        try {
            // Durumu güncelle: İşleniyor
            $this->order->update(['invoice_status' => 'processing']);

            // Servise sipariş verilerini gönder (Mapping işlemi burada veya serviste yapılabilir)
            // Endpointleri verdiğinde $result yapısını netleştireceğiz.
            $result = $kolaySoftService->createInvoice($this->order);

            // Başarılı olursa veritabanını güncelle
            if ($result['success']) {
                $this->order->update([
                    'invoice_status' => 'completed',
                    'invoice_number' => $result['invoice_number'] ?? null,
                    'invoice_external_id' => $result['invoice_id'] ?? null,
                    'invoice_url' => $result['url'] ?? null,
                    'invoice_error' => null
                ]);
                
                Log::info("Fatura başarıyla kesildi. Sipariş ID: " . $this->order->id);
            } else {
                throw new \Exception($result['message'] ?? 'Bilinmeyen API hatası');
            }

        } catch (\Exception $e) {
            // Hata durumunda güncelle
            $this->order->update([
                'invoice_status' => 'failed',
                'invoice_error' => $e->getMessage()
            ]);
            
            Log::error("Fatura kesilemedi. Sipariş ID: " . $this->order->id . " Hata: " . $e->getMessage());
            
            // Job'ı (isteğe bağlı) tekrar denemek için serbest bırakabilirsin
            // $this->release(60); 
        }
    }
}