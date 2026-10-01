<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;

test('logged in invitee accepts via the API', function () {
    $workspace = Workspace::factory()->create();
    $currentWorkspace = Workspace::factory()->create();
    [$plain, $hash] = WorkspaceInvitation::generateToken();
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'guest@example.com',
        'token' => $hash,
    ]);
    $user = User::factory()->create([
        'email' => 'guest@example.com',
        'current_workspace_id' => $currentWorkspace->id,
    ]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $currentWorkspace->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/workspace-invitations/{$invitation->id}/accept")
        ->assertOk();

    $this->assertTrue($user->fresh()->isMemberOfWorkspace($workspace->id));
    $this->assertSame($workspace->id, $user->fresh()->current_workspace_id);
});

test('the invitation route hands off to the SPA', function () {
    $workspace = Workspace::factory()->create();
    [$plain, $hash] = WorkspaceInvitation::generateToken();
    WorkspaceInvitation::factory()->create(['workspace_id' => $workspace->id, 'token' => $hash]);

    $this->get(route('workspace.invitation', $plain))
        ->assertRedirect("/app/invitation/{$plain}");
});

test('the invitation show endpoint exposes the public landing payload', function () {
    $workspace = Workspace::factory()->create();
    $inviter = User::factory()->create(['name' => 'Inviter Person']);
    [$plain, $hash] = WorkspaceInvitation::generateToken();
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'invited_by' => $inviter->id,
        'email' => 'invited@example.com',
        'role' => 'member',
        'token' => $hash,
    ]);

    $this->getJson("/api/v1/invitations/{$plain}")
        ->assertOk()
        ->assertJson([
            'id' => $invitation->id,
            'workspace_name' => $workspace->name,
            'role' => 'member',
            'inviter_name' => 'Inviter Person',
            'user_exists' => false,
        ])
        ->assertJsonStructure(['expires_at']);
});

test('the invitation show endpoint 404s for unknown or spent tokens', function () {
    $this->getJson('/api/v1/invitations/nope')->assertNotFound();

    [$plain, $hash] = WorkspaceInvitation::generateToken();
    WorkspaceInvitation::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'token' => $hash,
        'expires_at' => now()->subDay(),
    ]);

    $this->getJson("/api/v1/invitations/{$plain}")->assertNotFound();
});
