<?php

use App\Enums\MetricsStatus;
use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Models\AccountMetric;
use App\Models\ConnectedAccount;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\Workspace;

beforeEach(function (): void {
    config()->set('metrics.enabled', true);
});

test('index returns the analytics payload', function () {
    [, $workspace, $token] = issuedKey();
    $account = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::X,
        'metrics_status' => MetricsStatus::Ok,
    ]);
    AccountMetric::factory()->for($account, 'account')->create(['followers' => 100]);

    $this->withToken($token)->getJson('/api/v1/analytics')
        ->assertOk()
        ->assertJsonStructure([
            'accounts' => [['id', 'platform', 'handle', 'latest_followers', 'followers_delta', 'series']],
            'posts',
            'summary' => ['account_count', 'followers', 'engagement', 'posts'],
            'comparison' => ['top', 'bottom'],
            'rangeDays',
            'polling' => ['post_metrics_enabled', 'account_metrics_enabled'],
        ])
        ->assertJsonPath('rangeDays', 90)
        ->assertJsonPath('accounts.0.latest_followers', 100);
});

test('index 404s when metrics are disabled', function () {
    config()->set('metrics.enabled', false);
    [, , $token] = issuedKey();

    $this->withToken($token)->getJson('/api/v1/analytics')->assertNotFound();
});

test('index clamps the days window', function () {
    [, , $token] = issuedKey();

    $this->withToken($token)->getJson('/api/v1/analytics?days=1')->assertOk()->assertJsonPath('rangeDays', 7);
    $this->withToken($token)->getJson('/api/v1/analytics?days=9999')->assertOk()->assertJsonPath('rangeDays', 365);
});

test('index scopes accounts and posts to the caller workspace', function () {
    [, $workspace, $token] = issuedKey();
    $mine = ConnectedAccount::factory()->create(['workspace_id' => $workspace->id, 'platform' => Platform::X]);
    ConnectedAccount::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'platform' => Platform::X,
    ]);
    $foreignPost = Post::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/analytics')->assertOk();

    expect(collect($response->json('accounts'))->pluck('id')->all())->toBe([$mine->id]);
    expect(collect($response->json('posts'))->pluck('id'))->not->toContain($foreignPost->id);
});

test('index includes measured published posts in the comparison', function () {
    [, $workspace, $token] = issuedKey();
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
        'base_text' => 'a great post',
    ]);
    PostTarget::factory()->for($post)->create([
        'platform' => Platform::X,
        'likes' => 10,
        'comments' => 2,
        'reposts' => 1,
        'metrics_status' => MetricsStatus::Ok,
    ]);

    $this->withToken($token)->getJson('/api/v1/analytics')
        ->assertOk()
        ->assertJsonPath('comparison.top.0.id', $post->id)
        ->assertJsonPath('comparison.top.0.engagement', 13);
});
