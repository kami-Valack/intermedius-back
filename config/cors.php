<?php

$origins = array_values(array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL', 'http://localhost:5500,http://127.0.0.1:5500,http://localhost:8080,http://127.0.0.1:8080')))));

if (env('APP_ENV') === 'production') {
    $origins = array_values(array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL', '')))));
    $origins = array_values(array_filter($origins, fn ($origin) => $origin !== '*'));
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
