<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBankDetailsRequest;
use Illuminate\Http\Request;

class BankDetailsController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'iban' => $request->user()->iban,
            'express_number' => $request->user()->express_number,
        ]);
    }

    public function update(SaveBankDetailsRequest $request)
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return response()->json([
            'iban' => $user->iban,
            'express_number' => $user->express_number,
        ]);
    }
}
