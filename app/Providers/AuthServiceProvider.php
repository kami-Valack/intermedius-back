<?php

namespace App\Providers;

use App\Models\AgentLink;
use App\Models\CommunityRequest;
use App\Models\Property;
use App\Policies\AgentLinkPolicy;
use App\Policies\CommunityRequestPolicy;
use App\Policies\PropertyPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Property::class => PropertyPolicy::class,
        AgentLink::class => AgentLinkPolicy::class,
        CommunityRequest::class => CommunityRequestPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
