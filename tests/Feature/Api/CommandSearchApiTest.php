<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Enums\WorkspaceRole;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

test('requires authentication', function (): void {
    $this->getJson('/api/v1/command-search?q=hello')
        ->assertUnauthorized();
});

test('matches posts by base_text within the current workspace', function (): void {
    [$user, $workspace] = ownerActingIn();

    Post::factory()->for($workspace)->create([
        'author_id' => $user->id,
        'base_text' => 'Launch announcement draft',
    ]);
    Post::factory()->for($workspace)->create([
        'author_id' => $user->id,
        'base_text' => 'Unrelated text',
    ]);

    $this->getJson('/api/v1/command-search?q=launch')
        ->assertOk()
        ->assertJsonCount(1, 'posts')
        ->assertJsonPath('posts.0.excerpt', 'Launch announcement draft');
});

test('excludes deleted posts', function (): void {
    [$user, $workspace] = ownerActingIn();

    Post::factory()->for($workspace)->create([
        'author_id' => $user->id,
        'base_text' => 'Deleted launch',
        'status' => PostStatus::Deleted->value,
    ]);

    $this->getJson('/api/v1/command-search?q=launch')
        ->assertOk()
        ->assertJsonCount(0, 'posts');
});

test('never returns another workspace posts', function (): void {
    [$user] = ownerActingIn();
    $other = Workspace::factory()->create();
    Post::factory()->for($other)->create([
        'author_id' => $user->id,
        'base_text' => 'Secret launch from another workspace',
    ]);

    $this->getJson('/api/v1/command-search?q=launch')
        ->assertOk()
        ->assertJsonCount(0, 'posts');
});

test('returns nothing for queries shorter than two characters', function (): void {
    [$user, $workspace] = ownerActingIn();

    Post::factory()->for($workspace)->create([
        'author_id' => $user->id,
        'base_text' => 'a quick brown fox',
    ]);

    $this->getJson('/api/v1/command-search?q=a')
        ->assertOk()
        ->assertExactJson(['posts' => []]);
});

test('caps results at eight posts', function (): void {
    [$user, $workspace] = ownerActingIn();

    Post::factory()->count(12)->for($workspace)->create([
        'author_id' => $user->id,
        'base_text' => 'launch number',
    ]);

    $this->getJson('/api/v1/command-search?q=launch')
        ->assertOk()
        ->assertJsonCount(8, 'posts');
});

test('rejects api keys', function (): void {
    [, , $token] = issuedKey();

    $this->withToken($token)->getJson('/api/v1/command-search?q=launch')
        ->assertForbidden();
});

test('is throttled at 60 requests per minute', function (): void {
    ownerActingIn();

    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/command-search?q=launch')->assertOk();
    }

    $this->getJson('/api/v1/command-search?q=launch')->assertTooManyRequests();
});

test('unverified sessions can still search', function (): void {
    // Mirrors the legacy web route: auth-only, never verified-gated.
    config(['auth.email_verification.enabled' => true]);

    $user = User::factory()->unverified()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();

    $this->actingAs($user)
        ->getJson('/api/v1/command-search?q=launch')
        ->assertOk();
});
