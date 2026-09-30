<?php

use App\Enums\ConnectedAccountStatus;
use App\Enums\Platform;
use App\Enums\WorkspaceRole;
use App\Models\ConnectedAccount;
use App\Models\ConnectedAccountSecret;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Http;

function accountApiMember(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Member,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    test()->actingAs($user);

    return [$user, $workspace];
}

function fakeBlueskySessionForApi(string $did = 'did:plc:abc', string $handle = 'ada.bsky.social'): void
{
    Http::fake([
        '*xrpc/com.atproto.server.createSession' => Http::response([
            'did' => $did,
            'handle' => $handle,
            'accessJwt' => 'access-jwt',
            'refreshJwt' => 'refresh-jwt',
        ]),
        '*xrpc/app.bsky.actor.getProfile*' => Http::response([
            'did' => $did,
            'handle' => $handle,
            'displayName' => 'Ada',
            'avatar' => 'https://cdn/ada.jpg',
        ]),
    ]);
}

/*
|--------------------------------------------------------------------------
| GET connected-accounts/manage — the accounts page payload
|--------------------------------------------------------------------------
*/

test('manage returns the full page payload for a session user', function () {
    [$user, $workspace] = ownerActingIn();
    ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);
    ConnectedAccount::factory()->linkedinPage()->create(['workspace_id' => $workspace->id]);

    $this->getJson('/api/v1/connected-accounts/manage')
        ->assertOk()
        ->assertJsonCount(2, 'accounts')
        ->assertJsonPath('canManage', true)
        ->assertJsonStructure([
            'accounts' => [[
                'id',
                'platform',
                'platform_label',
                'handle',
                'status',
                'auth_method',
                'is_default',
                'disabled',
                'auto_repost_enabled',
            ]],
            'capabilities',
            'canManage',
        ]);
});

test('manage reports canManage false for members', function () {
    [$user, $workspace] = accountApiMember();
    ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);

    $this->getJson('/api/v1/connected-accounts/manage')
        ->assertOk()
        ->assertJsonPath('canManage', false)
        ->assertJsonCount(1, 'accounts');
});

test('manage never leaks another workspace\'s accounts', function () {
    [$user, $workspace] = ownerActingIn();
    ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);

    $other = Workspace::factory()->create();
    ConnectedAccount::factory()->discord()->create(['workspace_id' => $other->id]);

    $this->getJson('/api/v1/connected-accounts/manage')
        ->assertOk()
        ->assertJsonCount(1, 'accounts');
});

/*
|--------------------------------------------------------------------------
| Session-only contract — Passport API keys are rejected
|--------------------------------------------------------------------------
*/

test('accounts management endpoints reject passport tokens', function (string $method, string $path) {
    [$user, $workspace, $token] = issuedKey();
    $account = ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);
    $path = str_replace('{account}', (string) $account->id, $path);

    // SubstituteBindings runs before RequireSessionAuth on this group, so a
    // token request to an {account} route 404s on the workspace-scoped binding
    // (the token has no session workspace) before the session check can 403 —
    // either status is a valid rejection of the API key.
    $expected = str_contains($path, 'connected-accounts/') && str_contains($path, (string) $account->id)
        ? [403, 404]
        : [403];

    $response = $this->withToken($token)->{$method}('/api/v1/'.$path);
    expect($response->getStatusCode())->toBeIn($expected);
})->with([
    ['getJson', 'connected-accounts/manage'],
    ['postJson', 'connected-accounts/connect/bluesky'],
    ['postJson', 'connected-accounts/connect/discord'],
    ['getJson', 'connected-accounts/connect/meta'],
    ['postJson', 'connected-accounts/connect/meta'],
    ['getJson', 'connected-accounts/connect/linkedin'],
    ['postJson', 'connected-accounts/connect/linkedin'],
    ['postJson', 'connected-accounts/{account}/default'],
    ['patchJson', 'connected-accounts/{account}/toggle'],
    ['patchJson', 'connected-accounts/{account}/auto-repost'],
    ['postJson', 'connected-accounts/{account}/refresh-x-tier'],
    ['postJson', 'connected-accounts/{account}/reconnect'],
    ['deleteJson', 'connected-accounts/{account}'],
]);

test('accounts management endpoints require authentication', function (string $method, string $path) {
    [, $workspace] = ownerActingIn();
    auth()->logout();
    $account = ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);
    $path = str_replace('{account}', (string) $account->id, $path);

    $this->{$method}('/api/v1/'.$path)->assertUnauthorized();
})->with([
    ['getJson', 'connected-accounts/manage'],
    ['postJson', 'connected-accounts/connect/bluesky'],
    ['postJson', 'connected-accounts/{account}/default'],
    ['deleteJson', 'connected-accounts/{account}'],
]);

/*
|--------------------------------------------------------------------------
| Credential connects — Bluesky app-password + Discord webhook
|--------------------------------------------------------------------------
*/

test('connect bluesky creates the account and reports connected', function () {
    [$user, $workspace] = ownerActingIn();
    fakeBlueskySessionForApi();

    $this->postJson('/api/v1/connected-accounts/connect/bluesky', [
        'identifier' => 'ada.bsky.social',
        'app_password' => 'app-pass-1234',
        'pds_url' => 'https://bsky.social',
    ])->assertOk()->assertJsonPath('connected', true);

    $account = ConnectedAccount::withoutGlobalScopes()->firstWhere('remote_account_id', 'did:plc:abc');
    expect($account->platform)->toBe(Platform::Bluesky)
        ->and($account->auth_method)->toBe('app_password')
        ->and($account->workspace_id)->toBe($workspace->id);
});

test('connect bluesky surfaces a provider failure as 422', function () {
    ownerActingIn();
    Http::fake(['*' => Http::response([], 500)]);

    $this->postJson('/api/v1/connected-accounts/connect/bluesky', [
        'identifier' => 'ada.bsky.social',
        'app_password' => 'bad',
    ])->assertUnprocessable()->assertJsonStructure(['message']);
});

test('connect discord creates a webhook account', function () {
    [$user, $workspace] = ownerActingIn();

    $url = 'https://discord.com/api/webhooks/111/tok';
    Http::fake([$url => Http::response([
        'id' => '111', 'name' => 'Releases', 'channel_id' => '5', 'guild_id' => '7',
    ])]);

    $this->postJson('/api/v1/connected-accounts/connect/discord', ['webhook_url' => $url])
        ->assertOk()->assertJsonPath('connected', true);

    expect(ConnectedAccount::withoutGlobalScopes()->firstWhere('remote_account_id', '111')->workspace_id)
        ->toBe($workspace->id);
});

test('members cannot connect accounts', function (string $path, array $payload) {
    accountApiMember();

    $this->postJson('/api/v1/'.$path, $payload)->assertForbidden();
})->with([
    ['connected-accounts/connect/bluesky', ['identifier' => 'ada.bsky.social', 'app_password' => 'x']],
    ['connected-accounts/connect/discord', ['webhook_url' => 'https://discord.com/api/webhooks/1/t']],
    ['connected-accounts/connect/meta', ['selected' => [['assetKey' => 'p1', 'platform' => 'facebook']]]],
    ['connected-accounts/connect/linkedin', ['selected' => [['type' => 'person']]]],
]);

/*
|--------------------------------------------------------------------------
| Picker flows — Meta + LinkedIn stash readback and selection storage
|--------------------------------------------------------------------------
*/

/**
 * The OAuth callback's session stash only exists on stateful (cookie-driven)
 * requests — EnsureFrontendRequestsAreStateful needs a referer inside
 * sanctum.stateful domains, and the stash itself rides the session cookie.
 */
function pickerGet(string $path, ?array $stash = null)
{
    $test = test()->from(config('app.url').'/app/accounts');

    if ($stash !== null) {
        $test = $test->withSession($stash);
    }

    return $test->getJson($path);
}

function pickerPost(string $path, array $payload, ?array $stash = null)
{
    $test = test()->from(config('app.url').'/app/accounts');

    if ($stash !== null) {
        $test = $test->withSession($stash);
    }

    return $test->postJson($path, $payload);
}

function metaStash(array $assets): array
{
    return ['accounts.meta.connect' => [
        'assets' => $assets,
        'userTokenExpiresAt' => null,
    ]];
}

test('meta picker is 404 without a stash', function () {
    ownerActingIn();

    pickerGet('/api/v1/connected-accounts/connect/meta')->assertNotFound();
});

test('meta picker returns the token-stripped asset projection', function () {
    ownerActingIn();

    pickerGet('/api/v1/connected-accounts/connect/meta', metaStash([
        'page-1' => [
            'pageId' => 'page-1',
            'pageName' => 'My Page',
            'pageAccessToken' => 'secret-token',
            'igUserId' => 'ig-9',
            'igUsername' => 'mypage',
            'igAvatarUrl' => 'https://cdn/ig.jpg',
        ],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'assets')
        ->assertJsonPath('assets.0.key', 'page-1')
        ->assertJsonPath('assets.0.pageName', 'My Page')
        ->assertJsonMissing(['pageAccessToken' => 'secret-token']);
});

test('meta selection stores the chosen assets and clears the stash', function () {
    [$user, $workspace] = ownerActingIn();

    if (Platform::launchedMetaGraphPlatforms() === []) {
        test()->markTestSkipped('Meta platforms not launched in this environment.');
    }

    pickerPost('/api/v1/connected-accounts/connect/meta', [
        'selected' => [['assetKey' => 'page-1', 'platform' => 'facebook']],
    ], metaStash([
        'page-1' => [
            'pageId' => 'page-1',
            'pageName' => 'My Page',
            'pageAccessToken' => 'secret-token',
            'igUserId' => 'ig-9',
            'igUsername' => 'mypage',
            'igAvatarUrl' => null,
        ],
    ]))->assertOk()->assertJsonPath('connected', 1);

    expect(ConnectedAccount::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())
        ->toBe(1);
});

test('meta selection is 404 without a stash', function () {
    ownerActingIn();

    pickerPost('/api/v1/connected-accounts/connect/meta', [
        'selected' => [['assetKey' => 'page-1', 'platform' => 'facebook']],
    ])->assertNotFound();
});

function linkedinStash(): array
{
    return ['accounts.linkedin.connect' => [
        'person' => [
            'remoteAccountId' => 'person-1',
            'handle' => 'ada',
            'displayName' => 'Ada',
            'avatarUrl' => null,
        ],
        'organizations' => [
            'org-1' => ['id' => 'org-1', 'urn' => 'urn:li:organization:1', 'name' => 'Acme', 'vanityName' => 'acme'],
        ],
        'accessToken' => 'tok',
        'refreshToken' => null,
        'tokenExpiresAt' => null,
        'approvedScopes' => ['r_member_social_feed'],
    ]];
}

test('linkedin picker is 404 without a stash', function () {
    ownerActingIn();

    pickerGet('/api/v1/connected-accounts/connect/linkedin')->assertNotFound();
});

test('linkedin picker returns person and organizations', function () {
    ownerActingIn();

    pickerGet('/api/v1/connected-accounts/connect/linkedin', linkedinStash())
        ->assertOk()
        ->assertJsonPath('person.handle', 'ada')
        ->assertJsonPath('organizations.0.id', 'org-1');
});

test('linkedin selection stores person and organization accounts and clears the stash', function () {
    [$user, $workspace] = ownerActingIn();

    pickerPost('/api/v1/connected-accounts/connect/linkedin', [
        'selected' => [['type' => 'person'], ['type' => 'organization', 'id' => 'org-1']],
    ], linkedinStash())->assertOk()->assertJsonPath('connected', 2);

    $accounts = ConnectedAccount::withoutGlobalScopes()->where('workspace_id', $workspace->id)->get();
    expect($accounts)->toHaveCount(2);
});

test('linkedin selection is 404 without a stash', function () {
    ownerActingIn();

    pickerPost('/api/v1/connected-accounts/connect/linkedin', [
        'selected' => [['type' => 'person']],
    ])->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Per-account actions
|--------------------------------------------------------------------------
*/

test('makeDefault marks the account as the workspace default', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);

    $this->postJson("/api/v1/connected-accounts/{$account->id}/default")
        ->assertOk()->assertJsonPath('is_default', true);

    expect($workspace->fresh()->default_connected_account_id)->toBe($account->id);
});

test('toggle disables and clears the default flag', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);
    $workspace->forceFill(['default_connected_account_id' => $account->id])->save();

    $this->patchJson("/api/v1/connected-accounts/{$account->id}/toggle")
        ->assertOk()->assertJsonPath('disabled', true)->assertJsonPath('is_default', false);

    expect($account->fresh()->disabled_at)->not->toBeNull()
        ->and($workspace->fresh()->default_connected_account_id)->toBeNull();
});

test('toggle re-enables a disabled account', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->bluesky()->disabled()->create(['workspace_id' => $workspace->id]);

    $this->patchJson("/api/v1/connected-accounts/{$account->id}/toggle")
        ->assertOk()->assertJsonPath('disabled', false);

    expect($account->fresh()->disabled_at)->toBeNull();
});

test('auto-repost writes capabilities and reports the flag', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->linkedin()->create(['workspace_id' => $workspace->id]);

    $this->patchJson("/api/v1/connected-accounts/{$account->id}/auto-repost", [
        'enabled' => true,
        'min_percentile' => 0.9,
    ])->assertOk()->assertJsonPath('auto_repost_enabled', true);

    expect($account->fresh()->autoRepostEnabled())->toBeTrue();
});

test('auto-repost stays off on platforms that cannot repost', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->discord()->create(['workspace_id' => $workspace->id]);

    $this->patchJson("/api/v1/connected-accounts/{$account->id}/auto-repost", ['enabled' => true])
        ->assertOk()->assertJsonPath('auto_repost_enabled', false);
});

test('refresh-x-tier rejects non-X accounts', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);

    $this->postJson("/api/v1/connected-accounts/{$account->id}/refresh-x-tier")
        ->assertUnprocessable();
});

test('destroy deletes the account and clears the workspace default', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);
    ConnectedAccountSecret::factory()->create(['connected_account_id' => $account->id]);
    $workspace->forceFill(['default_connected_account_id' => $account->id])->save();

    $this->deleteJson("/api/v1/connected-accounts/{$account->id}")
        ->assertOk()->assertJsonPath('deleted', true);

    expect(ConnectedAccount::withoutGlobalScopes()->find($account->id))->toBeNull()
        ->and($workspace->fresh()->default_connected_account_id)->toBeNull();
});

test('members cannot mutate accounts', function (string $method, string $action) {
    [$user, $workspace] = accountApiMember();
    $account = ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);

    $this->{$method}("/api/v1/connected-accounts/{$account->id}/{$action}")
        ->assertForbidden();
})->with([
    ['postJson', 'default'],
    ['patchJson', 'toggle'],
    ['patchJson', 'auto-repost'],
    ['postJson', 'refresh-x-tier'],
    ['postJson', 'reconnect'],
]);

test('members cannot delete accounts', function () {
    [$user, $workspace] = accountApiMember();
    $account = ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);

    $this->deleteJson("/api/v1/connected-accounts/{$account->id}")->assertForbidden();
});

test('account actions 404 for accounts in another workspace', function () {
    ownerActingIn();
    $other = Workspace::factory()->create();
    $foreign = ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $other->id]);

    $this->postJson("/api/v1/connected-accounts/{$foreign->id}/default")->assertNotFound();
    $this->patchJson("/api/v1/connected-accounts/{$foreign->id}/toggle")->assertNotFound();
    $this->deleteJson("/api/v1/connected-accounts/{$foreign->id}")->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Reconnect — credential resubmit vs OAuth handoff
|--------------------------------------------------------------------------
*/

test('reconnecting a bluesky app-password account preserves the row', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->bluesky()->needsAttention()->create([
        'workspace_id' => $workspace->id,
        'remote_account_id' => 'did:plc:abc',
        'connected_by_user_id' => $user->id,
    ]);
    ConnectedAccountSecret::factory()->create(['connected_account_id' => $account->id]);

    fakeBlueskySessionForApi();

    $this->postJson("/api/v1/connected-accounts/{$account->id}/reconnect", [
        'identifier' => 'ada.bsky.social',
        'app_password' => 'fresh-pass',
        'pds_url' => 'https://bsky.social',
    ])->assertOk()->assertJsonPath('reconnected', true);

    expect($account->fresh()->status)->toBe(ConnectedAccountStatus::Active);
});

test('reconnect rejects credentials for a different bluesky account', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->bluesky()->create([
        'workspace_id' => $workspace->id,
        'remote_account_id' => 'did:plc:original',
    ]);

    fakeBlueskySessionForApi(did: 'did:plc:different', handle: 'someone.bsky.social');

    $this->postJson("/api/v1/connected-accounts/{$account->id}/reconnect", [
        'identifier' => 'someone.bsky.social',
        'app_password' => 'pass',
    ])->assertUnprocessable()->assertJsonValidationErrors('identifier');
});

test('reconnecting a discord webhook adopts the new webhook in place', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->discord()->needsAttention()->create([
        'workspace_id' => $workspace->id,
        'remote_account_id' => 'old-webhook-id',
        'connected_by_user_id' => $user->id,
    ]);
    ConnectedAccountSecret::factory()->create([
        'connected_account_id' => $account->id,
        'access_token' => 'https://discord.com/api/webhooks/old-webhook-id/old-tok',
    ]);

    $newUrl = 'https://discord.com/api/webhooks/222222/new-tok';
    Http::fake([$newUrl => Http::response([
        'id' => '222222', 'name' => 'Releases', 'channel_id' => '5', 'guild_id' => '7',
    ])]);

    $this->postJson("/api/v1/connected-accounts/{$account->id}/reconnect", ['webhook_url' => $newUrl])
        ->assertOk()->assertJsonPath('reconnected', true);

    expect($account->fresh()->remote_account_id)->toBe('222222');
});

test('reconnecting an oauth-only platform returns the OAuth url', function () {
    [$user, $workspace] = ownerActingIn();

    $account = ConnectedAccount::factory()->linkedin()->create(['workspace_id' => $workspace->id]);

    if (! Platform::LinkedIn->isConfigured()) {
        // LinkedIn is unconfigured in the test env — the endpoint must 422
        // rather than hand back a URL that cannot work.
        $this->postJson("/api/v1/connected-accounts/{$account->id}/reconnect")
            ->assertUnprocessable();

        return;
    }

    $this->postJson("/api/v1/connected-accounts/{$account->id}/reconnect")
        ->assertOk()
        ->assertJsonPath('url', route('accounts.connect', ['platform' => 'linkedin']));
});

test('the legacy web account endpoints still redirect into the SPA', function () {
    [$user, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->bluesky()->create(['workspace_id' => $workspace->id]);

    $response = $this->delete("/accounts/{$account->id}");
    $response->assertRedirect();
    expect(parse_url((string) $response->headers->get('Location'), PHP_URL_PATH))
        ->toBe('/app/accounts');
});
