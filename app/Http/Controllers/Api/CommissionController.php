<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommissionResource;
use App\Models\Commission;
use App\Services\RentalService;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Commission::query()->with(['property', 'agent', 'owner', 'payment']);
        if (! $user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)->orWhere('agent_id', $user->id);
            });
        }

        return CommissionResource::collection($query->latest()->paginate(50));
    }

    public function pay(Request $request, Commission $commission)
    {
        $validated = $request->validate([
            'payment_method' => ['sometimes', 'required', 'in:mcx_express,reference'],
        ]);

        $commission = app(RentalService::class)->payCommission(
            $request->user(),
            $commission,
            $validated['payment_method'] ?? 'reference'
        );

        return new CommissionResource($commission);
    }
}
