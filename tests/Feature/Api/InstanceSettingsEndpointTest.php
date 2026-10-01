<?php

declare(strict_types=1);

use App\Enums\InstanceRole;
use App\Models\User;
use App\Models\Workspace;

test('show returns the instance settings payload for an owner', function () {
    instanceOwnerActingIn();

    $this
        ->getJson('/api/v1/instance-settings')
        ->assertOk()
        ->assertJsonStructure([
            'settings' => [
                'registrations_enabled',
                'workspace_creation_enabled',
                'usage_tracking_enabled',
            ],
            'workspaces_enabled',
        ]);
});

test('non-owner gets 403 on every instance-settings read', function (string $url) {
    ownerActingIn();

    $this->getJson($url)->assertForbidden();
})->with([
    '/api/v1/instance-settings',
    '/api/v1/instance-settings/polling',
    '/api/v1/instance-settings/platforms',
    '/api/v1/instance-settings/usage',
    '/api/v1/instance-settings/admins',
]);

test('updateSettings persists and echoes the settings', function () {
    instanceOwnerActingIn();

    $settings = $this
        ->getJson('/api/v1/instance-settings')
        ->assertOk()
        ->json('settings');
    $settings['registrations_enabled'] = false;

    $this
        ->putJson('/api/v1/instance-settings', $settings)
        ->assertOk()
        ->assertJsonPath('settings.registrations_enabled', false);
});

test('updateSettings rejects a non-owner', function () {
    ownerActingIn();

    $this->putJson('/api/v1/instance-settings', ['registrations_enabled' => false])
        ->assertForbidden();
});

test('polling round-trips settings and sections', function () {
    instanceOwnerActingIn();

    $response = $this
        ->getJson('/api/v1/instance-settings/polling')
        ->assertOk()
        ->assertJsonStructure([
            'settings' => ['engagement', 'post_metrics', 'account_metrics', 'metrics_enabled', 'engagement_enabled'],
            'sections' => ['engagement', 'post_metrics', 'account_metrics'],
        ]);

    $settings = $response->json('settings');
    $settings['engagement']['x'] = 30;

    $this
        ->putJson('/api/v1/instance-settings/polling', $settings)
        ->assertOk()
        ->assertJsonPath('settings.engagement.x', 30);
});

test('platforms round-trips toggles', function () {
    instanceOwnerActingIn();

    $platforms = collect(
        $this->getJson('/api/v1/instance-settings/platforms')->assertOk()->json('platforms'),
    );
    $x = $platforms->firstWhere('platform', 'x');
    expect($x)->toHaveKeys(['platform', 'label', 'enabled', 'configured']);

    $this
        ->putJson('/api/v1/instance-settings/platforms', [
            'platforms' => $platforms->mapWithKeys(fn ($p) => [$p['platform'] => $p['platform'] !== 'x'])->all(),
            'linkedin_community_management_enabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('linkedin_community_management_enabled', true);
});

test('usage returns the paginator, summary and pricing metadata', function () {
    [, $workspace] = instanceOwnerActingIn();

    $response = $this
        ->getJson('/api/v1/instance-settings/usage')
        ->assertOk()
        ->assertJsonStructure([
            'filters' => ['search', 'sort', 'workspace'],
            'instance_summary' => ['workspace_count', 'x_estimated_cost_usd'],
            'workspace_usage' => ['data', 'current_page', 'last_page', 'total'],
            'pricing_source',
            'pricing_currency',
            'x_usage_available',
        ]);

    $names = collect($response->json('workspace_usage.data'))->pluck('name');
    expect($names)->toContain($workspace->name);
});

test('usage drilldown is eager when workspace query param is present', function () {
    [, $workspace] = instanceOwnerActingIn();

    $this
        ->getJson("/api/v1/instance-settings/usage?workspace={$workspace->id}")
        ->assertOk()
        ->assertJsonStructure([
            'drilldown' => [
                'workspace' => ['id', 'name', 'is_initial', 'quota', 'owner'],
                'counters',
                'error_events',
            ],
        ])
        ->assertJsonPath('drilldown.workspace.id', $workspace->id);
});

test('updateBudget stores a custom dollar override', function () {
    [, $workspace] = instanceOwnerActingIn();
    $target = Workspace::factory()->create();

    $this
        ->putJson("/api/v1/instance-settings/usage/workspaces/{$target->id}/budget", [
            'unlimited' => false,
            'dollars' => 12.5,
        ])
        ->assertOk();
});

test('updateBudget validates the payload', function () {
    instanceOwnerActingIn();
    $target = Workspace::factory()->create();

    $this
        ->putJson("/api/v1/instance-settings/usage/workspaces/{$target->id}/budget", [
            'dollars' => 'not-a-number',
        ])
        ->assertUnprocessable();
});

test('listAdmins returns owners and search-matched users', function () {
    instanceOwnerActingIn();
    $candidate = User::factory()->create(['email' => 'findme@example.com']);

    $response = $this
        ->getJson('/api/v1/instance-settings/admins?search=findme')
        ->assertOk();

    expect(collect($response->json('owners'))->pluck('id'))->toContain(
        User::query()->where('instance_role', InstanceRole::Owner)->first()?->id,
    );
    expect(collect($response->json('users'))->pluck('id'))->toContain($candidate->id);
});

test('listAdmins without search returns no user candidates', function () {
    instanceOwnerActingIn();

    $this
        ->getJson('/api/v1/instance-settings/admins')
        ->assertOk()
        ->assertJson(['users' => []]);
});

test('addAdmin promotes a registered user', function () {
    instanceOwnerActingIn();
    $candidate = User::factory()->create();

    $this
        ->postJson('/api/v1/instance-settings/admins', ['email' => $candidate->email])
        ->assertCreated()
        ->assertJsonPath('owner.id', $candidate->id);

    expect($candidate->fresh()->isInstanceOwner())->toBeTrue();
});

test('addAdmin rejects an unregistered email', function () {
    instanceOwnerActingIn();

    $this
        ->postJson('/api/v1/instance-settings/admins', ['email' => 'ghost@example.com'])
        ->assertUnprocessable();
});

test('removeAdmin demotes another owner', function () {
    [$actor] = instanceOwnerActingIn();
    $other = User::factory()->create(['instance_role' => InstanceRole::Owner]);

    $this
        ->deleteJson("/api/v1/instance-settings/admins/{$other->id}")
        ->assertOk();

    expect($other->fresh()->isInstanceOwner())->toBeFalse();
});

test('removeAdmin refuses to demote yourself', function () {
    [$actor] = instanceOwnerActingIn();

    $this
        ->deleteJson("/api/v1/instance-settings/admins/{$actor->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.owner.0', 'You cannot remove yourself as an instance owner.');
});

test('removeAdmin 404s for a non-owner id', function () {
    instanceOwnerActingIn();
    $member = User::factory()->create();

    $this
        ->deleteJson("/api/v1/instance-settings/admins/{$member->id}")
        ->assertNotFound();
});

test('read-scoped key cannot mutate instance settings', function () {
    instanceOwnerActingIn();
    [, , $token] = issuedKey('read');

    $this->getJson('/api/v1/instance-settings')->assertOk();

    $this->withToken($token)
        ->putJson('/api/v1/instance-settings', ['registrations_enabled' => false])
        ->assertForbidden();
});
