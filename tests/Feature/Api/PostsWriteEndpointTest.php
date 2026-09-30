<?php

use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Jobs\DeletePostTarget;
use App\Models\ConnectedAccount;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTarget;
use Illuminate\Support\Facades\Queue;

test('creates a draft post', function () {
    [, , $token] = issuedKey();

    $this->withToken($token)->postJson('/api/v1/posts', [
        'base_text' => 'Hello from the API',
        'destination' => ['kind' => 'all'],
    ])
        ->assertCreated()
        ->assertJsonPath('post.base_text', 'Hello from the API');
});

test('validates destination', function () {
    [, , $token] = issuedKey();

    $this->withToken($token)->postJson('/api/v1/posts', ['base_text' => 'x'])
        ->assertStatus(422);
});

test('updates a draft post', function () {
    [$user, $workspace, $token] = issuedKey();
    $post = Post::factory()->for($workspace)->create(['author_id' => $user->id]);

    $this->withToken($token)->patchJson("/api/v1/posts/{$post->id}", [
        'base_text' => 'Edited',
        'destination' => ['kind' => 'all'],
    ])
        ->assertOk()
        ->assertJsonPath('post.base_text', 'Edited');
});

test('creates a draft from the full composer payload', function () {
    [, $workspace, $token] = issuedKey();
    $account = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => 'instagram',
    ]);
    $other = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => 'x',
    ]);

    $response = $this->withToken($token)->postJson('/api/v1/posts', [
        'segments' => ['first', 'second'],
        'destination' => ['kind' => 'accounts', 'ids' => [$account->id, $other->id]],
        'segment_breaks' => [$account->id, $other->id],
        'skip_sync' => true,
        'mentions' => [[
            'id' => 'm1',
            'label' => '@friend',
            'handles' => ['instagram' => 'friend', 'x' => 'friendx'],
        ]],
    ]);

    $response->assertCreated();
    $post = Post::find($response->json('post.id'));
    expect($post->skip_sync)->toBeTrue()
        ->and($post->targets)->toHaveCount(2)
        ->and($post->mentions)->toHaveCount(1)
        ->and($post->mentions[0]['handles']['instagram'])->toBe('friend');
});

test('rejects a destination kind the composer never sends', function () {
    [, , $token] = issuedKey();

    $this->withToken($token)->postJson('/api/v1/posts', [
        'segments' => ['x'],
        'destination' => ['kind' => 'bogus'],
    ])->assertStatus(422);
});

test('updates a draft with per-target format, overrides, and placements', function () {
    [, $workspace, $token] = issuedKey();
    $account = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => 'instagram',
    ]);
    $post = Post::factory()->for($workspace)->create();
    $media = PostMedia::factory()->create(['workspace_id' => $workspace->id, 'post_id' => null]);

    $this->withToken($token)->patchJson("/api/v1/posts/{$post->id}", [
        'segments' => ['body text'],
        'destination' => ['kind' => 'account', 'id' => $account->id],
        'targets' => [[
            'connected_account_id' => $account->id,
            'format' => 'story',
            'content_override' => ['segments' => ['story text']],
            'segment_breaks' => [$account->id],
            'placements' => [[
                'media_id' => $media->id,
                'segment_ref' => 'seg-0',
                'position' => 0,
            ]],
        ]],
        'media_ids' => [$media->id],
        'expected_updated_at' => $post->updated_at->toIso8601String(),
    ])->assertOk();

    $target = $post->targets()->first()->fresh();
    expect($target->format)->toBe(PostFormat::Story)
        ->and($target->content_override['segments'])->toBe(['story text'])
        ->and($post->media()->pluck('post_media.id')->all())->toBe([$media->id]);
});

test('deletes a draft post', function () {
    [$user, $workspace, $token] = issuedKey();
    $post = Post::factory()->for($workspace)->create(['author_id' => $user->id, 'status' => 'draft']);

    $this->withToken($token)->deleteJson("/api/v1/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect(Post::whereKey($post->id)->exists())->toBeFalse();
});

test('returns 409 on a stale write', function () {
    [$user, $workspace, $token] = issuedKey();
    $post = Post::factory()->for($workspace)->create(['author_id' => $user->id]);

    $this->withToken($token)->patchJson("/api/v1/posts/{$post->id}", [
        'base_text' => 'Edited',
        'destination' => ['kind' => 'all'],
        'expected_updated_at' => '2020-01-01T00:00:00+00:00',
    ])
        ->assertStatus(409);
});

test('deleting a published post dispatches remote deletion for targets with a remote_id', function () {
    Queue::fake();

    [$user, $workspace, $token] = issuedKey();
    $post = Post::factory()->for($workspace)->create([
        'author_id' => $user->id,
        'status' => PostStatus::Published->value,
    ]);
    PostTarget::factory()->for($post)->create(['remote_id' => 'remote-abc']);

    $this->withToken($token)->deleteJson("/api/v1/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonPath('remote', true);

    Queue::assertPushed(DeletePostTarget::class);

    $post->refresh();
    expect(Post::whereKey($post->id)->exists())->toBeTrue()
        ->and($post->status)->toBe(PostStatus::Deleted);
});
