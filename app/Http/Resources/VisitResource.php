<?php

namespace App\Http\Resources;

use App\Models\Visit;
use App\Services\PropertyAccess;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $request->user();
        $paid = in_array($this->status, [Visit::STATUS_PAID, Visit::STATUS_COMPLETED], true);
        $canSeeCode = $user && in_array($user->id, [$this->user_id, $this->agent_id, $this->property?->user_id], true) && $paid;

        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'title' => $this->property?->title,
            'scheduled_at' => optional($this->scheduled_at)?->toIso8601String(),
            'status' => $this->status,
            'paid' => $paid,
            'code' => $canSeeCode ? $this->code : null,
            'checked_in_at' => optional($this->checked_in_at)?->toIso8601String(),
            'agent' => $this->agent?->name,
            'hold_expires_at' => optional($this->hold_expires_at)?->toIso8601String(),
            'property' => $this->whenLoaded('property', fn () => new PropertyResource($this->property)),
            'payment' => $this->whenLoaded('payment', fn () => new PaymentResource($this->payment)),
            'private_unlocked' => $user
                && $paid
                && $this->property
                && app(PropertyAccess::class)->canSeePrivate($user, $this->property),
        ];
    }
}
