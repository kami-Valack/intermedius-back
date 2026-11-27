<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_save_and_read_their_bank_details(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/bank-details', [
                'iban' => 'ao06 0000 0000 0000 0000 0000 0',
                'express_number' => ' +244 923 000 000 ',
            ])
            ->assertOk()
            ->assertJsonPath('iban', 'AO06000000000000000000000')
            ->assertJsonPath('express_number', '+244 923 000 000');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'iban' => 'AO06000000000000000000000',
            'express_number' => '+244 923 000 000',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/bank-details')
            ->assertOk()
            ->assertJsonPath('iban', 'AO06000000000000000000000')
            ->assertJsonPath('express_number', '+244 923 000 000');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonMissingPath('user.iban')
            ->assertJsonMissingPath('user.express_number');
    }

    public function test_bank_details_routes_require_authentication_and_validate_iban(): void
    {
        $this->putJson('/api/v1/bank-details', [
            'iban' => 'AO0600000000000000000000000',
            'express_number' => '923000000',
        ])->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/v1/bank-details', [
                'iban' => 'not-an-iban',
                'express_number' => '923000000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('iban');
    }
}
