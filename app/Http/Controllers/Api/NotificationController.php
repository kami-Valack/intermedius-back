<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $items = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(min((int) $request->input('per_page', 20), 50));

        return NotificationResource::collection($items);
    }

    public function read(Request $request, UserNotification $userNotification)
    {
        abort_unless($userNotification->user_id === $request->user()->id, 403);
        $userNotification->read_at = now();
        $userNotification->save();

        return new NotificationResource($userNotification);
    }

    public function readAll(Request $request)
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->noContent();
    }
}
