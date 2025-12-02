<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shopify App Name
    |--------------------------------------------------------------------------
    |
    | Uygulamanızın adı.
    |
    */
    'app_name' => env('SHOPIFY_APP_NAME', 'Laravel Shopify App'),

    /*
    |--------------------------------------------------------------------------
    | Shopify API Keys
    |--------------------------------------------------------------------------
    |
    | Shopify Partner panelinden aldığın anahtarlar.
    |
    */
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Shopify API Version
    |--------------------------------------------------------------------------
    |
    | Kullanılacak API sürümü.
    |
    */
    'api_version' => env('SHOPIFY_API_VERSION', '2024-01'),

    /*
    |--------------------------------------------------------------------------
    | Shopify Scopes
    |--------------------------------------------------------------------------
    |
    | İzinler (.env dosyasından çeker).
    |
    */
    'api_scopes' => env('SHOPIFY_APP_SCOPES', 'read_products,write_products'),

    /*
    |--------------------------------------------------------------------------
    | Shop Session Security
    |--------------------------------------------------------------------------
    */
    'myshopify_domain' => env('SHOPIFY_MYSHOPIFY_DOMAIN', 'myshopify.com'),

    /*
    |--------------------------------------------------------------------------
    | Route Names
    |--------------------------------------------------------------------------
    */
    'route_names' => [
        'home' => 'home',
        'authenticate' => 'authenticate',
        'authenticate.token' => 'authenticate.token',
        'billing' => 'billing',
        'billing.process' => 'billing.process',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Shopify'dan gelecek sinyaller.
    | Bizim "OrdersCreateJob" dosyamızın çalışması için burayı ayarladık.
    |
    */
    'webhooks' => [
        [
            'topic' => 'orders/create',
            'address' => env('APP_URL') . '/webhook/orders-create',
        ],
        [
            'topic' => 'app/uninstalled',
            'address' => env('APP_URL') . '/webhook/app-uninstalled',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Namespace
    |--------------------------------------------------------------------------
    |
    | Webhook geldiğinde hangi klasördeki Job'ı arayacak?
    | Örn: 'orders/create' geldiğinde 'App\Jobs\OrdersCreateJob' dosyasını arar.
    |
    */
    'job_namespace' => 'App\\Jobs',

    /*
    |--------------------------------------------------------------------------
    | Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env('SHOPIFY_APP_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => 'verify.shopify',

    /*
    |--------------------------------------------------------------------------
    | Turbo/Turbolinks
    |--------------------------------------------------------------------------
    */
    'turbo_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    */
    'billing_enabled' => env('SHOPIFY_BILLING_ENABLED', false),
    'billing_freemium_enabled' => env('SHOPIFY_BILLING_FREEMIUM_ENABLED', false),
    'billing_redirect' => env('SHOPIFY_BILLING_REDIRECT', null),

];
