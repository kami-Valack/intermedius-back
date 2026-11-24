<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_visit_history_is_listed_when_its_property_was_soft_deleted(): void
    {
        $client = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->create(['user_id' => $owner->id]);
        $visit = Visit::create([
            'user_id' => $client->id,
            'property_id' => $property->id,
            'agent_id' => $agent->id,
            'scheduled_at' => now()->addDay(),
            'status' => Visit::STATUS_COMPLETED,
            'code' => 'INT-1234',
            'paid_at' => now(),
            'checked_in_at' => now(),
        ]);
        $property->delete();

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/visits')
            ->assertOk()
            ->assertJsonPath('data.0.id', $visit->id)
            ->assertJsonPath('data.0.private_unlocked', false);
    }
}
