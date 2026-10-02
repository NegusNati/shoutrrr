<?php

use App\Enums\WorkspaceRole;
use App\Mcp\Servers\ShoutrrrServer;
use App\Mcp\Tools\ListConnectedAccountsTool;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

test('list_connected_accounts returns accounts in the bound workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $workspace->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    bindTokenToWorkspace($user, $workspace);

    ConnectedAccount::factory()->for($workspace)->create(['handle' => 'acme_co']);

    $response = ShoutrrrServer::actingAs($user)->tool(ListConnectedAccountsTool::class, []);
    $response->assertOk()->assertSee('acme_co');
});
