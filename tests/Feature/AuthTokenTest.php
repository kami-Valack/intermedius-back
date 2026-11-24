<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_issues_a_bearer_token_for_the_user_morph_type(): void
    {
        config([
            'ombala.api_token' => 'test-ombala-token',
            'ombala.sms_from' => '949844137',
            'ombala.base_url' => 'https://api.useombala.ao',
        ]);
        Http::fake(function (ClientRequest $request) {
            if (str_ends_with($request->url(), '/v1/otp')) {
                return Http::response([
                    'id' => 'provider-otp-123',
                    'phone_number' => '923000000',
                    'context' => 'intermediarius-registration',
                    'status' => 'PENDING',
                    'attempts_remaining' => 5,
                    'expires_at' => now()->addMinutes(5)->toIso8601String(),
                ], 201);
            }

            return Http::response([
                'verified' => true,
                'id' => 'provider-otp-123',
                'phone_number' => '923000000',
                'context' => 'intermediarius-registration',
                'status' => 'VERIFIED',
                'attempts_remaining' => 0,
                'expires_at' => now()->addMinutes(5)->toIso8601String(),
                'verified_at' => now()->toIso8601String(),
            ]);
        });

        $otpResponse = $this->postJson('/api/v1/auth/phone-verifications', [
            'phone' => '+244 923 000 000',
        ])->assertCreated()
            ->assertJsonPath('status', 'pending');
        $verificationId = $otpResponse->json('verification_id');

        $unverifiedRegistration = [
            'name' => 'Ana Silva',
            'email' => 'ana@example.test',
            'phone' => '+244 923 000 000',
            'phone_verification_id' => $verificationId,
            'role' => 'client',
            'password' => 'test-password-123',
        ];
        $this->postJson('/api/v1/auth/register', $unverifiedRegistration)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'phone_verification_required');
        $this->assertDatabaseMissing('users', ['email' => 'ana@example.test']);

        $this->postJson('/api/v1/auth/phone-verifications/verify', [
            'verification_id' => $verificationId,
            'code' => '123456',
        ])->assertOk()
            ->assertJsonPath('verified', true);

        $mismatchedRegistration = $unverifiedRegistration;
        $mismatchedRegistration['email'] = 'mismatched@example.test';
        $mismatchedRegistration['phone'] = '+244 923 111 111';
        $this->postJson('/api/v1/auth/register', $mismatchedRegistration)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'phone_verification_mismatch');
        $this->assertDatabaseMissing('users', ['email' => 'mismatched@example.test']);

        $response = $this->postJson('/api/v1/auth/register', $unverifiedRegistration);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'phone', 'role', 'status'],
            ]);
        $this->assertNotNull($response->json('user.phone_verified_at'));
        $this->assertDatabaseHas('phone_verifications', [
            'id' => $verificationId,
            'status' => 'verified',
        ]);
        $this->assertNotNull(
            \App\Models\PhoneVerification::query()->findOrFail($verificationId)->used_at
        );
        $replayRegistration = $unverifiedRegistration;
        $replayRegistration['email'] = 'replay@example.test';
        $this->postJson('/api/v1/auth/register', $replayRegistration)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'phone_verification_required');
        $this->assertDatabaseMissing('users', ['email' => 'replay@example.test']);

        Http::assertSent(fn (ClientRequest $request) => $request->hasHeader('Authorization', 'Token test-ombala-token')
            && str_ends_with($request->url(), '/v1/otp')
            && $request->data()['to'] === '923000000'
            && $request->data()['from'] === '949844137'
            && $request->data()['context'] === 'intermediarius-registration'
            && strpos($request->data()['message'], 'CODIGO_OTP') !== false);
        Http::assertSent(fn (ClientRequest $request) => $request->hasHeader('Authorization', 'Token test-ombala-token')
            && str_ends_with($request->url(), '/v1/otp/verify')
            && $request->data()['id'] === 'provider-otp-123'
            && $request->data()['code'] === '123456');

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'ana@example.test');
    }

    public function test_registration_remains_blocked_when_ombala_rejects_the_otp(): void
    {
        config([
            'ombala.api_token' => 'test-ombala-token',
            'ombala.sms_from' => '949844137',
            'ombala.base_url' => 'https://api.useombala.ao',
        ]);
        Http::fake(function (ClientRequest $request) {
            if (str_ends_with($request->url(), '/v1/otp/verify')) {
                return Http::response([
                    'verified' => false,
                    'id' => 'provider-otp-invalid',
                    'phone_number' => '922000111',
                    'context' => 'intermediarius-registration',
                    'status' => 'PENDING',
                    'attempts_remaining' => 4,
                ], 422);
            }

            return Http::response([
                'id' => 'provider-otp-invalid',
                'phone_number' => '922000111',
                'context' => 'intermediarius-registration',
                'status' => 'PENDING',
                'attempts_remaining' => 5,
                'expires_at' => now()->addMinutes(5)->toIso8601String(),
            ], 201);
        });

        $otpResponse = $this->postJson('/api/v1/auth/phone-verifications', [
            'phone' => '922000111',
        ])->assertCreated();
        $verificationId = $otpResponse->json('verification_id');

        $this->postJson('/api/v1/auth/phone-verifications/verify', [
            'verification_id' => $verificationId,
            'code' => '000000',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'otp_code_invalid');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'João Silva',
            'email' => 'joao@example.test',
            'phone' => '922000111',
            'phone_verification_id' => $verificationId,
            'role' => 'client',
            'password' => 'test-password-123',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'phone_verification_required');

        $this->assertDatabaseMissing('users', ['email' => 'joao@example.test']);
        $this->assertDatabaseHas('phone_verifications', [
            'id' => $verificationId,
            'status' => 'pending',
            'attempts_remaining' => 4,
        ]);
    }

    public function test_ombala_ssl_verification_cannot_be_disabled_in_production(): void
    {
        config([
            'ombala.api_token' => 'test-ombala-token',
            'ombala.ssl_verify' => false,
        ]);
        $this->app['env'] = 'production';
        Http::fake();

        try {
            app(\App\Services\OmbalaOtpService::class)->send('923111222');
            $this->fail('Disabled SSL verification must be rejected in production.');
        } catch (\App\Exceptions\BusinessException $exception) {
            $this->assertSame('insecure_tls_configuration', $exception->codeKey);
        }

        Http::assertNothingSent();
    }
}
