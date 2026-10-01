<?php

use App\Enums\InstanceRole;
use App\Models\User;
use Illuminate\Support\Facades\Config;

function instanceOwner(): User
{
    return User::factory()->create(['instance_role' => InstanceRole::Owner]);
}

// ---- auth boundary ----------------------------------------------------

test('instance settings endpoints require authentication', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'overview' => ['get', '/api/v1/settings/instance'],
    'update' => ['put', '/api/v1/settings/instance'],
    'polling' => ['get', '/api/v1/settings/instance/polling'],
    'update polling' => ['put', '/api/v1/settings/instance/polling'],
    'platforms' => ['get', '/api/v1/settings/instance/platforms'],
    'update platforms' => ['put', '/api/v1/settings/instance/platforms'],
    'usage' => ['get', '/api/v1/settings/instance/usage'],
    'x usage' => ['get', '/api/v1/settings/instance/usage/x'],
    'admins' => ['get', '/api/v1/settings/instance/admins'],
    'store admin' => ['post', '/api/v1/settings/instance/admins'],
]);

test('instance settings endpoints reject api-key auth', function (string $method, string $uri) {
    [, , $token] = issuedKey();

    $this->withToken($token)->json($method, $uri)->assertForbidden();
})->with([
    'overview' => ['get', '/api/v1/settings/instance'],
    'polling' => ['get', '/api/v1/settings/instance/polling'],
    'platforms' => ['get', '/api/v1/settings/instance/platforms'],
    'usage' => ['get', '/api/v1/settings/instance/usage'],
    'admins' => ['get', '/api/v1/settings/instance/admins'],
    'update' => ['put', '/api/v1/settings/instance'],
]);

test('non-owner users get 403 on instance settings', function (string $method, string $uri) {
    [, $workspace] = ownerActingIn();

    $this->json($method, $uri)->assertForbidden();
})->with([
    'overview' => ['get', '/api/v1/settings/instance'],
    'polling' => ['get', '/api/v1/settings/instance/polling'],
    'platforms' => ['get', '/api/v1/settings/instance/platforms'],
    'usage' => ['get', '/api/v1/settings/instance/usage'],
    'admins' => ['get', '/api/v1/settings/instance/admins'],
]);

// ---- overview ---------------------------------------------------------

test('instance overview returns settings and workspaces_enabled', function () {
    $owner = instanceOwner();
    $this->actingAs($owner);

    $this->getJson('/api/v1/settings/instance')
        ->assertOk()
        ->assertJsonStructure([
            'settings' => [
                'registrations_enabled',
                'workspace_creation_enabled',
                'usage_tracking_enabled',
                'quote_tweets_enabled',
            ],
            'workspaces_enabled',
        ]);
});

test('workspace_creation_enabled is forced off when workspaces are disabled', function () {
    Config::set('kit.workspaces.enabled', false);
    $owner = instanceOwner();
    $this->actingAs($owner);

    $this->getJson('/api/v1/settings/instance')
        ->assertOk()
        ->assertJsonPath('workspaces_enabled', false)
        ->assertJsonPath('settings.workspace_creation_enabled', false);
});

test('instance settings update persists toggles', function () {
    $owner = instanceOwner();
    $this->actingAs($owner);

    $this->putJson('/api/v1/settings/instance', [
        'registrations_enabled' => false,
        'workspace_creation_enabled' => true,
        'usage_tracking_enabled' => true,
        'quote_tweets_enabled' => false,
    ])->assertOk()
        ->assertJsonPath('settings.registrations_enabled', false)
        ->assertJsonPath('settings.quote_tweets_enabled', false);
});

// ---- polling ----------------------------------------------------------

test('polling returns settings and platform sections', function () {
    $owner = instanceOwner();
    $this->actingAs($owner);

    $this->getJson('/api/v1/settings/instance/polling')
        ->assertOk()
        ->assertJsonStructure([
            'settings' => [
                'metrics_enabled',
                'engagement_enabled',
                'messages_enabled',
                'direct_messages_enabled',
            ],
            'sections' => [
                'engagement' => [['platform', 'label']],
                'post_metrics' => [['platform', 'label']],
                'account_metrics' => [['platform', 'label']],
            ],
        ]);
});

// ---- platforms --------------------------------------------------------

test('platforms returns per-platform state', function () {
    $owner = instanceOwner();
    $this->actingAs($owner);

    $this->getJson('/api/v1/settings/instance/platforms')
        ->assertOk()
        ->assertJsonStructure([
            'platforms' => [['platform', 'label', 'enabled', 'configured']],
            'linkedin_community_management_enabled',
        ]);
});

// ---- usage ------------------------------------------------------------

test('usage returns summary and paginated workspace usage', function () {
    $owner = instanceOwner();
    [, $workspace] = ownerActingIn();
    $this->actingAs($owner);

    $this->getJson('/api/v1/settings/instance/usage')
        ->assertOk()
        ->assertJsonStructure([
            'filters' => ['search', 'sort', 'workspace'],
            'instance_summary' => ['workspace_count', 'x_estimated_cost_usd'],
            'workspace_usage' => ['data', 'total'],
            'pricing_source',
            'pricing_currency',
            'x_usage_available',
            'drilldown',
        ])
        ->assertJsonPath('instance_summary.workspace_count', 1)
        ->assertJsonPath('drilldown', null);
});

test('usage drilldown resolves a workspace', function () {
    $owner = instanceOwner();
    [, $workspace] = ownerActingIn();
    $this->actingAs($owner);

    $this->getJson("/api/v1/settings/instance/usage?workspace={$workspace->id}")
        ->assertOk()
        ->assertJsonPath('filters.workspace', $workspace->id)
        ->assertJsonPath('drilldown.workspace.id', $workspace->id)
        ->assertJsonStructure(['drilldown' => ['counters', 'error_events']]);
});

test('workspace budget update accepts unlimited', function () {
    $owner = instanceOwner();
    [, $workspace] = ownerActingIn();
    $this->actingAs($owner);

    $this->putJson("/api/v1/settings/instance/usage/workspaces/{$workspace->id}/budget", [
        'unlimited' => true,
        'dollars' => null,
    ])->assertOk()->assertJsonPath('updated', true);
});

test('x usage requires bearer token config', function () {
    Config::set('services.x.bearer_token', '');
    $owner = instanceOwner();
    $this->actingAs($owner);

    $this->getJson('/api/v1/settings/instance/usage/x')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Configure X_BEARER_TOKEN before fetching X API usage.');
});

// ---- admins -----------------------------------------------------------

test('admins lists instance owners and search results', function () {
    $owner = instanceOwner();
    $this->actingAs($owner);

    $this->getJson('/api/v1/settings/instance/admins')
        ->assertOk()
        ->assertJsonStructure([
            'owners' => [['id', 'name', 'email', 'avatar', 'created_at']],
            'users',
            'search',
        ])
        ->assertJsonCount(1, 'owners');
});

test('add admin promotes a plain user by email', function () {
    $owner = instanceOwner();
    $candidate = User::factory()->create(['instance_role' => null]);
    $this->actingAs($owner);

    $this->postJson('/api/v1/settings/instance/admins', ['email' => $candidate->email])
        ->assertCreated()
        ->assertJsonPath('added', true);

    expect($candidate->fresh()->instance_role)->toBe(InstanceRole::Owner);
});

test('add admin rejects an email that is already an owner', function () {
    $owner = instanceOwner();
    $other = instanceOwner();
    $this->actingAs($owner);

    $this->postJson('/api/v1/settings/instance/admins', ['email' => $other->email])
        ->assertUnprocessable();
});

test('owner cannot remove themselves', function () {
    $owner = instanceOwner();
    $this->actingAs($owner);

    $this->deleteJson("/api/v1/settings/instance/admins/{$owner->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.owner.0', 'You cannot remove yourself as an instance owner.');
});

test('last owner cannot be removed', function () {
    $owner = instanceOwner();
    $this->actingAs($owner);

    $this->deleteJson("/api/v1/settings/instance/admins/{$owner->id}")
        ->assertUnprocessable();
});

test('owner removal demotes the target', function () {
    $owner = instanceOwner();
    $other = instanceOwner();
    $this->actingAs($owner);

    $this->deleteJson("/api/v1/settings/instance/admins/{$other->id}")
        ->assertOk()
        ->assertJsonPath('removed', true);

    expect($other->fresh()->instance_role)->toBeNull();
});

test('removing a non-owner returns 404', function () {
    $owner = instanceOwner();
    $plain = User::factory()->create(['instance_role' => null]);
    $this->actingAs($owner);

    $this->deleteJson("/api/v1/settings/instance/admins/{$plain->id}")
        ->assertNotFound();
});
