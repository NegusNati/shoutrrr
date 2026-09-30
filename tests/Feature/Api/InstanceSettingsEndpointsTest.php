<?php

use App\Enums\InstanceRole;
use App\Enums\Platform;
use App\Models\User;
use App\Models\Workspace;
use App\Support\InstanceSettings;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Instance settings API — session-only endpoints for instance owners
|--------------------------------------------------------------------------
|
| Mirrors the Inertia InstanceSettingsController surface: general toggles,
| polling, platform freezes, X usage (list, drilldown, budget) and owner
| management. Every endpoint requires a session (RequireSessionAuth rejects
| Passport keys) AND an instance owner (403 otherwise).
|
*/

test('instance settings endpoints are unauthorized without credentials', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'show' => ['GET', '/api/v1/settings/instance'],
    'update' => ['PUT', '/api/v1/settings/instance'],
    'polling' => ['GET', '/api/v1/settings/instance/polling'],
    'polling update' => ['PUT', '/api/v1/settings/instance/polling'],
    'platforms' => ['GET', '/api/v1/settings/instance/platforms'],
    'platforms update' => ['PUT', '/api/v1/settings/instance/platforms'],
    'usage' => ['GET', '/api/v1/settings/instance/usage'],
    'x usage' => ['GET', '/api/v1/settings/instance/usage/x'],
    'drilldown' => ['GET', '/api/v1/settings/instance/usage/workspaces/w1'],
    'budget' => ['PUT', '/api/v1/settings/instance/usage/workspaces/w1/budget'],
    'admins' => ['GET', '/api/v1/settings/instance/admins'],
    'admin add' => ['POST', '/api/v1/settings/instance/admins'],
    'admin remove' => ['DELETE', '/api/v1/settings/instance/admins/u1'],
]);

test('api keys cannot reach the instance settings endpoints', function (string $method, string $uri) {
    [, , $token] = issuedKey();

    $this->withToken($token)->json($method, $uri)->assertForbidden();
})->with([
    'show' => ['GET', '/api/v1/settings/instance'],
    'update' => ['PUT', '/api/v1/settings/instance'],
    'polling' => ['GET', '/api/v1/settings/instance/polling'],
    'platforms' => ['GET', '/api/v1/settings/instance/platforms'],
    'usage' => ['GET', '/api/v1/settings/instance/usage'],
    'admins' => ['GET', '/api/v1/settings/instance/admins'],
    'admin add' => ['POST', '/api/v1/settings/instance/admins'],
]);

test('non-owners are forbidden from every instance endpoint', function (string $method, string $uri) {
    $workspace = Workspace::factory()->create();
    $owner = User::factory()->create(['instance_role' => InstanceRole::Owner]);
    $user = User::factory()->create();

    $uri = str_replace('{id}', $workspace->id, $uri);
    $uri = str_replace('{user}', $owner->id, $uri);

    $this->actingAs($user)->json($method, $uri)->assertForbidden();
})->with([
    'show' => ['GET', '/api/v1/settings/instance'],
    'update' => ['PUT', '/api/v1/settings/instance'],
    'polling' => ['GET', '/api/v1/settings/instance/polling'],
    'polling update' => ['PUT', '/api/v1/settings/instance/polling'],
    'platforms' => ['GET', '/api/v1/settings/instance/platforms'],
    'platforms update' => ['PUT', '/api/v1/settings/instance/platforms'],
    'usage' => ['GET', '/api/v1/settings/instance/usage'],
    'drilldown' => ['GET', '/api/v1/settings/instance/usage/workspaces/{id}'],
    'budget' => ['PUT', '/api/v1/settings/instance/usage/workspaces/{id}/budget'],
    'admins' => ['GET', '/api/v1/settings/instance/admins'],
    'admin add' => ['POST', '/api/v1/settings/instance/admins'],
    'admin remove' => ['DELETE', '/api/v1/settings/instance/admins/{user}'],
]);

function instanceOwnerActing(): User
{
    $owner = User::factory()->instanceOwner()->create();
    test()->actingAs($owner);

    return $owner;
}

/**
 * @param  array<string, int|bool>  $overrides  dotted-path => value
 * @return array<string, mixed>
 */
function apiPollingPayload(array $overrides = []): array
{
    $payload = [
        'metrics_enabled' => true,
        'engagement_enabled' => true,
        'messages_enabled' => true,
        'direct_messages_enabled' => false,
        'engagement' => [
            'enabled' => ['x' => true, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true],
            'x' => 15, 'bluesky' => 15, 'linkedin' => 15, 'facebook' => 15, 'instagram' => 15, 'threads' => 15,
        ],
        'post_metrics' => [
            'enabled' => ['x' => true, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true, 'discord' => true],
            'x' => 15, 'bluesky' => 15, 'linkedin' => 15, 'facebook' => 15, 'instagram' => 15, 'threads' => 15, 'discord' => 15,
        ],
        'account_metrics' => [
            'enabled' => ['x' => true, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true],
            'x' => 15, 'bluesky' => 15, 'linkedin' => 15, 'facebook' => 15, 'instagram' => 15, 'threads' => 15,
        ],
    ];

    foreach ($overrides as $path => $value) {
        data_set($payload, $path, $value);
    }

    return $payload;
}

test('show returns the instance settings and workspaces flag', function () {
    instanceOwnerActing();
    app(InstanceSettings::class)->update(['registrations_enabled' => false]);

    $this->getJson('/api/v1/settings/instance')
        ->assertOk()
        ->assertJsonPath('settings.registrations_enabled', false)
        ->assertJsonPath('workspaces_enabled', true);
});

test('update persists the general toggles and returns the payload', function () {
    instanceOwnerActing();

    $this->putJson('/api/v1/settings/instance', [
        'registrations_enabled' => false,
        'workspace_creation_enabled' => true,
        'usage_tracking_enabled' => true,
        'quote_tweets_enabled' => true,
    ])->assertOk()
        ->assertJsonPath('settings.registrations_enabled', false)
        ->assertJsonPath('settings.quote_tweets_enabled', true);

    expect(app(InstanceSettings::class)->registrationsEnabled())->toBeFalse()
        ->and(app(InstanceSettings::class)->quoteTweetsEnabled())->toBeTrue();
});

test('update forces workspace creation off when workspaces are disabled', function () {
    config(['kit.workspaces.enabled' => false]);
    instanceOwnerActing();

    $this->putJson('/api/v1/settings/instance', [
        'registrations_enabled' => true,
        'workspace_creation_enabled' => true,
        'usage_tracking_enabled' => true,
        'quote_tweets_enabled' => false,
    ])->assertOk()
        ->assertJsonPath('workspaces_enabled', false)
        ->assertJsonPath('settings.workspace_creation_enabled', false);
});

test('update rejects non-boolean input', function () {
    instanceOwnerActing();

    $this->putJson('/api/v1/settings/instance', [
        'registrations_enabled' => 'yes',
        'workspace_creation_enabled' => true,
        'usage_tracking_enabled' => true,
        'quote_tweets_enabled' => false,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('registrations_enabled');
});

test('polling exposes per-section platform lists and master switches', function () {
    instanceOwnerActing();

    $response = $this->getJson('/api/v1/settings/instance/polling')->assertOk();

    expect($response->json('sections.engagement.*.platform'))
        ->toBe(['x', 'bluesky', 'linkedin', 'facebook', 'instagram', 'threads'])
        ->and($response->json('sections.post_metrics.*.platform'))
        ->toBe(['x', 'bluesky', 'linkedin', 'facebook', 'instagram', 'threads', 'discord'])
        ->and($response->json('sections.account_metrics.*.platform'))
        ->toBe(['x', 'bluesky', 'linkedin', 'facebook', 'instagram', 'threads'])
        ->and($response->json('settings.metrics_enabled'))->toBeBool();
});

test('polling update persists intervals and enabled maps', function () {
    instanceOwnerActing();

    $this->putJson('/api/v1/settings/instance/polling', apiPollingPayload([
        'post_metrics.discord' => 60,
        'engagement.enabled.bluesky' => false,
    ]))->assertOk()
        ->assertJsonPath('settings.post_metrics.discord', 60)
        ->assertJsonPath('settings.engagement.enabled.bluesky', false);

    expect(app(InstanceSettings::class)->postMetricsPollIntervalMinutes(Platform::Discord))->toBe(60)
        ->and(app(InstanceSettings::class)->engagementPollingEnabled(Platform::Bluesky))->toBeFalse();
});

test('polling update rejects an out-of-range interval', function () {
    instanceOwnerActing();

    $this->putJson('/api/v1/settings/instance/polling', apiPollingPayload(['post_metrics.discord' => 4]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('post_metrics.discord');
});

test('polling update rejects a payload missing a supported platform', function () {
    instanceOwnerActing();

    $payload = apiPollingPayload();
    unset($payload['engagement']['enabled']['facebook'], $payload['engagement']['facebook']);

    $this->putJson('/api/v1/settings/instance/polling', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('engagement.enabled.facebook');
});

test('platforms returns every platform with enabled and configured flags', function () {
    instanceOwnerActing();
    app(InstanceSettings::class)->update(['platforms_enabled' => ['x' => false]]);

    $response = $this->getJson('/api/v1/settings/instance/platforms')->assertOk();

    $x = collect($response->json('platforms'))->firstWhere('platform', 'x');
    expect($x['enabled'])->toBeFalse()
        ->and($x)->toHaveKeys(['label', 'configured']);
    expect($response->json('linkedin_community_management_enabled'))->toBeFalse();
});

test('platforms update persists the freeze map and linkedin flag', function () {
    instanceOwnerActing();

    $platforms = collect(Platform::cases())
        ->mapWithKeys(fn ($p) => [$p->value => $p->value !== 'x'])
        ->all();

    $this->putJson('/api/v1/settings/instance/platforms', [
        'platforms' => $platforms,
        'linkedin_community_management_enabled' => true,
    ])->assertOk()
        ->assertJsonPath('linkedin_community_management_enabled', true);

    expect(app(InstanceSettings::class)->platformAvailable(Platform::X))->toBeFalse()
        ->and(app(InstanceSettings::class)->platformAvailable(Platform::Bluesky))->toBeTrue()
        ->and(app(InstanceSettings::class)->linkedinCommunityManagementEnabled())->toBeTrue();
});

test('usage lists workspaces with quota and spend', function () {
    instanceOwnerActing();
    Workspace::factory()->create(['name' => 'Acme']);
    Workspace::factory()->create(['name' => 'Globex']);

    $this->getJson('/api/v1/settings/instance/usage?search=Acme')
        ->assertOk()
        ->assertJsonCount(1, 'workspace_usage.data')
        ->assertJsonPath('workspace_usage.data.0.name', 'Acme')
        ->assertJsonPath('filters.search', 'Acme')
        ->assertJsonPath('instance_summary.workspace_count', 2)
        ->assertJsonStructure(['pricing_currency', 'x_usage_available']);
});

test('usage drilldown returns counters, errors and the workspace quota', function () {
    instanceOwnerActing();
    $workspace = Workspace::factory()->create(['name' => 'Initech', 'is_initial' => false]);
    app(InstanceSettings::class)->setXWorkspaceBudget($workspace->id, 'unlimited');

    $this->getJson("/api/v1/settings/instance/usage/workspaces/{$workspace->id}")
        ->assertOk()
        ->assertJsonPath('workspace.id', $workspace->id)
        ->assertJsonPath('workspace.quota.kind', 'unlimited')
        ->assertJsonStructure(['counters', 'error_events']);
});

test('usage drilldown 404s on a missing workspace', function () {
    instanceOwnerActing();

    $this->getJson('/api/v1/settings/instance/usage/workspaces/missing')->assertNotFound();
});

test('workspace budget update stores an unlimited override', function () {
    instanceOwnerActing();
    $workspace = Workspace::factory()->create(['is_initial' => false]);

    $this->putJson("/api/v1/settings/instance/usage/workspaces/{$workspace->id}/budget", [
        'unlimited' => true,
        'dollars' => null,
    ])->assertOk()->assertJsonPath('quota.kind', 'unlimited');

    expect(app(InstanceSettings::class)->xWorkspaceBudget($workspace->id))->toBe('unlimited');
});

test('workspace budget update stores dollar amounts as cents', function () {
    instanceOwnerActing();
    $workspace = Workspace::factory()->create(['is_initial' => false]);

    $this->putJson("/api/v1/settings/instance/usage/workspaces/{$workspace->id}/budget", [
        'unlimited' => false,
        'dollars' => 25.5,
    ])->assertOk();

    expect(app(InstanceSettings::class)->xWorkspaceBudget($workspace->id))->toBe(2550);
});

test('x usage proxies the X API and caches the result', function () {
    config(['services.x.bearer_token' => 'token']);
    instanceOwnerActing();

    Http::fake([
        'api.x.com/*' => Http::response(['data' => ['project_usage' => 42]]),
    ]);

    $this->getJson('/api/v1/settings/instance/usage/x')
        ->assertOk()
        ->assertJsonPath('data.project_usage', 42)
        ->assertJsonStructure(['fetched_at', 'source']);
});

test('x usage fails cleanly without a bearer token', function () {
    config(['services.x.bearer_token' => '']);
    instanceOwnerActing();

    $this->getJson('/api/v1/settings/instance/usage/x')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Configure X_BEARER_TOKEN before fetching X API usage.');
});

test('admins lists owners and searches non-owner users by email', function () {
    $owner = instanceOwnerActing();
    $candidate = User::factory()->create(['email' => 'candidate@example.com']);

    $this->getJson('/api/v1/settings/instance/admins')
        ->assertOk()
        ->assertJsonCount(1, 'owners')
        ->assertJsonPath('owners.0.id', $owner->id)
        ->assertJsonCount(0, 'users');

    $this->getJson('/api/v1/settings/instance/admins?search=candidate')
        ->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.email', 'candidate@example.com');
});

test('admins search never returns existing owners', function () {
    instanceOwnerActing();
    $other = User::factory()->instanceOwner()->create(['email' => 'other-owner@example.com']);

    $this->getJson('/api/v1/settings/instance/admins?search=other-owner')
        ->assertOk()
        ->assertJsonCount(0, 'users');
});

test('store admin promotes a user to owner', function () {
    instanceOwnerActing();
    $candidate = User::factory()->create(['email' => 'new-owner@example.com']);

    $this->postJson('/api/v1/settings/instance/admins', ['email' => 'new-owner@example.com'])
        ->assertCreated()
        ->assertJsonPath('owner.email', 'new-owner@example.com');

    expect($candidate->fresh()->isInstanceOwner())->toBeTrue();
});

test('store admin rejects emails that are not users or already owners', function () {
    instanceOwnerActing();
    User::factory()->instanceOwner()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/settings/instance/admins', ['email' => 'nobody@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/settings/instance/admins', ['email' => 'taken@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('destroy admin removes the owner role', function () {
    instanceOwnerActing();
    $other = User::factory()->instanceOwner()->create();

    $this->deleteJson("/api/v1/settings/instance/admins/{$other->id}")
        ->assertOk()
        ->assertJsonPath('removed', true);

    expect($other->fresh()->isInstanceOwner())->toBeFalse();
});

test('destroy admin rejects removing yourself', function () {
    $owner = instanceOwnerActing();
    User::factory()->instanceOwner()->create();

    $this->deleteJson("/api/v1/settings/instance/admins/{$owner->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('owner');
});

test('destroy admin 404s when the target is not an owner', function () {
    instanceOwnerActing();
    $member = User::factory()->create();

    $this->deleteJson("/api/v1/settings/instance/admins/{$member->id}")
        ->assertNotFound();
});
