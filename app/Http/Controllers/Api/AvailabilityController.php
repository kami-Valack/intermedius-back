<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveAvailabilityRequest;
use App\Models\Availability;
use App\Support\Normalizer;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function show(Request $request)
    {
        $availability = $request->user()->availability;

        return response()->json([
            'days' => $availability?->days ?? [1, 2, 3, 4, 5],
            'start' => $availability?->start ?? '09:00',
            'end' => $availability?->end ?? '17:00',
            'slot' => $availability?->slot ?? 60,
            'weekday_convention' => '1=segunda … 7=domingo (ISO). Domingo enviado como 0 é convertido para 7.',
        ]);
    }

    public function update(SaveAvailabilityRequest $request)
    {
        $availability = Availability::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'days' => Normalizer::weekDays($request->days),
                'start' => $request->start,
                'end' => $request->end,
                'slot' => $request->slot,
            ]
        );

        return response()->json([
            'days' => $availability->days,
            'start' => $availability->start,
            'end' => $availability->end,
            'slot' => $availability->slot,
        ]);
    }
}
