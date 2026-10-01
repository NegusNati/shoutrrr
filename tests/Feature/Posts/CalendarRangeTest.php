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

it('exposes the calendar at its own top-level endpoint', function (): void {
    expect(route('calendar.index', absolute: false))->toBe('/calendar');
    expect(route('calendar.month', ['yyyymm' => '2026-06'], absolute: false))->toBe('/calendar/2026-06');
});

it('bare calendar redirects into the SPA', function (): void {
    $this->actingAs($this->user)
        ->get('/calendar')
        ->assertRedirect('/app/calendar');
});

it('returns scheduled + published posts whose date falls in the visible window', function (): void {
    Post::factory()->for($this->workspace)->create([
        'author_id' => $this->user->id, 'status' => PostStatus::Scheduled->value,
        'scheduled_at' => '2026-06-15 09:00:00',
    ]);
    Post::factory()->for($this->workspace)->create([
        'author_id' => $this->user->id, 'status' => PostStatus::Published->value,
        'published_at' => '2026-06-20 12:00:00',
    ]);
    Post::factory()->for($this->workspace)->create([
        'author_id' => $this->user->id, 'status' => PostStatus::Draft->value,
    ]); // no date → excluded

    $this->actingAs($this->user)
        ->getJson('/api/v1/calendar?month=2026-06')
        ->assertJson(fn (Assert $json) => $json
            ->where('month', '2026-06')
            ->has('posts', 2)->etc());
});
