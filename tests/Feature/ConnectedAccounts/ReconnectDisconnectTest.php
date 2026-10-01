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

function ownerWithWorkspace(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    test()->actingAs($user);

    return [$user, $workspace];
}

test('reconnecting a bluesky account preserves its id and clears needs_attention', function () {
    [$user, $workspace] = ownerWithWorkspace();

    $account = ConnectedAccount::factory()->bluesky()->needsAttention()->create([
        'workspace_id' => $workspace->id,
        'remote_account_id' => 'did:plc:abc',
        'connected_by_user_id' => $user->id,
    ]);
    ConnectedAccountSecret::factory()->create(['connected_account_id' => $account->id]);

    Http::fake([
        '*xrpc/com.atproto.server.createSession' => Http::response([
            'did' => 'did:plc:abc',
            'handle' => 'ada.bsky.social',
            'accessJwt' => 'a',
            'refreshJwt' => 'r',
        ]),
        '*xrpc/app.bsky.actor.getProfile*' => Http::response([
            'did' => 'did:plc:abc',
            'handle' => 'ada.bsky.social',
            'displayName' => 'Ada',
        ]),
    ]);

    test()->postJson("/api/v1/connected-accounts/{$account->id}/reconnect", [
        'identifier' => 'ada.bsky.social',
        'app_password' => 'fresh-pass',
        'pds_url' => 'https://bsky.social',
    ])->assertOk()->assertJsonPath('reconnected', true);

    $fresh = $account->fresh();
    expect($fresh->id)->toBe($account->id)
        ->and($fresh->status)->toBe(ConnectedAccountStatus::Active)
        ->and(ConnectedAccount::withoutGlobalScopes()->count())->toBe(1);
});

test('reconnect is rejected when the submitted credentials resolve to a different account', function () {
    [$user, $workspace] = ownerWithWorkspace();
    $account = ConnectedAccount::factory()->bluesky()->create([
        'workspace_id' => $workspace->id,
        'remote_account_id' => 'did:plc:original',
    ]);

    Http::fake([
        '*xrpc/com.atproto.server.createSession' => Http::response([
            'did' => 'did:plc:different',
            'handle' => 'someone.bsky.social',
            'accessJwt' => 'a',
        ]),
        '*xrpc/app.bsky.actor.getProfile*' => Http::response([
            'did' => 'did:plc:different',
            'handle' => 'someone.bsky.social',
        ]),
    ]);

    test()->postJson("/api/v1/connected-accounts/{$account->id}/reconnect", [
        'identifier' => 'someone.bsky.social',
        'app_password' => 'pass',
    ])->assertUnprocessable()->assertJsonValidationErrors('identifier');
});

test('reconnecting a discord webhook adopts a recreated webhook onto the same account in place', function () {
    [$user, $workspace] = ownerWithWorkspace();

    $account = ConnectedAccount::factory()->discord()->needsAttention()->create([
        'workspace_id' => $workspace->id,
        'remote_account_id' => 'old-webhook-id',
        'handle' => 'Old name',
        'connected_by_user_id' => $user->id,
    ]);
    ConnectedAccountSecret::factory()->create([
        'connected_account_id' => $account->id,
        'access_token' => 'https://discord.com/api/webhooks/old-webhook-id/old-tok',
    ]);

    // The user deleted the old webhook and pasted a freshly created one, which
    // Discord hands back with a brand-new id.
    $newUrl = 'https://discord.com/api/webhooks/222222/new-tok';
    Http::fake([$newUrl => Http::response([
        'id' => '222222', 'name' => 'Releases', 'channel_id' => '5', 'guild_id' => '7',
    ])]);

    test()->postJson("/api/v1/connected-accounts/{$account->id}/reconnect", ['webhook_url' => $newUrl])
        ->assertOk()
        ->assertJsonPath('reconnected', true);

    $fresh = $account->fresh();
    expect($fresh->id)->toBe($account->id)
        ->and($fresh->remote_account_id)->toBe('222222')
        ->and($fresh->handle)->toBe('Releases')
        ->and($fresh->status)->toBe(ConnectedAccountStatus::Active)
        ->and($fresh->secret->access_token)->toBe($newUrl)
        ->and(ConnectedAccount::withoutGlobalScopes()->count())->toBe(1);
});

test('reconnecting a discord webhook requires a webhook url', function () {
    [$user, $workspace] = ownerWithWorkspace();
    $account = ConnectedAccount::factory()->discord()->create([
        'workspace_id' => $workspace->id,
        'connected_by_user_id' => $user->id,
    ]);

    test()->postJson("/api/v1/connected-accounts/{$account->id}/reconnect", [])
        ->assertJsonValidationErrors('webhook_url');
});

test('reconnecting a discord webhook with an invalid url flashes an error and changes nothing', function () {
    [$user, $workspace] = ownerWithWorkspace();
    Http::fake();

    $account = ConnectedAccount::factory()->discord()->needsAttention()->create([
        'workspace_id' => $workspace->id,
        'remote_account_id' => 'keep-me',
        'connected_by_user_id' => $user->id,
    ]);

    test()->postJson("/api/v1/connected-accounts/{$account->id}/reconnect", [
        'webhook_url' => 'https://evil.com/api/webhooks/1/t',
    ])->assertUnprocessable();

    expect($account->fresh()->remote_account_id)->toBe('keep-me')
        ->and($account->fresh()->status)->toBe(ConnectedAccountStatus::NeedsAttention);
});

test('reconnecting an OAuth account is rejected — the SPA links to the provider flow instead', function () {
    [$user, $workspace] = ownerWithWorkspace();

    $account = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::Facebook->value,
        'connected_by_user_id' => $user->id,
    ]);
    ConnectedAccountSecret::factory()->create(['connected_account_id' => $account->id]);

    test()->postJson("/api/v1/connected-accounts/{$account->id}/reconnect")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'OAuth accounts reconnect through the provider flow.');
});

test('disconnect removes both the account and its secret row', function () {
    [$user, $workspace] = ownerWithWorkspace();
    $account = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'connected_by_user_id' => $user->id,
    ]);
    ConnectedAccountSecret::factory()->create(['connected_account_id' => $account->id]);

    test()->deleteJson("/api/v1/connected-accounts/{$account->id}")
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect(ConnectedAccount::withoutGlobalScopes()->find($account->id))->toBeNull()
        ->and(ConnectedAccountSecret::find($account->id))->toBeNull();
});

test('a member cannot disconnect an account', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $account = ConnectedAccount::factory()->create(['workspace_id' => $workspace->id]);

    $member = User::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);
    $member->forceFill(['current_workspace_id' => $workspace->id])->save();

    test()->actingAs($member)->deleteJson("/api/v1/connected-accounts/{$account->id}")->assertForbidden();
});

test('an account from another workspace is not found scoped out', function () {
    ownerWithWorkspace();
    $otherWorkspace = Workspace::factory()->create();
    $foreign = ConnectedAccount::factory()->create(['workspace_id' => $otherWorkspace->id]);

    test()->deleteJson("/api/v1/connected-accounts/{$foreign->id}")->assertNotFound();
});
