<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;
use App\Models\ConnectedAccountNativeWatch;
use App\Models\User;
use App\Services\Billing\WorkspaceSubscriptionGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Native post tracking for the SPA — the JSON equivalent of
 * App\Http\Controllers\Settings\NativeTrackingController.
 *
 * The {account} route parameter resolves through the global
 * `Route::bind('account', ...)` in routes/accounts.php, which scopes the
 * lookup to the caller's current workspace and 404s on a foreign id.
 */
class NativeTrackingController extends Controller
{
    public function __construct(private readonly WorkspaceSubscriptionGate $gate) {}

    public function store(Request $request, ConnectedAccount $account): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);
        $this->authorizeManage($user, $workspace->id);

        if (! $account->platform->supportsNativeRead()) {
            throw ValidationException::withMessages([
                'account' => ucfirst($account->platform->value).' does not support native tracking, so its posts can only sync when published through Shoutrrr.',
            ]);
        }
        if (! $account->nativeWatch()->exists() && ! $this->gate->canTrackNativeAccount($workspace)) {
            $max = (int) config('subscriptions.max_native_tracked');
            throw ValidationException::withMessages([
                'account' => "You've reached your plan's limit of {$max} tracked accounts. Untrack one to track another.",
            ]);
        }

        ConnectedAccountNativeWatch::firstOrCreate(
            ['connected_account_id' => $account->id],
            ['workspace_id' => $workspace->id, 'enabled_at' => now(), 'enabled_by' => $user->id],
        );

        return response()->json(['message' => 'Native tracking enabled.'], 201);
    }

    public function destroy(Request $request, ConnectedAccount $account): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);
        $this->authorizeManage($user, $workspace->id);

        ConnectedAccountNativeWatch::query()
            ->where('workspace_id', $workspace->id)
            ->where('connected_account_id', $account->id)
            ->delete();

        return response()->json(['message' => 'Native tracking disabled.']);
    }

    private function authorizeManage(User $user, string $workspaceId): void
    {
        abort_unless($user->hasAllPermissions(['workspace.settings.manage'], $workspaceId), 403);
    }
}
