<?php

use App\Models\User;
use App\Models\Workspace;
use App\Support\InstanceSettings;

beforeEach(function (): void {
    // API routes resolve the session's workspace before reaching the
    // instance-owner gate, so give the owner a workspace to act inside.
    $this->owner = User::factory()->instanceOwner()->withWorkspace()->create();
});

it('paginates and searches the workspace usage table', function (): void {
    Workspace::factory()->create(['name' => 'Acme']);
    Workspace::factory()->create(['name' => 'Globex']);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/instance-settings/usage?search=Acme')
        ->assertOk()
        ->assertJsonCount(1, 'workspace_usage.data')
        ->assertJsonPath('workspace_usage.data.0.name', 'Acme')
        ->assertJsonStructure(['instance_summary' => ['workspace_count']]);
});

it('exposes the quota kind for each workspace', function (): void {
    $custom = Workspace::factory()->create(['name' => 'Custom', 'is_initial' => false]);
    app(InstanceSettings::class)->setXWorkspaceBudget($custom->id, 'unlimited');

    $this->actingAs($this->owner)
        ->getJson('/api/v1/instance-settings/usage?search=Custom')
        ->assertJsonPath('workspace_usage.data.0.quota.kind', 'unlimited');
});

it('returns drilldown data only when a workspace is selected', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Zeta']);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/instance-settings/usage')
        ->assertJson(fn ($json) => $json->missing('drilldown')->etc());

    $this->actingAs($this->owner)
        ->getJson('/api/v1/instance-settings/usage?workspace='.$workspace->id)
        ->assertJsonStructure(['drilldown' => ['counters', 'error_events']]);
});

it('includes the workspace quota in the drilldown payload', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Initech', 'is_initial' => false]);
    app(InstanceSettings::class)->setXWorkspaceBudget($workspace->id, 'unlimited');

    $this->actingAs($this->owner)
        ->getJson('/api/v1/instance-settings/usage?workspace='.$workspace->id)
        ->assertJsonPath('drilldown.workspace.id', $workspace->id)
        ->assertJsonPath('drilldown.workspace.quota.kind', 'unlimited');
});

it('flags the initial workspace in the drilldown so its quota editor locks', function (): void {
    $workspace = $this->owner->currentWorkspace;

    $this->actingAs($this->owner)
        ->getJson('/api/v1/instance-settings/usage?workspace='.$workspace->id)
        ->assertJsonPath('drilldown.workspace.is_initial', true)
        ->assertJsonPath('drilldown.workspace.quota.kind', 'unlimited');
});

it('includes the workspace owner in the drilldown payload', function (): void {
    $workspaceOwner = User::factory()->create(['name' => 'Ada Owner', 'email' => 'ada@example.test']);
    $workspace = Workspace::factory()->for($workspaceOwner, 'owner')->create(['name' => 'Umbrella']);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/instance-settings/usage?workspace='.$workspace->id)
        ->assertJsonPath('drilldown.workspace.owner.name', 'Ada Owner')
        ->assertJsonPath('drilldown.workspace.owner.email', 'ada@example.test')
        ->assertJsonStructure(['drilldown' => ['workspace' => ['owner' => ['avatar']]]]);
});
