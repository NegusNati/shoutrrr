<?php

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTarget;
use App\Models\PostTargetReply;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function apiEditSettings(): array
{
    return [
        'version' => 1,
        'background' => ['type' => 'gradient', 'id' => 'sunset'],
        'padding' => 4, 'radius' => 8, 'shadow' => 'md', 'aspect' => 'auto',
        'zoom' => 1, 'tilt' => ['x' => 0, 'y' => 0], 'crop' => null,
    ];
}

function apiReplyFor(string $workspaceId): PostTargetReply
{
    return PostTargetReply::factory()
        ->for(PostTarget::factory()->for(Post::factory()->create(['workspace_id' => $workspaceId])), 'target')
        ->create(['workspace_id' => $workspaceId]);
}

test('stores a beautified image on the reply workspace', function () {
    Storage::fake('public');
    [, $workspace, $token] = issuedKey();
    $reply = apiReplyFor($workspace->id);

    $this->withToken($token)->postJson("/api/v1/engagement/{$reply->id}/image-edit", [
        'composed' => UploadedFile::fake()->image('out.webp', 900, 600),
        'source' => UploadedFile::fake()->image('in.jpg'),
        'settings' => apiEditSettings(),
    ])
        ->assertCreated()
        ->assertJsonPath('media.mime', 'image/webp');

    expect(PostMedia::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())->toBe(1);
});

test('replaces an existing beautified image', function () {
    Storage::fake('public');
    [, $workspace, $token] = issuedKey();
    $reply = apiReplyFor($workspace->id);
    $media = PostMedia::factory()->create([
        'workspace_id' => $workspace->id, 'kind' => 'image', 'mime' => 'image/webp',
        'edit_settings' => apiEditSettings(),
    ]);

    $this->withToken($token)->putJson("/api/v1/engagement/{$reply->id}/image-edit/{$media->id}", [
        'composed' => UploadedFile::fake()->image('out.jpg', 900, 600),
        'settings' => apiEditSettings(),
    ])->assertOk();
});

test('rejects editing an animated gif', function () {
    Storage::fake('public');
    [, $workspace, $token] = issuedKey();
    $reply = apiReplyFor($workspace->id);
    $gif = PostMedia::factory()->create([
        'workspace_id' => $workspace->id, 'kind' => 'image', 'mime' => 'image/gif',
    ]);

    $this->withToken($token)->putJson("/api/v1/engagement/{$reply->id}/image-edit/{$gif->id}", [
        'composed' => UploadedFile::fake()->image('out.webp'),
        'settings' => apiEditSettings(),
    ])->assertStatus(422);
});

test('a reply id from another workspace is a 404', function () {
    Storage::fake('public');
    [, , $token] = issuedKey();
    $foreign = apiReplyFor(Workspace::factory()->create()->id);

    $this->withToken($token)->postJson("/api/v1/engagement/{$foreign->id}/image-edit", [
        'composed' => UploadedFile::fake()->image('out.webp'),
        'source' => UploadedFile::fake()->image('in.jpg'),
        'settings' => apiEditSettings(),
    ])->assertNotFound();
});
