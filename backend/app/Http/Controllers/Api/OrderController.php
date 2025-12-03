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
        $user = $request->user();
        if (!$user) return response()->json(['error' => 'Kullanıcı bulunamadı'], 401);

        $query = Order::where('user_id', $user->id);

        // Store ID filtresi varsa uygula
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(10); // Sayfalama

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

        // Siparişin kullanıcısının mağazasını bul (Fatura entegrasyonu için)
        // Bu sipariş hangi mağazaya ait? (Şu an Order modelinde store_id yoksa user'dan buluyoruz)
        // İdeal olan Order modelinde store_id olmasıdır. Şimdilik user'ın ilk mağazasını alıyoruz.
        $store = $order->user ? $order->user->stores()->first() : null;

        if (!$store) {
             return response()->json(['error' => 'Bu sipariş için fatura oluşturulacak mağaza bulunamadı.'], 400);
        }

        // Faturayı kuyruğa at (Store bilgisini job'a geçirmemiz gerekebilir ama şu an user üzerinden buluyor)
        CreateInvoiceJob::dispatch($order);

        return response()->json([
            'message' => 'Fatura oluşturma işlemi başlatıldı.',
            'order_number' => $order->order_number,
            'status' => 'processing'
        ]);
    }
}
