<?php

declare(strict_types=1);

use App\Enums\WorkspaceRole;
use App\Models\AccountSet;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Context;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => WorkspaceRole::Member,
    ]);
    $this->user->forceFill(['current_workspace_id' => $this->workspace->id])->save();
    Context::add('workspace_id', $this->workspace->id);
});

test('shell props expose accounts, sets, and limits on every page', function () {
    ConnectedAccount::factory()->for($this->workspace)->needsAttention()->create();
    AccountSet::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('shell.accounts.0.status', 'needs_attention')
        ->assertJsonPath('shell.accounts.0.max_video_duration_seconds', 140)
        ->assertJsonPath('shell.accounts.0.auto_repost_enabled', false)
        ->assertJsonCount(1, 'shell.accounts')
        ->assertJsonCount(1, 'shell.sets')
        ->assertJsonStructure(['shell' => ['limits']]);
});
