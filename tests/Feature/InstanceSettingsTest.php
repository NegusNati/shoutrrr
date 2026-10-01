<?php

use App\Enums\InstanceRole;
use App\Enums\Platform;
use App\Enums\UsageCategory;
use App\Models\UsageEvent;
use App\Models\UsagePeriodCounter;
use App\Models\User;
use App\Models\Workspace;
use App\Support\InstanceSettings;
use App\Support\UsageOperation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

test('instance owner can view instance settings', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->get(route('instance-settings.edit'))
        ->assertRedirect('/app/settings/instance');
});

test('regular users cannot view instance settings', function () {
    $user = User::factory()->withWorkspace()->create();

    $this->actingAs($user)
        ->get(route('instance-settings.edit'))
        ->assertRedirect('/app/settings/instance');

    $this->actingAs($user)
        ->getJson('/api/v1/instance-settings')
        ->assertForbidden();
});

test('instance owner can update instance settings', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->putJson('/api/v1/instance-settings', [
            'registrations_enabled' => false,
            'workspace_creation_enabled' => false,
            'usage_tracking_enabled' => false,
            'quote_tweets_enabled' => false,
        ])
        ->assertOk();

    expect(app(InstanceSettings::class)->registrationsEnabled())->toBeFalse()
        ->and(app(InstanceSettings::class)->workspaceCreationEnabled())->toBeFalse();
});

test('workspace creation setting is disabled when workspaces are globally disabled', function () {
    config(['kit.workspaces.enabled' => false]);

    $owner = User::factory()->instanceOwner()->withWorkspace()->create();
    app(InstanceSettings::class)->update([
        'workspace_creation_enabled' => true,
    ]);

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings')
        ->assertOk()
        ->assertJsonPath('workspaces_enabled', false)
        ->assertJsonPath('settings.workspace_creation_enabled', false);
});

test('workspace creation setting cannot be enabled when workspaces are globally disabled', function () {
    config(['kit.workspaces.enabled' => false]);

    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->putJson('/api/v1/instance-settings', [
            'registrations_enabled' => true,
            'workspace_creation_enabled' => true,
            'usage_tracking_enabled' => false,
            'quote_tweets_enabled' => false,
        ])
        ->assertOk();

    expect(app(InstanceSettings::class)->workspaceCreationEnabled())->toBeFalse();
});

test('linkedin engagement polling is gated on the community management setting', function () {
    $settings = app(InstanceSettings::class);

    // Off by default: LinkedIn must never be polled for replies (every fetch 403s
    // without the restricted r_member_social_feed scope).
    expect($settings->engagementPollingEnabled(Platform::LinkedIn))->toBeFalse();

    $settings->update(['linkedin_community_management_enabled' => true]);

    expect($settings->engagementPollingEnabled(Platform::LinkedIn))->toBeTrue();
});

test('instance owner can view polling settings', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/polling')
        ->assertOk()
        ->assertJsonPath('settings.engagement.enabled.x', true)
        ->assertJsonPath('settings.engagement.enabled.bluesky', true)
        ->assertJsonPath('settings.engagement.x', 360)
        ->assertJsonPath('settings.engagement.bluesky', 15)
        ->assertJsonPath('settings.post_metrics.enabled.x', true)
        ->assertJsonPath('settings.post_metrics.enabled.linkedin', true)
        ->assertJsonPath('settings.post_metrics.x', 360)
        ->assertJsonPath('settings.post_metrics.linkedin', 15)
        ->assertJsonPath('settings.account_metrics.enabled.x', true)
        ->assertJsonPath('settings.account_metrics.enabled.bluesky', true)
        ->assertJsonPath('settings.account_metrics.x', 1440)
        ->assertJsonPath('settings.account_metrics.bluesky', 1440)
        ->assertJsonPath('settings.account_metrics.linkedin', 1440);
});

test('instance owner can view usage details', function () {
    config()->set('services.x.bearer_token', 'x-bearer-token');

    $owner = User::factory()->instanceOwner()->withWorkspace()->create();
    $workspace = Workspace::factory()->create(['name' => 'Usage Workspace', 'is_initial' => false]);

    UsagePeriodCounter::factory()->create([
        'workspace_id' => $workspace->id,
        'category' => UsageCategory::Publish->value,
        'platform' => Platform::X->value,
        'operation' => UsageOperation::POST,
        'event_count' => 2,
        'total_quota' => 2,
    ]);

    UsagePeriodCounter::factory()->create([
        'workspace_id' => $workspace->id,
        'period_start' => Date::now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
        'period_end' => Date::now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        'category' => UsageCategory::Publish->value,
        'platform' => Platform::X->value,
        'operation' => UsageOperation::POST,
        'event_count' => 1,
        'total_quota' => 1,
    ]);

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/usage')
        ->assertOk()
        ->assertJsonPath('filters.workspace', null)
        ->assertJsonPath('x_usage_available', true)
        ->assertJsonPath('instance_summary.workspace_count', 2)
        ->assertJsonPath('instance_summary.x_estimated_cost_usd', 0.03)
        ->assertJsonCount(2, 'workspace_usage.data')
        ->assertJsonPath('workspace_usage.data.0.name', 'Usage Workspace')
        ->assertJsonPath('workspace_usage.data.0.x_estimated_cost_usd', 0.03)
        ->assertJsonPath('workspace_usage.data.0.x_previous_cost_usd', 0.015)
        ->assertJsonPath('workspace_usage.data.0.quota.kind', 'default');
});

test('instance usage marks x api usage unavailable without bearer token', function () {
    config()->set('services.x.bearer_token', '');

    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/usage')
        ->assertOk()
        ->assertJsonPath('x_usage_available', false);
});

test('instance usage drilldown scopes counters and error events to the selected workspace', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();
    $shownWorkspace = Workspace::factory()->create(['name' => 'Shown Workspace']);
    $hiddenWorkspace = Workspace::factory()->create(['name' => 'Hidden Workspace']);

    UsagePeriodCounter::factory()->create(['workspace_id' => $shownWorkspace->id]);
    UsagePeriodCounter::factory()->create(['workspace_id' => $hiddenWorkspace->id]);
    UsageEvent::factory()->create(['workspace_id' => $shownWorkspace->id, 'succeeded' => false]);
    UsageEvent::factory()->create(['workspace_id' => $hiddenWorkspace->id, 'succeeded' => false]);

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/usage')
        ->assertOk()
        ->assertJson(fn ($json) => $json->missing('drilldown')->etc());

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/usage?workspace='.$shownWorkspace->id)
        ->assertOk()
        ->assertJsonPath('filters.workspace', $shownWorkspace->id)
        ->assertJsonPath('drilldown.workspace.id', $shownWorkspace->id)
        ->assertJsonCount(1, 'drilldown.counters')
        ->assertJsonCount(1, 'drilldown.error_events');
});

test('instance usage does not override shared workspace shell props', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    // Guards the shared `instance.isOwner` prop (used by the sidebar and command
    // palette to show instance settings) against being clobbered by the page's
    // own usage-summary data, which is exposed separately as `instance_summary`.
    $this->actingAs($owner)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('instance.isOwner', true)
        ->assertJsonStructure(['workspaces' => ['enabled', 'current', 'all']]);

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/usage')
        ->assertOk()
        ->assertJsonStructure(['instance_summary' => ['workspace_count']]);
});

test('instance usage includes x pricing estimates', function () {
    config(['usage_pricing.platforms.x.currency' => 'EUR']);

    $owner = User::factory()->instanceOwner()->withWorkspace()->create();
    $workspace = Workspace::factory()->create(['is_initial' => false]);

    UsagePeriodCounter::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::X->value,
        'operation' => UsageOperation::POST,
        'event_count' => 2,
        'total_quota' => 2,
    ]);
    UsagePeriodCounter::factory()->create([
        'workspace_id' => $workspace->id,
        'period_start' => Date::now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
        'period_end' => Date::now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        'platform' => Platform::X->value,
        'operation' => UsageOperation::POST,
        'event_count' => 1,
        'total_quota' => 1,
    ]);

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/usage')
        ->assertOk()
        ->assertJsonPath('pricing_source', 'https://developer.x.com/#pricing')
        ->assertJsonPath('pricing_currency', 'EUR')
        ->assertJsonPath('workspace_usage.data.0.x_estimated_cost_usd', 0.03)
        ->assertJsonPath('workspace_usage.data.0.x_previous_cost_usd', 0.015)
        ->assertJsonPath('workspace_usage.data.0.x_cost_delta_usd', 0.015)
        ->assertJsonPath('instance_summary.x_estimated_cost_usd', 0.03);
});

test('instance owner can fetch x api usage', function () {
    config()->set('services.x.bearer_token', 'x-bearer-token');
    Cache::flush();

    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    Http::fake([
        'https://api.x.com/2/usage/tweets*' => Http::response([
            'data' => [
                'project_id' => '1234567890',
                'project_usage' => 15420,
                'project_cap' => 2000000,
                'cap_reset_day' => 12,
            ],
        ]),
    ]);

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/usage/x')
        ->assertOk()
        ->assertJsonPath('data.project_usage', 15420)
        ->assertJsonPath('source', 'https://api.x.com/2/usage/tweets');

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/usage/x')
        ->assertOk()
        ->assertJsonPath('data.project_usage', 15420)
        ->assertJsonPath('source', 'https://api.x.com/2/usage/tweets');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.x.com/2/usage/tweets?days=7&usage.fields=cap_reset_day%2Cdaily_client_app_usage%2Cdaily_project_usage%2Cproject_cap%2Cproject_id%2Cproject_usage'
        && $request->hasHeader('Authorization', 'Bearer x-bearer-token'));
    Http::assertSentCount(1);
});

test('x api usage fetch requires a configured bearer token', function () {
    config()->set('services.x.bearer_token', '');

    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/usage/x')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Configure X_BEARER_TOKEN before fetching X API usage.');
});

test('regular users cannot fetch x api usage', function () {
    config()->set('services.x.bearer_token', 'x-bearer-token');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/instance-settings/usage/x')
        ->assertForbidden();
});

test('analytics exposes disabled metric polling groups', function () {
    app(InstanceSettings::class)->update([
        'post_metrics_polling_enabled' => false,
        'account_metrics_polling_enabled' => false,
    ]);

    $workspace = Workspace::factory()->create();
    $owner = User::factory()->instanceOwner()->create(['current_workspace_id' => $workspace->id]);
    $owner->workspaceMemberships()->create([
        'workspace_id' => $workspace->id,
        'role' => 'owner',
    ]);

    $this->actingAs($owner)
        ->getJson('/api/v1/analytics')
        ->assertOk()
        ->assertJsonPath('polling.post_metrics_enabled.x', false)
        ->assertJsonPath('polling.post_metrics_enabled.bluesky', false)
        ->assertJsonPath('polling.account_metrics_enabled.x', false)
        ->assertJsonPath('polling.account_metrics_enabled.bluesky', false);
});

test('regular users cannot view usage details', function () {
    $user = User::factory()->withWorkspace()->create();

    $this->actingAs($user)
        ->get(route('instance-settings.usage'))
        ->assertRedirect('/app/settings/instance/usage');

    $this->actingAs($user)
        ->getJson('/api/v1/instance-settings/usage')
        ->assertForbidden();
});

test('instance owner can update polling settings', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->putJson('/api/v1/instance-settings/polling', [
            'engagement' => [
                'enabled' => ['x' => false, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true],
                'x' => 720, 'bluesky' => 30, 'linkedin' => 120, 'facebook' => 15, 'instagram' => 15, 'threads' => 15,
            ],
            'post_metrics' => [
                'enabled' => ['x' => false, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true, 'discord' => true],
                'x' => 1440, 'bluesky' => 45, 'linkedin' => 15, 'facebook' => 15, 'instagram' => 15, 'threads' => 15, 'discord' => 90,
            ],
            'account_metrics' => [
                'enabled' => ['x' => false, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true],
                'x' => 1440, 'bluesky' => 240, 'linkedin' => 1440, 'facebook' => 15, 'instagram' => 15, 'threads' => 15,
            ],
            'metrics_enabled' => true,
            'engagement_enabled' => true,
            'messages_enabled' => true,
            'direct_messages_enabled' => true,
        ])
        ->assertOk();

    $polling = app(InstanceSettings::class)->polling();

    // Every sent platform's enabled flag and interval persisted correctly, section by section.
    expect($polling['engagement']['enabled'])->toMatchArray([
        'x' => false, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true,
    ])
        ->and($polling['engagement'])->toMatchArray([
            'x' => 720, 'bluesky' => 30, 'linkedin' => 120, 'facebook' => 15, 'instagram' => 15, 'threads' => 15,
        ])
        ->and($polling['post_metrics']['enabled'])->toMatchArray([
            'x' => false, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true, 'discord' => true,
        ])
        ->and($polling['post_metrics'])->toMatchArray([
            'x' => 1440, 'bluesky' => 45, 'linkedin' => 15, 'facebook' => 15, 'instagram' => 15, 'threads' => 15, 'discord' => 90,
        ])
        ->and($polling['account_metrics']['enabled'])->toMatchArray([
            'x' => false, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true,
        ])
        ->and($polling['account_metrics'])->toMatchArray([
            'x' => 1440, 'bluesky' => 240, 'linkedin' => 1440, 'facebook' => 15, 'instagram' => 15, 'threads' => 15,
        ]);
});

test('instance owner can toggle the metrics and engagement master switches from the polling page', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->putJson('/api/v1/instance-settings/polling', [
            'engagement' => [
                'enabled' => ['x' => true, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true],
                'x' => 360, 'bluesky' => 15, 'linkedin' => 15, 'facebook' => 15, 'instagram' => 15, 'threads' => 15,
            ],
            'post_metrics' => [
                'enabled' => ['x' => true, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true, 'discord' => true],
                'x' => 360, 'bluesky' => 15, 'linkedin' => 15, 'facebook' => 15, 'instagram' => 15, 'threads' => 15, 'discord' => 15,
            ],
            'account_metrics' => [
                'enabled' => ['x' => true, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true],
                'x' => 1440, 'bluesky' => 1440, 'linkedin' => 1440, 'facebook' => 15, 'instagram' => 15, 'threads' => 15,
            ],
            'metrics_enabled' => false,
            'engagement_enabled' => false,
            'messages_enabled' => false,
            'direct_messages_enabled' => false,
        ])
        ->assertOk();

    expect(app(InstanceSettings::class)->metricsEnabled())->toBeFalse()
        ->and(app(InstanceSettings::class)->engagementEnabled())->toBeFalse()
        ->and(app(InstanceSettings::class)->messagesEnabled())->toBeFalse();
});

test('instance owner can toggle the messages master switch from the polling page', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    expect(app(InstanceSettings::class)->messagesEnabled())->toBeTrue();

    $this->actingAs($owner)
        ->putJson('/api/v1/instance-settings/polling', [
            'engagement' => [
                'enabled' => ['x' => true, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true],
                'x' => 360, 'bluesky' => 15, 'linkedin' => 15, 'facebook' => 15, 'instagram' => 15, 'threads' => 15,
            ],
            'post_metrics' => [
                'enabled' => ['x' => true, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true, 'discord' => true],
                'x' => 360, 'bluesky' => 15, 'linkedin' => 15, 'facebook' => 15, 'instagram' => 15, 'threads' => 15, 'discord' => 15,
            ],
            'account_metrics' => [
                'enabled' => ['x' => true, 'bluesky' => true, 'linkedin' => true, 'facebook' => true, 'instagram' => true, 'threads' => true],
                'x' => 1440, 'bluesky' => 1440, 'linkedin' => 1440, 'facebook' => 15, 'instagram' => 15, 'threads' => 15,
            ],
            'metrics_enabled' => true,
            'engagement_enabled' => true,
            'messages_enabled' => false,
            'direct_messages_enabled' => true,
        ])
        ->assertOk();

    // Turning messages off also stops asking connecting users for DM scopes.
    expect(app(InstanceSettings::class)->messagesEnabled())->toBeFalse()
        ->and(app(InstanceSettings::class)->directMessagesEnabled())->toBeFalse()
        ->and(app(InstanceSettings::class)->engagementEnabled())->toBeTrue();
});

test('regular users cannot view polling settings', function () {
    $user = User::factory()->withWorkspace()->create();

    $this->actingAs($user)
        ->get(route('instance-settings.polling'))
        ->assertRedirect('/app/settings/instance/polling');

    $this->actingAs($user)
        ->getJson('/api/v1/instance-settings/polling')
        ->assertForbidden();
});

test('instance owner can view instance admins and search registered users by email', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create(['email' => 'owner@example.com']);
    $matchingUser = User::factory()->create(['email' => 'admin-candidate@example.com']);
    User::factory()->create(['email' => 'other@example.com']);

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/admins?search=candidate')
        ->assertOk()
        ->assertJsonPath('owners.0.email', 'owner@example.com')
        ->assertJsonPath('search', 'candidate')
        ->assertJsonPath('users.0.id', $matchingUser->id)
        ->assertJsonPath('users.0.email', 'admin-candidate@example.com')
        ->assertJsonCount(1, 'users');
});

test('regular users cannot view instance admins', function () {
    $user = User::factory()->withWorkspace()->create();

    $this->actingAs($user)
        ->get(route('instance-settings.admins'))
        ->assertRedirect('/app/settings/instance/admins');

    $this->actingAs($user)
        ->getJson('/api/v1/instance-settings/admins')
        ->assertForbidden();
});

test('instance owner can add another registered user as an instance owner', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();
    $candidate = User::factory()->create(['email' => 'candidate@example.com']);

    $this->actingAs($owner)
        ->postJson('/api/v1/instance-settings/admins', [
            'email' => 'candidate@example.com',
        ])
        ->assertCreated();

    expect($candidate->fresh()->instance_role)->toBe(InstanceRole::Owner);
});

test('instance owner cannot add a missing user as an instance owner', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->postJson('/api/v1/instance-settings/admins', [
            'email' => 'missing@example.com',
        ])
        ->assertJsonValidationErrors('email');
});

test('instance owner can remove another instance owner', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();
    $otherOwner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/instance-settings/admins/{$otherOwner->id}")
        ->assertOk();

    expect($otherOwner->fresh()->instance_role)->toBeNull();
});

test('instance owner cannot remove the last instance owner', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/instance-settings/admins/{$owner->id}")
        ->assertJsonValidationErrors('owner');

    expect($owner->fresh()->instance_role)->toBe(InstanceRole::Owner);
});

test('instance owner cannot remove themselves while another owner exists', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();
    User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/instance-settings/admins/{$owner->id}")
        ->assertJsonValidationErrors('owner');

    expect($owner->fresh()->instance_role)->toBe(InstanceRole::Owner);
});
