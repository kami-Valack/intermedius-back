<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendPhoneVerificationRequest;
use App\Http\Requests\VerifyPhoneVerificationRequest;
use App\Models\PhoneVerification;
use App\Services\OmbalaOtpService;
use Illuminate\Http\JsonResponse;

class PhoneVerificationController extends Controller
{
    public function __construct(private OmbalaOtpService $otp)
    {
    }

    public function send(SendPhoneVerificationRequest $request): JsonResponse
    {
        $verification = $this->otp->send($request->validated()['phone']);

        return response()->json([
            'verification_id' => $verification->id,
            'status' => $verification->status,
            'attempts_remaining' => $verification->attempts_remaining,
            'expires_at' => $verification->expires_at->toIso8601String(),
        ], 201);
    }

    public function verify(VerifyPhoneVerificationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $verification = PhoneVerification::query()->findOrFail($data['verification_id']);
        $verification = $this->otp->verify($verification, $data['code']);

        return response()->json([
            'verified' => true,
            'verification_id' => $verification->id,
            'status' => $verification->status,
            'verified_at' => $verification->verified_at->toIso8601String(),
        ]);
    }
}
