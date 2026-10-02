<?php

use App\Enums\WorkspaceRole;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

test('user can create a workspace and becomes owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/workspaces', ['name' => 'New Co'])
        ->assertCreated();

    $workspace = Workspace::where('name', 'New Co')->firstOrFail();
    $this->assertSame(WorkspaceRole::Owner, $user->getMembershipForWorkspace($workspace->id)->role);
    $this->assertSame($workspace->id, $user->fresh()->current_workspace_id);
});

test('user cannot switch to a workspace they do not belong to', function () {
    $user = User::factory()->create();
    $other = Workspace::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/workspaces/switch', ['workspace_id' => $other->id])
        ->assertJsonValidationErrors('workspace_id');

    $this->assertNull($user->fresh()->current_workspace_id);
});

test('user can switch to their workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMembership::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $this->actingAs($user)->postJson('/api/v1/workspaces/switch', ['workspace_id' => $workspace->id])
        ->assertOk()
        ->assertJsonPath('current.id', $workspace->id);

    $this->assertSame($workspace->id, $user->fresh()->current_workspace_id);
});

test('switching works while the user sits on a workspace-scoped detail page', function () {
    $user = User::factory()->create();
    $current = Workspace::factory()->create();
    $next = Workspace::factory()->create();
    WorkspaceMembership::factory()->create(['workspace_id' => $current->id, 'user_id' => $user->id]);
    WorkspaceMembership::factory()->create(['workspace_id' => $next->id, 'user_id' => $user->id]);
    $user->forceFill(['current_workspace_id' => $current->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $current->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    Post::factory()->for($current)->create(['author_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/v1/workspaces/switch', ['workspace_id' => $next->id])
        ->assertOk()
        ->assertJsonPath('current.id', $next->id);

    $this->assertSame($next->id, $user->fresh()->current_workspace_id);
});
