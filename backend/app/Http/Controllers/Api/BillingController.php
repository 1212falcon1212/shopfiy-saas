<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class BillingController extends Controller
{
    /**
     * Fatura Oluşturma ve Yönlendirme (Plan Seçimi)
     */
    public function index(Request $request)
    {
        $user = auth()->user(); // Middleware'den geçerse user gelir
        
        // Eğer user yoksa (Middleware redirect edince session bazen kaybolabilir API'de)
        // Token ile gelmesi lazım.
        if (!$user) {
            // Fallback: İlk user (Test için)
            $user = User::first();
        }

        // Planı Bul (Tek planımız var: Pro Plan)
        $plan = Plan::where('name', 'Pro Plan')->first();

        if (!$plan) {
            return response()->json(['error' => 'Plan bulunamadı.'], 500);
        }

        // Shopify API ile Charge Oluştur
        try {
            $payload = [
                'recurring_application_charge' => [
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'return_url' => route('billing.process'), // Callback
                    'test' => $plan->test,
                    'trial_days' => $plan->trial_days,
                ]
            ];

            $response = $user->api()->rest('POST', '/admin/api/2024-04/recurring_application_charges.json', $payload);

            if ($response['errors']) {
                Log::error('Billing Charge Error:', $response['body']->container);
                return response()->json(['error' => 'Fatura oluşturulamadı.'], 500);
            }

            $charge = $response['body']->container['recurring_application_charge'];
            
            // Confirmation URL'i frontend'e dön
            // Frontend bu URL'e window.location.href ile gidecek
            return response()->json([
                'confirmation_url' => $charge['confirmation_url']
            ]);

        } catch (\Exception $e) {
            Log::error('Billing Exception: ' . $e->getMessage());
            return response()->json(['error' => 'Bir hata oluştu.'], 500);
        }
    }

    /**
     * Callback: Shopify'dan dönüş
     */
    public function process(Request $request)
    {
        $chargeId = $request->query('charge_id');
        $user = User::first(); // Callback'te auth olmayabilir, shop domain'den bulmak lazım ama şimdilik first()

        if (!$chargeId) {
            return redirect('http://localhost:3000?error=charge_missing');
        }

        try {
            // Charge'ı bul
            $response = $user->api()->rest('GET', "/admin/api/2024-04/recurring_application_charges/{$chargeId}.json");
            $charge = $response['body']->container['recurring_application_charge'];

            if ($charge['status'] === 'accepted') {
                // Aktive Et
                $activateResponse = $user->api()->rest('POST', "/admin/api/2024-04/recurring_application_charges/{$chargeId}/activate.json", [
                    'recurring_application_charge' => [
                        'id' => $chargeId,
                        'name' => $charge['name'],
                        'price' => $charge['price'],
                        'test' => $charge['test'],
                    ]
                ]);
                
                // DB'ye kaydet (Basitçe plan_id güncelle)
                $plan = Plan::where('name', 'Pro Plan')->first();
                $user->plan_id = $plan->id;
                $user->save();

                // Frontend'e başarıyla dön
                return redirect('http://localhost:3000?billing=success');
            }

            return redirect('http://localhost:3000?error=charge_declined');

        } catch (\Exception $e) {
            Log::error('Billing Process Exception: ' . $e->getMessage());
            return redirect('http://localhost:3000?error=server_error');
        }
    }
}
