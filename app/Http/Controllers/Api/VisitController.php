<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckinVisitRequest;
use App\Http\Requests\StoreVisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\Property;
use App\Models\Visit;
use App\Services\VisitService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function __construct(private VisitService $visits)
    {
    }

    public function index(Request $request)
    {
        $this->visits->expireHolds();
        $user = $request->user();
        $query = Visit::query()->with(['property.media', 'agent', 'payment']);
        if (! $user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('agent_id', $user->id)
                    ->orWhereHas('property', fn ($p) => $p->where('user_id', $user->id));
            });
        }

        return VisitResource::collection($query->latest()->paginate(50));
    }

    public function store(StoreVisitRequest $request)
    {
        $property = Property::findOrFail($request->property_id);
        $visit = $this->visits->create(
            $request->user(),
            $property,
            Carbon::parse($request->scheduled_at),
            $request->payment_method
        );

        return (new VisitResource($visit))->response()->setStatusCode(201);
    }

    public function checkin(CheckinVisitRequest $request)
    {
        $visit = $this->visits->checkin($request->user(), $request->code);

        return new VisitResource($visit);
    }
}
