<?php

use App\Models\WorkspaceMention;

test('lists saved mentions for the workspace', function () {
    [, $workspace, $token] = issuedKey();
    WorkspaceMention::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => '@taylor',
        'handles' => ['x' => '@taylorotwell'],
    ]);

    $this->withToken($token)->getJson('/api/v1/workspace-mentions')
        ->assertOk()
        ->assertJsonPath('data.0.name', '@taylor');
});

test('saves a mention library item for the current workspace', function () {
    [, $workspace, $token] = issuedKey();

    $this->withToken($token)->postJson('/api/v1/workspace-mentions', [
        'name' => '@taylor',
        'handles' => [
            'x' => '@taylorotwell',
            'bluesky' => '@taylor.bsky.social',
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('mention.name', '@taylor')
        ->assertJsonPath('mention.handles.x', '@taylorotwell');

    expect(WorkspaceMention::query()->where('workspace_id', $workspace->id)->first())
        ->not->toBeNull();
});

test('a read-only key cannot save mentions', function () {
    [, , $token] = issuedKey('read');

    $this->withToken($token)->postJson('/api/v1/workspace-mentions', [
        'name' => '@taylor',
        'handles' => ['x' => '@taylorotwell'],
    ])->assertForbidden();
});

test('cannot delete a mention belonging to another workspace', function () {
    [, , $token] = issuedKey();
    $foreign = WorkspaceMention::factory()->create();

    $this->withToken($token)->deleteJson("/api/v1/workspace-mentions/{$foreign->id}")
        ->assertNotFound();
});
