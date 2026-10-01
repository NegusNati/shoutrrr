<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConnectedAccountStatus;
use App\Enums\Platform;
use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceAccount;
use App\Http\Controllers\ConnectedAccounts\ConnectedAccountController;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConnectedAccount\ConnectBlueskyRequest;
use App\Http\Requests\ConnectedAccount\ConnectDiscordRequest;
use App\Http\Requests\ConnectedAccount\UpdateAutoRepostRequest;
use App\Models\ConnectedAccount;
use App\Services\ConnectedAccounts\AccountConnectionService;
use App\Services\ConnectedAccounts\BlueskyConnector;
use App\Services\ConnectedAccounts\DiscordConnector;
use App\Services\ConnectedAccounts\XAccountCapabilities;
use App\Services\Publishing\TokenManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Connected social accounts: the rich card list the Accounts page renders,
 * management actions (toggle/default/auto-repost/refresh/disconnect), and the
 * non-OAuth connect paths (Bluesky app password, Discord webhook). OAuth
 * connects stay on the web routes — they are browser redirect flows, not API
 * calls.
 */
class ConnectedAccountsController extends Controller
{
    use ResolvesWorkspaceAccount;

    public function __construct(
        private readonly BlueskyConnector $bluesky,
        private readonly DiscordConnector $discord,
        private readonly AccountConnectionService $connections,
        private readonly XAccountCapabilities $xCapabilities,
        private readonly TokenManager $tokens,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->user()->can('viewAny', ConnectedAccount::class) ?: abort(403);
        $defaultAccountId = $request->user()->currentWorkspace()->value('default_connected_account_id');

        $accounts = ConnectedAccount::query()
            ->with(['connectedBy:id,name', 'secret:connected_account_id,session'])
            ->latest()
            ->get()
            ->sortByDesc(fn (ConnectedAccount $account): bool => $account->id === $defaultAccountId)
            ->map(fn (ConnectedAccount $account): array => ConnectedAccountController::view($account, $defaultAccountId))
            ->values()
            ->all();

        return response()->json([
            'accounts' => $accounts,
            'capabilities' => Platform::capabilities(),
            'can_manage' => $request->user()->can('create', ConnectedAccount::class),
        ]);
    }

    public function connectBluesky(ConnectBlueskyRequest $request): JsonResponse
    {
        try {
            $data = $this->bluesky->connect(
                $request->string('identifier')->toString(),
                $request->string('app_password')->toString(),
                $request->input('pds_url'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // Bluesky has no OAuth scopes to inspect, so `dm_enabled` is the
        // operator's self-declared "this app password has DM access" checkbox.
        $data = $data->withCapabilities([
            ...($data->capabilities ?? []),
            'dm_enabled' => $request->boolean('dm_access'),
        ]);

        $this->connections->store($data, $request->user());

        return response()->json(['connected' => true], 201);
    }

    public function connectDiscord(ConnectDiscordRequest $request): JsonResponse
    {
        try {
            $data = $this->discord->connect($request->string('webhook_url')->toString());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->connections->store($data, $request->user());

        return response()->json(['connected' => true], 201);
    }

    public function toggle(Request $request, string $accountId): JsonResponse
    {
        $account = $this->findAccountOrFail($accountId);
        $request->user()->can('update', $account) ?: abort(403);

        DB::transaction(function () use ($request, $account): void {
            $disabling = ! $account->isDisabled();

            $account->forceFill([
                'disabled_at' => $disabling ? Date::now() : null,
            ])->save();

            if ($disabling) {
                $workspace = $request->user()->currentWorkspace()->first();
                if ($workspace?->default_connected_account_id === $account->id) {
                    $workspace->forceFill(['default_connected_account_id' => null])->save();
                }
            }
        });

        return response()->json(['disabled' => $account->isDisabled()]);
    }

    public function makeDefault(Request $request, string $accountId): JsonResponse
    {
        $account = $this->findAccountOrFail($accountId);
        $request->user()->can('update', $account) ?: abort(403);

        $request->user()->currentWorkspace()->firstOrFail()
            ->forceFill(['default_connected_account_id' => $account->id])
            ->save();

        return response()->json(['is_default' => true]);
    }

    public function autoRepost(UpdateAutoRepostRequest $request, string $accountId): JsonResponse
    {
        $account = $this->findAccountOrFail($accountId);
        $validated = $request->validated();

        $enabled = $account->platform->supportsRepost() && (bool) $validated['enabled'];

        $updates = array_filter([
            'enabled' => $enabled,
            'min_percentile' => isset($validated['min_percentile']) ? (float) $validated['min_percentile'] : null,
        ], fn ($value): bool => $value !== null);

        // Locked read-modify-write: a concurrent capabilities writer (e.g. the
        // X tier refresh) can't clobber the JSON column mid-merge.
        DB::transaction(function () use ($account, $updates): void {
            $locked = ConnectedAccount::query()->lockForUpdate()->findOrFail($account->id);

            $autoRepost = [...($locked->capabilities['auto_repost'] ?? []), ...$updates];

            $locked->forceFill([
                'capabilities' => [...($locked->capabilities ?? []), 'auto_repost' => $autoRepost],
            ])->save();
        });

        return response()->json(['auto_repost_enabled' => $enabled]);
    }

    public function refreshXAccountTier(Request $request, string $accountId): JsonResponse
    {
        $account = $this->findAccountOrFail($accountId);
        $request->user()->can('update', $account) ?: abort(403);

        if ($account->platform !== Platform::X) {
            return response()->json(['message' => 'Only X accounts have a subscription tier.'], 422);
        }

        try {
            $credentials = $this->tokens->fresh($account);
            $capabilities = $this->xCapabilities->tryForAccessToken(
                isset($credentials['access_token']) ? (string) $credentials['access_token'] : null,
            );
        } catch (Throwable $exception) {
            Log::warning('Could not refresh X account subscription tier.', [
                'account_id' => $account->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => "We couldn't refresh {$account->handle}'s X account tier. Your existing limit was kept."], 422);
        }

        if ($capabilities === null) {
            return response()->json(['message' => "We couldn't refresh {$account->handle}'s X account tier. Your existing limit was kept."], 422);
        }

        DB::transaction(function () use ($account, $capabilities): void {
            $locked = ConnectedAccount::query()->lockForUpdate()->findOrFail($account->id);

            $locked->forceFill([
                'capabilities' => array_replace($locked->capabilities ?? [], $capabilities),
                'status' => ConnectedAccountStatus::Active->value,
                'refresh_failed_at' => null,
                'refresh_failure_reason' => null,
            ])->save();
        });

        $account->refresh();

        return response()->json([
            'x_subscription_label' => $account->xSubscriptionLabel(),
            'max_text_length' => $account->maxTextLength(),
            'max_video_duration_seconds' => $account->maxVideoDurationSeconds(),
        ]);
    }

    /**
     * Credential re-checks for non-OAuth accounts: Bluesky app password and
     * Discord webhook. OAuth reconnects re-run the provider flow — a browser
     * redirect, so the SPA links to the web route instead of calling this.
     */
    public function reconnect(Request $request, string $accountId): JsonResponse
    {
        $account = $this->findAccountOrFail($accountId);
        $request->user()->can('update', $account) ?: abort(403);

        if ($account->auth_method === 'webhook') {
            $validated = $request->validate([
                'webhook_url' => ['required', 'string', 'max:2048'],
            ]);

            try {
                $data = $this->discord->connect($validated['webhook_url']);
            } catch (RuntimeException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            $this->connections->reconnect($account, $data, $request->user());

            return response()->json(['reconnected' => true]);
        }

        if (! $account->platform->supportsAppPassword()) {
            return response()->json(['message' => 'OAuth accounts reconnect through the provider flow.'], 422);
        }

        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'app_password' => ['required', 'string', 'max:255'],
            'pds_url' => ['nullable', 'url', 'max:255'],
        ]);

        try {
            $data = $this->bluesky->connect(
                $validated['identifier'],
                $validated['app_password'],
                $validated['pds_url'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if ($data->remoteAccountId !== $account->remote_account_id) {
            return response()->json([
                'message' => 'Those credentials are for a different Bluesky account.',
                'errors' => ['identifier' => ['Those credentials are for a different Bluesky account.']],
            ], 422);
        }

        $this->connections->store($data, $request->user());

        return response()->json(['reconnected' => true]);
    }

    public function destroy(Request $request, string $accountId): JsonResponse
    {
        $account = $this->findAccountOrFail($accountId);
        $request->user()->can('delete', $account) ?: abort(403);

        $workspace = $request->user()->currentWorkspace()->first();
        if ($workspace?->default_connected_account_id === $account->id) {
            $workspace->forceFill(['default_connected_account_id' => null])->save();
        }

        $account->secret()->delete();
        $account->delete();

        return response()->json(['deleted' => true]);
    }
}
