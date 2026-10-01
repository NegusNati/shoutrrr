<?php

use App\Enums\InstanceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\InstanceSettings;

it('lets an instance owner set a dollar budget, unlimited, or clear it', function (): void {
    $owner = User::factory()->create(['instance_role' => InstanceRole::Owner]);
    $workspace = Workspace::factory()->create(['is_initial' => false]);

    $this->actingAs($owner)
        ->put("/api/v1/instance-settings/usage/workspaces/{$workspace->id}/budget", ['unlimited' => false, 'dollars' => 15])
        ->assertRedirect();
    expect(app(InstanceSettings::class)->xWorkspaceBudget($workspace->id))->toBe(1500);

    $this->actingAs($owner)
        ->put("/api/v1/instance-settings/usage/workspaces/{$workspace->id}/budget", ['unlimited' => true, 'dollars' => null])
        ->assertRedirect();
    expect(app(InstanceSettings::class)->xWorkspaceBudget($workspace->id))->toBe('unlimited');

    $this->actingAs($owner)
        ->put("/api/v1/instance-settings/usage/workspaces/{$workspace->id}/budget", ['unlimited' => false, 'dollars' => null])
        ->assertRedirect();
    expect(app(InstanceSettings::class)->xWorkspaceBudget($workspace->id))->toBeNull();
});

it('forbids non-owners from editing a workspace budget', function (): void {
    $user = User::factory()->create(['instance_role' => null]);
    $workspace = Workspace::factory()->create();

    $this->actingAs($user)
        ->put("/api/v1/instance-settings/usage/workspaces/{$workspace->id}/budget", ['unlimited' => true, 'dollars' => null])
        ->assertForbidden();
});
