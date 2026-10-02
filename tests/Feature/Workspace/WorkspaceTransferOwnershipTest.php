<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

test('owner can transfer ownership and is demoted to admin', function () {
    $workspace = Workspace::factory()->create();
    $owner = User::factory()->create(['current_workspace_id' => $workspace->id]);
    $target = User::factory()->create();
    $workspace->update(['owner_id' => $owner->id]);
    WorkspaceMembership::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $targetMembership = WorkspaceMembership::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $target->id]);

    $this->actingAs($owner)
        ->postJson("/api/v1/workspaces/{$workspace->id}/transfer", ['membership_id' => $targetMembership->id])
        ->assertOk();

    $this->assertSame($target->id, $workspace->fresh()->owner_id);
    $this->assertSame(WorkspaceRole::Owner, $target->fresh()->getMembershipForWorkspace($workspace->id)->role);
    $this->assertSame(WorkspaceRole::Admin, $owner->fresh()->getMembershipForWorkspace($workspace->id)->role);
});

test('non owner cannot transfer ownership', function () {
    $workspace = Workspace::factory()->create();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    $target = User::factory()->create();
    WorkspaceMembership::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);
    $targetMembership = WorkspaceMembership::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $target->id]);

    $this->actingAs($member)
        ->postJson("/api/v1/workspaces/{$workspace->id}/transfer", ['membership_id' => $targetMembership->id])
        ->assertForbidden();
});
