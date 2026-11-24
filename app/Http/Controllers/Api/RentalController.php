<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRentalRequest;
use App\Http\Resources\RentalResource;
use App\Models\Property;
use App\Models\Rental;
use App\Services\RentalService;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    public function __construct(private RentalService $rentals)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Rental::query()->with(['property.media', 'payment', 'owner', 'agent', 'tenant']);
        if (! $user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('owner_id', $user->id)
                    ->orWhere('agent_id', $user->id);
            });
        }

        return RentalResource::collection($query->latest()->paginate(50));
    }

    public function store(StoreRentalRequest $request)
    {
        $property = Property::findOrFail($request->property_id);
        $rental = $this->rentals->create($request->user(), $property, $request->validated());

        return (new RentalResource($rental))->response()->setStatusCode(201);
    }
}
