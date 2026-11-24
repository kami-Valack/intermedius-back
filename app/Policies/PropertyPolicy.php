<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function delete(User $user, Property $property): bool
    {
        return $user->isAdmin() || $user->id === $property->user_id;
    }
}
