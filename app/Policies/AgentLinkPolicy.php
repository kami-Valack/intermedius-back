<?php

namespace App\Policies;

use App\Models\AgentLink;
use App\Models\User;

class AgentLinkPolicy
{
    public function update(User $user, AgentLink $link): bool
    {
        return $user->isAdmin() || $user->id === $link->agent_id || $user->id === $link->owner_id;
    }
}
