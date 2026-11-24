<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(['key' => 'visit_fee'], ['value' => '3000']);
        Setting::firstOrCreate(['key' => 'platform_commission_pct'], ['value' => '5.00']);
        Setting::firstOrCreate(['key' => 'agent_commission_pct'], ['value' => '5.00']);
    }
}
