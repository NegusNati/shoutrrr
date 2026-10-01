<?php

use App\Models\User;

it('shares feedback=false when the feature is off', function () {
    config(['feedback.enabled' => false, 'feedback.webhook_url' => null]);
    $user = User::factory()->withWorkspace()->create();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('features.feedback', false);
});

it('shares feedback=true when enabled and webhook is set', function () {
    config(['feedback.enabled' => true, 'feedback.webhook_url' => 'https://discord.com/api/webhooks/1/tok']);
    $user = User::factory()->withWorkspace()->create();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('features.feedback', true);
});
