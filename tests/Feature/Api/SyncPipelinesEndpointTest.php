<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\ConnectedAccount;
use App\Models\ConnectedAccountNativeWatch;
use App\Models\SyncPipeline;

test('index returns accounts, pipelines and tracking state for the bound workspace', function () {
    [, $workspace, $token] = issuedKey();
    $account = ConnectedAccount::factory()->for($workspace)->create();
    $otherWorkspaceAccount = ConnectedAccount::factory()->create();
    $pipeline = SyncPipeline::factory()->for($workspace)->create([
        'source_connected_account_id' => $account->id,
    ]);
    $pipeline->destinations()->sync([$account->id]);

    $response = $this->withToken($token)
        ->getJson('/api/v1/sync-pipelines')
        ->assertOk()
        ->assertJsonStructure([
            'accounts',
            'pipelines',
            'maxPipelines',
            'canCreate',
            'trackableAccounts',
            'trackedAccountIds',
            'canTrack',
            'maxTracked',
        ]);

    expect(collect($response->json('accounts'))->pluck('id'))
        ->toContain($account->id)
        ->not->toContain($otherWorkspaceAccount->id);
    expect(collect($response->json('pipelines'))->pluck('id'))
        ->toContain($pipeline->id);
});

test('create stores a pipeline with destinations', function () {
    [, $workspace, $token] = issuedKey();
    $source = ConnectedAccount::factory()->for($workspace)->create();
    $dest = ConnectedAccount::factory()->for($workspace)->create();

    $response = $this->withToken($token)
        ->postJson('/api/v1/sync-pipelines', [
            'name' => 'X to Bluesky',
            'source_connected_account_id' => $source->id,
            'destination_connected_account_ids' => [$dest->id],
        ])
        ->assertCreated()
        ->assertJsonStructure(['id', 'tracked_source']);

    $pipeline = SyncPipeline::query()->findOrFail($response->json('id'));
    expect($pipeline->destinations()->pluck('connected_accounts.id')->all())
        ->toBe([$dest->id]);
});

test('create rejects a source that is also a destination', function () {
    [, $workspace, $token] = issuedKey();
    $source = ConnectedAccount::factory()->for($workspace)->create();

    $this->withToken($token)
        ->postJson('/api/v1/sync-pipelines', [
            'name' => 'Self loop',
            'source_connected_account_id' => $source->id,
            'destination_connected_account_ids' => [$source->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['destination_connected_account_ids.0']);
});

test('create rejects an account from a foreign workspace', function () {
    [, $workspace, $token] = issuedKey();
    $source = ConnectedAccount::factory()->for($workspace)->create();
    $foreign = ConnectedAccount::factory()->create();

    $this->withToken($token)
        ->postJson('/api/v1/sync-pipelines', [
            'name' => 'Foreign dest',
            'source_connected_account_id' => $source->id,
            'destination_connected_account_ids' => [$foreign->id],
        ])
        ->assertUnprocessable();
});

test('patch toggles a pipeline', function () {
    [, $workspace, $token] = issuedKey();
    $account = ConnectedAccount::factory()->for($workspace)->create();
    $pipeline = SyncPipeline::factory()->for($workspace)->create([
        'source_connected_account_id' => $account->id,
        'enabled' => true,
    ]);

    $this->withToken($token)
        ->patchJson("/api/v1/sync-pipelines/{$pipeline->id}", ['enabled' => false])
        ->assertOk()
        ->assertJson(['enabled' => false]);

    expect($pipeline->fresh()->enabled)->toBeFalse();
});

test('patch on a foreign pipeline 404s', function () {
    [, , $token] = issuedKey();
    $pipeline = SyncPipeline::factory()->create();

    $this->withToken($token)
        ->patchJson("/api/v1/sync-pipelines/{$pipeline->id}", ['enabled' => false])
        ->assertNotFound();
});

test('remove deletes a pipeline and 404s on foreign', function () {
    [, $workspace, $token] = issuedKey();
    $mine = SyncPipeline::factory()->for($workspace)->create();
    $foreign = SyncPipeline::factory()->create();

    $this->withToken($token)
        ->deleteJson("/api/v1/sync-pipelines/{$mine->id}")
        ->assertOk();

    $this->withToken($token)
        ->deleteJson("/api/v1/sync-pipelines/{$foreign->id}")
        ->assertNotFound();

    expect(SyncPipeline::query()->whereKey($mine->id)->exists())->toBeFalse();
});

test('native tracking toggles on and off for a native-read account', function () {
    [, $workspace, $token] = issuedKey();
    $account = ConnectedAccount::factory()->for($workspace)->create([
        'platform' => Platform::X,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/sync-pipelines/native-tracking/{$account->id}")
        ->assertOk();

    expect(ConnectedAccountNativeWatch::query()->where('connected_account_id', $account->id)->exists())
        ->toBeTrue();

    $this->withToken($token)
        ->deleteJson("/api/v1/sync-pipelines/native-tracking/{$account->id}")
        ->assertOk();

    expect(ConnectedAccountNativeWatch::query()->where('connected_account_id', $account->id)->exists())
        ->toBeFalse();
});

test('native tracking rejects a platform that cannot be watched', function () {
    [, $workspace, $token] = issuedKey();
    $account = ConnectedAccount::factory()->for($workspace)->create([
        'platform' => Platform::LinkedIn,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/sync-pipelines/native-tracking/{$account->id}")
        ->assertUnprocessable();
});

test('native tracking on a foreign account 404s', function () {
    [, , $token] = issuedKey();
    $account = ConnectedAccount::factory()->create([
        'platform' => Platform::X,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/sync-pipelines/native-tracking/{$account->id}")
        ->assertNotFound();
});
