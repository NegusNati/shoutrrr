<?php

use App\Dto\Messaging\MessageSendResult;
use App\Enums\Platform;
use App\Models\ConnectedAccount;
use App\Models\ConnectedAccountSecret;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\PostMedia;
use App\Models\Workspace;
use App\Services\Messaging\Contracts\DirectMessageConnector;
use App\Services\Messaging\MessageConnectorRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('messages.enabled', true);
    Storage::fake('public');
});

function dmAccount(Workspace $workspace, Platform $platform = Platform::X): ConnectedAccount
{
    $account = ConnectedAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => $platform,
        'capabilities' => ['dm_enabled' => true],
        'token_expires_at' => now()->addHour(),
    ]);
    ConnectedAccountSecret::factory()->create([
        'connected_account_id' => $account->id,
        'access_token' => 'tok',
    ]);

    return $account;
}

function fakeMessageConnector(MessageSendResult $result): void
{
    $connector = Mockery::mock(DirectMessageConnector::class);
    $connector->shouldReceive('sendMessage')->andReturn($result);
    $registry = Mockery::mock(MessageConnectorRegistry::class);
    $registry->shouldReceive('for')->andReturn($connector);
    app()->instance(MessageConnectorRegistry::class, $registry);
}

test('index returns the conversation list with filters', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace);
    $convo = Conversation::factory()->for($account, 'account')->create([
        'workspace_id' => $workspace->id,
        'unread_count' => 2,
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/messages')
        ->assertOk()
        ->assertJsonStructure([
            'conversations' => ['data', 'current_page', 'last_page'],
            'filters' => ['archived'],
        ]);

    expect($response->json('conversations.data.0.id'))->toBe($convo->id);
});

test('index 404s when messages are disabled', function () {
    config()->set('messages.enabled', false);
    [, , $token] = issuedKey();

    $this->withToken($token)->getJson('/api/v1/messages')->assertNotFound();
});

test('index scopes conversations to the caller workspace', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace);
    $mine = Conversation::factory()->for($account, 'account')->create(['workspace_id' => $workspace->id]);
    Conversation::factory()->create(['workspace_id' => Workspace::factory()->create()->id]);

    $ids = collect(
        $this->withToken($token)->getJson('/api/v1/messages')->assertOk()->json('conversations.data')
    )->pluck('id')->all();

    expect($ids)->toBe([$mine->id]);
});

test('index archived filter lists only archived conversations', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace);
    Conversation::factory()->for($account, 'account')->create(['workspace_id' => $workspace->id]);
    $archived = Conversation::factory()->for($account, 'account')->create([
        'workspace_id' => $workspace->id,
        'archived_at' => now(),
    ]);

    $ids = collect(
        $this->withToken($token)->getJson('/api/v1/messages?archived=1')->assertOk()->json('conversations.data')
    )->pluck('id')->all();

    expect($ids)->toBe([$archived->id]);
});

test('thread returns the conversation and its messages', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace);
    $convo = Conversation::factory()->for($account, 'account')->create(['workspace_id' => $workspace->id]);
    DirectMessage::factory()->for($convo)->create(['workspace_id' => $workspace->id]);

    $this->withToken($token)->getJson("/api/v1/messages/{$convo->id}/thread")
        ->assertOk()
        ->assertJsonStructure(['conversation', 'messages']);
});

test('thread 404s for a conversation in another workspace', function () {
    [, , $token] = issuedKey();
    $foreign = Conversation::factory()->create(['workspace_id' => Workspace::factory()->create()->id]);

    $this->withToken($token)->getJson("/api/v1/messages/{$foreign->id}/thread")->assertNotFound();
});

test('markRead clears the unread count and archive archives', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace);
    $convo = Conversation::factory()->for($account, 'account')->create([
        'workspace_id' => $workspace->id,
        'unread_count' => 3,
    ]);

    $this->withToken($token)->postJson("/api/v1/messages/{$convo->id}/read")->assertNoContent();
    expect($convo->refresh()->unread_count)->toBe(0);
    expect($convo->read_at)->not->toBeNull();

    $this->withToken($token)->postJson("/api/v1/messages/{$convo->id}/archive")->assertNoContent();
    expect($convo->refresh()->archived_at)->not->toBeNull();
});

test('respond sends the message through the connector and returns 201', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace);
    $convo = Conversation::factory()->for($account, 'account')->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::X,
    ]);
    fakeMessageConnector(MessageSendResult::ok('sent-1'));

    $this->withToken($token)->postJson("/api/v1/messages/{$convo->id}/reply", ['text' => 'hello'])
        ->assertCreated()
        ->assertJsonPath('message.is_ours', true);
});

test('respond 409s when the 24-hour window has closed', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace, Platform::Instagram);
    $convo = Conversation::factory()->for($account, 'account')->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::Instagram,
        'messaging_window_expires_at' => now()->subMinute(),
    ]);

    $this->withToken($token)->postJson("/api/v1/messages/{$convo->id}/reply", ['text' => 'late'])
        ->assertStatus(409);
});

test('respond validates the body', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace);
    $convo = Conversation::factory()->for($account, 'account')->create(['workspace_id' => $workspace->id]);

    $this->withToken($token)->postJson("/api/v1/messages/{$convo->id}/reply", [])->assertStatus(422);
});

test('uploading media returns the media view', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace);
    $convo = Conversation::factory()->for($account, 'account')->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::X,
    ]);

    $this->withToken($token)->postJson("/api/v1/messages/{$convo->id}/media", [
        'file' => UploadedFile::fake()->image('pic.jpg', 200, 200),
    ])
        ->assertCreated()
        ->assertJsonStructure(['media' => ['id', 'url', 'kind']]);
});

test('media upload 404s on a platform without DM media support', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace, Platform::Bluesky);
    $convo = Conversation::factory()->for($account, 'account')->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::Bluesky,
    ]);

    $this->withToken($token)->postJson("/api/v1/messages/{$convo->id}/media", [
        'file' => UploadedFile::fake()->image('pic.jpg', 200, 200),
    ])->assertNotFound();
});

test('deleting unclaimed draft media works', function () {
    [, $workspace, $token] = issuedKey();
    $account = dmAccount($workspace);
    $convo = Conversation::factory()->for($account, 'account')->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::X,
    ]);
    $media = PostMedia::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken($token)->deleteJson("/api/v1/messages/{$convo->id}/media/{$media->id}")
        ->assertOk()
        ->assertJsonPath('deleted', true);
});
