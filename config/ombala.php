<?php

return [
    'base_url' => env('OMBALA_BASE_URL', 'https://api.useombala.ao'),
    'api_token' => env('OMBALA_API_TOKEN'),
    'sms_from' => env('OMBALA_SMS_FROM'),
    'timeout' => (int) env('OMBALA_TIMEOUT', 15),
    'ssl_verify' => filter_var(env('OMBALA_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    'context' => 'intermediarius-registration',
    'otp_length' => 6,
    'expires_in' => 300,
    'max_attempts' => 5,
    'resend_cooldown_seconds' => 60,
    'max_sends_per_hour' => 3,
    'registration_valid_minutes' => 15,
];
