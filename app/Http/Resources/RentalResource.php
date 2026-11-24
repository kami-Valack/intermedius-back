<?php

namespace App\Http\Resources;

use App\Models\Rental;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalResource extends JsonResource
{
    public function toArray($request)
    {
        $active = $this->status === Rental::STATUS_ACTIVE;

        return [
            'id' => $this->id,
            'contract_number' => $active ? $this->contract_number : null,
            'property_id' => $this->property_id,
            'title' => $this->property?->title,
            'tenant' => $this->tenant_name,
            'phone' => $this->tenant_phone,
            'nif' => $this->tenant_nif,
            'start_date' => optional($this->start_date)?->toDateString(),
            'duration_months' => (int) $this->duration_months,
            'monthly_rent' => (int) $this->monthly_rent,
            'platform_pct' => (string) $this->platform_pct,
            'platform_fee' => (int) $this->platform_fee,
            'tenant_total' => (int) $this->tenant_total,
            'agent_commission_amount' => (int) $this->agent_commission_amount,
            'status' => $this->status,
            'activated_at' => optional($this->activated_at)?->toIso8601String(),
            'legal_notice' => 'Este contrato é um comprovativo operacional e não constitui aconselhamento jurídico. Requer validação legal local em Angola.',
            'payment' => $this->whenLoaded('payment', fn () => new PaymentResource($this->payment)),
            'property' => $this->whenLoaded('property', fn () => new PropertyResource($this->property)),
        ];
    }
}
