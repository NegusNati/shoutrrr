<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Enums\WorkspaceRole;
use App\Models\ConnectedAccount;
use App\Models\ConnectedAccountNativeWatch;
use App\Models\SyncPipeline;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

/*
|--------------------------------------------------------------------------
| Sync API — session+workspace scoped pipelines and native tracking
|--------------------------------------------------------------------------
|
| Mirrors Settings\SyncPipelinesController/NativeTrackingController: manage
| permission gates everything; the source account can never be a destination;
| native tracking is opt-in and plan-capped.
|
*/

function syncAccount(Workspace $workspace, array $attributes = []): ConnectedAccount
{
    return ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::X->value,
        ...$attributes,
    ]);
}

test('sync endpoints are unauthorized without credentials', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/sync'],
    'store' => ['POST', '/api/v1/sync'],
    'native tracking' => ['POST', '/api/v1/sync/native-tracking/abc'],
]);

test('api keys cannot reach the sync endpoints', function (string $method, string $uri) {
    [, , $token] = issuedKey();

    $this->withToken($token)->json($method, $uri)->assertForbidden();
})->with([
    'index' => ['GET', '/api/v1/sync'],
    'store' => ['POST', '/api/v1/sync'],
    // Note: the bound routes ({syncPipeline}, {account}) can't feature here —
    // model binding resolves before the auth middleware runs, so an
    // unresolvable id 404s regardless of credentials.
]);

test('sync index lists accounts, pipelines and tracking state', function () {
    [, $workspace] = ownerActingIn();
    $source = syncAccount($workspace, ['handle' => '@source']);
    $dest = syncAccount($workspace, ['handle' => '@dest']);
    $pipeline = SyncPipeline::factory()->create([
        'workspace_id' => $workspace->id,
        'source_connected_account_id' => $source->id,
        'name' => 'X → X',
    ]);
    $pipeline->destinations()->sync([$dest->id]);
    ConnectedAccountNativeWatch::create([
        'workspace_id' => $workspace->id,
        'connected_account_id' => $source->id,
        'enabled_at' => now(),
    ]);

    $this->getJson('/api/v1/sync')
        ->assertOk()
        ->assertJsonPath('pipelines.0.id', $pipeline->id)
        ->assertJsonPath('pipelines.0.destination_connected_account_ids.0', $dest->id)
        ->assertJsonPath('trackedAccountIds.0', $source->id)
        ->assertJsonStructure([
            'accounts' => [['id', 'platform', 'handle', 'supports_native']],
            'maxPipelines',
            'canCreate',
            'trackableAccounts',
            'canTrack',
            'maxTracked',
        ]);
});

test('sync store creates a pipeline with destinations', function () {
    [, $workspace] = ownerActingIn();
    $source = syncAccount($workspace);
    $dest = syncAccount($workspace);

    $this->postJson('/api/v1/sync', [
        'name' => 'Mirror',
        'source_connected_account_id' => $source->id,
        'destination_connected_account_ids' => [$dest->id],
    ])->assertCreated();

    $pipeline = SyncPipeline::query()->where('workspace_id', $workspace->id)->first();
    expect($pipeline)->not->toBeNull()
        ->and($pipeline->source_connected_account_id)->toBe($source->id)
        ->and($pipeline->destinations->pluck('id')->all())->toBe([$dest->id]);
});

test('sync store rejects the source account as a destination', function () {
    [, $workspace] = ownerActingIn();
    $source = syncAccount($workspace);

    $this->postJson('/api/v1/sync', [
        'name' => 'Loop',
        'source_connected_account_id' => $source->id,
        'destination_connected_account_ids' => [$source->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('destination_connected_account_ids.0');
});

test('sync store rejects accounts from another workspace', function () {
    [, $workspace] = ownerActingIn();
    $source = syncAccount($workspace);
    $foreign = syncAccount(Workspace::factory()->create());

    $this->postJson('/api/v1/sync', [
        'name' => 'Cross',
        'source_connected_account_id' => $source->id,
        'destination_connected_account_ids' => [$foreign->id],
    ])->assertUnprocessable();
});

test('sync store with track_source enables native tracking when eligible', function () {
    [, $workspace] = ownerActingIn();
    $source = syncAccount($workspace);
    $dest = syncAccount($workspace);

    $this->postJson('/api/v1/sync', [
        'name' => 'Tracked',
        'source_connected_account_id' => $source->id,
        'destination_connected_account_ids' => [$dest->id],
        'track_source' => true,
    ])->assertCreated();

    expect(
        ConnectedAccountNativeWatch::query()
            ->where('connected_account_id', $source->id)
            ->exists(),
    )->toBeTrue();
});

test('sync update toggles the enabled flag', function () {
    [, $workspace] = ownerActingIn();
    $pipeline = SyncPipeline::factory()->create([
        'workspace_id' => $workspace->id,
        'enabled' => true,
    ]);

    $this->patchJson("/api/v1/sync/{$pipeline->id}", ['enabled' => false])
        ->assertOk();

    expect($pipeline->fresh()->enabled)->toBeFalse();
});

test('sync update cannot point at another workspace pipeline', function () {
    ownerActingIn();
    $pipeline = SyncPipeline::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
    ]);

    $this->patchJson("/api/v1/sync/{$pipeline->id}", ['enabled' => false])
        ->assertNotFound();

    $this->deleteJson("/api/v1/sync/{$pipeline->id}")->assertNotFound();
});

test('sync destroy removes the pipeline', function () {
    [, $workspace] = ownerActingIn();
    $pipeline = SyncPipeline::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $this->deleteJson("/api/v1/sync/{$pipeline->id}")->assertOk();

    expect(SyncPipeline::find($pipeline->id))->toBeNull();
});

test('native tracking toggles on and off for a workspace account', function () {
    [, $workspace] = ownerActingIn();
    $account = syncAccount($workspace);

    $this->postJson("/api/v1/sync/native-tracking/{$account->id}")
        ->assertCreated();

    expect(
        ConnectedAccountNativeWatch::query()
            ->where('connected_account_id', $account->id)
            ->exists(),
    )->toBeTrue();

    $this->deleteJson("/api/v1/sync/native-tracking/{$account->id}")->assertOk();

    expect(
        ConnectedAccountNativeWatch::query()
            ->where('connected_account_id', $account->id)
            ->exists(),
    )->toBeFalse();
});

test('native tracking rejects platforms that cannot be watched', function () {
    [, $workspace] = ownerActingIn();
    // LinkedIn doesn't support native reads.
    $account = syncAccount($workspace, ['platform' => Platform::LinkedIn->value]);

    $this->postJson("/api/v1/sync/native-tracking/{$account->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account');
});

test('native tracking 404s for another workspaces account', function () {
    ownerActingIn();
    $foreign = syncAccount(Workspace::factory()->create());

    $this->postJson("/api/v1/sync/native-tracking/{$foreign->id}")
        ->assertNotFound();
});

test('members without workspace.settings.manage cannot use sync endpoints', function () {
    [, $workspace] = ownerActingIn();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->actingAs($member)->getJson('/api/v1/sync')->assertForbidden();
    $this->actingAs($member)->postJson('/api/v1/sync', [])->assertForbidden();
});
