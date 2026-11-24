<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommunityRequestResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'tipologia' => $this->tipologia,
            'municipality' => $this->municipality,
            'neighborhood' => $this->neighborhood,
            'budget' => $this->budget,
            'message' => $this->message,
            'status' => $this->status,
            'client_name' => $this->client?->name,
            'offers' => CommunityOfferResource::collection($this->whenLoaded('offers')),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
