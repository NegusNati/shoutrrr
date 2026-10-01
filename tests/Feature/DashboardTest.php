<?php

use App\Enums\WorkspaceRole;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTarget;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceMention;
use Illuminate\Support\Facades\Context;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users are redirected to the SPA dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertRedirect('/app/dashboard');
});

test('the recent feed exposes full row data including targets', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Member,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    Context::add('workspace_id', $workspace->id);

    $post = Post::factory()->for($workspace)->create(['author_id' => $user->id]);
    PostTarget::factory()->for($post)->create();

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure(['posts' => [['targets', 'published_at']]]);
});

test('the recent feed exposes attached media for a row thumbnail', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Member,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    Context::add('workspace_id', $workspace->id);

    $post = Post::factory()->for($workspace)->create(['author_id' => $user->id]);
    PostTarget::factory()->for($post)->create();
    PostMedia::factory()->for($post)->create([
        'workspace_id' => $workspace->id,
        'position' => 0,
        'kind' => 'image',
    ]);
    PostMedia::factory()->for($post)->video()->create([
        'workspace_id' => $workspace->id,
        'position' => 1,
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('posts.0.media_count', 2)
        ->assertJsonPath('posts.0.media_preview.kind', 'image')
        ->assertJsonStructure(['posts' => [['media_preview' => ['url']]]]);
});

test('a video-only post exposes no preview url so the list shows an icon tile', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Member,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    Context::add('workspace_id', $workspace->id);

    $post = Post::factory()->for($workspace)->create(['author_id' => $user->id]);
    PostTarget::factory()->for($post)->create();
    PostMedia::factory()->for($post)->video()->create([
        'workspace_id' => $workspace->id,
        'position' => 0,
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('posts.0.media_count', 1)
        ->assertJsonPath('posts.0.media_preview.kind', 'video')
        ->assertJsonPath('posts.0.media_preview.url', null);
});

test('the dashboard includes saved workspace mentions for the composer', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Member,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    Context::add('workspace_id', $workspace->id);

    WorkspaceMention::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => '@saved',
        'handles' => ['x' => '@saved_x'],
    ]);
    WorkspaceMention::factory()->create(['name' => '@foreign']);

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonCount(1, 'savedMentions')
        ->assertJsonPath('savedMentions.0.name', '@saved');
});
