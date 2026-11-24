<?php

return [
    'visit_fee' => (int) env('VISIT_FEE', 3000),
    'platform_commission_pct' => env('PLATFORM_COMMISSION_PCT', '5.00'),
    'agent_commission_pct' => env('AGENT_COMMISSION_PCT', '5.00'),
    'min_photos' => 5,
    'max_photos' => 10,
    'max_photo_kb' => (int) env('MAX_PHOTO_KB', 5120),
    'max_video_mb' => (int) env('MAX_VIDEO_MB', 20),
    'visit_hold_minutes' => 30,
    'max_agents_per_property' => 5,
    /*
     * Convenção ISO: 1=segunda … 7=domingo.
     * O formulário em roles.js envia domingo como 0; a API aceita 0 e grava 7.
     * O frontend deve passar a enviar 7 para domingo (ver docs/DECISIONS.md).
     */
    'weekday_convention' => 'iso_1_monday_7_sunday',
    'currency' => 'AOA',
    'payment_gateway' => env('PAYMENT_GATEWAY', 'fake'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5500'),
    'gpo' => [
        'base_url' => env('GPO_API_BASE_URL', 'https://gpo.drip.infinityfree.me'),
        'api_key' => env('GPO_API_KEY'),
        'timeout' => (int) env('GPO_API_TIMEOUT', 15),
        'ssl_verify' => filter_var(env('GPO_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    ],
    'fake_webhook_secret' => env('FAKE_PAYMENT_WEBHOOK_SECRET', 'local-fake-secret'),
    'fake_webhook_signature_header' => env('FAKE_PAYMENT_SIGNATURE_HEADER', 'X-Fake-Payment-Signature'),
];
