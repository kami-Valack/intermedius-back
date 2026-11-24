<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public const KEYS = [
        'visit_fee',
        'platform_commission_pct',
        'agent_commission_pct',
    ];

    public function all(): array
    {
        return [
            'visit_fee' => $this->visitFee(),
            'platform_commission_pct' => $this->platformCommissionPct(),
            'agent_commission_pct' => $this->agentCommissionPct(),
        ];
    }

    public function visitFee(): int
    {
        return (int) $this->get('visit_fee', config('intermediarius.visit_fee'));
    }

    public function platformCommissionPct(): string
    {
        return (string) $this->get('platform_commission_pct', config('intermediarius.platform_commission_pct'));
    }

    public function agentCommissionPct(): string
    {
        return (string) $this->get('agent_commission_pct', config('intermediarius.agent_commission_pct'));
    }

    public function put(array $values, int $adminId): array
    {
        foreach ($values as $key => $value) {
            if (! in_array($key, self::KEYS, true)) {
                continue;
            }
            Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
        Cache::forget('settings.all');
        Setting::updateOrCreate(
            ['key' => 'last_updated_by'],
            ['value' => (string) $adminId]
        );

        return $this->all();
    }

    public function get(string $key, $default = null)
    {
        $all = Cache::remember('settings.all', 60, function () {
            return Setting::query()->pluck('value', 'key')->all();
        });

        return $all[$key] ?? $default;
    }
}
