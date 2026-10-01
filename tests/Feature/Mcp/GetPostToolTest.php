<?php

use App\Enums\WorkspaceRole;
use App\Mcp\Servers\ShoutrrrServer;
use App\Mcp\Tools\GetPostTool;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

test('get_post returns a post in the bound workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $workspace->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    bindTokenToWorkspace($user, $workspace);

    $post = Post::factory()->for($workspace)->create(['base_text' => 'hello world']);

    $response = ShoutrrrServer::actingAs($user)
        ->tool(GetPostTool::class, ['post_id' => $post->id]);

    $response->assertOk()->assertSee('hello world');
});

test('get_post errors for a post in another workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $workspace->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    bindTokenToWorkspace($user, $workspace);

    // Create a post in a completely separate workspace (no Context set at this point).
    $foreign = Post::factory()->for(Workspace::factory())->create();

    $response = ShoutrrrServer::actingAs($user)
        ->tool(GetPostTool::class, ['post_id' => $foreign->id]);

    $response->assertHasErrors();
});
