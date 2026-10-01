<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserProfileController extends ProfileController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'mustVerifyEmail' => config('auth.email_verification.enabled', false) && $request->user() instanceof MustVerifyEmail,
        ]);
    }

    public function updateProfile(ProfileUpdateRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        unset($validated['photo']);

        $photoError = $this->applyProfileUpdate($user, $validated, $request->file('photo'));

        if ($photoError !== null) {
            throw ValidationException::withMessages(['photo' => $photoError]);
        }

        return response()->json(['message' => 'Profile updated.']);
    }

    public function destroyProfile(ProfileDeleteRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $blockReason = $this->deletionBlockReason($user);

        if ($blockReason !== null) {
            throw ValidationException::withMessages(['password' => $blockReason]);
        }

        $this->deleteAccount($user);

        return response()->json(['message' => 'Account deleted.']);
    }
}
