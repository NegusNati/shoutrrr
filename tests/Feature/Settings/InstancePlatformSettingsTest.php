<?php

use App\Enums\Platform;
use App\Models\User;
use App\Support\InstanceSettings;

it('defaults every platform to available', function () {
    $settings = app(InstanceSettings::class);

    expect($settings->platformAvailable(Platform::X))->toBeTrue();
    expect($settings->platformsEnabled())->toBe([
        'x' => true,
        'bluesky' => true,
        'linkedin' => true,
        'facebook' => true,
        'instagram' => true,
        'threads' => true,
        'discord' => true,
    ]);
});

it('freezes a single platform while leaving the rest available', function () {
    $settings = app(InstanceSettings::class);
    $settings->update(['platforms_enabled' => ['x' => false]]);

    expect($settings->platformAvailable(Platform::X))->toBeFalse();
    expect($settings->platformAvailable(Platform::Bluesky))->toBeTrue();
});

it('stops polling for a frozen platform regardless of the polling toggle', function () {
    $settings = app(InstanceSettings::class);
    $settings->update([
        'engagement_polling_enabled' => ['x' => true],
        'platforms_enabled' => ['x' => false],
    ]);

    expect($settings->engagementPollingEnabled(Platform::X))->toBeFalse();
});

it('lets an owner view the platforms page', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->get(route('instance-settings.platforms'))
        ->assertRedirect('/app/settings/instance/platforms');

    $this->actingAs($owner)
        ->getJson('/api/v1/instance-settings/platforms')
        ->assertOk()
        ->assertJsonCount(7, 'platforms')
        ->assertJsonPath('linkedin_community_management_enabled', false);
});

it('forbids a non-owner from the platforms page', function () {
    $user = User::factory()->withWorkspace()->create(['instance_role' => null]);

    $this->actingAs($user)
        ->getJson('/api/v1/instance-settings/platforms')
        ->assertForbidden();
});

it('persists platform toggles for an owner', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->putJson('/api/v1/instance-settings/platforms', [
            'platforms' => [
                'x' => false,
                'bluesky' => true,
                'linkedin' => true,
                'facebook' => true,
                'instagram' => true,
                'threads' => true,
                'discord' => true,
            ],
            'linkedin_community_management_enabled' => false,
        ])
        ->assertOk();

    expect(app(InstanceSettings::class)->platformAvailable(Platform::X))->toBeFalse();
});

it('persists the linkedin community management toggle from the platforms page', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    expect(app(InstanceSettings::class)->linkedinCommunityManagementEnabled())->toBeFalse();

    $this->actingAs($owner)
        ->putJson('/api/v1/instance-settings/platforms', [
            'platforms' => [
                'x' => true,
                'bluesky' => true,
                'linkedin' => true,
                'facebook' => true,
                'instagram' => true,
                'threads' => true,
                'discord' => true,
            ],
            'linkedin_community_management_enabled' => true,
        ])
        ->assertOk();

    expect(app(InstanceSettings::class)->linkedinCommunityManagementEnabled())->toBeTrue();
});

it('rejects a platforms update missing the linkedin community management field', function () {
    $owner = User::factory()->instanceOwner()->withWorkspace()->create();

    $this->actingAs($owner)
        ->putJson('/api/v1/instance-settings/platforms', [
            'platforms' => [
                'x' => true,
                'bluesky' => true,
                'linkedin' => true,
                'facebook' => true,
                'instagram' => true,
                'threads' => true,
                'discord' => true,
            ],
        ])
        ->assertJsonValidationErrors('linkedin_community_management_enabled');
});
