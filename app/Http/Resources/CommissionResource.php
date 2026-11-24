<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommissionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'rental_id' => $this->rental_id,
            'property_id' => $this->property_id,
            'property_title' => $this->property?->title,
            'owner_id' => $this->owner_id,
            'agent_id' => $this->agent_id,
            'agent_name' => $this->agent?->name,
            'pct' => (string) $this->pct,
            'amount' => (int) $this->amount,
            'status' => $this->status,
            'paid_at' => optional($this->paid_at)?->toIso8601String(),
            'payment' => $this->whenLoaded('payment', fn () => new PaymentResource($this->payment)),
        ];
    }
}
