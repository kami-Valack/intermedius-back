<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteAgentRequest;
use App\Http\Requests\UpdateAgentLinkRequest;
use App\Http\Resources\AgentLinkResource;
use App\Models\AgentLink;
use App\Models\Property;
use App\Services\AgentLinkService;
use Illuminate\Http\Request;

class AgentLinkController extends Controller
{
    public function __construct(private AgentLinkService $links)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = AgentLink::query()->with(['property', 'agent', 'owner']);
        if (! $user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)->orWhere('agent_id', $user->id);
            });
        }

        return AgentLinkResource::collection($query->latest()->paginate(50));
    }

    public function invite(InviteAgentRequest $request, Property $property)
    {
        $link = $this->links->invite(
            $request->user(),
            $property,
            $request->agent_email,
            $request->commission_pct,
            $request->terms
        );

        return (new AgentLinkResource($link))->response()->setStatusCode(201);
    }

    public function update(UpdateAgentLinkRequest $request, AgentLink $agentLink)
    {
        $this->authorize('update', $agentLink);
        $link = $this->links->changeStatus($request->user(), $agentLink, $request->status);

        return new AgentLinkResource($link);
    }
}
