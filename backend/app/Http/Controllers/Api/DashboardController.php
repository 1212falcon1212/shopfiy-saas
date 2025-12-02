<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        // Şimdilik ilk kullanıcıyı alıyoruz (Auth eklenince auth()->user() olacak)
        $user = User::first();

        if (!$user) {
            return response()->json(['error' => 'Mağaza bulunamadı'], 404);
        }

        // 1. İstatistikler
        $totalRevenue = Order::where('user_id', $user->id)->sum('total_price');
        $totalOrders = Order::where('user_id', $user->id)->count();
        $totalProducts = Product::where('user_id', $user->id)->count();

        // Ortalama Sepet Tutarı (Ciro / Sipariş Sayısı)
        $avgOrderValue = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;

        // 2. Son 5 Sipariş
        $recentOrders = Order::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'order_number', 'customer_name', 'total_price', 'currency', 'financial_status', 'created_at']);

        return response()->json([
            'stats' => [
                'revenue' => number_format($totalRevenue, 2, '.', ''),
                'orders' => $totalOrders,
                'products' => $totalProducts,
                'avg_cart' => number_format($avgOrderValue, 2, '.', ''),
                'currency' => 'TRY' // Varsayılan (Siparişten de çekilebilir)
            ],
            'recent_orders' => $recentOrders
        ]);
    }
}
