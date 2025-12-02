<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\CreateInvoiceJob;

class OrderController extends Controller
{
    // Siparişleri Listele
    public function index(Request $request)
    {
        // Gerçek senaryoda: auth()->id() kullanılır.
        // Test için: user_id = 1 varsayıyoruz (Frontend'den gönderilecek veya Auth middleware eklenecek)
        $userId = $request->query('user_id', 1);

        $orders = Order::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(10); // Sayfalama

        return response()->json($orders);
    }

    // Sipariş Detayı (YENİ)
    public function show($id)
    {
        // İlişkili tabloları (varsa) 'with' ile çekebiliriz, şimdilik line_items JSON olarak tutulduğu için düz çekiyoruz.
        // Ancak JSON cast özelliğini modelde tanımlamış olmalıyız.
        $order = Order::findOrFail($id);

        // Güvenlik kontrolü: Kullanıcı sadece kendi siparişini görebilmeli
        // if ($order->user_id !== auth()->id()) abort(403);

        return response()->json($order);
    }

    // Fatura PDF Oluştur
    public function invoice($id)
    {
        $order = Order::findOrFail($id);

        $pdf = Pdf::loadView('invoice', compact('order'));

        return $pdf->download('fatura-'.$order->order_number.'.pdf');
    }

    public function createInvoice($id)
{
    $order = \App\Models\Order::find($id);

    if (!$order) {
        return response()->json(['error' => 'Sipariş bulunamadı'], 404);
    }

    // Faturayı kuyruğa at
    CreateInvoiceJob::dispatch($order);

    return response()->json([
        'message' => 'Fatura oluşturma işlemi başlatıldı.',
        'order_number' => $order->order_number,
        'status' => 'processing'
    ]);
}
}
