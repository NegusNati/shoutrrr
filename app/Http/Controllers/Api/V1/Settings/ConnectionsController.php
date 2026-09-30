<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * OAuth sign-in providers linked to the user account (settings/connections) —
 * distinct from ConnectedAccount, which is a workspace's publishing account.
 */
class ConnectionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $linkedAccounts = $user->socialAccounts()->get(['id', 'provider'])->keyBy('provider');

        return response()->json([
            'connections' => collect(SocialProvider::enabledProviders())
                ->map(fn (string $key): array => [
                    'provider' => $key,
                    'label' => SocialProvider::from($key)->label(),
                    'connected' => $linkedAccounts->has($key),
                    'id' => $linkedAccounts->get($key)?->id,
                ])
                ->values()
                ->all(),
            'hasPassword' => $user->hasPassword(),
        ]);
    }

    public function destroy(Request $request, SocialAccount $socialAccount): JsonResponse
    {
        abort_unless($socialAccount->user_id === $request->user()->id, 403);

        // Lock the user row so concurrent disconnect requests can't both pass the
        // last-method check and leave the account with no way to sign in.
        $deleted = DB::transaction(function () use ($request, $socialAccount): bool {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->first();

            if (! $user || $user->loginMethodCount() <= 1) {
                return false;
            }

            $socialAccount->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json(
                ['message' => 'You cannot disconnect your only sign-in method. Set a password first.'],
                422,
            );
        }

        return response()->json(['disconnected' => true]);
    }
}
