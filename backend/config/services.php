<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'kolaysoft' => [
        'base_url' => env('KOLAYSOFT_BASE_URL'),
        'username' => env('KOLAYSOFT_USERNAME'),
        'password' => env('KOLAYSOFT_PASSWORD'),
        'supplier_vkn_tckn' => env('KOLAYSOFT_SUPPLIER_VKN_TCKN'), // Gönderici VKN/TCKN (XML'deki supplier ile eşleşmeli)
        'supplier_name' => env('KOLAYSOFT_SUPPLIER_NAME', 'SaaS Magaza A.S.'), // Gönderici Firma Adı
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'figma' => [
        'access_token' => env('FIGMA_ACCESS_TOKEN'),
        'base_url' => 'https://api.figma.com/v1',
    ],

];
