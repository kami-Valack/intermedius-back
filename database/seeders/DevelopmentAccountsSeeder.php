<?php

namespace Database\Seeders;

use App\Models\Availability;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('DevelopmentAccountsSeeder só pode ser executado em local/testing.');
        }

        $password = env('DEV_SEED_PASSWORD', 'password');

        $accounts = [
            [
                'email' => 'admin@intermediarius.test',
                'name' => 'Administrador de teste',
                'phone' => '+244 900 000 001',
                'role' => User::ROLE_ADMIN,
            ],
            [
                'email' => 'cliente@intermediarius.test',
                'name' => 'Ana Cliente',
                'phone' => '+244 923 000 001',
                'role' => User::ROLE_CLIENT,
            ],
            [
                'email' => 'proprietario@intermediarius.test',
                'name' => 'Paulo Proprietário',
                'phone' => '+244 923 000 002',
                'nif' => '123456789',
                'role' => User::ROLE_OWNER,
            ],
            [
                'email' => 'agente@intermediarius.test',
                'name' => 'Iva Intermediária',
                'phone' => '+244 923 000 003',
                'nif' => '987654321',
                'role' => User::ROLE_AGENT,
            ],
        ];

        foreach ($accounts as $account) {
            $role = $account['role'];
            unset($account['role']);

            User::firstOrCreate(
                ['email' => $account['email']],
                array_merge($account, [
                    'role' => $role,
                    'status' => User::STATUS_ACTIVE,
                    'password' => Hash::make($password),
                ])
            );
        }

        $agent = User::query()->where('email', 'agente@intermediarius.test')->firstOrFail();

        Availability::firstOrCreate(
            ['user_id' => $agent->id],
            ['days' => [1, 2, 3, 4, 5], 'start' => '09:00', 'end' => '17:00', 'slot' => 60]
        );

        $this->command?->info('Contas de teste criadas; use DEV_SEED_PASSWORD (padrão local: password).');
    }
}
