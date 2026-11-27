<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_property_owner_who_is_available_can_be_assigned_to_a_visit(): void
    {
        config(['intermediarius.payment_gateway' => 'fake']);

        $owner = User::factory()->owner()->create();
        $client = User::factory()->create();
        $property = Property::factory()->create(['user_id' => $owner->id]);
        $scheduledAt = now()->addDays(2)->setTime(10, 0);

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/v1/availability', [
                'days' => [$scheduledAt->isoWeekday()],
                'start' => '09:00',
                'end' => '17:00',
                'slot' => 60,
            ])
            ->assertOk();

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/visits', [
                'property_id' => $property->id,
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'payment_method' => 'reference',
            ])
            ->assertCreated()
            ->assertJsonPath('agent', $owner->name);

        $this->assertDatabaseHas('visits', [
            'id' => $response->json('id'),
            'agent_id' => $owner->id,
        ]);
    }
}
