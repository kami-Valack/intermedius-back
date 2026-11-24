<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\AgentLink;
use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function __construct(private PropertyService $properties)
    {
    }

    public function index(Request $request)
    {
        $query = Property::query()
            ->where('status', Property::STATUS_ACTIVE)
            ->with(['media', 'owner', 'approvedAgents.agent']);

        if ($q = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', '%'.$q.'%')
                    ->orWhere('municipality', 'like', '%'.$q.'%')
                    ->orWhere('neighborhood', 'like', '%'.$q.'%');
            });
        }
        foreach (['category', 'province', 'municipality'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (int) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (int) $request->input('max_price'));
        }
        if ($request->filled('bedrooms')) {
            $query->where('bedrooms', '>=', (int) $request->input('bedrooms'));
        }

        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default => $query->latest(),
        };

        $perPage = min(max((int) $request->input('per_page', 12), 1), 50);

        return PropertyResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, Property $property)
    {
        if ($property->status !== Property::STATUS_ACTIVE) {
            $user = $request->user();
            $allowed = $user && ($user->isAdmin() || $user->id === $property->user_id);
            abort_unless($allowed, 404);
        }
        $property->load(['media', 'owner', 'approvedAgents.agent']);

        return new PropertyResource($property);
    }

    public function mine(Request $request)
    {
        $user = $request->user();
        $ids = AgentLink::query()
            ->where('agent_id', $user->id)
            ->where('status', AgentLink::STATUS_APPROVED)
            ->pluck('property_id');

        $query = Property::query()
            ->with(['media', 'owner', 'approvedAgents.agent'])
            ->where(function ($q) use ($user, $ids) {
                $q->where('user_id', $user->id)->orWhereIn('id', $ids);
            })
            ->latest();

        return PropertyResource::collection($query->paginate(min((int) $request->input('per_page', 20), 50)));
    }

    public function store(StorePropertyRequest $request)
    {
        $property = $this->properties->create(
            $request->user(),
            $request->validated(),
            $request->file('photos', []),
            $request->file('video')
        );

        return (new PropertyResource($property))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Property $property)
    {
        $this->authorize('delete', $property);
        $this->properties->delete($request->user(), $property);

        return response()->noContent();
    }
}
