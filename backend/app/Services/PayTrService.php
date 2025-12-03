<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PayTrService
{
    protected $merchantId;
    protected $merchantKey;
    protected $merchantSalt;
    protected $testMode;

    public function __construct()
    {
        $this->merchantId = config('services.paytr.merchant_id');
        $this->merchantKey = config('services.paytr.merchant_key');
        $this->merchantSalt = config('services.paytr.merchant_salt');
        $this->testMode = config('services.paytr.test_mode');
    }

    /**
     * PayTR iFrame Token oluşturur.
     */
    public function createPaymentToken(array $data)
    {
        // Zorunlu alanlar:
        // email, payment_amount, merchant_oid, user_name, user_address, user_phone
        // merchant_ok_url, merchant_fail_url, user_basket, currency

        $merchant_id = $this->merchantId;
        $merchant_key = $this->merchantKey;
        $merchant_salt = $this->merchantSalt;

        $email = $data['email'];
        $payment_amount = $data['payment_amount'] * 100; // Kuruş cinsinden
        $merchant_oid = $data['merchant_oid'];
        $user_name = $data['user_name'];
        $user_address = $data['user_address'];
        $user_phone = $data['user_phone'];
        $merchant_ok_url = $data['merchant_ok_url'];
        $merchant_fail_url = $data['merchant_fail_url'];
        $user_basket = base64_encode(json_encode($data['user_basket']));
        $user_ip = request()->ip();
        $timeout_limit = "30";
        $debug_on = 1;
        $test_mode = $this->testMode;
        $no_installment = 0;
        $max_installment = 0;
        $currency = $data['currency'] ?? 'TL'; // TL, USD, EUR

        $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $user_basket . $no_installment . $max_installment . $currency . $test_mode;
        $paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));

        $post_vals = [
            'merchant_id' => $merchant_id,
            'user_ip' => $user_ip,
            'merchant_oid' => $merchant_oid,
            'email' => $email,
            'payment_amount' => $payment_amount,
            'paytr_token' => $paytr_token,
            'user_basket' => $user_basket,
            'debug_on' => $debug_on,
            'no_installment' => $no_installment,
            'max_installment' => $max_installment,
            'user_name' => $user_name,
            'user_address' => $user_address,
            'user_phone' => $user_phone,
            'merchant_ok_url' => $merchant_ok_url,
            'merchant_fail_url' => $merchant_fail_url,
            'timeout_limit' => $timeout_limit,
            'currency' => $currency,
            'test_mode' => $test_mode
        ];

        // PayTR API'ye istek at
        $response = Http::asForm()->post('https://www.paytr.com/odeme/api/get-token', $post_vals);

        return $response->json();
    }

    /**
     * PayTR Callback doğrulama.
     */
    public function validateCallback($postData)
    {
        $merchant_key = $this->merchantKey;
        $merchant_salt = $this->merchantSalt;

        $hash = base64_encode(hash_hmac('sha256', $postData['merchant_oid'] . $merchant_salt . $postData['status'] . $postData['total_amount'], $merchant_key, true));

        return $hash === $postData['hash'];
    }
}

