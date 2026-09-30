<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Dto\ConnectedAccount\ConnectedAccountData;
use App\Enums\Platform;
use App\Http\Controllers\ConnectedAccounts\LinkedInPageConnectionController;
use App\Http\Controllers\ConnectedAccounts\MetaConnectionController;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConnectedAccount\ConnectBlueskyRequest;
use App\Http\Requests\ConnectedAccount\ConnectDiscordRequest;
use App\Models\ConnectedAccount;
use App\Services\ConnectedAccounts\AccountConnectionService;
use App\Services\ConnectedAccounts\BlueskyConnector;
use App\Services\ConnectedAccounts\DiscordConnector;
use App\Support\MetaConnectStash;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Credential-based connect endpoints plus the server-side half of the
 * OAuth picker flows: the OAuth callbacks stash provider data in the session
 * and hand the browser to the SPA picker page, which reads a token-stripped
 * projection through this controller's GETs and posts the user's selection
 * back through the POSTs.
 */
class AccountConnectionsController extends Controller
{
    public function __construct(
        private readonly BlueskyConnector $connector,
        private readonly DiscordConnector $discord,
        private readonly AccountConnectionService $connections,
    ) {}

    public function connectBluesky(ConnectBlueskyRequest $request): JsonResponse
    {
        try {
            $data = $this->connector->connect(
                $request->string('identifier')->toString(),
                $request->string('app_password')->toString(),
                $request->input('pds_url'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // Bluesky has no OAuth scopes to inspect, so `dm_enabled` is set from the
        // operator's self-declaration that the app password has DM access, made
        // at connect time via the "This app password has DM access" checkbox.
        $data = $data->withCapabilities([
            ...($data->capabilities ?? []),
            'dm_enabled' => $request->boolean('dm_access'),
        ]);

        $this->connections->store($data, $request->user());

        return response()->json(['connected' => true]);
    }

    public function connectDiscord(ConnectDiscordRequest $request): JsonResponse
    {
        try {
            $data = $this->discord->connect($request->string('webhook_url')->toString());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->connections->store($data, $request->user());

        return response()->json(['connected' => true]);
    }

    /**
     * Picker payload for the Meta connect page. The OAuth callback has already
     * stashed the Pages/IG assets (with page access tokens) server-side — this
     * returns the token-stripped projection only.
     */
    public function metaPicker(Request $request): JsonResponse
    {
        $request->user()->can('create', ConnectedAccount::class) ?: abort(403);

        $stash = MetaConnectStash::get($request);

        if (! is_array($stash)) {
            return response()->json([
                'message' => 'Your Meta connection expired. Please try connecting again.',
            ], 404);
        }

        return response()->json([
            'assets' => MetaConnectStash::projectAssets($stash['assets']),
        ]);
    }

    public function storeMetaSelection(Request $request): JsonResponse
    {
        $request->user()->can('create', ConnectedAccount::class) ?: abort(403);

        $stash = MetaConnectStash::get($request);
        $stashedAssets = $stash['assets'] ?? [];

        if ($stashedAssets === []) {
            return response()->json([
                'message' => 'Your Meta connection expired. Please try connecting again.',
            ], 404);
        }

        $launchedPlatforms = array_map(
            fn (Platform $platform): string => $platform->value,
            Platform::launchedMetaGraphPlatforms(),
        );

        $validated = $request->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*.assetKey' => ['required', 'string', Rule::in(array_keys($stashedAssets))],
            'selected.*.platform' => ['required', 'string', Rule::in($launchedPlatforms)],
        ]);

        $created = 0;

        foreach ($validated['selected'] as $selection) {
            $asset = $stashedAssets[$selection['assetKey']];
            $platform = Platform::from($selection['platform']);

            // The asset must actually support the chosen platform — a linked IG
            // account is required for Instagram.
            if (! in_array($platform->value, MetaConnectStash::availablePlatformsFor($asset), true)) {
                throw ValidationException::withMessages([
                    'selected' => "{$platform->label()} is not available for the selected Page.",
                ]);
            }

            $this->connections->store(MetaConnectionController::buildAccountData($asset, $platform), $request->user());
            $created++;
        }

        MetaConnectStash::forget($request);

        return response()->json(['connected' => $created]);
    }

    /**
     * Picker payload for the LinkedIn connect page — the OAuth callback has
     * stashed the person profile + administered organizations server-side.
     */
    public function linkedinPicker(Request $request): JsonResponse
    {
        $request->user()->can('create', ConnectedAccount::class) ?: abort(403);

        $stash = $request->session()->get(LinkedInPageConnectionController::SESSION_KEY);

        if (! is_array($stash)) {
            return response()->json([
                'message' => 'Your LinkedIn connection expired. Please try again.',
            ], 404);
        }

        return response()->json([
            'person' => $stash['person'],
            'organizations' => array_values($stash['organizations'] ?? []),
        ]);
    }

    public function storeLinkedinSelection(Request $request): JsonResponse
    {
        $request->user()->can('create', ConnectedAccount::class) ?: abort(403);

        $stash = $request->session()->get(LinkedInPageConnectionController::SESSION_KEY);

        if (! is_array($stash)) {
            return response()->json([
                'message' => 'Your LinkedIn connection expired. Please try again.',
            ], 404);
        }

        /** @var array<string, array{id: string, urn: string, name: string, vanityName: string}> $organizations */
        $organizations = $stash['organizations'] ?? [];

        $validated = $request->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*.type' => ['required', 'string', Rule::in(['person', 'organization'])],
            'selected.*.id' => ['required_if:selected.*.type,organization', 'nullable', 'string', Rule::in(array_keys($organizations))],
        ]);

        $token = (string) ($stash['accessToken'] ?? '');
        $refresh = $stash['refreshToken'] ?? null;
        $expiresAt = isset($stash['tokenExpiresAt'])
            ? CarbonImmutable::parse((string) $stash['tokenExpiresAt'])
            : null;

        /** @var array<int, string> $granted */
        $granted = (array) ($stash['approvedScopes'] ?? []);

        $user = $request->user();

        // Persist every selected account in one transaction so a mid-loop failure
        // can't leave the workspace with a partial set of connected accounts.
        DB::transaction(function () use ($validated, $organizations, $stash, $token, $refresh, $expiresAt, $granted, $user): void {
            foreach ($validated['selected'] as $selection) {
                if ($selection['type'] === 'person') {
                    $person = $stash['person'];
                    $data = new ConnectedAccountData(
                        platform: Platform::LinkedIn,
                        remoteAccountId: (string) $person['remoteAccountId'],
                        handle: (string) $person['handle'],
                        displayName: $person['displayName'] ?? null,
                        avatarUrl: $person['avatarUrl'] ?? null,
                        authMethod: 'oauth',
                        accessToken: $token,
                        refreshToken: $refresh,
                        capabilities: ['linkedin_account_type' => 'person', 'linkedin_engagement' => in_array('r_member_social_feed', $granted, true)],
                        tokenExpiresAt: $expiresAt,
                    );
                } else {
                    $organization = $organizations[$selection['id']];
                    $data = new ConnectedAccountData(
                        platform: Platform::LinkedIn,
                        remoteAccountId: (string) $organization['id'],
                        handle: (string) $organization['vanityName'],
                        displayName: (string) $organization['name'],
                        avatarUrl: null,
                        authMethod: 'oauth',
                        accessToken: $token,
                        refreshToken: $refresh,
                        capabilities: ['linkedin_account_type' => 'organization', 'linkedin_engagement' => in_array('r_organization_social', $granted, true)],
                        tokenExpiresAt: $expiresAt,
                    );
                }

                $this->connections->store($data, $user);
            }
        });

        $created = count($validated['selected']);
        $request->session()->forget(LinkedInPageConnectionController::SESSION_KEY);

        return response()->json(['connected' => $created]);
    }
}
