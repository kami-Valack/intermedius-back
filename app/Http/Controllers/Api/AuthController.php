<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\OmbalaOtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OmbalaOtpService $phoneVerifications)
    {
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $user = DB::transaction(function () use ($request, $data) {
            $verification = $this->phoneVerifications->consumeForRegistration(
                $data['phone_verification_id'],
                $data['phone']
            );
            $documentPath = null;
            $documentName = null;
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store('documents', 'local');
                $documentName = $request->file('document')->getClientOriginalName();
            }

            $role = $data['role'];

            return User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'phone_verified_at' => $verification->verified_at,
                'nif' => $data['nif'] ?? null,
                'role' => $role,
                'status' => $role === User::ROLE_CLIENT ? User::STATUS_ACTIVE : User::STATUS_PENDING,
                'document_path' => $documentPath,
                'document_name' => $documentName,
                'password' => Hash::make($data['password']),
            ]);
        });

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->toAuthArray(),
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::query()->where('email', strtolower($request->email))->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['E-mail ou palavra-passe incorrectos.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->toAuthArray(),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()->toAuthArray()]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
