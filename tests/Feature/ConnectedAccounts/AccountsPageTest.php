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

function viewerInWorkspace(WorkspaceRole $role): User
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'handle' => '@listed',
        'capabilities' => [
            'x_premium' => true,
            'max_text_length' => 25_000,
            'max_video_duration_seconds' => 14_400,
            'verified_type' => 'blue',
            'x_subscription_tier' => 'premium',
            'x_subscription_checked_at' => '2026-07-10T12:00:00+00:00',
        ],
    ]);

    return $user;
}

test('the accounts page lists accounts and exposes capabilities and canManage to owners', function () {
    $owner = viewerInWorkspace(WorkspaceRole::Owner);

    test()->actingAs($owner)->get('/accounts')
        ->assertRedirect('/app/accounts');

    test()->actingAs($owner)->getJson('/api/v1/connected-accounts')
        ->assertOk()
        ->assertJsonPath('can_manage', true)
        ->assertJsonCount(7, 'capabilities')
        ->assertJsonCount(1, 'accounts')
        ->assertJsonPath('accounts.0.handle', '@listed')
        ->assertJsonPath('accounts.0.x_premium', true)
        ->assertJsonPath('accounts.0.x_subscription_tier', 'premium')
        ->assertJsonPath('accounts.0.x_subscription_label', 'X Premium')
        ->assertJsonPath('accounts.0.x_subscription_checked_at', '2026-07-10T12:00:00+00:00')
        ->assertJsonPath('accounts.0.max_text_length', 25_000)
        ->assertJsonPath('accounts.0.max_video_duration_seconds', 14_400)
        ->assertJsonPath('accounts.0.is_default', false)
        ->assertJson(fn ($json) => $json->missing('accounts.0.secret')->etc());
});

test('owners can refresh an X subscription tier without reconnecting', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMembership::factory()->owner()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);
    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
    $account = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => ConnectedAccountStatus::NeedsAttention->value,
        'token_expires_at' => now()->addHour(),
        'refresh_failed_at' => now(),
        'refresh_failure_reason' => 'HTTP 401: invalid token',
        'capabilities' => [
            'x_premium' => false,
            'max_text_length' => 280,
            'max_video_duration_seconds' => 140,
            'verified_type' => 'blue',
            'x_subscription_tier' => 'free',
        ],
    ]);
    ConnectedAccountSecret::factory()->create([
        'connected_account_id' => $account->id,
        'access_token' => 'stored-access-token',
    ]);

    Http::fake([
        'https://api.x.com/2/users/me*' => Http::response([
            'data' => [
                'id' => $account->remote_account_id,
                'subscription_type' => 'PremiumPlus',
                'verified_type' => 'none',
            ],
        ]),
    ]);

    test()->actingAs($owner)
        ->post(route('accounts.refresh-x-tier', $account))
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'X Premium+')
            && str_contains($message, '25000'));

    expect($account->fresh()->xSubscriptionTier())->toBe('premium_plus')
        ->and($account->fresh()->maxTextLength())->toBe(25_000)
        ->and($account->fresh()->status)->toBe(ConnectedAccountStatus::Active)
        ->and($account->fresh()->refresh_failed_at)->toBeNull()
        ->and($account->fresh()->refresh_failure_reason)->toBeNull();
});

test('a failed X tier lookup retains the existing account limit', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMembership::factory()->owner()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);
    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
    $account = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'token_expires_at' => now()->addHour(),
        'capabilities' => [
            'x_premium' => true,
            'max_text_length' => 25_000,
            'max_video_duration_seconds' => 14_400,
            'verified_type' => 'none',
            'x_subscription_tier' => 'premium',
        ],
    ]);
    ConnectedAccountSecret::factory()->create([
        'connected_account_id' => $account->id,
        'access_token' => 'stored-access-token',
    ]);

    Http::fake([
        'https://api.x.com/2/users/me*' => Http::response([], 503),
    ]);

    test()->actingAs($owner)
        ->post(route('accounts.refresh-x-tier', $account))
        ->assertRedirect()
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'existing limit was kept'));

    expect($account->fresh()->xSubscriptionTier())->toBe('premium')
        ->and($account->fresh()->maxTextLength())->toBe(25_000);
});

test('the accounts page exposes a saved custom PDS so reconnect can replay it', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMembership::factory()->owner()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);
    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

    $default = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::Bluesky,
        'handle' => '@default-pds',
        'auth_method' => 'oauth',
        'created_at' => now()->subMinute(),
    ]);
    ConnectedAccountSecret::factory()->create([
        'connected_account_id' => $default->id,
        'session' => ['pds' => 'https://bsky.social'],
    ]);

    $custom = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::Bluesky,
        'handle' => '@custom',
        'auth_method' => 'oauth',
        'created_at' => now(),
    ]);
    ConnectedAccountSecret::factory()->create([
        'connected_account_id' => $custom->id,
        'session' => ['pds' => 'https://pds.example'],
    ]);

    test()->actingAs($owner)->getJson('/api/v1/connected-accounts')
        ->assertOk()
        ->assertJsonCount(2, 'accounts')
        ->assertJsonPath('accounts.0.handle', '@custom')
        ->assertJsonPath('accounts.0.pds_url', 'https://pds.example')
        ->assertJsonPath('accounts.1.handle', '@default-pds')
        ->assertJsonPath('accounts.1.pds_url', null);
});

test('owners can set a workspace default account from the accounts page', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMembership::factory()->owner()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);
    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
    $account = ConnectedAccount::factory()->create(['workspace_id' => $workspace->id]);

    test()->actingAs($owner)
        ->post(route('accounts.default', $account))
        ->assertRedirect(route('accounts.index'));

    expect($workspace->fresh()->default_connected_account_id)->toBe($account->id);
});

test('members cannot set a workspace default account', function () {
    $member = User::factory()->create(['email_verified_at' => now()]);
    $workspace = Workspace::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);
    $member->forceFill(['current_workspace_id' => $workspace->id])->save();
    $account = ConnectedAccount::factory()->create(['workspace_id' => $workspace->id]);

    test()->actingAs($member)
        ->post(route('accounts.default', $account))
        ->assertForbidden();

    expect($workspace->fresh()->default_connected_account_id)->toBeNull();
});

test('the accounts page marks and lists the workspace default account first', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMembership::factory()->owner()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);
    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
    ConnectedAccount::factory()->create(['workspace_id' => $workspace->id, 'handle' => '@regular']);
    $default = ConnectedAccount::factory()->create(['workspace_id' => $workspace->id, 'handle' => '@default']);
    $workspace->forceFill(['default_connected_account_id' => $default->id])->save();

    test()->actingAs($owner)->getJson('/api/v1/connected-accounts')
        ->assertOk()
        ->assertJsonPath('accounts.0.id', $default->id)
        ->assertJsonPath('accounts.0.is_default', true)
        ->assertJsonPath('accounts.0.handle', '@default');
});

test('disconnecting the workspace default account clears the default', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMembership::factory()->owner()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);
    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
    $account = ConnectedAccount::factory()->create(['workspace_id' => $workspace->id]);
    $workspace->forceFill(['default_connected_account_id' => $account->id])->save();

    test()->actingAs($owner)
        ->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'));

    expect($workspace->fresh()->default_connected_account_id)->toBeNull();
});

test('members see the list but cannot manage', function () {
    $member = viewerInWorkspace(WorkspaceRole::Member);

    test()->actingAs($member)->getJson('/api/v1/connected-accounts')
        ->assertOk()
        ->assertJsonPath('can_manage', false);
});

test('the accounts page requires authentication', function () {
    test()->get('/accounts')->assertRedirect(route('login'));
});

test('the accounts index marks a linkedin page account', function () {
    [, $workspace] = ownerActingIn();

    ConnectedAccount::factory()->linkedinPage()->create(['workspace_id' => $workspace->id]);

    test()->get(route('accounts.index'))
        ->assertRedirect('/app/accounts');

    test()->getJson('/api/v1/connected-accounts')
        ->assertOk()
        ->assertJsonPath('accounts.0.is_linkedin_page', true);
});

test('get requests to account member paths return method not allowed', function () {
    $owner = viewerInWorkspace(WorkspaceRole::Owner);

    test()->actingAs($owner)
        ->get('/accounts/does-not-exist')
        ->assertMethodNotAllowed();
});
