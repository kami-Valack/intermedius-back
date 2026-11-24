<?php

namespace App\Services;

use App\Models\AgentLink;
use App\Models\Property;
use App\Models\Rental;
use App\Models\User;
use App\Models\Visit;

class PropertyAccess
{
    public function canSeePrivate(User $user, Property $property): bool
    {
        if ($user->isAdmin() || $property->user_id === $user->id) {
            return true;
        }

        $isApprovedAgent = AgentLink::query()
            ->where('property_id', $property->id)
            ->where('agent_id', $user->id)
            ->where('status', AgentLink::STATUS_APPROVED)
            ->exists();
        if ($isApprovedAgent) {
            return true;
        }

        $paidVisit = Visit::query()
            ->where('property_id', $property->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [Visit::STATUS_PAID, Visit::STATUS_COMPLETED])
            ->exists();
        if ($paidVisit) {
            return true;
        }

        return Rental::query()
            ->where('property_id', $property->id)
            ->where('user_id', $user->id)
            ->where('status', Rental::STATUS_ACTIVE)
            ->exists();
    }
}
