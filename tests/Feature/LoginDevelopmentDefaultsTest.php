<?php

it('exposes development default credentials via auth options in local', function (): void {
    $this->app->detectEnvironment(fn () => 'local');

    $this->getJson('/api/v1/auth/options')
        ->assertOk()
        ->assertJsonPath('defaultLogin.email', 'test@example.com')
        ->assertJsonPath('defaultLogin.password', 'password');
});

it('does not expose development default credentials outside local', function (): void {
    $this->app->detectEnvironment(fn () => 'production');

    $this->getJson('/api/v1/auth/options')
        ->assertOk()
        ->assertJson(fn ($json) => $json->missing('defaultLogin')->etc());
});
