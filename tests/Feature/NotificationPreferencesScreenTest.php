<?php

use App\Enums\NotificationType;
use App\Models\User;

test('preferences screen renders with current values', function () {
    $user = User::factory()->withWorkspace()->create();

    $this->actingAs($user)
        ->get(route('notifications.preferences'))
        ->assertRedirect('/app/settings/notifications');

    $this->actingAs($user)
        ->getJson('/api/v1/settings/notifications')
        ->assertOk()
        ->assertJsonStructure(['preferences']);
});

test('updating preferences persists the matrix', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson("/api/v1/settings/notifications", [
            'preferences' => [
                'post_published' => ['in_app' => false, 'mail' => false],
                'publish_failed' => ['in_app' => false, 'mail' => true],
                'workspace_invite' => ['in_app' => true, 'mail' => true],
                'account_needs_attention' => ['in_app' => true, 'mail' => false],
            ],
        ])
        ->assertOk();

    $prefs = $user->fresh()->notificationPreferences();
    expect($prefs->allows(NotificationType::PostPublished, 'in_app'))->toBeFalse();
    // always-on clamp still applies on read
    expect($prefs->allows(NotificationType::PublishFailed, 'in_app'))->toBeTrue();
    expect($prefs->allows(NotificationType::AccountNeedsAttention, 'mail'))->toBeFalse();
});
