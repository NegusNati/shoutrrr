<?php

use App\Enums\PostStatus;
use App\Enums\WorkspaceRole;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;

/*
|--------------------------------------------------------------------------
| SPA session API — session-cookie access to /api/v1
|--------------------------------------------------------------------------
|
| The web SPA authenticates with the same session cookie as the legacy app
| (Sanctum stateful), while external consumers keep using API keys. These
| tests cover the session-only surface (/me, workspaces, onboarding,
| invitations) plus the dual-auth contract on the shared endpoints.
|
*/

test('me is unauthorized without credentials', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('me resolves for a session-authenticated user', function () {
    [$user, $workspace] = ownerActingIn();

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('auth.user.id', $user->id)
        ->assertJsonPath('workspaces.current.id', $workspace->id)
        ->assertJsonStructure([
            'auth' => ['user', 'mustVerifyEmail'],
            'workspaces' => ['current', 'all'],
            'shell' => ['accounts', 'sets', 'limits'],
            'features',
            'socialite' => ['providers'],
        ]);
});

test('me resolves for an api-key request too', function () {
    [$user, , $token] = issuedKey();

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('auth.user.id', $user->id);
});

test('auth options is public and reports registration flags', function () {
    $this->getJson('/api/v1/auth/options')
        ->assertOk()
        ->assertJsonStructure([
            'canResetPassword',
            'canRegister',
            'providers',
            'passwordRules',
        ]);
});

test('workspaces index lists the member workspaces', function () {
    [$user, $workspace] = ownerActingIn();

    $this->getJson('/api/v1/workspaces')
        ->assertOk()
        ->assertJsonPath('current.id', $workspace->id);
});

test('session requests can switch the current workspace', function () {
    [$user, $workspace] = ownerActingIn();
    $second = Workspace::factory()->create(['name' => 'Second']);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $second->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Admin,
    ]);

    $this->postJson('/api/v1/workspaces/switch', ['workspace_id' => $second->id])
        ->assertOk()
        ->assertJsonPath('current.id', $second->id);

    expect($user->fresh()->current_workspace_id)->toBe($second->id);
});

test('api keys cannot switch the session workspace', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)
        ->postJson('/api/v1/workspaces/switch', ['workspace_id' => $workspace->id])
        ->assertForbidden();
});

test('a session user can create a workspace', function () {
    [$user] = ownerActingIn();

    $this->postJson('/api/v1/workspaces', ['name' => 'New Team'])
        ->assertCreated()
        ->assertJsonPath('current.name', 'New Team');

    expect($user->fresh()->currentWorkspace->name)->toBe('New Team');
});

test('dashboard payload carries posts and onboarding', function () {
    [$user, $workspace] = ownerActingIn();
    $post = Post::factory()->for($workspace)->create(['author_id' => $user->id]);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure(['onboarding', 'savedMentions', 'posts'])
        ->assertJsonPath('posts.0.id', $post->id);
});

test('posts index reports status counts and sets in meta', function () {
    [$user, $workspace, $token] = issuedKey();
    Post::factory()->for($workspace)->create(['author_id' => $user->id, 'status' => 'draft']);
    Post::factory()->for($workspace)->create(['author_id' => $user->id, 'status' => 'published']);

    $this->withToken($token)->getJson('/api/v1/posts')
        ->assertOk()
        ->assertJsonPath('meta.counts.draft', 1)
        ->assertJsonPath('meta.counts.published', 1)
        ->assertJsonPath('meta.counts.all', 2);
});

test('posts index accepts status=all', function () {
    [$user, $workspace, $token] = issuedKey();
    Post::factory()->for($workspace)->create(['author_id' => $user->id, 'status' => 'draft']);

    $this->withToken($token)->getJson('/api/v1/posts?status=all')
        ->assertOk()
        ->assertJsonPath('meta.counts.all', 1);
});

test('duplicating a published post creates a draft', function () {
    [$user, $workspace, $token] = issuedKey();
    $post = Post::factory()->for($workspace)->create([
        'author_id' => $user->id,
        'status' => 'published',
    ]);

    $response = $this->withToken($token)
        ->postJson("/api/v1/posts/{$post->id}/duplicate")
        ->assertCreated();

    expect(Post::find($response->json('post.id'))->status)->toBe(PostStatus::Draft);
});

test('duplicating a draft is rejected', function () {
    [$user, $workspace, $token] = issuedKey();
    $post = Post::factory()->for($workspace)->create([
        'author_id' => $user->id,
        'status' => 'draft',
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/posts/{$post->id}/duplicate")
        ->assertUnprocessable();
});

test('onboarding welcomed marks the timestamp', function () {
    [, $workspace] = ownerActingIn();
    expect($workspace->onboarding_welcomed_at)->toBeNull();

    $this->postJson('/api/v1/onboarding/welcomed')->assertOk();

    expect($workspace->fresh()->onboarding_welcomed_at)->not->toBeNull();
});

test('onboarding welcomed returns a connect url when asked', function () {
    [, $workspace] = ownerActingIn();

    $response = $this->postJson('/api/v1/onboarding/welcomed', ['connect' => true])
        ->assertOk();

    expect($response->json('connect_url'))->toContain('/accounts');
});

test('onboarding dismiss is conflict without a connected account', function () {
    ownerActingIn();

    $this->postJson('/api/v1/onboarding/dismiss')->assertConflict();
});

test('completing a click-to-complete onboarding step returns its SPA url', function () {
    [, $workspace] = ownerActingIn();

    $response = $this->postJson('/api/v1/onboarding/steps/complete', ['key' => 'timezone'])
        ->assertOk();

    expect($response->json('redirect_url'))->toBe('/app/settings/workspace')
        ->and($workspace->fresh()->onboarding_progress)->toContain('timezone');
});

test('a workspace invitation can be accepted by the invitee', function () {
    [, $workspace] = ownerActingIn();
    $invitee = User::factory()->create();
    [, $hash] = WorkspaceInvitation::generateToken();
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => $invitee->email,
        'token' => $hash,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->postJson("/api/v1/workspace-invitations/{$invitation->id}/accept")
        ->assertOk();

    expect($invitation->fresh()->isAccepted())->toBeTrue()
        ->and($workspace->members()->where('user_id', $invitee->id)->exists())->toBeTrue();
});

test('a non-invitee cannot accept the invitation', function () {
    [$user, $workspace] = ownerActingIn();
    [, $hash] = WorkspaceInvitation::generateToken();
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'someone-else@example.com',
        'token' => $hash,
        'expires_at' => now()->addDays(7),
    ]);

    // 404 rather than 403 — the API doesn't reveal the invitation exists.
    $this->postJson("/api/v1/workspace-invitations/{$invitation->id}/accept")
        ->assertNotFound();
});

test('a workspace invitation can be declined by the invitee', function () {
    [, $workspace] = ownerActingIn();
    $invitee = User::factory()->create();
    [, $hash] = WorkspaceInvitation::generateToken();
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => $invitee->email,
        'token' => $hash,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->deleteJson("/api/v1/workspace-invitations/{$invitation->id}")
        ->assertNoContent();

    expect(WorkspaceInvitation::find($invitation->id))->toBeNull();
});
