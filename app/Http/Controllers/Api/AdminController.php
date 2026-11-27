<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewPropertyRequest;
use App\Http\Requests\ReviewUserRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\UserResource;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rental;
use App\Models\User;
use App\Models\Visit;
use App\Services\PropertyService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function stats()
    {
        return response()->json([
            'users_pending' => User::query()->where('status', User::STATUS_PENDING)->count(),
            'properties_pending' => Property::query()->where('status', Property::STATUS_PENDING)->count(),
            'visits_paid' => Visit::query()->whereIn('status', [Visit::STATUS_PAID, Visit::STATUS_COMPLETED])->count(),
            'rentals_active' => Rental::query()->where('status', Rental::STATUS_ACTIVE)->count(),
            'commissions_due' => (int) Commission::query()->where('status', Commission::STATUS_DUE)->sum('amount'),
            'payments_paid' => Payment::query()->where('status', Payment::STATUS_PAID)->count(),
        ]);
    }

    public function users(Request $request)
    {
        $query = User::query()->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        return UserResource::collection($query->paginate(min((int) $request->input('per_page', 20), 50)));
    }

    public function reviewUser(ReviewUserRequest $request, User $user)
    {
        abort_if($user->role === User::ROLE_ADMIN, 422, 'Não altere administradores por esta via.');
        $user->status = $request->status;
        $user->save();

        return new UserResource($user);
    }

    public function properties(Request $request)
    {
        $query = Property::query()->with(['media', 'owner', 'approvedAgents.agent'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return PropertyResource::collection($query->paginate(min((int) $request->input('per_page', 20), 50)));
    }

    public function reviewProperty(ReviewPropertyRequest $request, Property $property, PropertyService $service)
    {
        $property = $service->review($property, $request->status, $request->reason, $request->user());

        return new PropertyResource($property->load(['media', 'owner', 'approvedAgents.agent']));
    }

    public function payments()
    {
        return PaymentResource::collection(Payment::query()->latest()->paginate(50));
    }

    public function ownerPayouts(Request $request)
    {
        $payouts = Rental::query()
            ->selectRaw('owner_id, SUM(monthly_rent) as monthly_amount_due, COUNT(*) as active_rentals_count')
            ->where('status', Rental::STATUS_ACTIVE)
            ->groupBy('owner_id')
            ->with('owner:id,name,email,iban,express_number')
            ->orderBy('owner_id')
            ->paginate(max(1, min((int) $request->input('per_page', 20), 50)));

        $payouts->getCollection()->transform(function (Rental $rental) {
            return [
                'owner' => [
                    'id' => $rental->owner->id,
                    'name' => $rental->owner->name,
                    'email' => $rental->owner->email,
                ],
                'monthly_amount_due' => (int) $rental->monthly_amount_due,
                'active_rentals_count' => (int) $rental->active_rentals_count,
                'currency' => config('intermediarius.currency'),
                'destination' => [
                    'iban' => $rental->owner->iban,
                    'express_number' => $rental->owner->express_number,
                ],
            ];
        });

        return response()->json($payouts);
    }

    public function settings(SettingsService $settings)
    {
        return response()->json($settings->all());
    }

    public function saveSettings(UpdateSettingsRequest $request, SettingsService $settings)
    {
        return response()->json($settings->put($request->validated(), $request->user()->id));
    }
}
