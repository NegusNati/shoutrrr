<?php

use App\Enums\WorkspaceRole;
use App\Models\ApiKey;
use App\Models\PostingSchedule;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Notifications\WorkspaceInviteNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Workspace settings API — session-only endpoints bound to the current workspace
|--------------------------------------------------------------------------
|
| Overview, members, invitations, API keys and subscription act on the
| signed-in user's current workspace. Passport API keys are rejected by
| RequireSessionAuth before validation; session users resolve the workspace
| through ResolveApiWorkspace.
|
*/

test('workspace settings endpoints are unauthorized without credentials', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'overview' => ['GET', '/api/v1/settings/workspace'],
    'update' => ['PATCH', '/api/v1/settings/workspace'],
    'delete' => ['DELETE', '/api/v1/settings/workspace'],
    'leave' => ['POST', '/api/v1/settings/workspace/leave'],
    'transfer' => ['POST', '/api/v1/settings/workspace/transfer'],
    'timezone' => ['PUT', '/api/v1/settings/workspace/timezone'],
    'members' => ['GET', '/api/v1/settings/workspace/members'],
    'invite' => ['POST', '/api/v1/settings/workspace/invitations'],
    'member role' => ['PATCH', '/api/v1/settings/workspace/members/m1'],
    'member remove' => ['DELETE', '/api/v1/settings/workspace/members/m1'],
    'invitation cancel' => ['DELETE', '/api/v1/settings/workspace/invitations/i1'],
    'api keys' => ['GET', '/api/v1/settings/workspace/api-keys'],
    'api key create' => ['POST', '/api/v1/settings/workspace/api-keys'],
    'api key revoke' => ['DELETE', '/api/v1/settings/workspace/api-keys/k1'],
    'subscription' => ['GET', '/api/v1/settings/workspace/subscription'],
    'checkout' => ['POST', '/api/v1/settings/workspace/subscription/checkout'],
    'portal' => ['POST', '/api/v1/settings/workspace/subscription/portal'],
]);

test('api keys cannot reach the workspace settings endpoints', function (string $method, string $uri) {
    [, , $token] = issuedKey();

    $this->withToken($token)->json($method, $uri)->assertForbidden();
})->with([
    'overview' => ['GET', '/api/v1/settings/workspace'],
    'update' => ['PATCH', '/api/v1/settings/workspace'],
    'delete' => ['DELETE', '/api/v1/settings/workspace'],
    'leave' => ['POST', '/api/v1/settings/workspace/leave'],
    'transfer' => ['POST', '/api/v1/settings/workspace/transfer'],
    'timezone' => ['PUT', '/api/v1/settings/workspace/timezone'],
    'members' => ['GET', '/api/v1/settings/workspace/members'],
    'invite' => ['POST', '/api/v1/settings/workspace/invitations'],
    'api keys' => ['GET', '/api/v1/settings/workspace/api-keys'],
    'api key create' => ['POST', '/api/v1/settings/workspace/api-keys'],
    'subscription' => ['GET', '/api/v1/settings/workspace/subscription'],
    'checkout' => ['POST', '/api/v1/settings/workspace/subscription/checkout'],
    'portal' => ['POST', '/api/v1/settings/workspace/subscription/portal'],
]);

test('overview returns the workspace, permissions and timezone', function () {
    [$user, $workspace] = ownerActingIn();
    PostingSchedule::factory()->create([
        'workspace_id' => $workspace->id,
        'timezone' => 'Europe/Berlin',
    ]);

    $this->getJson('/api/v1/settings/workspace')
        ->assertOk()
        ->assertJsonPath('workspace.id', $workspace->id)
        ->assertJsonPath('workspace.name', $workspace->name)
        ->assertJsonPath('canManage', true)
        ->assertJsonPath('isOwner', true)
        ->assertJsonPath('timezone', 'Europe/Berlin')
        ->assertJsonStructure(['timezones']);
});

test('overview blocks deleting the only workspace', function () {
    ownerActingIn();

    $this->getJson('/api/v1/settings/workspace')
        ->assertOk()
        ->assertJsonPath('canDelete', false)
        ->assertJsonPath('deleteDisabledReason', 'You can’t delete your only workspace.');
});

test('overview allows deleting when another workspace exists', function () {
    [$user, $workspace] = ownerActingIn();
    $other = Workspace::factory()->create(['owner_id' => $user->id, 'is_initial' => false]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $other->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner,
    ]);
    $workspace->forceFill(['is_initial' => false])->save();

    $this->getJson('/api/v1/settings/workspace')
        ->assertOk()
        ->assertJsonPath('canDelete', true)
        ->assertJsonPath('deleteDisabledReason', null);
});

test('workspace update renames the workspace', function () {
    [$user, $workspace] = ownerActingIn();

    $this->patchJson('/api/v1/settings/workspace', ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('workspace.name', 'Renamed');

    expect($workspace->fresh()->name)->toBe('Renamed');
});

test('workspace update accepts a photo via the post + _method spoof', function () {
    [$user, $workspace] = ownerActingIn();

    $file = UploadedFile::fake()->image('logo.png', 64, 64);

    $this->post('/api/v1/settings/workspace', [
        '_method' => 'PATCH',
        'name' => $workspace->name,
        'photo' => $file,
    ])->assertOk()->assertJsonPath('workspace.name', $workspace->name);

    expect($workspace->fresh()->logo)->not->toBeNull();
});

test('workspace update validates the payload', function () {
    ownerActingIn();

    $this->patchJson('/api/v1/settings/workspace', ['name' => ''])
        ->assertStatus(422);
});

test('timezone update creates the posting schedule', function () {
    [$user, $workspace] = ownerActingIn();

    $this->putJson('/api/v1/settings/workspace/timezone', ['timezone' => 'America/New_York'])
        ->assertOk()
        ->assertJsonPath('timezone', 'America/New_York');

    expect(PostingSchedule::where('workspace_id', $workspace->id)->first()->timezone)
        ->toBe('America/New_York');
});

test('timezone update rejects an invalid timezone', function () {
    ownerActingIn();

    $this->putJson('/api/v1/settings/workspace/timezone', ['timezone' => 'Not/AZone'])
        ->assertStatus(422);
});

test('members lists members and pending invitations', function () {
    [$user, $workspace] = ownerActingIn();
    $member = User::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);
    WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'invited_by' => $user->id,
    ]);

    $this->getJson('/api/v1/settings/workspace/members')
        ->assertOk()
        ->assertJsonCount(2, 'members')
        ->assertJsonCount(1, 'pendingInvitations')
        ->assertJsonPath('canManage', true)
        ->assertJsonPath('availableRoles', ['member', 'admin']);
});

test('members reports canManage false for plain members', function () {
    [, $workspace] = ownerActingIn();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->actingAs($member)
        ->getJson('/api/v1/settings/workspace/members')
        ->assertOk()
        ->assertJsonPath('canManage', false);
});

test('invite creates a pending invitation and notifies the invitee', function () {
    Notification::fake();
    [, $workspace] = ownerActingIn();

    $this->postJson('/api/v1/settings/workspace/invitations', [
        'email' => 'new-member@example.com',
        'role' => 'member',
    ])->assertCreated()->assertJsonPath('invitation.email', 'new-member@example.com');

    expect($workspace->invitations()->where('email', 'new-member@example.com')->exists())->toBeTrue();
    Notification::assertSentOnDemand(WorkspaceInviteNotification::class);
});

test('invite rejects an email that is already a member', function () {
    [$user] = ownerActingIn();

    $this->postJson('/api/v1/settings/workspace/invitations', [
        'email' => $user->email,
        'role' => 'member',
    ])->assertStatus(422)->assertJsonPath('errors.email.0', 'This user is already a member.');
});

test('invite rejects a duplicate pending invitation', function () {
    [$user, $workspace] = ownerActingIn();
    WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'invited_by' => $user->id,
        'email' => 'pending@example.com',
    ]);

    $this->postJson('/api/v1/settings/workspace/invitations', [
        'email' => 'pending@example.com',
        'role' => 'member',
    ])->assertStatus(422);
});

test('invite requires the users.manage permission', function () {
    [, $workspace] = ownerActingIn();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->actingAs($member)
        ->postJson('/api/v1/settings/workspace/invitations', [
            'email' => 'someone@example.com',
            'role' => 'member',
        ])->assertForbidden();
});

test('member role update changes the role', function () {
    [, $workspace] = ownerActingIn();
    $membership = WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->patchJson("/api/v1/settings/workspace/members/{$membership->id}", ['role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('member.role', 'admin');

    expect($membership->fresh()->role)->toBe(WorkspaceRole::Admin);
});

test('member role update rejects changing your own role', function () {
    [$user, $workspace] = ownerActingIn();
    $own = WorkspaceMembership::where('workspace_id', $workspace->id)
        ->where('user_id', $user->id)->firstOrFail();

    $this->patchJson("/api/v1/settings/workspace/members/{$own->id}", ['role' => 'member'])
        ->assertStatus(422);
});

test('member role update rejects changing the owner', function () {
    [$user, $workspace] = ownerActingIn();
    $admin = User::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $admin->id,
        'role' => WorkspaceRole::Admin,
    ]);
    // An admin acting in the workspace cannot touch the owner membership.
    $this->actingAs($admin);
    $admin->forceFill(['current_workspace_id' => $workspace->id])->save();

    $ownerMembership = WorkspaceMembership::where('workspace_id', $workspace->id)
        ->where('user_id', $user->id)->firstOrFail();

    $this->patchJson("/api/v1/settings/workspace/members/{$ownerMembership->id}", ['role' => 'member'])
        ->assertStatus(422);
});

test('member role update on another workspace membership is not found', function () {
    [, $workspace] = ownerActingIn();
    $foreign = WorkspaceMembership::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->patchJson("/api/v1/settings/workspace/members/{$foreign->id}", ['role' => 'admin'])
        ->assertNotFound();
});

test('remove member deletes the membership and reassigns their current workspace', function () {
    [, $workspace] = ownerActingIn();
    $other = Workspace::factory()->create();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    $membership = WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $other->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->deleteJson("/api/v1/settings/workspace/members/{$membership->id}")
        ->assertOk();

    expect(WorkspaceMembership::find($membership->id))->toBeNull();
    expect($member->fresh()->current_workspace_id)->toBe($other->id);
});

test('remove member rejects removing the owner', function () {
    [$user, $workspace] = ownerActingIn();
    $own = WorkspaceMembership::where('workspace_id', $workspace->id)
        ->where('user_id', $user->id)->firstOrFail();

    $this->deleteJson("/api/v1/settings/workspace/members/{$own->id}")
        ->assertStatus(422);
});

test('remove member requires the users.manage permission', function () {
    [, $workspace] = ownerActingIn();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);
    $target = WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->actingAs($member)
        ->deleteJson("/api/v1/settings/workspace/members/{$target->id}")
        ->assertForbidden();
});

test('cancel invitation deletes it', function () {
    [$user, $workspace] = ownerActingIn();
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'invited_by' => $user->id,
    ]);

    $this->deleteJson("/api/v1/settings/workspace/invitations/{$invitation->id}")
        ->assertOk();

    expect(WorkspaceInvitation::find($invitation->id))->toBeNull();
});

test('cancel invitation on another workspace is not found', function () {
    ownerActingIn();
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
    ]);

    $this->deleteJson("/api/v1/settings/workspace/invitations/{$invitation->id}")
        ->assertNotFound();
});

test('leave removes the member and moves their current workspace', function () {
    [, $workspace] = ownerActingIn();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);
    $other = Workspace::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $other->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->actingAs($member)
        ->postJson('/api/v1/settings/workspace/leave')
        ->assertOk()
        ->assertJsonPath('workspaces.current.id', $other->id);

    expect($member->fresh()->current_workspace_id)->toBe($other->id);
});

test('leave is blocked for a sole owner with other members', function () {
    [, $workspace] = ownerActingIn();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->postJson('/api/v1/settings/workspace/leave')->assertStatus(422);
});

test('workspace delete removes it and moves members to their next workspace', function () {
    [$user, $workspace] = ownerActingIn();
    $workspace->forceFill(['is_initial' => false])->save();
    $other = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $other->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner,
    ]);

    $this->deleteJson('/api/v1/settings/workspace')->assertOk();

    expect(Workspace::find($workspace->id))->toBeNull();
    expect($user->fresh()->current_workspace_id)->toBe($other->id);
});

test('workspace delete is forbidden for non-owners', function () {
    [, $workspace] = ownerActingIn();
    $admin = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $admin->id,
        'role' => WorkspaceRole::Admin,
    ]);

    $this->actingAs($admin)->deleteJson('/api/v1/settings/workspace')->assertForbidden();
});

test('workspace delete is blocked when it is the only workspace', function () {
    ownerActingIn();

    $this->deleteJson('/api/v1/settings/workspace')->assertStatus(422);
});

test('workspace delete protects the initial workspace when subscriptions run', function () {
    config(['subscriptions.enabled' => true]);
    [$user, $workspace] = ownerActingIn();
    $other = Workspace::factory()->create(['owner_id' => $user->id, 'is_initial' => false]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $other->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner,
    ]);

    $this->deleteJson('/api/v1/settings/workspace')->assertStatus(422);

    expect(Workspace::find($workspace->id))->not->toBeNull();
});

test('transfer ownership swaps the roles and updates the workspace owner', function () {
    [$user, $workspace] = ownerActingIn();
    $target = WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->postJson('/api/v1/settings/workspace/transfer', ['membership_id' => $target->id])
        ->assertOk();

    expect($target->fresh()->role)->toBe(WorkspaceRole::Owner);
    expect($workspace->fresh()->owner_id)->toBe($target->user_id);
});

test('transfer ownership is forbidden for non-owners', function () {
    [, $workspace] = ownerActingIn();
    $admin = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $admin->id,
        'role' => WorkspaceRole::Admin,
    ]);
    $target = WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->actingAs($admin)
        ->postJson('/api/v1/settings/workspace/transfer', ['membership_id' => $target->id])
        ->assertForbidden();
});

test('transfer ownership rejects a membership from another workspace', function () {
    ownerActingIn();
    $foreign = WorkspaceMembership::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->postJson('/api/v1/settings/workspace/transfer', ['membership_id' => $foreign->id])
        ->assertNotFound();
});

test('api keys index lists active keys only', function () {
    [, $workspace] = ownerActingIn();
    ApiKey::factory()->create(['workspace_id' => $workspace->id, 'name' => 'CI bot']);
    ApiKey::factory()->create(['workspace_id' => $workspace->id, 'revoked_at' => now()]);

    $this->getJson('/api/v1/settings/workspace/api-keys')
        ->assertOk()
        ->assertJsonCount(1, 'apiKeys')
        ->assertJsonPath('apiKeys.0.name', 'CI bot');
});

test('api keys store returns the plaintext key once', function () {
    ownerActingIn();

    $this->postJson('/api/v1/settings/workspace/api-keys', [
        'name' => 'Deploy bot',
        'scope' => 'write',
    ])->assertCreated()
        ->assertJsonPath('apiKey.name', 'Deploy bot')
        ->assertJsonStructure(['plainTextApiKey']);
});

test('api keys store validates the scope', function () {
    ownerActingIn();

    $this->postJson('/api/v1/settings/workspace/api-keys', [
        'name' => 'x',
        'scope' => 'admin',
    ])->assertStatus(422);
});

test('api keys destroy revokes the key', function () {
    [, $workspace] = ownerActingIn();
    $key = ApiKey::factory()->create(['workspace_id' => $workspace->id]);

    $this->deleteJson("/api/v1/settings/workspace/api-keys/{$key->id}")
        ->assertOk();

    expect($key->fresh()->revoked_at)->not->toBeNull();
});

test('api keys destroy on another workspace key is not found', function () {
    ownerActingIn();
    $key = ApiKey::factory()->create(['workspace_id' => Workspace::factory()->create()->id]);

    $this->deleteJson("/api/v1/settings/workspace/api-keys/{$key->id}")->assertNotFound();
});

test('api keys require the settings.manage permission', function () {
    [, $workspace] = ownerActingIn();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->actingAs($member)
        ->getJson('/api/v1/settings/workspace/api-keys')
        ->assertForbidden();
});

test('subscription returns 404 when subscriptions are disabled', function () {
    ownerActingIn();

    $this->getJson('/api/v1/settings/workspace/subscription')->assertNotFound();
});

test('subscription reports the budget payload when enabled', function () {
    config(['subscriptions.enabled' => true]);
    ownerActingIn();

    $this->getJson('/api/v1/settings/workspace/subscription')
        ->assertOk()
        ->assertJsonStructure([
            'subscribed',
            'monthlyPrice',
            'monthlyXBudgetMicrousd',
            'monthlyXBudgetUsedMicrousd',
            'monthlyXBudgetRemainingMicrousd',
            'canManageSubscription',
            'canAccessPortal',
        ]);
});

test('subscription requires the billing.manage permission', function () {
    config(['subscriptions.enabled' => true]);
    [, $workspace] = ownerActingIn();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->actingAs($member)
        ->getJson('/api/v1/settings/workspace/subscription')
        ->assertForbidden();
});
