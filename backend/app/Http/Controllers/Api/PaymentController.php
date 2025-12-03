<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PayTrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $paytrService;

    public function __construct(PayTrService $paytrService)
    {
        $this->paytrService = $paytrService;
    }

    /**
     * Ödeme sürecini başlatır ve iFrame token döner.
     */
    public function init(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'interval' => 'required|in:monthly,yearly',
            'currency' => 'required|in:TRY,USD,EUR',
        ]);

        $user = Auth::user();
        $plan = Plan::findOrFail($request->plan_id);

        // Fiyatı belirle
        $price = match ($request->currency) {
            'TRY' => $plan->price_try,
            'USD' => $plan->price_usd,
            'EUR' => $plan->price_eur,
            default => $plan->price_usd,
        };

        // Sipariş numarası oluştur
        $merchant_oid = 'SAAS-' . time() . '-' . $user->id;

        // Ödeme kaydı oluştur
        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'interval' => $request->interval,
            'amount' => $price,
            'currency' => $request->currency,
            'status' => 'pending',
            'paytr_merchant_oid' => $merchant_oid,
        ]);

        // Kullanıcı sepeti (Plan bilgisi) - İsim JSON olduğu için decode ediyoruz veya string'e çeviriyoruz
        $planName = json_decode($plan->name, true)['tr'] ?? 'Plan';
        $user_basket = [
            [$planName, $price, 1]
        ];

        // PayTR Token İsteği
        $data = [
            'email' => $user->email,
            'payment_amount' => $price,
            'merchant_oid' => $merchant_oid,
            'user_name' => $user->name ?? 'Kullanıcı',
            'user_address' => 'Dijital Hizmet', // Adres zorunlu değilse veya kullanıcıdan alınmıyorsa
            'user_phone' => '05555555555', // Telefon zorunlu, kullanıcıdan alınmalı veya dummy
            'merchant_ok_url' => config('app.frontend_url') . '/pricing/success',
            'merchant_fail_url' => config('app.frontend_url') . '/pricing/failed',
            'user_basket' => $user_basket,
            'currency' => $request->currency === 'TRY' ? 'TL' : $request->currency,
        ];

        $result = $this->paytrService->createPaymentToken($data);

        if ($result['status'] == 'success') {
            return response()->json([
                'success' => true,
                'token' => $result['token']
            ]);
        } else {
            Log::error('PayTR Token Error: ' . json_encode($result));
            return response()->json([
                'success' => false,
                'message' => 'Ödeme başlatılamadı: ' . ($result['reason'] ?? 'Bilinmeyen hata')
            ], 400);
        }
    }

    /**
     * PayTR Callback (Webhook)
     */
    public function callback(Request $request)
    {
        // PayTR'dan gelen POST isteği
        $postData = $request->all();

        Log::info('PayTR Callback:', $postData);

        // Hash doğrulama
        if (!$this->paytrService->validateCallback($postData)) {
            return response('PAYTR notification failed: bad hash', 400);
        }

        $merchant_oid = $postData['merchant_oid'];
        $payment = Payment::where('paytr_merchant_oid', $merchant_oid)->first();

        if (!$payment) {
            return response('PAYTR notification failed: order not found', 404);
        }

        if ($postData['status'] == 'success') {
            // Ödeme Başarılı
            $payment->update([
                'status' => 'success',
                'paytr_ref_no' => $postData['paytr_ref_no'] ?? null,
            ]);

            // Kullanıcının mevcut aboneliğini kontrol et
            $existingSubscription = Subscription::where('user_id', $payment->user_id)
                ->where('status', 'active')
                ->first();

            if ($existingSubscription) {
                // Varsa süresi doldu mu veya iptal mi edelim?
                // Eğer plan değişiyorsa, eskisini iptal et.
                // Eğer aynı plansa süreyi uzat.
                if ($existingSubscription->plan_id != $payment->plan_id) {
                    $existingSubscription->update(['status' => 'cancelled', 'canceled_at' => now()]);
                    $this->createNewSubscription($payment);
                } else {
                    $this->extendSubscription($existingSubscription, $payment->interval);
                }
            } else {
                $this->createNewSubscription($payment);
            }

        } else {
            // Ödeme Başarısız
            $payment->update([
                'status' => 'failed',
                'error_message' => $postData['failed_reason_code'] . ' - ' . $postData['failed_reason_msg']
            ]);
        }

        return response('OK');
    }

    private function createNewSubscription(Payment $payment)
    {
        $endsAt = $payment->interval === 'yearly' ? now()->addYear() : now()->addMonth();

        $subscription = Subscription::create([
            'user_id' => $payment->user_id,
            'plan_id' => $payment->plan_id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $endsAt,
        ]);
        
        $payment->update(['subscription_id' => $subscription->id]);
    }

    private function extendSubscription(Subscription $subscription, string $interval)
    {
        $currentEndsAt = $subscription->ends_at && $subscription->ends_at->isFuture() 
            ? $subscription->ends_at 
            : now();
            
        $newEndsAt = $interval === 'yearly' 
            ? $currentEndsAt->addYear() 
            : $currentEndsAt->addMonth();

        $subscription->update([
            'ends_at' => $newEndsAt,
            'status' => 'active' // Süresi dolmuşsa tekrar aktif yap
        ]);
    }
}
