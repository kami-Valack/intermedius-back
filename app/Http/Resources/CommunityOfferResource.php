<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommunityOfferResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'accepted' => (bool) $this->accepted,
            'professional' => $this->professional?->name,
            'property' => $this->whenLoaded('property', fn () => new PropertyResource($this->property)),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
