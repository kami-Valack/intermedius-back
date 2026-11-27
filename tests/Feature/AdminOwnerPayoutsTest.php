<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOwnerPayoutsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_monthly_amounts_and_owner_payment_destinations(): void
    {
        $owner = User::factory()->owner()->create([
            'iban' => 'AO0600000000000000000000000',
            'express_number' => '+244 923 000 000',
        ]);
        $tenant = User::factory()->create();

        $this->createRental($owner, $tenant, 100000, Rental::STATUS_ACTIVE);
        $this->createRental($owner, $tenant, 50000, Rental::STATUS_ACTIVE);
        $this->createRental($owner, $tenant, 200000, Rental::STATUS_PENDING_PAYMENT);

        $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/v1/admin/owner-payouts')
            ->assertOk()
            ->assertJsonPath('data.0.owner.id', $owner->id)
            ->assertJsonPath('data.0.monthly_amount_due', 150000)
            ->assertJsonPath('data.0.active_rentals_count', 2)
            ->assertJsonPath('data.0.currency', 'AOA')
            ->assertJsonPath('data.0.destination.iban', 'AO0600000000000000000000000')
            ->assertJsonPath('data.0.destination.express_number', '+244 923 000 000');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_owner_payout_list_is_only_available_to_admins(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/admin/owner-payouts')
            ->assertForbidden();
    }

    private function createRental(User $owner, User $tenant, int $monthlyRent, string $status): Rental
    {
        $property = Property::factory()->create(['user_id' => $owner->id]);

        return Rental::create([
            'user_id' => $tenant->id,
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'tenant_name' => $tenant->name,
            'tenant_phone' => $tenant->phone,
            'tenant_nif' => $tenant->nif,
            'start_date' => now()->toDateString(),
            'duration_months' => 12,
            'monthly_rent' => $monthlyRent,
            'platform_pct' => '5.00',
            'platform_fee' => 5000,
            'tenant_total' => $monthlyRent + 5000,
            'agent_commission_pct' => '0.00',
            'agent_commission_amount' => 0,
            'status' => $status,
        ]);
    }
}
