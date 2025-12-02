<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\User; // User modelini ekledik
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    public function index()
    {
        // GÜNCELLEME: Auth::user() yerine User::first() kullanıyoruz (Dashboard gibi)
        // Bu sayede token sorunu yaşamadan test edebiliriz.
        $user = User::first();

        if (!$user) {
            return response()->json(['error' => 'Kullanıcı bulunamadı'], 404);
        }

        // 1. Kullanıcının zaten bir planı var mı kontrol et
        if ($user->plan_id) {
            // Test ederken sürekli "Zaten planınız var" demesin diye burayı geçici olarak pasif yapabilirsin
            // return response()->json(['message' => 'Zaten bir planınız var.', 'active' => true]);
        }

        // 2. Pro Planı Bul (RECURRING tipindeki ilk plan)
        $plan = Plan::where('type', 'RECURRING')->first();

        if (!$plan) {
            return response()->json(['error' => 'Plan bulunamadı. db:seed çalıştırdınız mı?'], 500);
        }

        // 3. Shopify API ile Ödeme Linki Oluştur
        try {
            $response = $user->api()->rest('POST', '/admin/api/2024-01/recurring_application_charges.json', [
                'recurring_application_charge' => [
                    'name' => $plan->name,
                    'price' => $plan->price,
                    // test: true olduğu için gerçek kartınızdan para çekmez
                    'return_url' => env('APP_URL') . '/api/billing/process',
                    'test' => true,
                    'trial_days' => $plan->trial_days ?? 3,
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Shopify Billing Exception: ' . $e->getMessage());
            return response()->json(['error' => 'Shopify API Hatası: ' . $e->getMessage()], 500);
        }

        if ($response['errors']) {
             \Illuminate\Support\Facades\Log::error('Shopify Billing API Error Status: ' . ($response['status'] ?? 'Unknown'));
             // Body'yi string'e çevirip, çok uzunsa keselim
             $bodyContent = is_string($response['body']) ? $response['body'] : json_encode($response['body']);
             \Illuminate\Support\Facades\Log::error('Shopify Billing API Body Start: ' . substr($bodyContent, 0, 500));
             
            return response()->json(['error' => 'Shopify Billing Hatası', 'details' => $response['body'] ?? 'No body'], 500);
        }

        $confirmationUrl = $response['body']['recurring_application_charge']['confirmation_url'];

        return response()->json(['confirmation_url' => $confirmationUrl]);
    }

    public function process(Request $request)
    {
        $chargeId = $request->query('charge_id');
        $user = User::first(); // Burada da User::first() kullanıyoruz

        if (!$chargeId) {
            return redirect(env('FRONTEND_URL') . '/dashboard?billing=error');
        }

        // Charge'ı aktif et
        $response = $user->api()->rest('POST', "/admin/api/2024-01/recurring_application_charges/{$chargeId}/activate.json");

        if (!$response['errors']) {
            $plan = Plan::where('type', 'RECURRING')->first();
            $user->plan_id = $plan->id;
            $user->save();

            return redirect(env('FRONTEND_URL') . '/dashboard?billing=success');
        }

        return redirect(env('FRONTEND_URL') . '/dashboard?billing=failed');
    }
}
