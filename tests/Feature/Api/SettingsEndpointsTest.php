<?php

use App\Enums\SocialProvider;
use App\Enums\WorkspaceRole;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Settings API — session-only endpoints for user settings
|--------------------------------------------------------------------------
|
| Profile, security, connections and notification preferences belong to the
| signed-in user, not a workspace, so every action rejects Passport API
| keys while session users get the full surface.
|
*/

test('settings endpoints are unauthorized without credentials', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'profile read-through patch' => ['PATCH', '/api/v1/settings/profile'],
    'profile delete' => ['DELETE', '/api/v1/settings/profile'],
    'security' => ['GET', '/api/v1/settings/security'],
    'password' => ['PUT', '/api/v1/settings/password'],
    'connections' => ['GET', '/api/v1/settings/connections'],
    'notifications' => ['GET', '/api/v1/settings/notifications'],
    'notifications update' => ['PUT', '/api/v1/settings/notifications'],
]);

test('api keys cannot reach the settings endpoints', function (string $method, string $uri) {
    [, , $token] = issuedKey();

    $this->withToken($token)->json($method, $uri)->assertForbidden();
})->with([
    'profile update' => ['PATCH', '/api/v1/settings/profile'],
    'profile delete' => ['DELETE', '/api/v1/settings/profile'],
    'security' => ['GET', '/api/v1/settings/security'],
    'password' => ['PUT', '/api/v1/settings/password'],
    'connections' => ['GET', '/api/v1/settings/connections'],
    'notifications' => ['GET', '/api/v1/settings/notifications'],
    'notifications update' => ['PUT', '/api/v1/settings/notifications'],
]);

test('profile update changes name and email', function () {
    [$user] = ownerActingIn();

    $this->patchJson('/api/v1/settings/profile', [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ])->assertOk()->assertJsonPath('user.name', 'New Name');

    expect($user->fresh()->email)->toBe('new@example.com');
});

test('profile update works via the post + _method spoof the SPA sends for photo uploads', function () {
    [$user] = ownerActingIn();

    $this->post('/api/v1/settings/profile', [
        '_method' => 'PATCH',
        'name' => 'Spoofed Name',
        'email' => $user->email,
    ])->assertOk()->assertJsonPath('user.name', 'Spoofed Name');

    expect($user->fresh()->name)->toBe('Spoofed Name');
});

test('profile update validates the payload', function () {
    ownerActingIn();

    $this->patchJson('/api/v1/settings/profile', [
        'name' => '',
        'email' => 'not-an-email',
    ])->assertStatus(422);
});

test('profile delete requires the current password', function () {
    [$user] = ownerActingIn();
    $user->forceFill(['password' => Hash::make('correct-password')])->save();

    $this->deleteJson('/api/v1/settings/profile', ['password' => 'wrong-password'])
        ->assertStatus(422);

    expect($user->fresh())->not->toBeNull();
});

test('profile delete removes the user and their sole-member workspaces', function () {
    [$user, $workspace] = ownerActingIn();
    $user->forceFill(['password' => Hash::make('correct-password')])->save();

    $this->deleteJson('/api/v1/settings/profile', ['password' => 'correct-password'])
        ->assertOk();

    expect($user->fresh())->toBeNull();
    expect(Workspace::find($workspace->id))->toBeNull();
});

test('profile delete is blocked for sole owners of multi-member workspaces', function () {
    [$user, $workspace] = ownerActingIn();
    $user->forceFill(['password' => Hash::make('correct-password')])->save();
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => User::factory()->create()->id,
        'role' => WorkspaceRole::Admin,
    ]);

    $this->deleteJson('/api/v1/settings/profile', ['password' => 'correct-password'])
        ->assertStatus(422);

    expect($user->fresh())->not->toBeNull();
});

test('security payload exposes password rules and two-factor flags', function () {
    ownerActingIn();

    $this->getJson('/api/v1/settings/security')
        ->assertOk()
        ->assertJsonStructure([
            'canManageTwoFactor',
            'canManagePasskeys',
            'passkeys',
            'passwordRules',
        ]);
});

test('password update changes the stored password', function () {
    [$user] = ownerActingIn();
    $user->forceFill(['password' => Hash::make('old-password')])->save();

    $this->putJson('/api/v1/settings/password', [
        'current_password' => 'old-password',
        'password' => 'new-secure-password-1234',
        'password_confirmation' => 'new-secure-password-1234',
    ])->assertOk();

    expect(Hash::check('new-secure-password-1234', $user->fresh()->password))->toBeTrue();
});

test('password update rejects a wrong current password', function () {
    [$user] = ownerActingIn();
    $user->forceFill(['password' => Hash::make('old-password')])->save();

    $this->putJson('/api/v1/settings/password', [
        'current_password' => 'wrong',
        'password' => 'new-secure-password-1234',
        'password_confirmation' => 'new-secure-password-1234',
    ])->assertStatus(422);
});

test('connections lists enabled providers and the password flag', function () {
    config(['kit.auth.socialite.enabled' => true, 'kit.auth.socialite.providers' => ['google', 'x']]);
    [$user] = ownerActingIn();
    SocialAccount::factory()->for($user)->forProvider(SocialProvider::Google)->create();

    $this->getJson('/api/v1/settings/connections')
        ->assertOk()
        ->assertJsonPath('hasPassword', true)
        ->assertJsonPath('connections.0.provider', 'google')
        ->assertJsonPath('connections.0.connected', true)
        ->assertJsonPath('connections.1.provider', 'x')
        ->assertJsonPath('connections.1.connected', false);
});

test('connections destroy unlinks an account the user owns', function () {
    config(['kit.auth.socialite.enabled' => true, 'kit.auth.socialite.providers' => ['google']]);
    [$user] = ownerActingIn();
    $socialAccount = SocialAccount::factory()->for($user)->forProvider(SocialProvider::Google)->create();

    $this->deleteJson("/api/v1/settings/connections/{$socialAccount->id}")
        ->assertOk();

    expect(SocialAccount::find($socialAccount->id))->toBeNull();
});

test('connections destroy cannot unlink the only sign-in method', function () {
    config(['kit.auth.socialite.enabled' => true, 'kit.auth.socialite.providers' => ['google']]);
    [$user] = ownerActingIn();
    $socialAccount = SocialAccount::factory()->for($user)->forProvider(SocialProvider::Google)->create();
    $user->forceFill(['password' => null])->save();

    $this->deleteJson("/api/v1/settings/connections/{$socialAccount->id}")
        ->assertStatus(422);

    expect(SocialAccount::find($socialAccount->id))->not->toBeNull();
});

test('connections destroy refuses another users account', function () {
    config(['kit.auth.socialite.enabled' => true, 'kit.auth.socialite.providers' => ['google']]);
    ownerActingIn();
    $foreign = SocialAccount::factory()->for(User::factory()->create())->forProvider(SocialProvider::Google)->create();

    $this->deleteJson("/api/v1/settings/connections/{$foreign->id}")
        ->assertForbidden();
});

test('notifications exposes the preference matrix and always-on events', function () {
    ownerActingIn();

    $this->getJson('/api/v1/settings/notifications')
        ->assertOk()
        ->assertJsonStructure(['preferences', 'alwaysOn']);
});

test('notifications update persists the preference matrix', function () {
    [$user] = ownerActingIn();

    $this->putJson('/api/v1/settings/notifications', [
        'preferences' => [
            'post_published' => ['in_app' => false, 'mail' => true],
        ],
    ])->assertOk()->assertJsonPath('preferences.post_published.in_app', false);

    expect($user->fresh()->notificationPreferences()->toArray()['post_published']['in_app'])->toBeFalse();
});

test('notifications update validates the matrix shape', function () {
    ownerActingIn();

    $this->putJson('/api/v1/settings/notifications', [
        'preferences' => ['post_published' => ['in_app' => 'not-a-bool']],
    ])->assertStatus(422);
});
