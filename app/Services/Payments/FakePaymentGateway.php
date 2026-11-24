<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessException;
use App\Models\Payment;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function initiate(string $purpose, int $amount, string $currency, string $method, array $meta = []): array
    {
        if (! in_array($method, ['reference', 'mcx_express'], true)) {
            throw new BusinessException('Método de pagamento não suportado pelo gateway fake.', 'payment_method_unsupported', 422);
        }

        $reference = 'GPO-'.strtoupper(Str::random(8));
        return [
            'reference' => $reference,
            'entity' => null,
            'external_id' => $reference,
            'instructions' => [
                'method' => $method,
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'message' => 'Referência de teste; confirmação disponível apenas no ambiente local.',
            ],
        ];
    }

    public function checkStatus(Payment $payment): array
    {
        return [
            'paid' => false,
            'reference' => $payment->reference,
            'external_id' => (string) $payment->external_id,
            'amount' => (int) $payment->amount,
        ];
    }

    public function requestCharge(Payment $payment, string $recipientName, string $recipientPhone): array
    {
        return [];
    }

    public function verifyWebhook(string $rawBody, array $headers): array
    {
        $secret = (string) config('intermediarius.fake_webhook_secret');
        $header = (string) config('intermediarius.fake_webhook_signature_header');
        $provided = $headers[strtolower($header)] ?? $headers[$header] ?? null;
        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            throw new BusinessException('Assinatura do webhook inválida.', 'invalid_signature', 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            throw new BusinessException('Payload de webhook inválido.', 'invalid_webhook', 422);
        }

        return $payload;
    }
}
