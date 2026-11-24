<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SettingsSeeder::class);

        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('Seeders de dados de demonstração ignorados fora de local/testing.');

            return;
        }

        $this->call(DevelopmentAccountsSeeder::class);
    }
}
