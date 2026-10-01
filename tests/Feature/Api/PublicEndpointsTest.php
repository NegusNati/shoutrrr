<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\PostShare;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;

test('public share endpoint returns the shared post without auth', function () {
    $workspace = Workspace::factory()->create();
    $post = Post::factory()->for($workspace)->create();
    $plain = 'share-token-under-test';
    PostShare::factory()->for($post)->create([
        'token_hash' => hash('sha256', $plain),
        'expires_at' => now()->addDay(),
    ]);

    $this->getJson("/api/v1/shares/public/{$plain}")
        ->assertOk()
        ->assertJsonStructure(['post']);
});

test('public share endpoint returns null post for unknown token', function () {
    $this->getJson('/api/v1/shares/public/does-not-exist')
        ->assertOk()
        ->assertJsonPath('post', null);
});

test('public invitation endpoint returns invitation details without auth', function () {
    [, $workspace, $token] = issuedKey();
    [$plainToken, $hash] = WorkspaceInvitation::generateToken();
    WorkspaceInvitation::factory()->for($workspace)->create([
        'email' => 'invitee@example.com',
        'token' => $hash,
        'expires_at' => now()->addDays(7),
    ]);

    $this->getJson("/api/v1/workspace-invitations/token/{$plainToken}")
        ->assertOk()
        ->assertJsonStructure([
            'invitation' => ['token', 'workspace_name', 'role', 'inviter_name', 'expires_at'],
            'userExists',
            'loginUrl',
            'registerUrl',
        ])
        ->assertJsonPath('userExists', false);
});

test('public invitation endpoint 404s for unknown token', function () {
    $this->getJson('/api/v1/workspace-invitations/token/not-a-token')->assertNotFound();
});

test('composer payload endpoint returns accounts sets limits and mentions', function () {
    [, $workspace, $token] = issuedKey();
    $post = Post::factory()->for($workspace)->create();

    $this->withToken($token)
        ->getJson("/api/v1/posts/{$post->id}/compose")
        ->assertOk()
        ->assertJsonStructure([
            'post',
            'accounts',
            'sets',
            'limits',
            'savedMentions',
            'metricsEnabled',
        ]);
});

test('composer payload on a foreign post 404s', function () {
    [, , $token] = issuedKey();
    $post = Post::factory()->create();

    $this->withToken($token)
        ->getJson("/api/v1/posts/{$post->id}/compose")
        ->assertNotFound();
});

test('post media endpoints are workspace scoped', function () {
    [, , $token] = issuedKey();
    $post = Post::factory()->create();

    $this->withToken($token)
        ->postJson("/api/v1/posts/{$post->id}/media", [])
        ->assertNotFound();

    $this->withToken($token)
        ->postJson("/api/v1/posts/{$post->id}/gifs", [])
        ->assertNotFound();
});

test('post metrics endpoint on foreign post 404s', function () {
    [, , $token] = issuedKey();
    $post = Post::factory()->create();

    $this->withToken($token)
        ->postJson("/api/v1/posts/{$post->id}/metrics/refresh")
        ->assertNotFound();
});
