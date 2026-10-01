<?php

use App\Enums\WorkspaceRole;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\Fluent\AssertableJson as Assert;

beforeEach(function () {
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys', ['--no-interaction' => true]);
    }

    // No personal access client is seeded here on purpose: ApiKeyManager
    // provisions one lazily on first use, so this test also guards that a fresh
    // environment can mint API keys out of the box.
});

/**
 * @return array{0: User, 1: Workspace}
 */
function ownerInWorkspaceForApiKeys(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $workspace->members()->create(['user_id' => $user->id, 'role' => 'owner']);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $workspace->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );

    return [$user, $workspace];
}

test('an owner can create an api key and sees the plaintext once', function () {
    [$user, $workspace] = ownerInWorkspaceForApiKeys();

    $response = $this->actingAs($user)->postJson('/api/v1/settings/workspace/api-keys', [
        'name' => 'CI bot',
        'scope' => 'write',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('api_keys', ['workspace_id' => $workspace->id, 'name' => 'CI bot', 'scope' => 'write']);
    expect($response->json('plainTextApiKey'))->toBeString()->not->toBeEmpty();
});

test('a member without settings.manage cannot create a key', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $workspace->members()->create(['user_id' => $user->id, 'role' => 'member']);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $workspace->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );

    $this->actingAs($user)->postJson('/api/v1/settings/workspace/api-keys', ['name' => 'x', 'scope' => 'read'])
        ->assertForbidden();
});

test('an owner can revoke a key', function () {
    [$user, $workspace] = ownerInWorkspaceForApiKeys();
    $apiKey = ApiKey::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $this->actingAs($user)->deleteJson("/api/v1/settings/workspace/api-keys/{$apiKey->id}")->assertOk();

    expect($apiKey->fresh()->revoked_at)->not->toBeNull();
});

test('keys from another workspace are not manageable', function () {
    [$user] = ownerInWorkspaceForApiKeys();
    $foreign = ApiKey::factory()->create(); // other workspace

    $this->actingAs($user)->deleteJson("/api/v1/settings/workspace/api-keys/{$foreign->id}")->assertNotFound();
});

test('the api-keys settings page renders for an owner', function () {
    [$user, $workspace] = ownerInWorkspaceForApiKeys();
    ApiKey::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'name' => 'Existing']);

    $this->actingAs($user)->getJson('/api/v1/settings/workspace/api-keys')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json->has('apiKeys', 1)->etc());
});
