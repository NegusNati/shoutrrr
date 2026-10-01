<?php

declare(strict_types=1);

use App\Enums\MetricsStatus;
use App\Enums\PostStatus;
use App\Models\ConnectedAccount;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\Workspace;

test('unauthenticated requests are rejected', function () {
    $this->json('get', '/api/v1/analytics')->assertUnauthorized();
});

test('api key requests resolve the workspace analytics payload', function () {
    [, $workspace, $token] = issuedKey();

    Post::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => PostStatus::Published->value,
        'published_at' => now()->subDays(3),
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/analytics')
        ->assertOk()
        ->assertJsonStructure([
            'accounts',
            'posts',
            'summary' => [
                'account_count',
                'followers' => ['value', 'delta'],
                'engagement' => ['value', 'delta'],
                'posts' => ['value', 'delta'],
            ],
            'comparison' => ['top', 'bottom'],
            'rangeDays',
            'polling' => [
                'post_metrics_enabled',
                'account_metrics_enabled',
            ],
        ]);
});

test('session requests resolve the workspace analytics payload', function () {
    [, $workspace] = ownerActingIn();

    Post::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => PostStatus::Published->value,
        'published_at' => now()->subDays(3),
    ]);

    $this->getJson('/api/v1/analytics')
        ->assertOk()
        ->assertJsonPath('summary.posts.value', 1)
        ->assertJsonPath('rangeDays', 90);
});

test('days parameter is clamped to the allowed range', function () {
    [, $workspace] = ownerActingIn();

    $this->getJson('/api/v1/analytics?days=3')
        ->assertOk()
        ->assertJsonPath('rangeDays', 7);

    $this->getJson('/api/v1/analytics?days=9999')
        ->assertOk()
        ->assertJsonPath('rangeDays', 365);
});

test('posts outside the window and unpublished posts are excluded', function () {
    [, $workspace] = ownerActingIn();

    Post::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => PostStatus::Published->value,
        'published_at' => now()->subDays(200),
    ]);
    Post::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => PostStatus::Draft->value,
        'published_at' => now()->subDay(),
    ]);
    Post::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => PostStatus::Published->value,
        'published_at' => now()->subDays(10),
    ]);

    $this->getJson('/api/v1/analytics?days=30')
        ->assertOk()
        ->assertJsonPath('summary.posts.value', 1)
        ->assertJsonCount(1, 'posts');
});

test('published posts with ok metrics are ranked by engagement', function () {
    [, $workspace] = ownerActingIn();

    $quiet = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => PostStatus::Published->value,
        'published_at' => now()->subDays(2),
    ]);
    PostTarget::factory()->create([
        'post_id' => $quiet->id,
        'likes' => 1,
        'comments' => 0,
        'reposts' => 0,
        'metrics_status' => MetricsStatus::Ok,
    ]);

    $loud = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => PostStatus::Published->value,
        'published_at' => now()->subDays(2),
    ]);
    PostTarget::factory()->create([
        'post_id' => $loud->id,
        'likes' => 40,
        'comments' => 5,
        'reposts' => 5,
        'metrics_status' => MetricsStatus::Ok,
    ]);

    $this->getJson('/api/v1/analytics')
        ->assertOk()
        ->assertJsonPath('comparison.top.0.id', $loud->id)
        ->assertJsonPath('comparison.top.0.engagement', 50)
        ->assertJsonPath('summary.engagement.value', 51);
});

test('other workspaces data is not leaked', function () {
    [, $workspace] = ownerActingIn();
    $other = Workspace::factory()->create();

    Post::factory()->create([
        'workspace_id' => $other->id,
        'status' => PostStatus::Published->value,
        'published_at' => now()->subDay(),
    ]);
    ConnectedAccount::factory()->create(['workspace_id' => $other->id]);

    $this->getJson('/api/v1/analytics')
        ->assertOk()
        ->assertJsonPath('summary.posts.value', 0)
        ->assertJsonCount(0, 'accounts');
});
