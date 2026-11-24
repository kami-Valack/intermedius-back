<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AgentLinkResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'property_title' => $this->property?->title,
            'owner_id' => $this->owner_id,
            'agent_id' => $this->agent_id,
            'agent_name' => $this->agent?->name,
            'agent_email' => $this->agent?->email,
            'status' => $this->status,
            'commission_pct' => (string) $this->commission_pct,
            'terms' => $this->terms,
            'tracking_code' => $this->tracking_code,
            'status_changed_at' => optional($this->status_changed_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
