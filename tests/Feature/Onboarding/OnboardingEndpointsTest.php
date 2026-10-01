<?php

use App\Models\ConnectedAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

function onboardingActor(): User
{
    $workspace = Workspace::factory()->create([
        'onboarding_welcomed_at' => null,
        'onboarding_dismissed_at' => null,
    ]);
    $user = User::factory()->create([
        'current_workspace_id' => $workspace->id,
        'email_verified_at' => now(),
    ]);
    WorkspaceMembership::factory()->owner()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    return $user;
}

test('welcomed endpoint stamps the current workspace', function () {
    $user = onboardingActor();

    $this->actingAs($user)->postJson('/api/v1/onboarding/welcomed')
        ->assertOk()->assertJsonPath('connect_url', null);

    expect($user->currentWorkspace->fresh()->onboarding_welcomed_at)->not->toBeNull();
});

test('dismiss endpoint stamps the current workspace', function () {
    $user = onboardingActor();
    ConnectedAccount::factory()->create(['workspace_id' => $user->current_workspace_id]);

    $this->actingAs($user)->postJson('/api/v1/onboarding/dismiss')->assertNoContent();

    expect($user->currentWorkspace->fresh()->onboarding_dismissed_at)->not->toBeNull();
});

test('dismiss endpoint is blocked until an account is connected', function () {
    $user = onboardingActor();

    $this->actingAs($user)->postJson('/api/v1/onboarding/dismiss')->assertStatus(409);

    expect($user->currentWorkspace->fresh()->onboarding_dismissed_at)->toBeNull();
});

test('welcomed with connect redirects to accounts and stamps the workspace', function () {
    $user = onboardingActor();

    $this->actingAs($user)
        ->postJson('/api/v1/onboarding/welcomed', ['connect' => true])
        ->assertOk()->assertJsonPath('connect_url', route('accounts.index'));

    expect($user->currentWorkspace->fresh()->onboarding_welcomed_at)->not->toBeNull();
});

test('completing the timezone step records it and redirects to workspace settings', function () {
    $user = onboardingActor();

    $this->actingAs($user)
        ->postJson('/api/v1/onboarding/steps/complete', ['key' => 'timezone'])
        ->assertOk()->assertJsonPath('redirect_url', route('settings.workspace'));

    expect($user->currentWorkspace->fresh()->onboarding_progress)->toContain('timezone');
});

test('completing the timezone step is idempotent', function () {
    $user = onboardingActor();
    $user->currentWorkspace->forceFill(['onboarding_progress' => ['timezone']])->save();

    $this->actingAs($user)->postJson('/api/v1/onboarding/steps/complete', ['key' => 'timezone'])->assertOk();

    expect($user->currentWorkspace->fresh()->onboarding_progress)->toBe(['timezone']);
});

test('an unknown step key is rejected', function () {
    $user = onboardingActor();

    $this->actingAs($user)->postJson('/api/v1/onboarding/steps/complete', ['key' => 'nope'])->assertNotFound();
});

test('a data-derived step cannot be completed by clicking', function () {
    $user = onboardingActor();

    // connect_account is not click-to-complete — the endpoint rejects it.
    $this->actingAs($user)->postJson('/api/v1/onboarding/steps/complete', ['key' => 'connect_account'])->assertNotFound();

    expect($user->currentWorkspace->fresh()->onboarding_progress)->toBeNull();
});

test('welcome modal connect CTA redirects to accounts without recording progress', function () {
    $user = onboardingActor();

    $this->actingAs($user)
        ->postJson('/api/v1/onboarding/welcomed', ['connect' => true])
        ->assertOk()->assertJsonPath('connect_url', route('accounts.index'));

    expect($user->currentWorkspace->fresh()->onboarding_progress)->toBeNull();
});

test('a member without the timezone permission cannot complete it', function () {
    $workspace = Workspace::factory()->create();
    $member = User::factory()->create([
        'current_workspace_id' => $workspace->id,
        'email_verified_at' => now(),
    ]);
    WorkspaceMembership::factory()->create([ // default role = member (read-only)
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);

    $this->actingAs($member)->postJson('/api/v1/onboarding/steps/complete', ['key' => 'timezone'])->assertForbidden();

    expect($workspace->fresh()->onboarding_progress)->toBeNull();
});

test('guests cannot hit the onboarding endpoints', function () {
    $this->postJson('/api/v1/onboarding/welcomed')->assertUnauthorized();
});
