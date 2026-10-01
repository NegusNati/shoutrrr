<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

test('dashboard shares onboarding prop for the current workspace owner', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create([
        'current_workspace_id' => $workspace->id,
        'email_verified_at' => now(),
    ]);
    WorkspaceMembership::factory()->owner()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('onboarding.welcomed', false)
        ->assertJsonPath('onboarding.dismissed', false)
        ->assertJsonPath('onboarding.complete', false)
        ->assertJsonCount(4, 'onboarding.steps');
});
