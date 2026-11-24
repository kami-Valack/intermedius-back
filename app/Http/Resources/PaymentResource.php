<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'purpose' => $this->purpose,
            'method' => $this->method,
            'reference' => $this->reference,
            'amount' => (int) $this->amount,
            'currency' => $this->currency,
            'entity' => $this->entity,
            'status' => $this->status,
            'data' => $this->instructions,
            'paid_at' => optional($this->paid_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
