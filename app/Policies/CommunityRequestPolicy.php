<?php

namespace App\Policies;

use App\Models\CommunityRequest;
use App\Models\User;

class CommunityRequestPolicy
{
    public function close(User $user, CommunityRequest $request): bool
    {
        return $user->id === $request->user_id;
    }
}
