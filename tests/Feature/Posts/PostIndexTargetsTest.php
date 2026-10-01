<?php

use App\Enums\ErrorKind;
use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Enums\WorkspaceRole;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Testing\Fluent\AssertableJson as Assert;

test('posts index payload includes per-target status and published_at', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Member,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $workspace->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => PostStatus::Partial,
        'published_at' => now(),
    ]);
    PostTarget::factory()->for($post)->create([
        'platform' => Platform::X->value,
        'status' => PostTargetStatus::Failed->value,
        'error_kind' => ErrorKind::RateLimited->value,
        'error_message' => 'slow down',
        'attempts' => 3,
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/posts')
        ->assertJson(fn (Assert $json) => $json
            ->where('data.0.published_at', fn ($value) => $value !== null)
            ->where('data.0.targets.0.platform', 'x')
            ->where('data.0.targets.0.status', 'failed')
            ->where('data.0.targets.0.error_kind', 'rate_limited')
            ->where('data.0.targets.0.error_message', 'slow down')
            ->where('data.0.targets.0.attempts', 3)->etc());
});
