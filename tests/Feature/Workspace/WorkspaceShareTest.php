<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

beforeEach(function () {
    config(['kit.workspaces.enabled' => true]);
});

test('dashboard shares current workspace and list', function () {
    $workspace = Workspace::factory()->create(['name' => 'Acme']);
    $user = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMembership::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('workspaces.current.name', 'Acme')
        ->assertJsonPath('workspaces.current.role', 'owner')
        ->assertJsonCount(1, 'workspaces.all')
        ->assertJsonPath('workspaces.enabled', true);
});

test('dashboard does not allow workspace creation when workspaces are globally disabled', function () {
    config(['kit.workspaces.enabled' => false]);

    $user = User::factory()->withWorkspace()->create();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('workspaces.enabled', false)
        ->assertJsonPath('workspaces.canCreateWorkspaces', false);
});
