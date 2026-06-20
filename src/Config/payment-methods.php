<?php

use Webkul\Iyzico\Payment\Iyzico;

return [
    'iyzico' => [
        'class' => Iyzico::class,
        'code' => 'iyzico',
        'title' => 'Iyzico',
        'description' => 'Pay securely with your credit/debit card via Iyzico.',
        'active' => false,
        'sandbox' => true,
        'api_test_key' => '',
        'api_test_secret_key' => '',
        'api_live_key' => '',
        'api_live_secret_key' => '',
        'identity_number' => '',
        'locale' => 'TR',
        'secure3d' => true,
        'sort' => 10,
    ],
];
