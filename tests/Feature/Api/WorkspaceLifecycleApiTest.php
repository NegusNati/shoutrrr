<?php

declare(strict_types=1);

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

/*
|--------------------------------------------------------------------------
| Workspace lifecycle — /api/v1 session-only endpoints
|--------------------------------------------------------------------------
|
| Ports of the legacy web routes (workspaces.leave / destroy / transfer):
| DELETE /api/v1/workspaces/{workspace}/leave
| DELETE /api/v1/workspaces/{workspace}
| POST   /api/v1/workspaces/{workspace}/transfer
|
*/

/**
 * Session-authenticated member of $workspace with a second workspace they can
 * fall back to — mirrors the state a leaving/deleting session needs.
 *
 * @return array{0: User, 1: Workspace, 2: Workspace}
 */
function memberWithFallback(WorkspaceRole $role = WorkspaceRole::Member): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $fallback = Workspace::factory()->create();

    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $fallback->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Member,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    test()->actingAs($user);

    return [$user, $workspace, $fallback];
}

test('member leaves their current workspace and lands on the next one', function (): void {
    [$user, $workspace, $fallback] = memberWithFallback();

    $this
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/leave")
        ->assertOk()
        ->assertJsonPath('message', 'You left the workspace.');

    expect($user->fresh()->isMemberOfWorkspace($workspace->id))->toBeFalse();
    expect($user->fresh()->current_workspace_id)->toBe($fallback->id);
});

test('member can leave a workspace that is not their current one', function (): void {
    [$user, $workspace, $fallback] = memberWithFallback();

    $this
        ->deleteJson("/api/v1/workspaces/{$fallback->id}/leave")
        ->assertOk();

    expect($user->fresh()->isMemberOfWorkspace($fallback->id))->toBeFalse();
    expect($user->fresh()->current_workspace_id)->toBe($workspace->id);
});

test('leaving a workspace the user does not belong to 404s', function (): void {
    [$user, $workspace] = ownerActingIn();
    $foreign = Workspace::factory()->create();

    $this->deleteJson("/api/v1/workspaces/{$foreign->id}/leave")->assertNotFound();

    expect($user->fresh()->current_workspace_id)->toBe($workspace->id);
});

test('sole owner with other members cannot leave until they transfer', function (): void {
    [, $workspace] = ownerActingIn();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/leave")
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('workspace');
});

test('sole owner without other members can still leave', function (): void {
    [$user, $workspace] = ownerActingIn();
    $fallback = Workspace::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $fallback->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/leave")->assertOk();

    expect($user->fresh()->isMemberOfWorkspace($workspace->id))->toBeFalse();
    expect($user->fresh()->current_workspace_id)->toBe($fallback->id);
});

test('owner deletes the workspace and members land on their next workspace', function (): void {
    [$owner, $workspace, $fallback] = memberWithFallback(WorkspaceRole::Owner);
    $workspace->forceFill(['owner_id' => $owner->id])->save();

    // A member whose current workspace is the deleted one falls back too;
    // one with no other workspace ends up with no current workspace.
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $fallback->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member,
    ]);
    $stranded = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $stranded->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this
        ->deleteJson("/api/v1/workspaces/{$workspace->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Workspace deleted.');

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse();
    expect(WorkspaceMembership::query()->where('workspace_id', $workspace->id)->exists())->toBeFalse();
    expect($owner->fresh()->current_workspace_id)->toBe($fallback->id);
    expect($member->fresh()->current_workspace_id)->toBe($fallback->id);
    expect($stranded->fresh()->current_workspace_id)->toBeNull();
});

test('non-owners cannot delete the workspace', function (): void {
    [, $workspace] = memberWithFallback();

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}")->assertForbidden();

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue();
});

test('an owner with no other workspace cannot delete their last one', function (): void {
    [, $workspace] = ownerActingIn();

    $this
        ->deleteJson("/api/v1/workspaces/{$workspace->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('workspace');

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue();
});

test('the initial workspace cannot be deleted when subscriptions are enabled', function (): void {
    config(['subscriptions.enabled' => true]);

    [$owner, $workspace] = ownerActingIn();
    $workspace->forceFill(['is_initial' => true])->save();
    $fallback = Workspace::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $fallback->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this
        ->deleteJson("/api/v1/workspaces/{$workspace->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('workspace');

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue();
});

test('transfer promotes the target to owner and demotes the caller to admin', function (): void {
    [$owner, $workspace] = ownerActingIn();
    $target = WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this
        ->postJson("/api/v1/workspaces/{$workspace->id}/transfer", [
            'membership_id' => $target->id,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Ownership transferred.');

    expect($target->fresh()->role)->toBe(WorkspaceRole::Owner);
    expect($owner->getMembershipForWorkspace($workspace->id)->role)->toBe(WorkspaceRole::Admin);
    expect($workspace->fresh()->owner_id)->toBe($target->user_id);
});

test('only the owner can transfer ownership', function (): void {
    [, $workspace] = memberWithFallback(WorkspaceRole::Admin);
    $target = WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this
        ->postJson("/api/v1/workspaces/{$workspace->id}/transfer", [
            'membership_id' => $target->id,
        ])
        ->assertForbidden();
});

test('transfer target must belong to the same workspace', function (): void {
    [, $workspace] = ownerActingIn();
    $foreignMembership = WorkspaceMembership::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Member,
    ]);

    $this
        ->postJson("/api/v1/workspaces/{$workspace->id}/transfer", [
            'membership_id' => $foreignMembership->id,
        ])
        ->assertNotFound();
});

test('transfer validates the membership id', function (): void {
    [, $workspace] = ownerActingIn();

    $this
        ->postJson("/api/v1/workspaces/{$workspace->id}/transfer", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('membership_id');

    $this
        ->postJson("/api/v1/workspaces/{$workspace->id}/transfer", [
            'membership_id' => 'missing',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('membership_id');
});

test('api keys cannot reach the lifecycle endpoints', function (string $method, string $uri): void {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)->json($method, str_replace('{id}', $workspace->id, $uri))->assertForbidden();
})->with([
    'leave' => ['DELETE', '/api/v1/workspaces/{id}/leave'],
    'delete' => ['DELETE', '/api/v1/workspaces/{id}'],
    'transfer' => ['POST', '/api/v1/workspaces/{id}/transfer'],
]);

test('unverified session users cannot reach the lifecycle endpoints', function (string $method, string $uri): void {
    config(['auth.email_verification.enabled' => true]);

    $user = User::factory()->unverified()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    $this->actingAs($user);

    $this->json($method, str_replace('{id}', $workspace->id, $uri))->assertForbidden();
})->with([
    'leave' => ['DELETE', '/api/v1/workspaces/{id}/leave'],
    'delete' => ['DELETE', '/api/v1/workspaces/{id}'],
    'transfer' => ['POST', '/api/v1/workspaces/{id}/transfer'],
]);

test('guests get 401', function (): void {
    $workspace = Workspace::factory()->create();

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/leave")->assertUnauthorized();
});
