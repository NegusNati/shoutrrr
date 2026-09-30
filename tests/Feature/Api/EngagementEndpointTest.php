<?php

use App\Dto\Engagement\ReplyActionResult;
use App\Dto\Engagement\ReplyPostResult;
use App\Enums\Platform;
use App\Enums\ReplyStatus;
use App\Models\ConnectedAccount;
use App\Models\ConnectedAccountSecret;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\PostTargetReply;
use App\Models\Workspace;
use App\Services\Engagement\Contracts\EngagementConnector;
use App\Services\Engagement\EngagementConnectorRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('engagement.enabled', true);
    Storage::fake('public');
});

function engagementReply(Workspace $workspace, array $overrides = []): PostTargetReply
{
    $account = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::X,
        'token_expires_at' => now()->addHour(),
    ]);
    ConnectedAccountSecret::factory()->create([
        'connected_account_id' => $account->id,
        'access_token' => 'tok',
    ]);

    $target = PostTarget::factory()
        ->for(Post::factory()->create(['workspace_id' => $workspace->id]))
        ->for($account, 'account')
        ->create(['platform' => Platform::X, 'remote_id' => '500']);

    return PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::X,
        'remote_reply_id' => '900',
        'remote_cid' => null,
        'status' => ReplyStatus::Pending,
        'is_ours' => false,
        ...$overrides,
    ]);
}

function fakeReplyConnector(ReplyActionResult|ReplyPostResult $result): void
{
    $connector = Mockery::mock(EngagementConnector::class);
    $connector->shouldReceive('postReply')->andReturn($result);
    $connector->shouldReceive('likeReply')->andReturn($result);
    $connector->shouldReceive('unlikeReply')->andReturn($result);
    $connector->shouldReceive('deleteReply')->andReturn($result);
    $registry = Mockery::mock(EngagementConnectorRegistry::class);
    $registry->shouldReceive('for')->andReturn($connector);
    app()->instance(EngagementConnectorRegistry::class, $registry);
}

test('index returns conversation groups with filters, facets, and flags', function () {
    [, $workspace, $token] = issuedKey();
    $reply = engagementReply($workspace);

    $response = $this->withToken($token)->getJson('/api/v1/engagement')
        ->assertOk()
        ->assertJsonStructure([
            'replies' => ['data', 'current_page', 'last_page'],
            'filters' => ['account', 'platform', 'target', 'post', 'unread', 'archived'],
            'facets' => ['accounts', 'posts'],
            'engagementEnabled' => ['x', 'bluesky', 'linkedin'],
            'linkedinCommunityManagementEnabled',
            'savedMentions',
        ]);

    expect($response->json('replies.data.0.id'))->toBe($reply->id);
    expect($response->json('replies.data.0.conversation_key'))->toBe($reply->post_target_id.':'.$reply->conversation_remote_id);
});

test('index 404s when engagement is disabled', function () {
    config()->set('engagement.enabled', false);
    [, , $token] = issuedKey();

    $this->withToken($token)->getJson('/api/v1/engagement')->assertNotFound();
});

test('index scopes replies to the caller workspace', function () {
    [, $workspace, $token] = issuedKey();
    $mine = engagementReply($workspace);
    engagementReply(Workspace::factory()->create());

    $ids = collect(
        $this->withToken($token)->getJson('/api/v1/engagement')->assertOk()->json('replies.data')
    )->pluck('id')->all();

    expect($ids)->toBe([$mine->id]);
});

test('thread returns the conversation and marks inbound replies read', function () {
    [, $workspace, $token] = issuedKey();
    $reply = engagementReply($workspace);

    $this->withToken($token)->getJson("/api/v1/engagement/{$reply->id}/thread")
        ->assertOk()
        ->assertJsonStructure(['post_excerpt', 'thread']);

    expect($reply->fresh()->read_at)->not->toBeNull();
});

test('thread 404s for a reply in another workspace', function () {
    [, , $token] = issuedKey();
    $foreign = engagementReply(Workspace::factory()->create());

    $this->withToken($token)->getJson("/api/v1/engagement/{$foreign->id}/thread")->assertNotFound();
});

test('markRead marks the inbound thread read and archive archives it', function () {
    [, $workspace, $token] = issuedKey();
    $reply = engagementReply($workspace);

    $this->withToken($token)->postJson("/api/v1/engagement/{$reply->id}/read")->assertNoContent();
    expect($reply->fresh()->read_at)->not->toBeNull();

    $this->withToken($token)->postJson("/api/v1/engagement/{$reply->id}/archive")->assertNoContent();
    expect($reply->fresh()->status)->toBe(ReplyStatus::Archived);
});

test('respond posts the reply through the connector and returns 201', function () {
    [, $workspace, $token] = issuedKey();
    $reply = engagementReply($workspace);
    fakeReplyConnector(ReplyPostResult::ok('at://mine', 'cidmine'));

    $this->withToken($token)->postJson("/api/v1/engagement/{$reply->id}/reply", ['text' => 'thank you!'])
        ->assertCreated()
        ->assertJsonPath('reply.text', 'thank you!')
        ->assertJsonPath('reply.is_ours', true);
});

test('respond validates the body', function () {
    [, $workspace, $token] = issuedKey();
    $reply = engagementReply($workspace);

    $this->withToken($token)->postJson("/api/v1/engagement/{$reply->id}/reply", [])->assertStatus(422);
});

test('like and unlike toggle the platform like', function () {
    [, $workspace, $token] = issuedKey();
    $reply = engagementReply($workspace);
    fakeReplyConnector(ReplyActionResult::ok('like-1'));

    $this->withToken($token)->postJson("/api/v1/engagement/{$reply->id}/like")
        ->assertOk()
        ->assertExactJson(['is_liked' => true]);

    $this->withToken($token)->deleteJson("/api/v1/engagement/{$reply->id}/like")
        ->assertOk()
        ->assertExactJson(['is_liked' => false]);
});

test('deleting an inbound reply is forbidden', function () {
    [, $workspace, $token] = issuedKey();
    $reply = engagementReply($workspace);

    $this->withToken($token)->deleteJson("/api/v1/engagement/{$reply->id}")->assertForbidden();
});

test('deleting our own reply removes it', function () {
    [, $workspace, $token] = issuedKey();
    $reply = engagementReply($workspace, ['is_ours' => true]);
    fakeReplyConnector(ReplyActionResult::ok());

    $this->withToken($token)->deleteJson("/api/v1/engagement/{$reply->id}")->assertNoContent();

    expect(PostTargetReply::withoutGlobalScopes()->find($reply->id))->toBeNull();
});

test('uploading media returns the media view', function () {
    [, $workspace, $token] = issuedKey();
    $reply = engagementReply($workspace);

    $this->withToken($token)->postJson("/api/v1/engagement/{$reply->id}/media", [
        'file' => UploadedFile::fake()->image('pic.jpg', 200, 200),
        'alt_text' => 'a picture',
    ])
        ->assertCreated()
        ->assertJsonStructure(['media' => ['id', 'url', 'kind']]);
});
