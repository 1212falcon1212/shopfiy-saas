<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\User;
use App\Jobs\ProductPushJob;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    // ... (Diğer metodlar aynı kalıyor)
    public function index(Request $request)
    {
        $user = User::first(); 
        if (!$user) return response()->json(['error' => 'Mağaza bulunamadı'], 404);

        $query = Product::where('user_id', $user->id);

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('vendor', 'like', "%{$search}%");
            });
        }

        $limit = $request->query('limit', 50);
        $products = $query->orderBy('created_at', 'desc')->paginate($limit);

        return response()->json($products);
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json($product);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'price' => 'required_without:variants', 
        ]);

        $user = User::first(); 

        if (!$user) {
            return response()->json(['error' => 'Kullanıcı bulunamadı'], 404);
        }

        // Varyasyonları hazırla
        $variants = [];
        if ($request->has('variants') && count($request->variants) > 0) {
            foreach ($request->variants as $v) {
                $variants[] = [
                    'option1' => $v['value'], // Değer (Örn: Kırmızı)
                    'name' => $v['name'] ?? 'Seçenek', // İsim (Örn: Renk)
                    'price' => $v['price'],
                    'sku' => $v['sku'] ?? '',
                    'inventory_quantity' => $v['inventory_quantity'] ?? 0,
                ];
            }
        } else {
            // Varyasyon yoksa
            $variants[] = [
                'price' => $request->price,
                'sku' => $request->sku,
                'inventory_quantity' => $request->inventory_quantity ?? 0,
            ];
        }

        // Kategori (Category/Product Type) Yönetimi
        $categoryName = $request->input('category') ?? $request->input('product_type');
        
        if ($categoryName) {
            \App\Models\Category::firstOrCreate(['name' => $categoryName]);
        }

        // Yerel veritabanına kaydet
        $product = Product::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'body_html' => $request->body_html,
            'vendor' => $request->vendor, // Marka (Vendor) veritabanına işleniyor
            'product_type' => $categoryName,
            'status' => $request->status ?? 'draft',
            'total_inventory' => $request->inventory_quantity ?? 0, 
            'image_src' => $request->image_url, 
            'variants' => $variants 
        ]);

        // Kategori (Collection) Seçildiyse (Eski mantık için destek)
        if ($request->has('collection_id')) {
            $product->collection_id_to_sync = $request->collection_id;
        }

        // Kuyruğa at
        ProductPushJob::dispatch($product, $user);

        return response()->json([
            'message' => 'Ürün oluşturuldu ve Shopify gönderim kuyruğuna eklendi!',
            'data' => $product
        ], 201);
    }
    
    public function bulkPush(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id'
        ]);

        $user = User::first();
        
        $count = 0;
        foreach ($request->product_ids as $id) {
            $product = Product::find($id);
            if ($product && $product->user_id == $user->id) {
                ProductPushJob::dispatch($product, $user);
                $count++;
            }
        }

        return response()->json([
            'message' => "{$count} adet ürün Shopify gönderim kuyruğuna eklendi."
        ]);
    }
}
