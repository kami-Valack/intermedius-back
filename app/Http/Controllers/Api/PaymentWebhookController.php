<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function fake(Request $request, PaymentService $payments)
    {
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = $values[0] ?? null;
            $headers[strtolower($key)] = $values[0] ?? null;
        }

        $payment = $payments->handleWebhook($request->getContent(), $headers);

        return new PaymentResource($payment);
    }
}
