<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Favorite;
use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $properties = Property::query()
            ->whereIn('id', Favorite::query()->where('user_id', $request->user()->id)->pluck('property_id'))
            ->with(['media', 'approvedAgents.agent'])
            ->latest()
            ->paginate(min((int) $request->input('per_page', 20), 50));

        return PropertyResource::collection($properties);
    }

    public function toggle(Request $request, Property $property)
    {
        $favorited = app(PropertyService::class)->toggleFavorite($request->user(), $property);

        return response()->json([
            'favorited' => $favorited,
            'message' => $favorited ? 'Adicionado aos favoritos.' : 'Removido dos favoritos.',
        ]);
    }
}
