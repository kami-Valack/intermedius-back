<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\PhoneVerification;
use App\Support\AngolaPhoneNumber;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class OmbalaOtpService
{
    public function send(string $rawPhone): PhoneVerification
    {
        $phone = AngolaPhoneNumber::international($rawPhone);
        if ($phone === null) {
            throw new BusinessException('Indique um número móvel angolano válido.', 'invalid_phone', 422);
        }

        $nationalPhone = substr($phone, 3);
        $rateKey = 'ombala-registration-otp:'.hash('sha256', $phone);
        $maxSends = (int) config('ombala.max_sends_per_hour', 3);
        if (RateLimiter::tooManyAttempts($rateKey.':hour', $maxSends)) {
            throw new BusinessException('Limite de códigos por hora atingido para este número.', 'otp_rate_limited', 429);
        }

        $cooldownKey = $rateKey.':cooldown';
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $wait = RateLimiter::availableIn($cooldownKey);
            throw new BusinessException('Aguarde '.$wait.' segundos antes de pedir outro código.', 'otp_cooldown', 429);
        }

        $localId = (string) Str::uuid();
        $response = $this->post('/v1/otp', [
            'to' => $nationalPhone,
            'from' => $this->sender(),
            'context' => config('ombala.context'),
            'message' => 'Intermediarius: o seu código é CODIGO_OTP. Não o partilhe.',
            'length' => (int) config('ombala.otp_length', 6),
            'expires_in' => (int) config('ombala.expires_in', 300),
            'max_attempts' => (int) config('ombala.max_attempts', 5),
            'reference' => $localId,
        ]);

        if (! $response->successful()) {
            $this->throwProviderError($response);
        }

        $payload = $response->json();
        $providerPhone = is_array($payload) && is_string($payload['phone_number'] ?? null)
            ? AngolaPhoneNumber::international($payload['phone_number'])
            : null;
        if (! is_array($payload)
            || ! is_string($payload['id'] ?? null)
            || $payload['id'] === ''
            || ($payload['status'] ?? null) !== 'PENDING'
            || $providerPhone !== $phone
            || ! is_int($payload['attempts_remaining'] ?? null)
            || ! is_string($payload['expires_at'] ?? null)
            || strtotime($payload['expires_at']) === false) {
            throw new BusinessException('Resposta inválida do serviço de verificação Ombala.', 'otp_provider_invalid_response', 502);
        }

        $hourSeconds = 3600;
        RateLimiter::hit($rateKey.':hour', $hourSeconds);
        RateLimiter::hit($cooldownKey, (int) config('ombala.resend_cooldown_seconds', 60));

        return PhoneVerification::create([
            'id' => $localId,
            'phone' => $phone,
            'ombala_otp_id' => $payload['id'],
            'context' => config('ombala.context'),
            'status' => PhoneVerification::STATUS_PENDING,
            'attempts_remaining' => $payload['attempts_remaining'],
            'expires_at' => Carbon::createFromTimestamp(strtotime($payload['expires_at'])),
        ]);
    }

    public function verify(PhoneVerification $verification, string $code): PhoneVerification
    {
        if ($verification->status !== PhoneVerification::STATUS_PENDING) {
            throw new BusinessException('Este código já não pode ser verificado. Peça um novo.', 'otp_not_pending', 422);
        }
        if (! $verification->expires_at || $verification->expires_at->isPast()) {
            $verification->status = PhoneVerification::STATUS_EXPIRED;
            $verification->save();

            throw new BusinessException('Código expirado. Peça um novo código.', 'otp_expired', 422);
        }

        $response = $this->post('/v1/otp/verify', [
            'id' => $verification->ombala_otp_id,
            'context' => $verification->context,
            'code' => $code,
        ]);
        $payload = $response->json();

        if ($response->status() === 422 || ($response->successful() && is_array($payload) && ($payload['verified'] ?? null) !== true)) {
            $this->updateFailedAttempt($verification, $payload);
            throw new BusinessException('Código inválido, expirado ou bloqueado.', 'otp_code_invalid', 422);
        }
        if ($response->status() === 404) {
            throw new BusinessException('Código de verificação não encontrado ou substituído.', 'otp_code_not_found', 422);
        }
        if (! $response->successful()) {
            $this->throwProviderError($response);
        }

        $providerPhone = is_array($payload) && is_string($payload['phone_number'] ?? null)
            ? AngolaPhoneNumber::international($payload['phone_number'])
            : null;
        if (! is_array($payload)
            || ($payload['verified'] ?? null) !== true
            || ($payload['id'] ?? null) !== $verification->ombala_otp_id
            || ($payload['context'] ?? null) !== $verification->context
            || ($payload['status'] ?? null) !== 'VERIFIED'
            || $providerPhone !== $verification->phone) {
            throw new BusinessException('Resposta de verificação inválida do Ombala.', 'otp_provider_invalid_response', 502);
        }

        $verification->status = PhoneVerification::STATUS_VERIFIED;
        $verification->attempts_remaining = 0;
        $verification->verified_at = now();
        $verification->save();

        return $verification;
    }

    public function consumeForRegistration(string $verificationId, string $rawPhone): PhoneVerification
    {
        $phone = AngolaPhoneNumber::international($rawPhone);
        if ($phone === null) {
            throw new BusinessException('Indique um número móvel angolano válido.', 'invalid_phone', 422);
        }

        $verification = PhoneVerification::query()->lockForUpdate()->find($verificationId);
        if (! $verification
            || $verification->status !== PhoneVerification::STATUS_VERIFIED
            || $verification->used_at
            || ! $verification->verified_at
            || $verification->verified_at->lt(now()->subMinutes((int) config('ombala.registration_valid_minutes', 15)))) {
            throw new BusinessException('Verifique o telefone antes de criar a conta.', 'phone_verification_required', 422);
        }
        if (! hash_equals($verification->phone, $phone)) {
            throw new BusinessException('O telefone não corresponde ao número verificado.', 'phone_verification_mismatch', 422);
        }

        $verification->used_at = now();
        $verification->save();

        return $verification;
    }

    private function post(string $path, array $payload): Response
    {
        try {
            return $this->client()->post($this->baseUrl().$path, $payload);
        } catch (ConnectionException $exception) {
            throw new BusinessException('O serviço de verificação de telefone está indisponível.', 'otp_provider_unavailable', 503);
        }
    }

    private function client(): PendingRequest
    {
        $token = (string) config('ombala.api_token');
        if ($token === '') {
            throw new BusinessException('OMBALA_API_TOKEN não está configurado.', 'otp_provider_unconfigured', 503);
        }

        $verifySsl = (bool) config('ombala.ssl_verify', true);
        if (! $verifySsl && ! app()->environment('local', 'testing')) {
            throw new BusinessException(
                'A verificação SSL do Ombala não pode ser desactivada fora dos ambientes local e de testes.',
                'insecure_tls_configuration',
                503
            );
        }

        return Http::acceptJson()
            ->withHeaders(['Authorization' => 'Token '.$token])
            ->withOptions(['verify' => $verifySsl])
            ->timeout((int) config('ombala.timeout', 15));
    }

    private function sender(): string
    {
        $sender = trim((string) config('ombala.sms_from'));
        if ($sender === '') {
            throw new BusinessException('OMBALA_SMS_FROM não está configurado.', 'otp_provider_unconfigured', 503);
        }

        return $sender;
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('ombala.base_url'), '/');
        $parts = parse_url($baseUrl);
        if (($parts['scheme'] ?? null) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'api.useombala.ao') {
            throw new BusinessException('OMBALA_BASE_URL deve apontar para https://api.useombala.ao.', 'otp_provider_unconfigured', 503);
        }

        return $baseUrl;
    }

    private function throwProviderError(Response $response): void
    {
        $status = $response->status() === 429
            ? 429
            : ($response->status() === 401 || $response->status() >= 500 ? 503 : 502);
        $code = $response->status() === 429 ? 'otp_provider_rate_limited' : 'otp_provider_error';

        throw new BusinessException('Ombala não conseguiu processar a verificação do telefone.', $code, $status);
    }

    private function updateFailedAttempt(PhoneVerification $verification, $payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        if (is_int($payload['attempts_remaining'] ?? null)) {
            $verification->attempts_remaining = $payload['attempts_remaining'];
        }

        $status = strtolower((string) ($payload['status'] ?? ''));
        if (in_array($status, [
            PhoneVerification::STATUS_PENDING,
            PhoneVerification::STATUS_EXPIRED,
            PhoneVerification::STATUS_BLOCKED,
            PhoneVerification::STATUS_CANCELLED,
        ], true)) {
            $verification->status = $status;
        }
        $verification->save();
    }
}
