<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTarget;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function spaUser(): array
{
    $user = User::factory()->withWorkspace()->create();
    $user->refresh();

    return [$user, $user->currentWorkspace];
}

function spaPost(Workspace $workspace, array $attributes = []): Post
{
    return Post::factory()->for($workspace)->create([
        'status' => 'draft',
        ...$attributes,
    ]);
}

// ---------------------------------------------------------------------------
// platform-limits
// ---------------------------------------------------------------------------

test('platform-limits returns the platform limit descriptors', function () {
    [$user] = spaUser();

    $this->actingAs($user)->getJson('/api/v1/platform-limits')
        ->assertOk()
        ->assertJsonStructure(['limits' => [['platform', 'maxLength', 'maxMedia']]]);
});

// ---------------------------------------------------------------------------
// posts/next-slot
// ---------------------------------------------------------------------------

test('next-slot reports no schedule when the workspace has none', function () {
    [$user] = spaUser();

    $this->actingAs($user)->getJson('/api/v1/posts/next-slot')
        ->assertOk()
        ->assertJsonPath('has_schedule', false)
        ->assertJsonPath('slot', null);
});

// ---------------------------------------------------------------------------
// workspace-mentions store
// ---------------------------------------------------------------------------

test('workspace-mentions store creates a saved mention', function () {
    [$user] = spaUser();

    $this->actingAs($user)->postJson('/api/v1/workspace-mentions', [
        'name' => '@friend',
        'handles' => ['x' => 'friendx'],
    ])
        ->assertCreated()
        ->assertJsonPath('mention.name', '@friend')
        ->assertJsonPath('mention.handles.x', 'friendx');
});

test('workspace-mentions store validates the @-prefix', function () {
    [$user] = spaUser();

    $this->actingAs($user)->postJson('/api/v1/workspace-mentions', [
        'name' => 'friend',
        'handles' => ['x' => 'friendx'],
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// posts/{post}/media
// ---------------------------------------------------------------------------

test('media store uploads an image onto a draft', function () {
    Storage::fake('public');
    [$user, $workspace] = spaUser();
    $post = spaPost($workspace);

    $this->actingAs($user)->post("/api/v1/posts/{$post->id}/media", [
        'file' => UploadedFile::fake()->image('p.jpg', 800, 600)->size(300),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('media.mime', 'image/jpeg')
        ->assertJsonPath('media.kind', 'image');
});

test('media alt updates the alt text', function () {
    [$user, $workspace] = spaUser();
    $post = spaPost($workspace);
    $media = PostMedia::factory()->create([
        'workspace_id' => $workspace->id,
        'post_id' => $post->id,
    ]);

    $this->actingAs($user)->patchJson("/api/v1/posts/{$post->id}/media/{$media->id}/alt", [
        'alt_text' => 'a description',
    ])
        ->assertOk()
        ->assertJsonPath('media.alt_text', 'a description');
});

test('media destroy removes the attachment', function () {
    [$user, $workspace] = spaUser();
    $post = spaPost($workspace);
    $media = PostMedia::factory()->create([
        'workspace_id' => $workspace->id,
        'post_id' => $post->id,
    ]);

    $this->actingAs($user)->deleteJson("/api/v1/posts/{$post->id}/media/{$media->id}")
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect(PostMedia::whereKey($media->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// posts/{post}/media/video-url + video
// ---------------------------------------------------------------------------

test('video-url signs a workspace-scoped upload key', function () {
    config()->set('filesystems.default', 'public');
    Storage::fake('public');

    [$user, $workspace] = spaUser();
    $post = spaPost($workspace);

    $response = $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/media/video-url", [
        'content_type' => 'video/mp4',
    ]);

    $response->assertOk()->assertJsonStructure(['key', 'url', 'headers']);
    expect($response->json('key'))->toStartWith('tmp/media/'.$workspace->id.'/')
        ->and($response->json('key'))->toEndWith('.mp4');
});

// ---------------------------------------------------------------------------
// posts/{post}/image-edit
// ---------------------------------------------------------------------------

function editSettingsPayload(): string
{
    return json_encode([
        'version' => 1,
        'background' => ['type' => 'gradient', 'id' => 'sunset', 'angle' => 135, 'stops' => [['color' => '#f00', 'at' => 0], ['color' => '#00f', 'at' => 1]]],
        'padding' => 64,
        'radius' => 12,
        'shadow' => 'medium',
        'aspect' => 'auto',
        'zoom' => 1,
        'tilt' => ['rotateX' => 0, 'rotateY' => 0],
        'crop' => null,
    ]);
}

test('image-edit stores composed + source and returns edit settings', function () {
    Storage::fake('public');
    [$user, $workspace] = spaUser();
    $post = spaPost($workspace);

    $response = $this->actingAs($user)->post("/api/v1/posts/{$post->id}/image-edit", [
        'composed' => UploadedFile::fake()->image('out.png', 800, 600),
        'source' => UploadedFile::fake()->image('src.png', 1200, 900),
        'settings' => editSettingsPayload(),
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('media.mime', 'image/png')
        ->assertJsonPath('media.edit_settings.padding', 64);
    expect($response->json('media.source_url'))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// gifs
// ---------------------------------------------------------------------------

test('gifs browse returns normalized items', function () {
    config()->set('services.klipy.key', 'test-key');
    Http::fake(['https://api.klipy.com/*' => Http::response([
        'result' => true,
        'data' => [
            'has_next' => false,
            'data' => [[
                'id' => 1,
                'slug' => 'yay-1',
                'title' => 'Yay',
                'file' => ['sm' => ['gif' => ['url' => 'https://cdn.klipy.com/sm.gif', 'width' => 120, 'height' => 90, 'size' => 1000]]],
            ]],
        ],
    ])]);

    [$user] = spaUser();

    $this->actingAs($user)->getJson('/api/v1/gifs/gif?q=yay')
        ->assertOk()
        ->assertJsonPath('has_next', false)
        ->assertJsonPath('items.0.slug', 'yay-1');
});

test('gifs browse 404s when the feature is off', function () {
    config()->set('services.klipy.key', null);
    [$user] = spaUser();

    $this->actingAs($user)->getJson('/api/v1/gifs/gif')->assertNotFound();
});

// ---------------------------------------------------------------------------
// posts/{id}/metrics/refresh
// ---------------------------------------------------------------------------

test('metrics refresh re-captures published targets and returns the rollup', function () {
    config(['metrics.enabled' => true]);
    Queue::fake();

    [$user, $workspace] = spaUser();
    $post = spaPost($workspace, ['status' => 'published']);
    PostTarget::factory()->for($post)->create([
        'status' => 'published',
        'remote_id' => 'remote-1',
    ]);

    // dispatchSync runs the capture inline in the web route too, so faking the
    // queue is safe; the endpoint's own throttle is what bounds real API reads.
    $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/metrics/refresh")
        ->assertOk()
        ->assertJsonStructure(['targets', 'totals']);
});

test('post metrics returns the read-only rollup without capturing', function () {
    config(['metrics.enabled' => true]);
    [$user, $workspace] = spaUser();
    $post = spaPost($workspace, ['status' => 'published']);
    PostTarget::factory()->for($post)->create([
        'status' => 'published',
        'remote_id' => 'remote-1',
    ]);

    $this->actingAs($user)->getJson("/api/v1/posts/{$post->id}/metrics")
        ->assertOk()
        ->assertJsonStructure(['targets', 'totals']);
});

test('metrics refresh 404s when metrics are disabled', function () {
    config(['metrics.enabled' => false]);
    [$user, $workspace] = spaUser();
    $post = spaPost($workspace, ['status' => 'published']);

    $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/metrics/refresh")
        ->assertNotFound();
});

test('post gifs attach downloads and attaches the gif', function () {
    Storage::fake('local');
    config()->set('services.klipy.key', 'test-key');
    Http::fake([
        'https://static.klipy.com/*' => Http::response(
            base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
            200,
            ['Content-Type' => 'image/gif'],
        ),
        'https://api.klipy.com/*' => Http::response(['result' => true, 'data' => []]),
    ]);

    [$user, $workspace] = spaUser();
    $post = spaPost($workspace);

    $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/gifs", [
        'catalog' => 'gif',
        'slug' => 'happy-dance-991',
        'title' => 'Happy dance',
        'variants' => [
            ['url' => 'https://static.klipy.com/ok.gif', 'mime' => 'image/gif', 'width' => 320, 'height' => 240, 'bytes' => 40000],
        ],
    ])
        ->assertCreated()
        ->assertJsonStructure(['media' => ['id', 'kind', 'mime']]);
});
