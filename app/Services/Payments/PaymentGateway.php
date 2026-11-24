<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    public function name(): string;

    /**
     * @return array{reference:string,entity:?string,instructions:array,external_id:?string}
     */
    public function initiate(string $purpose, int $amount, string $currency, string $method, array $meta = []): array;

    public function requestCharge(Payment $payment, string $recipientName, string $recipientPhone): array;

    /**
     * @return array{paid:bool,reference:string,external_id:string,amount:int}
     */
    public function checkStatus(Payment $payment): array;

    public function verifyWebhook(string $rawBody, array $headers): array;
}
