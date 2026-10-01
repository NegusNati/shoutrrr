<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\Api\ApiKeyManager;

/*
|--------------------------------------------------------------------------
| Email-verification gate — /api/v1
|--------------------------------------------------------------------------
|
| Every legacy mutating route sat behind `verified`; the API must mirror it.
| The only unverified-reachable surface is the SPA bootstrap (/me,
| notifications) and the auth-only settings tail — exactly as the legacy web
| groups allowed.
|
*/

function unverifiedOwner(): array
{
    // Verification is instance-toggled: hasVerifiedEmail() returns true when the
    // feature is off (no mailer), so the gate only bites when enabled.
    config(['auth.email_verification.enabled' => true]);

    $user = User::factory()->unverified()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    test()->actingAs($user);

    return [$user, $workspace];
}

test('unverified users can still load the SPA bootstrap endpoints', function (): void {
    unverifiedOwner();

    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/v1/notifications')->assertOk();
});

test('unverified users keep the auth-only settings surface', function (): void {
    unverifiedOwner();

    $this->getJson('/api/v1/settings/profile')->assertOk();
    $this->getJson('/api/v1/settings/connections')->assertOk();
    $this->getJson('/api/v1/settings/notifications')->assertOk();
    $this->getJson('/api/v1/settings/workspace')->assertOk();
});

test('unverified users are forbidden from workspace data endpoints', function (string $method, string $uri): void {
    unverifiedOwner();

    $this->json($method, $uri)->assertForbidden();
})->with([
    ['GET', '/api/v1/dashboard'],
    ['GET', '/api/v1/workspaces'],
    ['POST', '/api/v1/workspaces'],
    ['POST', '/api/v1/workspaces/switch'],
    ['POST', '/api/v1/onboarding/welcomed'],
    ['GET', '/api/v1/workspace-mentions'],
    ['GET', '/api/v1/posts'],
    ['POST', '/api/v1/posts'],
    ['GET', '/api/v1/account-sets'],
    ['GET', '/api/v1/calendar'],
    ['GET', '/api/v1/posting-schedule'],
    ['PUT', '/api/v1/posting-schedule'],
    ['GET', '/api/v1/engagement'],
    ['GET', '/api/v1/messages'],
]);

test('unverified users are forbidden from the verified-only settings tail', function (): void {
    unverifiedOwner();

    $this->deleteJson('/api/v1/settings/profile')->assertForbidden();
    $this->putJson('/api/v1/settings/password', [])->assertForbidden();
    $this->getJson('/api/v1/settings/security')->assertForbidden();
    $this->getJson('/api/v1/settings/workspace/subscription')->assertForbidden();
    $this->postJson('/api/v1/billing/checkout', [])->assertForbidden();
    $this->postJson('/api/v1/billing/portal', [])->assertForbidden();
});

test('the confirmed-password gate still applies on top of verified', function (): void {
    [$user] = ownerActingIn();

    // Verified but password-unconfirmed session: 403 -> verified ok -> 423.
    $this->getJson('/api/v1/settings/security')->assertStatus(423);
});

test('unverified api-key owners are forbidden too', function (): void {
    config(['auth.email_verification.enabled' => true]);

    $keyOwner = User::factory()->unverified()->create();

    // Re-issue a key for an unverified user by forging the membership.
    $workspace = Workspace::factory()->create();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $keyOwner->id,
        'role' => WorkspaceRole::Member,
    ]);
    [, $plain] = app(ApiKeyManager::class)
        ->issue($workspace, $keyOwner, 'unverified', 'write', null);

    $this->withToken($plain)->getJson('/api/v1/dashboard')->assertForbidden();
    $this->withToken($plain)->postJson('/api/v1/posts', [])->assertForbidden();
    // Bootstrap remains open for api keys too.
    $this->withToken($plain)->getJson('/api/v1/me')->assertOk();
});

test('verified users reach the same endpoints', function (): void {
    ownerActingIn();

    $this->getJson('/api/v1/dashboard')->assertOk();
    $this->getJson('/api/v1/posts')->assertOk();
    $this->getJson('/api/v1/workspaces')->assertOk();
});
