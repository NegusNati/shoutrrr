<?php

use App\Enums\Platform;
use App\Models\ConnectedAccount;
use App\Models\Workspace;

test('lists connected accounts for the bound workspace only', function () {
    [, $workspace] = ownerActingIn();
    $mine = ConnectedAccount::factory()->for($workspace)->create();
    $other = ConnectedAccount::factory()->create(); // different workspace

    $response = $this->getJson('/api/v1/connected-accounts')->assertOk();

    $ids = collect($response->json('accounts'))->pluck('id');
    expect($ids)->toContain($mine->id)->not->toContain($other->id);
    expect($response->json('capabilities'))->toBeArray();
    expect($response->json('can_manage'))->toBeTrue();
});

test('toggle disables an account and clears it as the default', function () {
    [, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->for($workspace)->create();
    $workspace->forceFill(['default_connected_account_id' => $account->id])->save();

    $this
        ->patchJson("/api/v1/connected-accounts/{$account->id}/toggle")
        ->assertOk()
        ->assertJson(['disabled' => true]);

    expect($account->fresh()->isDisabled())->toBeTrue();
    expect($workspace->fresh()->default_connected_account_id)->toBeNull();
});

test('makeDefault marks the account as default for the workspace', function () {
    [, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->for($workspace)->create();

    $this
        ->postJson("/api/v1/connected-accounts/{$account->id}/default")
        ->assertOk()
        ->assertJson(['is_default' => true]);

    expect($workspace->fresh()->default_connected_account_id)->toBe($account->id);
});

test('autoRepost writes the capability without clobbering other keys', function () {
    [, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->for($workspace)->create([
        'platform' => Platform::X,
        'capabilities' => ['other_key' => 1],
    ]);

    $this
        ->patchJson("/api/v1/connected-accounts/{$account->id}/auto-repost", ['enabled' => true])
        ->assertOk()
        ->assertJson(['auto_repost_enabled' => true]);

    expect($account->fresh()->capabilities)
        ->toMatchArray(['other_key' => 1, 'auto_repost' => ['enabled' => true]]);
});

test('destroy deletes the account and clears the workspace default', function () {
    [, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->for($workspace)->create();
    $workspace->forceFill(['default_connected_account_id' => $account->id])->save();

    $this
        ->deleteJson("/api/v1/connected-accounts/{$account->id}")
        ->assertOk()
        ->assertJson(['deleted' => true]);

    expect(ConnectedAccount::find($account->id))->toBeNull();
    expect($workspace->fresh()->default_connected_account_id)->toBeNull();
});

test('management actions 404 on a foreign-workspace account', function (string $method, string $suffix) {
    [, $workspace] = ownerActingIn();
    $foreign = ConnectedAccount::factory()->for(Workspace::factory()->create())->create();

    $this
        ->json($method, "/api/v1/connected-accounts/{$foreign->id}/{$suffix}")
        ->assertNotFound();
})->with([
    ['PATCH', 'toggle'],
    ['POST', 'default'],
    ['PATCH', 'auto-repost'],
    ['POST', 'reconnect'],
    ['POST', 'refresh-x-tier'],
]);

test('destroy 404s on a foreign-workspace account', function () {
    [, $workspace] = ownerActingIn();
    $foreign = ConnectedAccount::factory()->for(Workspace::factory()->create())->create();

    $this
        ->deleteJson("/api/v1/connected-accounts/{$foreign->id}")
        ->assertNotFound();
});

test('write actions reject a read-only key', function () {
    [, $workspace, $token] = issuedKey('read');
    $account = ConnectedAccount::factory()->for($workspace)->create();

    $this->withToken($token)
        ->patchJson("/api/v1/connected-accounts/{$account->id}/toggle")
        ->assertForbidden();

    $this->withToken($token)
        ->postJson('/api/v1/connected-accounts/connect/bluesky', [
            'identifier' => 'a.bsky.social',
            'app_password' => 'x',
        ])
        ->assertForbidden();
});

test('connect meta pending is 404 without a stashed OAuth flow', function () {
    [, $workspace] = ownerActingIn();
    $this
        ->getJson('/api/v1/connected-accounts/connect/meta')
        ->assertNotFound();

    $this
        ->postJson('/api/v1/connected-accounts/connect/meta', ['selected' => [['assetKey' => 'a', 'platform' => 'facebook']]])
        ->assertNotFound();
});

test('connect linkedin pending is 404 without a stashed OAuth flow', function () {
    [, $workspace] = ownerActingIn();
    $this
        ->getJson('/api/v1/connected-accounts/connect/linkedin')
        ->assertNotFound();

    $this
        ->postJson('/api/v1/connected-accounts/connect/linkedin', ['selected' => [['type' => 'person']]])
        ->assertNotFound();
});
