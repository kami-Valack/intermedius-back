<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMorphMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_initiated_payment_uses_the_morph_alias_and_is_loadable(): void
    {
        config(['intermediarius.payment_gateway' => 'fake']);

        $client = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['user_id' => $owner->id]);
        $visit = Visit::create([
            'user_id' => $client->id,
            'property_id' => $property->id,
            'agent_id' => $owner->id,
            'scheduled_at' => now()->addDay(),
            'status' => Visit::STATUS_PENDING_PAYMENT,
        ]);

        $payment = app(PaymentService::class)->initiate(
            $visit,
            PaymentService::PURPOSE_VISIT,
            3000,
            'reference',
            $client
        );

        $this->assertSame('visit', $payment->payable_type);
        $this->assertSame($payment->id, $visit->fresh()->payment->id);
    }
}
