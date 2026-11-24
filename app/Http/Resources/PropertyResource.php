<?php

namespace App\Http\Resources;

use App\Models\AgentLink;
use App\Services\PropertyAccess;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $request->user();
        $photos = $this->media?->where('kind', 'photo')->values() ?? collect();
        $video = $this->media?->firstWhere('kind', 'video');
        $cover = $photos->firstWhere('is_cover') ?? $photos->first();
        $agent = $this->approvedAgents?->first()?->agent;

        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'type' => $this->type,
            'price' => (int) $this->price,
            'period' => $this->period(),
            'bedrooms' => (int) $this->bedrooms,
            'baths' => (int) $this->baths,
            'area' => $this->area,
            'province' => $this->province,
            'municipality' => $this->municipality,
            'neighborhood' => $this->neighborhood,
            'image' => $cover?->publicUrl(),
            'photos' => $photos->map->publicUrl()->values(),
            'video' => $video?->publicUrl(),
            'description' => $this->description,
            'amenities' => $this->amenities ?? [],
            'agent' => $agent?->name ?? 'Intermediário por atribuir',
            'status' => $this->status,
            'badge' => $this->badge(),
            'pin' => [(int) $this->visual_pin_x, (int) $this->visual_pin_y],
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];

        if ($user && app(PropertyAccess::class)->canSeePrivate($user, $this->resource)) {
            $data['private_address'] = $this->private_address;
            $data['private_lat'] = $this->private_lat;
            $data['private_lng'] = $this->private_lng;
        }

        return $data;
    }
}
