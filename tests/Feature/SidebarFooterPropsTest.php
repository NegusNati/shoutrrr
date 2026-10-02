<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\AppVersion;
use App\Support\CommunityStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;

function actingOwnerInWorkspace(): User
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $workspace->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    Context::add('workspace_id', $workspace->id);
    test()->actingAs($user);

    return $user;
}

afterEach(fn () => AppVersion::fake(null));

test('cloud defers a billing prop for billing managers and no community prop', function () {
    config(['subscriptions.enabled' => true]);
    actingOwnerInWorkspace();

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('billing.subscribed', false)
        ->assertJsonPath('billing.manageUrl', route('billing.index'))
        ->assertJsonPath('community', null)
        ->assertJsonPath('updateAvailable', false);
});

test('members without billing.manage do not receive a billing prop', function () {
    config(['subscriptions.enabled' => true]);
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Member,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $workspace->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    Context::add('workspace_id', $workspace->id);

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('billing', null);
});

test('self-hosted defers a community prop and the update flag, no billing prop', function () {
    AppVersion::fake('v1.3.0-rc.5');
    config(['subscriptions.enabled' => false]);
    config(['instance.community.repo' => 'coollabsio/shoutrrr']);
    config(['instance.community.sponsor_url' => 'https://github.com/sponsors/coollabsio']);
    Cache::put(CommunityStats::StarsCacheKey, 4210);
    Cache::put(CommunityStats::LatestOverallCacheKey, 'v99.0.0');
    actingOwnerInWorkspace();

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('billing', null)
        ->assertJsonPath('community.repoUrl', 'https://github.com/coollabsio/shoutrrr')
        ->assertJsonPath('community.sponsorUrl', 'https://github.com/sponsors/coollabsio')
        ->assertJsonPath('community.stars', 4210)
        ->assertJsonPath('updateAvailable', true);
});

test('self-hosted names the available version and links to its release', function () {
    AppVersion::fake('v1.3.0-rc.5');
    config(['subscriptions.enabled' => false]);
    config(['instance.community.repo' => 'coollabsio/shoutrrr']);
    Cache::put(CommunityStats::LatestOverallCacheKey, 'v99.0.0');
    actingOwnerInWorkspace();

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('updateAvailable', true)
        ->assertJsonPath('latestVersion', 'v99.0.0')
        ->assertJsonPath('latestReleaseUrl', 'https://github.com/coollabsio/shoutrrr/releases/tag/v99.0.0');
});

test('self-hosted up-to-date exposes no available version', function () {
    config(['subscriptions.enabled' => false]);
    Cache::put(CommunityStats::LatestOverallCacheKey, AppVersion::current());
    actingOwnerInWorkspace();

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('updateAvailable', false)
        ->assertJsonPath('latestVersion', null)
        ->assertJsonPath('latestReleaseUrl', null);
});

test('cloud never exposes an available version', function () {
    config(['subscriptions.enabled' => true]);
    Cache::put(CommunityStats::LatestOverallCacheKey, 'v99.0.0');
    actingOwnerInWorkspace();

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('updateAvailable', false)
        ->assertJsonPath('latestVersion', null)
        ->assertJsonPath('latestReleaseUrl', null);
});
