<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Settings\ConnectionsController;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserConnectionsController extends ConnectionsController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->connectionsPayload($user));
    }

    public function remove(Request $request, string $socialAccountId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SocialAccount|null $socialAccount */
        $socialAccount = SocialAccount::query()->find($socialAccountId);
        abort_unless($socialAccount !== null && $socialAccount->user_id === $user->id, 404);

        if (! $this->disconnectAccount($user, $socialAccount)) {
            return response()->json([
                'message' => 'You cannot disconnect your only sign-in method. Set a password first.',
            ], 422);
        }

        return response()->json(['message' => 'Account disconnected.']);
    }
}
