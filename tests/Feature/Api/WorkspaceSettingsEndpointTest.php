<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\PostingSchedule;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;

test('workspace overview returns payload for bound workspace', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->getJson('/api/v1/settings/workspace')
        ->assertOk()
        ->assertJsonStructure([
            'workspace' => ['id', 'name', 'slug', 'logo', 'owner_id'],
            'canManage',
            'isOwner',
            'canDelete',
            'deleteDisabledReason',
            'timezone',
            'timezones',
        ])
        ->assertJsonPath('workspace.id', $workspace->id);
});

test('workspace update renames the workspace', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->patchJson('/api/v1/settings/workspace', ['name' => 'New Name'])
        ->assertOk();

    expect($workspace->fresh()->name)->toBe('New Name');
});

test('workspace timezone saves onto posting schedule', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->putJson('/api/v1/settings/workspace/timezone', ['timezone' => 'Africa/Addis_Ababa'])
        ->assertOk();

    expect(PostingSchedule::query()->where('workspace_id', $workspace->id)->value('timezone'))
        ->toBe('Africa/Addis_Ababa');
});

test('members show lists members and pending invitations', function () {
    [$user, $workspace, $token] = issuedKey();

    $response = $this->withToken($token)
        ->getJson('/api/v1/settings/workspace/members')
        ->assertOk()
        ->assertJsonStructure([
            'members',
            'pendingInvitations',
            'canManage',
            'availableRoles',
        ]);

    expect(collect($response->json('members'))->pluck('user_id'))
        ->toContain($user->id);
});

test('invite creates a pending invitation and emails it', function () {
    [, $workspace, $token] = issuedKey();

    $response = $this->withToken($token)
        ->postJson('/api/v1/settings/workspace/invite', [
            'email' => 'invitee@example.com',
            'role' => 'member',
        ])
        ->assertCreated();

    expect(WorkspaceInvitation::query()
        ->where('workspace_id', $workspace->id)
        ->where('email', 'invitee@example.com')
        ->pending()
        ->exists())->toBeTrue();
});

test('invite rejects an existing member email', function () {
    [$user, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->postJson('/api/v1/settings/workspace/invite', [
            'email' => $user->email,
            'role' => 'member',
        ])
        ->assertUnprocessable();
});

test('member role update and removal', function () {
    [, $workspace, $token] = issuedKey();
    $member = User::factory()->create();
    $membership = $workspace->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->withToken($token)
        ->patchJson("/api/v1/settings/workspace/members/{$membership->id}", ['role' => 'admin'])
        ->assertOk();

    expect($membership->fresh()->role->value)->toBe('admin');

    $this->withToken($token)
        ->deleteJson("/api/v1/settings/workspace/members/{$membership->id}")
        ->assertOk();

    expect(WorkspaceMembership::query()->whereKey($membership->id)->exists())->toBeFalse();
});

test('member role update on foreign membership 404s', function () {
    [, $workspace, $token] = issuedKey();
    $other = Workspace::factory()->create();
    $member = User::factory()->create();
    $membership = $other->members()->create(['user_id' => $member->id, 'role' => 'member']);

    $this->withToken($token)
        ->patchJson("/api/v1/settings/workspace/members/{$membership->id}", ['role' => 'admin'])
        ->assertNotFound();
});

test('cancel invitation deletes it', function () {
    [$user, $workspace, $token] = issuedKey();
    $invitation = WorkspaceInvitation::factory()->for($workspace)->create(['invited_by' => $user->id]);

    $this->withToken($token)
        ->deleteJson("/api/v1/settings/workspace/invitations/{$invitation->id}")
        ->assertOk();

    expect(WorkspaceInvitation::query()->whereKey($invitation->id)->exists())->toBeFalse();
});

test('api keys list, create returns plaintext once, revoke', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->getJson('/api/v1/settings/workspace/api-keys')
        ->assertOk()
        ->assertJsonStructure(['apiKeys']);

    $response = $this->withToken($token)
        ->postJson('/api/v1/settings/workspace/api-keys', [
            'name' => 'ci-key',
            'scope' => 'read',
        ])
        ->assertCreated()
        ->assertJsonStructure(['apiKey' => ['id', 'last_four'], 'plainTextApiKey']);

    $keyId = $response->json('apiKey.id');

    $this->withToken($token)
        ->deleteJson("/api/v1/settings/workspace/api-keys/{$keyId}")
        ->assertOk();

    expect(ApiKey::query()->whereKey($keyId)->first()->revoked_at)->not->toBeNull();
});

test('api key create validates scope', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->postJson('/api/v1/settings/workspace/api-keys', [
            'name' => 'bad',
            'scope' => 'admin',
        ])
        ->assertUnprocessable();
});

test('subscription endpoint 404s when subscriptions disabled', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->getJson('/api/v1/settings/workspace/subscription')
        ->assertNotFound();
});
