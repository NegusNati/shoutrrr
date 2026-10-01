<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Enums\WorkspaceRole;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Context;
use Illuminate\Testing\Fluent\AssertableJson as Assert;

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    $this->user = User::factory()->create(['current_workspace_id' => $this->workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => WorkspaceRole::Owner,
    ]);
    Context::add('workspace_id', $this->workspace->id);
});

function makePost(Workspace $w, User $u, PostStatus $status): Post
{
    return Post::factory()->for($w)->create([
        'author_id' => $u->id,
        'status' => $status->value,
    ]);
}

it('lists workspace posts and excludes deleted', function (): void {
    makePost($this->workspace, $this->user, PostStatus::Draft);
    makePost($this->workspace, $this->user, PostStatus::Deleted);

    $this->actingAs($this->user)
        ->getJson('/api/v1/posts')
        ->assertJson(fn (Assert $json) => $json
            ->has('data', 1)
            ->where('data.0.status', 'draft')->etc());
});

it('filters by status tab', function (): void {
    makePost($this->workspace, $this->user, PostStatus::Draft);
    makePost($this->workspace, $this->user, PostStatus::Scheduled);

    $this->actingAs($this->user)
        ->getJson('/api/v1/posts?'.http_build_query(['status' => 'scheduled']))
        ->assertJson(fn (Assert $json) => $json
            ->has('data', 1)
            ->where('data.0.status', 'scheduled')->etc());
});

it('exposes per-status tab counts that exclude deleted', function (): void {
    makePost($this->workspace, $this->user, PostStatus::Draft);
    makePost($this->workspace, $this->user, PostStatus::Draft);
    makePost($this->workspace, $this->user, PostStatus::Scheduled);
    makePost($this->workspace, $this->user, PostStatus::Deleted);

    $this->actingAs($this->user)
        ->getJson('/api/v1/posts')
        ->assertJson(fn (Assert $json) => $json
            ->where('meta.counts.all', 3)
            ->where('meta.counts.draft', 2)
            ->where('meta.counts.scheduled', 1)
            ->where('meta.counts.published', 0)->etc());
});

it('filters by text query on base_text', function (): void {
    Post::factory()->for($this->workspace)->create(['author_id' => $this->user->id, 'base_text' => 'launch announcement']);
    Post::factory()->for($this->workspace)->create(['author_id' => $this->user->id, 'base_text' => 'weekly recap']);

    $this->actingAs($this->user)
        ->getJson('/api/v1/posts?'.http_build_query(['q' => 'launch']))
        ->assertJson(fn (Assert $json) => $json
            ->has('data', 1)
            ->where('data.0.base_text', 'launch announcement')->etc());
});
