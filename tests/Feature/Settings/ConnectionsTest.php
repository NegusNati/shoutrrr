<?php

use App\Models\SocialAccount;
use App\Models\User;

test('enabled provider keys are shared globally for the settings nav gate', function () {
    config()->set('kit.auth.socialite.providers', ['google']);

    $this->actingAs(User::factory()->withWorkspace()->create())
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('socialite.providers', ['google']);

    config()->set('kit.auth.socialite.enabled', false);

    $this->actingAs(User::factory()->withWorkspace()->create())
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('socialite.providers', []);
});

test('connections page lists enabled providers and link state', function () {
    config()->set('kit.auth.socialite.providers', ['google']);

    $user = User::factory()->withWorkspace()->create();
    SocialAccount::factory()->create(['user_id' => $user->id, 'provider' => 'google']);

    $this->actingAs($user)
        ->getJson('/api/v1/settings/connections')
        ->assertOk()
        ->assertJsonPath('hasPassword', true)
        ->assertJsonCount(1, 'connections')
        ->assertJsonPath('connections.0.provider', 'google')
        ->assertJsonPath('connections.0.connected', true)
        ->assertJsonPath('connections.0.id', $user->socialAccounts()->first()->id);
});

test('connections page is reachable by an oauth-only user without password confirmation', function () {
    $user = User::factory()->withWorkspace()->create(['password' => null]);
    SocialAccount::factory()->create(['user_id' => $user->id, 'provider' => 'google']);

    $this->actingAs($user)
        ->get(route('connections.edit'))
        ->assertRedirect('/app/settings/connections');

    $this->actingAs($user)
        ->getJson('/api/v1/settings/connections')
        ->assertOk();
});

test('a provider can be disconnected when another login method remains', function () {
    $user = User::factory()->create(); // has password
    $account = SocialAccount::factory()->create(['user_id' => $user->id, 'provider' => 'google']);

    $this->actingAs($user)
        ->delete(route('connections.destroy', $account))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SocialAccount::find($account->id))->toBeNull();
});

test('disconnecting the only login method is rejected', function () {
    $user = User::factory()->create(['password' => null]); // no password
    $account = SocialAccount::factory()->create(['user_id' => $user->id, 'provider' => 'google']);

    $this->actingAs($user)
        ->delete(route('connections.destroy', $account))
        ->assertSessionHas('error');

    expect(SocialAccount::find($account->id))->not->toBeNull();
});

test('a user cannot disconnect another users social account', function () {
    $user = User::factory()->create();
    $otherAccount = SocialAccount::factory()->create();

    $this->actingAs($user)
        ->delete(route('connections.destroy', $otherAccount))
        ->assertForbidden();
});
