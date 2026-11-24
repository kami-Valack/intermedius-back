<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessException;
use App\Models\Payment;
use App\Support\AngolaPhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GpoFaciGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'gpo_faci';
    }

    public function initiate(string $purpose, int $amount, string $currency, string $method, array $meta = []): array
    {
        if (! in_array($method, ['reference', 'mcx_express'], true)) {
            throw new BusinessException(
                'Método de pagamento não suportado pela GPO FACI.',
                'payment_method_unsupported',
                422
            );
        }

        if ($currency !== 'AOA') {
            throw new BusinessException('A GPO FACI só aceita pagamentos em AOA.', 'currency_unsupported', 422);
        }

        $recipientName = trim((string) ($meta['recipient_name'] ?? ''));
        if ($recipientName === '' || strlen($recipientName) > 255) {
            throw new BusinessException('Nome do destinatário inválido para cobrança GPO.', 'invalid_recipient', 422);
        }
        $this->angolaMobile($meta['recipient_phone'] ?? null);

        $result = $this->send(function () use ($purpose, $amount, $meta) {
            return $this->client()->post($this->baseUrl().'/api/reference/', [
                'dsReferencia' => 'Intermediarius - '.$purpose.' #'.($meta['payable_id'] ?? ''),
                'vlReferencia' => $amount,
                'tpFpReferencia' => 'PER',
                'idFpConta' => 0,
                'nuQuantidade' => 1,
            ]);
        });

        $reference = $result['nuReferencia'] ?? null;
        $externalId = $result['idFpReferencia'] ?? null;
        $validatedId = is_scalar($externalId) ? filter_var((string) $externalId, FILTER_VALIDATE_INT) : false;
        if (! is_scalar($reference)
            || ! preg_match('/^[0-9]+$/D', (string) $reference)
            || $validatedId === false
            || $validatedId < 1) {
            throw new BusinessException('Resposta de criação de referência inválida da GPO FACI.', 'provider_invalid_response', 502);
        }

        return [
            'reference' => (string) $reference,
            'entity' => null,
            'external_id' => (string) $externalId,
            'instructions' => [
                'provider' => 'GPO FACI',
                'method' => $method,
                'reference' => (string) $reference,
                'idFpReferencia' => (string) $externalId,
                'amount' => $amount,
                'currency' => $currency,
                'charge_requested' => false,
                'message' => 'Referência criada; a cobrança para o telemóvel será enviada pela plataforma.',
            ],
        ];
    }

    public function requestCharge(Payment $payment, string $recipientName, string $recipientPhone): array
    {
        $recipientName = trim($recipientName);
        if ($recipientName === '' || strlen($recipientName) > 255) {
            throw new BusinessException('Nome do destinatário inválido para cobrança GPO.', 'invalid_recipient', 422);
        }

        $phone = $this->angolaMobile($recipientPhone);
        $externalId = filter_var((string) $payment->external_id, FILTER_VALIDATE_INT);
        if ($externalId === false || $externalId < 1) {
            throw new BusinessException('ID da referência inválido para cobrança GPO.', 'invalid_reference', 422);
        }

        $this->sendResponse(function () use ($recipientName, $phone, $externalId) {
            return $this->client()->post($this->baseUrl().'/api/requester/', [
                'dsTelemovelDestinatario' => $phone,
                'noDestinatario' => $recipientName,
                'referenciaIn' => json_encode([
                    ['idFpReferencia' => $externalId, 'nuQuantidade' => 1],
                ], JSON_THROW_ON_ERROR),
                'idFpContaSelecionada' => 0,
            ]);
        });

        return [
            'charge_requested' => true,
            'message' => 'Cobrança enviada para o telemóvel. Também pode pagar pela referência; a confirmação será consultada pela plataforma.',
        ];
    }

    public function checkStatus(Payment $payment): array
    {
        $result = $this->send(function () use ($payment) {
            return $this->client()->get($this->baseUrl().'/api/reference/status/', [
                'idFpReferencia' => $payment->external_id,
                'nuReferencia' => $payment->reference,
            ]);
        });

        $reference = $result['nuReferencia'] ?? null;
        $externalId = $result['idFpReferencia'] ?? null;
        $amount = $this->integerAmount($result['vlReferencia'] ?? null);
        $paid = $result['paid'] ?? null;

        if ((string) $reference !== $payment->reference
            || (string) $externalId !== (string) $payment->external_id
            || $amount !== (int) $payment->amount
            || ! is_bool($paid)) {
            throw new BusinessException('Resposta de estado da GPO não coincide com o pagamento local.', 'payment_mismatch', 502);
        }

        return [
            'paid' => $paid,
            'reference' => (string) $reference,
            'external_id' => (string) $externalId,
            'amount' => $amount,
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): array
    {
        throw new BusinessException('A GPO FACI não fornece webhook; use a consulta de estado.', 'webhook_unsupported', 405);
    }

    private function client(): PendingRequest
    {
        $apiKey = (string) config('intermediarius.gpo.api_key');
        if ($apiKey === '') {
            throw new BusinessException('GPO_API_KEY não está configurada.', 'gateway_unconfigured', 503);
        }

        $verifySsl = (bool) config('intermediarius.gpo.ssl_verify', true);
        if (! $verifySsl && ! app()->environment('local', 'testing')) {
            throw new BusinessException(
                'A verificação SSL da GPO não pode ser desactivada fora dos ambientes local e de testes.',
                'insecure_tls_configuration',
                503
            );
        }

        return Http::acceptJson()
            ->withHeaders(['X-API-Key' => $apiKey])
            ->withOptions(['verify' => $verifySsl])
            ->timeout((int) config('intermediarius.gpo.timeout', 15));
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('intermediarius.gpo.base_url'), '/');
        $parts = parse_url($baseUrl);
        if (($parts['scheme'] ?? null) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'gpo.drip.infinityfree.me') {
            throw new BusinessException('GPO_API_BASE_URL deve apontar para o endpoint HTTPS GPO FACI configurado.', 'gateway_unconfigured', 503);
        }

        return $baseUrl;
    }

    private function send(callable $request): array
    {
        $response = $this->sendResponse($request);

        $body = $response->json();
        if (! is_array($body) || ! is_array($body['result'] ?? null)) {
            throw new BusinessException('Resposta inválida da GPO FACI.', 'provider_invalid_response', 502);
        }

        return $body['result'];
    }

    private function sendResponse(callable $request): Response
    {
        try {
            /** @var Response $response */
            $response = $request();
        } catch (ConnectionException $exception) {
            throw new BusinessException('GPO FACI está indisponível no momento.', 'payment_provider_unavailable', 503);
        }

        if (! $response->successful()) {
            $status = $response->status() === 401 ? 503 : 502;
            throw new BusinessException('GPO FACI recusou ou não concluiu a operação.', 'payment_provider_error', $status);
        }

        return $response;
    }

    private function angolaMobile($value): string
    {
        if (! is_string($value)) {
            throw new BusinessException('Telemóvel do destinatário inválido para cobrança GPO.', 'invalid_recipient', 422);
        }

        $digits = AngolaPhoneNumber::international($value);
        if ($digits === null) {
            throw new BusinessException('Use um número móvel angolano com indicativo 244.', 'invalid_recipient', 422);
        }

        return $digits;
    }

    private function integerAmount($value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (is_float($value)) {
            return is_finite($value) && $value >= 0 && floor($value) === $value && $value <= PHP_INT_MAX
                ? (int) $value
                : null;
        }

        if (is_string($value) && preg_match('/^(0|[1-9][0-9]*)(?:\\.0+)?$/D', $value, $matches)) {
            $integer = filter_var($matches[1], FILTER_VALIDATE_INT);

            return $integer === false ? null : $integer;
        }

        return null;
    }
}
