<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConnectedAccountStatus;
use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConnectedAccount\UpdateAutoRepostRequest;
use App\Models\ConnectedAccount;
use App\Services\ConnectedAccounts\AccountConnectionService;
use App\Services\ConnectedAccounts\BlueskyConnector;
use App\Services\ConnectedAccounts\DiscordConnector;
use App\Services\ConnectedAccounts\XAccountCapabilities;
use App\Services\Publishing\TokenManager;
use App\Support\CursorPage;
use App\Support\InstanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ConnectedAccountsController extends Controller
{
    public function __construct(
        private readonly BlueskyConnector $connector,
        private readonly DiscordConnector $discord,
        private readonly AccountConnectionService $connections,
        private readonly XAccountCapabilities $xCapabilities,
        private readonly TokenManager $tokens,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = ConnectedAccount::query()
            ->orderBy('id', 'desc')
            ->cursorPaginate($validated['per_page'] ?? 25)
            ->through(fn (ConnectedAccount $account): array => [
                'id' => $account->id,
                'platform' => $account->platform->value,
                'platform_label' => $account->platform->label(),
                'handle' => $account->handle,
                'display_name' => $account->display_name,
                'status' => $account->status->value,
                'status_label' => $account->status->label(),
                'token_expires_at' => $account->token_expires_at?->toIso8601String(),
            ]);

        return response()->json(CursorPage::make($paginator));
    }

    /**
     * Full payload for the connected-accounts page: every account in the
     * workspace plus the platform capability and permission flags the page
     * needs to render connect/manage affordances.
     */
    public function manage(Request $request): JsonResponse
    {
        $request->user()->can('viewAny', ConnectedAccount::class) ?: abort(403);
        $defaultAccountId = $request->user()->currentWorkspace()->value('default_connected_account_id');

        $accounts = ConnectedAccount::query()
            ->with(['connectedBy:id,name', 'secret:connected_account_id,session'])
            ->latest()
            ->get()
            ->sortByDesc(fn (ConnectedAccount $account): bool => $account->id === $defaultAccountId)
            ->map(fn (ConnectedAccount $account): array => [
                'id' => $account->id,
                'platform' => $account->platform->value,
                'platform_label' => $account->platform->label(),
                'handle' => $account->handle,
                'display_name' => $account->display_name,
                'avatar_url' => $account->avatar_url,
                'status' => $account->status->value,
                'status_label' => $account->status->label(),
                'auth_method' => $account->auth_method,
                'connected_by' => $account->connectedBy?->name,
                'token_expires_at' => $account->token_expires_at?->toIso8601String(),
                'max_text_length' => $account->maxTextLength(),
                'max_video_duration_seconds' => $account->maxVideoDurationSeconds(),
                'x_premium' => $account->hasXPremium(),
                'x_subscription_tier' => $account->xSubscriptionTier(),
                'x_subscription_label' => $account->xSubscriptionLabel(),
                'x_subscription_checked_at' => $account->xSubscriptionCheckedAt(),
                'is_linkedin_page' => $account->isLinkedInOrganization(),
                'is_default' => $account->id === $defaultAccountId,
                'disabled' => $account->isDisabled(),
                'pds_url' => $this->customPdsUrl($account),
                'auto_repost_enabled' => $account->autoRepostEnabled(),
            ])
            ->values()
            ->all();

        return response()->json([
            'accounts' => $accounts,
            'capabilities' => Platform::capabilities(),
            'canManage' => $request->user()->can('create', ConnectedAccount::class),
        ]);
    }

    public function makeDefault(Request $request, ConnectedAccount $account): JsonResponse
    {
        $request->user()->can('update', $account) ?: abort(403);

        $request->user()->currentWorkspace()->firstOrFail()
            ->forceFill(['default_connected_account_id' => $account->id])
            ->save();

        return response()->json(['is_default' => true]);
    }

    public function toggle(Request $request, ConnectedAccount $account): JsonResponse
    {
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

        return response()->json([
            'disabled' => $account->isDisabled(),
            'is_default' => $request->user()->currentWorkspace()
                ->value('default_connected_account_id') === $account->id,
        ]);
    }

    public function autoRepost(UpdateAutoRepostRequest $request, ConnectedAccount $account): JsonResponse
    {
        $validated = $request->validated();

        // Only meaningful on platforms with a native repost API.
        $enabled = $account->platform->supportsRepost() && (bool) $validated['enabled'];

        $updates = array_filter([
            'enabled' => $enabled,
            'min_percentile' => isset($validated['min_percentile']) ? (float) $validated['min_percentile'] : null,
        ], fn ($value): bool => $value !== null);

        // Read-modify-write at both levels: never overwrite the whole capabilities
        // array, and never clobber a previously stored min_percentile when a
        // request (e.g. a disable toggle) omits it. Serialize against concurrent
        // capability writers (e.g. an X tier refresh) by locking the row for the
        // read-modify-write so one save can't clobber the other's JSON column.
        DB::transaction(function () use ($account, $updates): void {
            $locked = ConnectedAccount::query()->lockForUpdate()->findOrFail($account->id);

            $autoRepost = [...($locked->capabilities['auto_repost'] ?? []), ...$updates];

            $locked->forceFill([
                'capabilities' => [...($locked->capabilities ?? []), 'auto_repost' => $autoRepost],
            ])->save();
        });

        return response()->json(['auto_repost_enabled' => $enabled]);
    }

    public function refreshXTier(Request $request, ConnectedAccount $account): JsonResponse
    {
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

            return response()->json([
                'message' => "We couldn't refresh {$account->handle}'s X account tier. Your existing limit was kept.",
            ], 422);
        }

        if ($capabilities === null) {
            return response()->json([
                'message' => "We couldn't refresh {$account->handle}'s X account tier. Your existing limit was kept.",
            ], 422);
        }

        // Lock the row for the capabilities read-modify-write so a concurrent
        // auto-repost toggle (which writes the same JSON column) can't clobber
        // the freshly-fetched tier, or vice versa.
        DB::transaction(function () use ($account, $capabilities): void {
            $locked = ConnectedAccount::query()->lockForUpdate()->findOrFail($account->id);

            $locked->forceFill([
                'capabilities' => array_replace($locked->capabilities ?? [], $capabilities),
                // A successful authenticated X lookup proves the stored access token
                // is usable. Recover an account that a previous refresh failure left
                // blocked, otherwise publishing would fail before it can use that
                // still-valid token.
                'status' => ConnectedAccountStatus::Active->value,
                'refresh_failed_at' => null,
                'refresh_failure_reason' => null,
            ])->save();
        });

        $account->refresh();

        return response()->json([
            'x_subscription_label' => $account->xSubscriptionLabel(),
            'x_subscription_checked_at' => $account->xSubscriptionCheckedAt(),
            'max_text_length' => $account->maxTextLength(),
            'max_video_duration_seconds' => $account->maxVideoDurationSeconds(),
        ]);
    }

    /**
     * Credential-based reconnects complete here (422 on provider failure);
     * OAuth-based reconnects hand back a URL for the browser to follow.
     */
    public function reconnect(Request $request, ConnectedAccount $account): JsonResponse
    {
        $request->user()->can('update', $account) ?: abort(403);

        if (! app(InstanceSettings::class)->platformAvailable($account->platform)) {
            return response()->json([
                'message' => "{$account->platform->label()} is disabled on this instance.",
            ], 422);
        }

        // OAuth accounts reconnect by re-running the provider flow (which upserts
        // onto this row via the unique constraint); only app-password accounts
        // resubmit credentials here.
        if ($account->platform === Platform::Bluesky && $account->auth_method === 'oauth') {
            if (! $account->platform->isConfigured()) {
                return response()->json([
                    'message' => "{$account->platform->label()} is not configured for reconnection.",
                ], 422);
            }

            return response()->json([
                'url' => route('accounts.bluesky.oauth', array_filter([
                    'identifier' => ltrim($account->handle, '@'),
                    'pds_url' => $this->customPdsUrl($account),
                ])),
            ]);
        }

        // Webhook accounts (Discord) reconnect by resubmitting a webhook URL.
        // A deleted-and-recreated webhook has a new remote id, so adopt it onto
        // this row in place instead of upserting — which, keyed on the new id,
        // would spawn a duplicate card rather than heal the existing one.
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
            if (! $account->platform->isConfigured()) {
                return response()->json([
                    'message' => "{$account->platform->label()} is not configured for reconnection.",
                ], 422);
            }

            // Facebook/Instagram reconnect by re-running the shared Meta
            // Login + Page-selection flow, not the generic per-platform route.
            if ($account->platform->usesMetaConnectionFlow()) {
                return response()->json(['url' => route('accounts.meta.redirect')]);
            }

            return response()->json([
                'url' => route('accounts.connect', ['platform' => $account->platform->value]),
            ]);
        }

        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'app_password' => ['required', 'string', 'max:255'],
            'pds_url' => ['nullable', 'url', 'max:255'],
        ]);

        try {
            $data = $this->connector->connect(
                $validated['identifier'],
                $validated['app_password'],
                $validated['pds_url'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if ($data->remoteAccountId !== $account->remote_account_id) {
            throw ValidationException::withMessages([
                'identifier' => 'Those credentials are for a different Bluesky account.',
            ]);
        }

        $this->connections->store($data, $request->user());

        return response()->json(['reconnected' => true]);
    }

    public function destroy(Request $request, ConnectedAccount $account): JsonResponse
    {
        $request->user()->can('delete', $account) ?: abort(403);

        $workspace = $request->user()->currentWorkspace()->first();
        if ($workspace?->default_connected_account_id === $account->id) {
            $workspace->forceFill(['default_connected_account_id' => null])->save();
        }

        $account->secret()->delete();
        $account->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * The saved PDS for a Bluesky account, but only when it differs from the
     * default discovery target — so reconnect can re-run OAuth against a custom
     * service URL instead of silently falling back to bsky.social.
     */
    private function customPdsUrl(ConnectedAccount $account): ?string
    {
        if ($account->platform !== Platform::Bluesky) {
            return null;
        }

        $pds = $account->secret?->session['pds'] ?? null;

        if (! is_string($pds) || $pds === '' || rtrim($pds, '/') === 'https://bsky.social') {
            return null;
        }

        return $pds;
    }
}
