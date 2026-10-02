<?php

use App\Enums\InstanceRole;
use App\Models\User;
use App\Support\InstanceSettings;

it('lets an instance owner enable usage tracking', function () {
    $owner = User::factory()->create(['instance_role' => InstanceRole::Owner->value]);

    $this->actingAs($owner)->put('/api/v1/instance-settings', [
        'registrations_enabled' => false,
        'workspace_creation_enabled' => true,
        'usage_tracking_enabled' => true,
        'quote_tweets_enabled' => false,
    ])->assertOk();

    expect(app(InstanceSettings::class)->usageTrackingEnabled())->toBeTrue();
});

it('rejects a missing usage_tracking_enabled field', function () {
    $owner = User::factory()->create(['instance_role' => InstanceRole::Owner->value]);

    $this->actingAs($owner)->put('/api/v1/instance-settings', [
        'registrations_enabled' => false,
        'workspace_creation_enabled' => true,
    ])->assertJsonValidationErrors('usage_tracking_enabled');
});
