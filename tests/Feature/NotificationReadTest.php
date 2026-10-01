<?php

declare(strict_types=1);

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Str;

function makeNotification(User $user, ?string $workspaceId, ?string $readAt = null): string
{
    $id = (string) Str::uuid();
    $user->notifications()->create([
        'id' => $id,
        'type' => 'App\\Notifications\\PostPublishedNotification',
        'data' => ['event' => 'post_published', 'title' => 'X', 'body' => '', 'href' => null, 'icon' => 'bell', 'workspace_id' => $workspaceId],
        'read_at' => $readAt,
    ]);

    return $id;
}

test('a user can mark one notification read', function () {
    $user = User::factory()->create();
    $ws = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $ws->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $ws->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    $id = makeNotification($user, $ws->id);

    $this->actingAs($user)->postJson("/api/v1/notifications/{$id}/read")->assertNoContent();

    expect($user->notifications()->find($id)->read_at)->not->toBeNull();
});

test('a user cannot mark another users notification read', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ws = Workspace::factory()->create();
    $id = makeNotification($owner, $ws->id);

    $this->actingAs($other)->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();

    expect($owner->notifications()->find($id)->read_at)->toBeNull();
});

test('mark-all-read clears unread for the current workspace and global notifications only', function () {
    $user = User::factory()->create();
    $wsA = Workspace::factory()->create();
    $wsB = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $wsA->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $wsA->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    makeNotification($user, $wsA->id);
    makeNotification($user, null);
    $bId = makeNotification($user, $wsB->id);

    $this->actingAs($user)->postJson('/api/v1/notifications/read-all')->assertNoContent();

    expect($user->unreadNotifications()->count())->toBe(1);
    expect($user->notifications()->find($bId)->read_at)->toBeNull();
});

test('a user can delete one notification', function () {
    $user = User::factory()->create();
    $ws = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $ws->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $ws->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    $id = makeNotification($user, $ws->id);

    $this->actingAs($user)->deleteJson("/api/v1/notifications/{$id}")->assertNoContent();

    expect($user->notifications()->find($id))->toBeNull();
});

test('a user cannot delete another users notification', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ws = Workspace::factory()->create();
    $id = makeNotification($owner, $ws->id);

    $this->actingAs($other)->deleteJson("/api/v1/notifications/{$id}")->assertNotFound();

    expect($owner->notifications()->find($id))->not->toBeNull();
});

test('delete-all removes notifications for the current workspace and global notifications only', function () {
    $user = User::factory()->create();
    $wsA = Workspace::factory()->create();
    $wsB = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $wsA->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $wsA->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    $aId = makeNotification($user, $wsA->id);
    $globalId = makeNotification($user, null);
    $bId = makeNotification($user, $wsB->id);

    $this->actingAs($user)->deleteJson('/api/v1/notifications')->assertNoContent();

    expect($user->notifications()->find($aId))->toBeNull();
    expect($user->notifications()->find($globalId))->toBeNull();
    expect($user->notifications()->find($bId))->not->toBeNull();
});

test('a json request can delete one notification without a redirect', function () {
    $user = User::factory()->create();
    $ws = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $ws->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $ws->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    $id = makeNotification($user, $ws->id);

    $this->actingAs($user)
        ->deleteJson("/api/v1/notifications/{$id}")
        ->assertNoContent();

    expect($user->notifications()->find($id))->toBeNull();
});

test('a json request can delete all notifications without a redirect', function () {
    $user = User::factory()->create();
    $ws = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $ws->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $ws->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    $id = makeNotification($user, $ws->id);

    $this->actingAs($user)
        ->deleteJson('/api/v1/notifications')
        ->assertNoContent();

    expect($user->notifications()->find($id))->toBeNull();
});

test('a json request can mark one notification read without a redirect', function () {
    $user = User::factory()->create();
    $ws = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $ws->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $ws->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    $id = makeNotification($user, $ws->id);

    $this->actingAs($user)
        ->postJson("/api/v1/notifications/{$id}/read")
        ->assertNoContent();

    expect($user->notifications()->find($id)->read_at)->not->toBeNull();
});

test('a json request can mark all notifications read without a redirect', function () {
    $user = User::factory()->create();
    $ws = Workspace::factory()->create();
    $user->forceFill(['current_workspace_id' => $ws->id])->save();
    WorkspaceMembership::query()->firstOrCreate(
        ['workspace_id' => $ws->id, 'user_id' => $user->id],
        ['role' => WorkspaceRole::Member],
    );
    makeNotification($user, $ws->id);

    $this->actingAs($user)
        ->postJson('/api/v1/notifications/read-all')
        ->assertNoContent();

    expect($user->unreadNotifications()->count())->toBe(0);
});
