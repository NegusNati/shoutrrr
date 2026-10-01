<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Notification;

// ---- auth boundary ----------------------------------------------------

test('workspace settings endpoints require authentication', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'overview' => ['get', '/api/v1/settings/workspace'],
    'update' => ['patch', '/api/v1/settings/workspace'],
    'destroy' => ['delete', '/api/v1/settings/workspace'],
    'timezone' => ['put', '/api/v1/settings/workspace/timezone'],
    'members' => ['get', '/api/v1/settings/workspace/members'],
    'invite' => ['post', '/api/v1/settings/workspace/invite'],
    'leave' => ['post', '/api/v1/settings/workspace/leave'],
    'transfer' => ['post', '/api/v1/settings/workspace/transfer'],
    'api-keys index' => ['get', '/api/v1/settings/workspace/api-keys'],
    'api-keys store' => ['post', '/api/v1/settings/workspace/api-keys'],
    'subscription' => ['get', '/api/v1/settings/workspace/subscription'],
]);

test('workspace settings endpoints reject api-key auth', function (string $method, string $uri) {
    [, , $token] = issuedKey();

    $this->withToken($token)->json($method, $uri)->assertForbidden();
})->with([
    'overview' => ['get', '/api/v1/settings/workspace'],
    'members' => ['get', '/api/v1/settings/workspace/members'],
    'invite' => ['post', '/api/v1/settings/workspace/invite'],
    'api-keys index' => ['get', '/api/v1/settings/workspace/api-keys'],
]);

// ---- overview ---------------------------------------------------------

test('workspace overview returns the workspace and capability flags', function () {
    [, $workspace] = ownerActingIn();

    $this->getJson('/api/v1/settings/workspace')
        ->assertOk()
        ->assertJsonPath('workspace.id', $workspace->id)
        ->assertJsonPath('workspace.name', $workspace->name)
        ->assertJsonPath('canManage', true)
        ->assertJsonPath('isOwner', true)
        ->assertJsonPath('canDelete', false) // only workspace
        ->assertJsonStructure(['workspace', 'canManage', 'isOwner', 'canDelete', 'timezone', 'timezones']);
});

test('workspace update renames it', function () {
    [, $workspace] = ownerActingIn();

    $this->patchJson('/api/v1/settings/workspace', ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('workspace.name', 'Renamed');

    expect($workspace->fresh()->name)->toBe('Renamed');
});

test('workspace update validates the name', function () {
    ownerActingIn();

    $this->patchJson('/api/v1/settings/workspace', ['name' => ''])
        ->assertUnprocessable();
});

test('a member without settings permission cannot update the workspace', function () {
    [$user] = ownerActingIn();
    $member = User::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $user->current_workspace_id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $member->forceFill(['current_workspace_id' => $user->current_workspace_id])->save();
    $this->actingAs($member);

    $this->patchJson('/api/v1/settings/workspace', ['name' => 'Nope'])
        ->assertForbidden();
});

test('timezone updates the posting schedule', function () {
    [, $workspace] = ownerActingIn();

    $this->putJson('/api/v1/settings/workspace/timezone', ['timezone' => 'Africa/Addis_Ababa'])
        ->assertOk()
        ->assertJsonPath('timezone', 'Africa/Addis_Ababa');

    expect($workspace->postingSchedule()->first()?->timezone)->toBe('Africa/Addis_Ababa');
});

// ---- members + invitations --------------------------------------------

test('members lists members and pending invitations', function () {
    [$user, $workspace] = ownerActingIn();
    WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'invited_by' => $user->id,
        'email' => 'pending@example.com',
    ]);

    $this->getJson('/api/v1/settings/workspace/members')
        ->assertOk()
        ->assertJsonPath('canManage', true)
        ->assertJsonStructure(['members', 'pendingInvitations', 'canManage', 'availableRoles'])
        ->assertJsonPath('members.0.user_id', $user->id)
        ->assertJsonPath('pendingInvitations.0.email', 'pending@example.com');
});

test('invite creates a pending invitation and notifies', function () {
    Notification::fake();
    [, $workspace] = ownerActingIn();

    $this->postJson('/api/v1/settings/workspace/invite', [
        'email' => 'new@example.com',
        'role' => 'member',
    ])->assertCreated()->assertJsonPath('invitation.email', 'new@example.com');

    expect(
        $workspace->invitations()->where('email', 'new@example.com')->pending()->exists(),
    )->toBeTrue();
});

test('invite rejects an email that is already a member', function () {
    [$user] = ownerActingIn();

    $this->postJson('/api/v1/settings/workspace/invite', [
        'email' => $user->email,
        'role' => 'member',
    ])->assertUnprocessable();
});

test('updateMemberRole changes a member role', function () {
    [, $workspace] = ownerActingIn();
    $membership = WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'role' => 'member',
    ]);

    $this->patchJson("/api/v1/settings/workspace/members/{$membership->id}", ['role' => 'admin'])
        ->assertOk();

    expect($membership->fresh()->role->value)->toBe('admin');
});

test('updateMemberRole rejects changing your own role', function () {
    [$user, $workspace] = ownerActingIn();
    $membership = $user->getMembershipForWorkspace($workspace->id);

    $this->patchJson("/api/v1/settings/workspace/members/{$membership->id}", ['role' => 'admin'])
        ->assertUnprocessable();
});

test('removeMember deletes the membership', function () {
    [, $workspace] = ownerActingIn();
    $member = User::factory()->create();
    $membership = WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->deleteJson("/api/v1/settings/workspace/members/{$membership->id}")
        ->assertOk()->assertJsonPath('deleted', true);

    expect($workspace->members()->where('user_id', $member->id)->exists())->toBeFalse();
});

test('removeMember refuses to remove the owner', function () {
    [$user, $workspace] = ownerActingIn();
    $membership = $user->getMembershipForWorkspace($workspace->id);

    $this->deleteJson("/api/v1/settings/workspace/members/{$membership->id}")
        ->assertUnprocessable();
});

test('cancelInvitation deletes a pending invitation', function () {
    [$user, $workspace] = ownerActingIn();
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'invited_by' => $user->id,
    ]);

    $this->deleteJson("/api/v1/settings/workspace/invitations/{$invitation->id}")
        ->assertOk();

    expect($workspace->invitations()->find($invitation->id))->toBeNull();
});

test('cross-workspace members are not reachable', function () {
    ownerActingIn();
    $other = Workspace::factory()->create();
    $membership = WorkspaceMembership::factory()->create(['workspace_id' => $other->id]);

    $this->patchJson("/api/v1/settings/workspace/members/{$membership->id}", ['role' => 'admin'])
        ->assertNotFound();
    $this->deleteJson("/api/v1/settings/workspace/members/{$membership->id}")
        ->assertNotFound();
});

// ---- leave / destroy / transfer ----------------------------------------

test('a sole owner with other members cannot leave', function () {
    [, $workspace] = ownerActingIn();
    WorkspaceMembership::factory()->create(['workspace_id' => $workspace->id, 'role' => 'member']);

    $this->postJson('/api/v1/settings/workspace/leave')->assertUnprocessable();
});

test('a member can leave and lands on their next workspace', function () {
    [$owner] = ownerActingIn();
    $member = User::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $owner->current_workspace_id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $own = Workspace::factory()->create(['owner_id' => $member->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $own->id,
        'user_id' => $member->id,
        'role' => 'owner',
    ]);
    $member->forceFill(['current_workspace_id' => $owner->current_workspace_id])->save();
    $this->actingAs($member);

    $this->postJson('/api/v1/settings/workspace/leave')
        ->assertOk()
        ->assertJsonPath('left', true)
        ->assertJsonPath('next_workspace_id', $own->id);
});

test('a non-owner cannot destroy the workspace', function () {
    [$owner] = ownerActingIn();
    $member = User::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $owner->current_workspace_id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $member->forceFill(['current_workspace_id' => $owner->current_workspace_id])->save();
    $this->actingAs($member);

    $this->deleteJson('/api/v1/settings/workspace')->assertForbidden();
});

test('the only workspace cannot be deleted', function () {
    ownerActingIn();

    $this->deleteJson('/api/v1/settings/workspace')->assertUnprocessable();
});

test('destroy deletes the workspace and reassigns members', function () {
    [$user, $workspace] = ownerActingIn();
    $second = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $second->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);

    $this->deleteJson('/api/v1/settings/workspace')
        ->assertOk()->assertJsonPath('deleted', true);

    expect(Workspace::find($workspace->id))->toBeNull();
    expect($user->fresh()->current_workspace_id)->toBe($second->id);
});

test('transferOwnership promotes the target and demotes the owner', function () {
    [$user, $workspace] = ownerActingIn();
    $member = User::factory()->create();
    $membership = WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->postJson('/api/v1/settings/workspace/transfer', ['membership_id' => $membership->id])
        ->assertOk()->assertJsonPath('transferred', true);

    expect($membership->fresh()->role->value)->toBe('owner');
    expect($user->getMembershipForWorkspace($workspace->id)->role->value)->toBe('admin');
    expect($workspace->fresh()->owner_id)->toBe($member->id);
});

test('a non-owner cannot transfer ownership', function () {
    [$owner] = ownerActingIn();
    $member = User::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $owner->current_workspace_id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $member->forceFill(['current_workspace_id' => $owner->current_workspace_id])->save();
    $this->actingAs($member);

    $this->postJson('/api/v1/settings/workspace/transfer', ['membership_id' => $member->getMembershipForWorkspace($owner->current_workspace_id)->id])
        ->assertForbidden();
});

// ---- api keys ----------------------------------------------------------

test('api keys can be listed, created and revoked', function () {
    [, $workspace] = ownerActingIn();

    $this->getJson('/api/v1/settings/workspace/api-keys')
        ->assertOk()->assertJsonCount(0, 'apiKeys');

    $created = $this->postJson('/api/v1/settings/workspace/api-keys', [
        'name' => 'CI bot',
        'scope' => 'write',
    ])->assertCreated()
        ->assertJsonPath('apiKey.name', 'CI bot')
        ->assertJsonStructure(['apiKey' => ['id', 'name', 'last_four', 'scope'], 'plainTextApiKey']);

    $id = $created->json('apiKey.id');

    $this->getJson('/api/v1/settings/workspace/api-keys')
        ->assertJsonCount(1, 'apiKeys');

    $this->deleteJson("/api/v1/settings/workspace/api-keys/{$id}")
        ->assertOk()->assertJsonPath('revoked', true);
});

test('api keys are hidden from members without manage permission', function () {
    [$owner] = ownerActingIn();
    $member = User::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $owner->current_workspace_id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $member->forceFill(['current_workspace_id' => $owner->current_workspace_id])->save();
    $this->actingAs($member);

    $this->getJson('/api/v1/settings/workspace/api-keys')->assertForbidden();
});

// ---- subscription -------------------------------------------------------

test('subscription 404s when subscriptions are disabled', function () {
    ownerActingIn();

    $this->getJson('/api/v1/settings/workspace/subscription')->assertNotFound();
});
