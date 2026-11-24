<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Models\Visit;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public const PURPOSE_VISIT = Payment::PURPOSE_VISIT;
    public const PURPOSE_RENTAL = Payment::PURPOSE_RENTAL;
    public const PURPOSE_COMMISSION = Payment::PURPOSE_COMMISSION;

    public function __construct(
        private PaymentGateway $gateway,
        private SettingsService $settings
    ) {
    }

    public function initiate(Model $payable, string $purpose, int $amount, string $method, $actor): Payment
    {
        $recipientName = $actor instanceof User ? $actor->name : '';
        $recipientPhone = $actor instanceof User ? $actor->phone : '';
        if ($payable instanceof Rental) {
            $recipientName = $payable->tenant_name;
            $recipientPhone = $payable->tenant_phone;
        }

        $init = $this->gateway->initiate($purpose, $amount, config('intermediarius.currency'), $method, [
            'payable_type' => $payable::class,
            'payable_id' => $payable->id,
            'recipient_name' => $recipientName,
            'recipient_phone' => $recipientPhone,
        ]);

        $payment = Payment::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->id,
            'purpose' => $purpose,
            'gateway' => $this->gateway->name(),
            'method' => $method,
            'reference' => $init['reference'],
            'external_id' => $init['external_id'],
            'amount' => $amount,
            'currency' => config('intermediarius.currency'),
            'entity' => $init['entity'],
            'status' => Payment::STATUS_PENDING,
            'instructions' => $init['instructions'],
            'audit' => [
                'initiated_by' => $actor->id ?? null,
                'initiated_at' => now()->toIso8601String(),
            ],
        ]);

        try {
            $chargeInstructions = $this->gateway->requestCharge(
                $payment,
                (string) $recipientName,
                (string) $recipientPhone
            );
        } catch (BusinessException $exception) {
            $payment->audit = array_merge($payment->audit ?? [], [
                'charge_request_failed_at' => now()->toIso8601String(),
                'charge_request_error_code' => $exception->codeKey,
            ]);
            $payment->instructions = array_merge($payment->instructions ?? [], [
                'charge_requested' => false,
                'message' => 'A GPO não confirmou o envio da cobrança ao telemóvel. A referência pode ser paga manualmente.',
            ]);
            $payment->save();

            throw $exception;
        }

        if ($chargeInstructions) {
            $payment->instructions = array_merge($payment->instructions ?? [], $chargeInstructions);
            $payment->save();
        }

        return $payment;
    }

    public function handleWebhook(string $rawBody, array $headers): Payment
    {
        $payload = $this->gateway->verifyWebhook($rawBody, $headers);
        $eventId = (string) ($payload['event_id'] ?? $payload['id'] ?? $payload['transaction_id'] ?? '');
        $reference = (string) ($payload['reference'] ?? '');
        $amount = (int) ($payload['amount'] ?? 0);
        $currency = (string) ($payload['currency'] ?? '');
        $entity = isset($payload['entity']) ? (string) $payload['entity'] : null;
        $status = strtolower((string) ($payload['status'] ?? 'paid'));

        if ($eventId === '' || $reference === '') {
            throw new BusinessException('Webhook sem identificador ou referência.', 'invalid_webhook', 422);
        }

        return DB::transaction(function () use ($eventId, $reference, $amount, $currency, $entity, $status, $payload) {
            $provider = $this->gateway->name();
            $existingEvent = WebhookEvent::query()
                ->where('provider', $provider)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();
            if ($existingEvent) {
                return Payment::query()->where('reference', $reference)->firstOrFail();
            }

            WebhookEvent::create([
                'provider' => $provider,
                'event_id' => $eventId,
                'status' => $status,
                'payload_meta' => [
                    'reference' => $reference,
                    'amount' => $amount,
                    'currency' => $currency,
                    'keys' => array_keys($payload),
                ],
            ]);

            $payment = Payment::query()->where('reference', $reference)->lockForUpdate()->first();
            if (! $payment) {
                throw new BusinessException('Pagamento local não encontrado.', 'payment_not_found', 422);
            }
            if ($payment->amount !== $amount || strtoupper($payment->currency) !== strtoupper($currency)) {
                throw new BusinessException('Montante ou moeda não coincidem.', 'payment_mismatch', 422);
            }
            if ($entity && $payment->entity && $payment->entity !== $entity) {
                throw new BusinessException('Entidade da transacção não coincide.', 'payment_mismatch', 422);
            }
            if ($payment->status === Payment::STATUS_PAID) {
                return $payment;
            }
            if ($status !== 'paid' && $status !== 'confirmed') {
                $payment->status = Payment::STATUS_FAILED;
                $payment->save();

                return $payment;
            }

            $this->confirmPayment($payment, 'fake_webhook', $eventId);

            return $payment;
        });
    }

    public function syncPendingPayments(int $limit = 100): int
    {
        $limit = min(max($limit, 1), 500);
        $payments = Payment::query()
            ->where('gateway', $this->gateway->name())
            ->where('status', Payment::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $confirmed = 0;
        foreach ($payments as $payment) {
            $status = $this->gateway->checkStatus($payment);
            if (! ($status['paid'] ?? false)) {
                continue;
            }

            $didConfirm = DB::transaction(function () use ($payment) {
                $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if ($locked->status !== Payment::STATUS_PENDING) {
                    return false;
                }

                $this->confirmPayment($locked, 'gpo_status_poll', 'gpo:'.$locked->external_id);

                return true;
            });

            if ($didConfirm) {
                $confirmed++;
            }
        }

        return $confirmed;
    }

    private function confirmPayment(Payment $payment, string $source, ?string $idempotencyKey): void
    {
        $payment->status = Payment::STATUS_PAID;
        $payment->paid_at = now();
        $payment->idempotency_key = $idempotencyKey;
        $payment->audit = array_merge($payment->audit ?? [], [
            'confirmed_by' => $source,
            'confirmed_at' => now()->toIso8601String(),
        ]);
        $payment->save();

        $this->applyBusinessEffects($payment);
    }

    public function applyBusinessEffects(Payment $payment): void
    {
        $payable = $payment->payable;
        if ($payable instanceof Visit) {
            app(VisitService::class)->confirmPaid($payable);
        }
        if ($payable instanceof Rental) {
            app(RentalService::class)->activate($payable);
        }
        if ($payable instanceof Commission) {
            $payable->status = Commission::STATUS_PAID;
            $payable->paid_at = now();
            $payable->save();
        }
    }
}
