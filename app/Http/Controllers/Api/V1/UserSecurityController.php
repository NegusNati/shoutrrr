<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Settings\SecurityController;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\JsonResponse;

class UserSecurityController extends SecurityController
{
    public function show(TwoFactorAuthenticationRequest $request): JsonResponse
    {
        return response()->json($this->securityPayload($request));
    }

    public function updatePassword(PasswordUpdateRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return response()->json(['message' => 'Password updated.']);
    }
}
