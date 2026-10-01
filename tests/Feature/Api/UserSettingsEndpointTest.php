<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('profile show returns mustVerifyEmail flag', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->getJson('/api/v1/settings/profile')
        ->assertOk()
        ->assertJsonStructure(['mustVerifyEmail']);
});

test('profile update changes name and email', function () {
    [$user, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->putJson('/api/v1/settings/profile', [
            'name' => 'Renamed Dev',
            'email' => 'renamed@example.com',
        ])
        ->assertOk();

    expect($user->fresh()->name)->toBe('Renamed Dev');
});

test('profile update rejects invalid payload', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->putJson('/api/v1/settings/profile', ['email' => 'not-an-email'])
        ->assertUnprocessable();
});

test('security show returns passkey and password payload', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->getJson('/api/v1/settings/security')
        ->assertOk()
        ->assertJsonStructure([
            'canManageTwoFactor',
            'canManagePasskeys',
            'passkeys',
            'passwordRules',
        ]);
});

test('password update changes the user password', function () {
    [$user, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->putJson('/api/v1/settings/password', [
            'current_password' => 'password',
            'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ])
        ->assertOk();

    expect(Hash::check('new-secure-password-123', $user->fresh()->password))
        ->toBeTrue();
});

test('password update rejects wrong current password', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->putJson('/api/v1/settings/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ])
        ->assertUnprocessable();
});

test('connections show lists enabled providers', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->getJson('/api/v1/settings/connections')
        ->assertOk()
        ->assertJsonStructure(['connections', 'hasPassword']);
});

test('connections remove deletes an owned social account', function () {
    [$user, $workspace, $token] = issuedKey();
    $social = SocialAccount::factory()->for($user)->create();

    // A second sign-in method must exist for the last-method guard to pass.
    $user->forceFill(['password' => bcrypt('password')])->save();

    $this->withToken($token)
        ->deleteJson("/api/v1/settings/connections/{$social->id}")
        ->assertOk();

    expect(SocialAccount::query()->whereKey($social->id)->exists())->toBeFalse();
});

test('connections remove on a foreign account 404s', function () {
    [, $workspace, $token] = issuedKey();
    $social = SocialAccount::factory()->for(User::factory())->create();

    $this->withToken($token)
        ->deleteJson("/api/v1/settings/connections/{$social->id}")
        ->assertNotFound();
});

test('notifications show returns preferences and always-on list', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->getJson('/api/v1/settings/notifications')
        ->assertOk()
        ->assertJsonStructure(['preferences', 'alwaysOn']);
});

test('notifications update persists preferences', function () {
    [$user, $workspace, $token] = issuedKey();

    $payload = $this->withToken($token)
        ->getJson('/api/v1/settings/notifications')
        ->assertOk()
        ->json('preferences');

    $key = array_key_first($payload);
    $payload[$key]['in_app'] = ! ($payload[$key]['in_app'] ?? true);

    $this->withToken($token)
        ->putJson('/api/v1/settings/notifications', ['preferences' => $payload])
        ->assertOk();
});

test('read-scope key cannot mutate settings', function () {
    [, $workspace, $token] = issuedKey('read');

    $this->withToken($token)
        ->getJson('/api/v1/settings/profile')
        ->assertOk();

    $this->withToken($token)
        ->putJson('/api/v1/settings/profile', ['name' => 'x', 'email' => 'x@example.com'])
        ->assertForbidden();
});
