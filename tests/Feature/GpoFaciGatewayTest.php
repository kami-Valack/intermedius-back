<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GpoFaciGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'intermediarius.payment_gateway' => 'gpo',
            'intermediarius.gpo.api_key' => 'test-api-key',
            'intermediarius.gpo.base_url' => 'https://gpo.drip.infinityfree.me',
        ]);
    }

    public function test_it_creates_a_reference_and_syncs_a_paid_status_once(): void
    {
        Http::fake(function (ClientRequest $request) {
            if (str_ends_with($request->url(), '/api/reference/')) {
                return Http::response([
                    'result' => [
                        'idFpReferencia' => 27070,
                        'nuReferencia' => '617798378',
                    ],
                ]);
            }

            return Http::response([
                'result' => [
                    'paid' => true,
                    'idFpReferencia' => 27070,
                    'nuReferencia' => '617798378',
                    'vlReferencia' => 5000,
                ],
            ]);
        });

        $client = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->create(['user_id' => $owner->id]);
        $visit = Visit::create([
            'user_id' => $client->id,
            'property_id' => $property->id,
            'agent_id' => $agent->id,
            'scheduled_at' => now()->addDay(),
            'status' => Visit::STATUS_PENDING_PAYMENT,
        ]);

        $payments = app(PaymentService::class);
        $payment = $payments->initiate(
            $visit,
            PaymentService::PURPOSE_VISIT,
            5000,
            'mcx_express',
            $client
        );

        $this->assertSame('617798378', $payment->reference);
        $this->assertSame('27070', $payment->external_id);
        $this->assertSame('mcx_express', $payment->method);
        $this->assertTrue($payment->instructions['charge_requested']);
        $this->assertSame(1, $payments->syncPendingPayments());
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(Visit::STATUS_PAID, $visit->fresh()->status);
        $this->assertNotEmpty($visit->fresh()->code);
        $this->assertSame(0, $payments->syncPendingPayments());

        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/reference/')
            && $request->hasHeader('X-API-Key', 'test-api-key')
            && $request->data()['vlReferencia'] === 5000);
        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/requester/')
            && $request->hasHeader('X-API-Key', 'test-api-key')
            && preg_match('/^2449[0-9]{8}$/D', $request->data()['dsTelemovelDestinatario'])
            && $request->data()['noDestinatario'] === $client->name
            && $request->data()['idFpContaSelecionada'] === 0
            && json_decode($request->data()['referenciaIn'], true) === [
                ['idFpReferencia' => 27070, 'nuQuantidade' => 1],
            ]);
        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/reference/status/')
            && str_contains($request->url(), 'idFpReferencia=27070')
            && str_contains($request->url(), 'nuReferencia=617798378')
            && $request->hasHeader('X-API-Key', 'test-api-key'));
    }

    public function test_it_rejects_an_invalid_recipient_phone_before_creating_a_reference(): void
    {
        Http::fake();

        try {
            app(\App\Services\Payments\GpoFaciGateway::class)->initiate(
                Payment::PURPOSE_VISIT,
                5000,
                'AOA',
                'reference',
                ['recipient_name' => 'Cliente', 'recipient_phone' => '123'],
            );
            $this->fail('A phone number with invalid format should be rejected.');
        } catch (\App\Exceptions\BusinessException $exception) {
            $this->assertSame('invalid_recipient', $exception->codeKey);
        }

        Http::assertNothingSent();
    }

    public function test_it_does_not_allow_ssl_verification_to_be_disabled_in_production(): void
    {
        Http::fake();
        config(['intermediarius.gpo.ssl_verify' => false]);
        $this->app['env'] = 'production';

        try {
            app(\App\Services\Payments\GpoFaciGateway::class)->initiate(
                Payment::PURPOSE_VISIT,
                5000,
                'AOA',
                'reference',
                ['recipient_name' => 'Cliente', 'recipient_phone' => '244923123456'],
            );
            $this->fail('Disabled SSL verification must be rejected in production.');
        } catch (\App\Exceptions\BusinessException $exception) {
            $this->assertSame('insecure_tls_configuration', $exception->codeKey);
        }

        Http::assertNothingSent();
    }

    public function test_it_keeps_the_pending_payment_if_the_requester_call_fails(): void
    {
        Http::fake(function (ClientRequest $request) {
            if (str_ends_with($request->url(), '/api/reference/')) {
                return Http::response([
                    'result' => [
                        'idFpReferencia' => 27072,
                        'nuReferencia' => '617798380',
                    ],
                ]);
            }

            return Http::response([], 503);
        });

        $client = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->create(['user_id' => $owner->id]);
        $visit = Visit::create([
            'user_id' => $client->id,
            'property_id' => $property->id,
            'agent_id' => $agent->id,
            'scheduled_at' => now()->addDay(),
            'status' => Visit::STATUS_PENDING_PAYMENT,
        ]);

        try {
            app(PaymentService::class)->initiate(
                $visit,
                PaymentService::PURPOSE_VISIT,
                5000,
                'reference',
                $client
            );
            $this->fail('A failed requester call should be surfaced to the caller.');
        } catch (\App\Exceptions\BusinessException $exception) {
            $this->assertSame('payment_provider_error', $exception->codeKey);
        }

        $payment = Payment::query()->where('external_id', '27072')->firstOrFail();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertFalse($payment->instructions['charge_requested']);
        $this->assertSame('payment_provider_error', $payment->audit['charge_request_error_code']);
    }

    public function test_it_rejects_a_provider_status_for_a_different_amount(): void
    {
        Http::fake([
            'https://gpo.drip.infinityfree.me/api/reference/status/*' => Http::response([
                'result' => [
                    'paid' => true,
                    'idFpReferencia' => 27070,
                    'nuReferencia' => '617798378',
                    'vlReferencia' => 4999,
                ],
            ]),
        ]);

        $payment = new Payment([
            'reference' => '617798378',
            'external_id' => '27070',
            'amount' => 5000,
        ]);

        $this->expectException(\App\Exceptions\BusinessException::class);
        app(\App\Services\Payments\GpoFaciGateway::class)->checkStatus($payment);
    }

    public function test_an_unpaid_reference_does_not_confirm_the_visit(): void
    {
        Http::fake(function (ClientRequest $request) {
            if (str_ends_with($request->url(), '/api/reference/')) {
                return Http::response([
                    'result' => [
                        'idFpReferencia' => 27071,
                        'nuReferencia' => '617798379',
                    ],
                ]);
            }

            return Http::response([
                'result' => [
                    'paid' => false,
                    'idFpReferencia' => 27071,
                    'nuReferencia' => '617798379',
                    'vlReferencia' => 5000,
                ],
            ]);
        });

        $client = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->create(['user_id' => $owner->id]);
        $visit = Visit::create([
            'user_id' => $client->id,
            'property_id' => $property->id,
            'agent_id' => $agent->id,
            'scheduled_at' => now()->addDay(),
            'status' => Visit::STATUS_PENDING_PAYMENT,
        ]);

        $payments = app(PaymentService::class);
        $payment = $payments->initiate(
            $visit,
            PaymentService::PURPOSE_VISIT,
            5000,
            'reference',
            $client
        );

        $this->assertSame(0, $payments->syncPendingPayments());
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(Visit::STATUS_PENDING_PAYMENT, $visit->fresh()->status);
        $this->assertNull($visit->fresh()->code);
    }
}
