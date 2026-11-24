<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommunityOfferRequest;
use App\Http\Requests\StoreCommunityRequest;
use App\Http\Resources\CommunityOfferResource;
use App\Http\Resources\CommunityRequestResource;
use App\Models\CommunityRequest;
use App\Models\Property;
use App\Services\CommunityService;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function __construct(private CommunityService $community)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = CommunityRequest::query()->with(['client', 'offers.property.media', 'offers.professional']);
        if ($user->role === 'client') {
            $query->where('user_id', $user->id);
        } elseif (! $user->isAdmin()) {
            $query->where('status', CommunityRequest::STATUS_OPEN);
        }

        return CommunityRequestResource::collection($query->latest()->paginate(50));
    }

    public function store(StoreCommunityRequest $request)
    {
        $created = $this->community->create($request->user(), $request->validated());

        return (new CommunityRequestResource($created->load('client')))->response()->setStatusCode(201);
    }

    public function close(Request $request, CommunityRequest $communityRequest)
    {
        $this->authorize('close', $communityRequest);
        $closed = $this->community->close($request->user(), $communityRequest);

        return new CommunityRequestResource($closed);
    }

    public function offer(StoreCommunityOfferRequest $request, CommunityRequest $communityRequest)
    {
        $property = Property::findOrFail($request->property_id);
        $offer = $this->community->respond(
            $request->user(),
            $communityRequest,
            $property,
            $request->boolean('accept')
        );

        return (new CommunityOfferResource($offer->load('property.media', 'professional')))->response()->setStatusCode(201);
    }
}
