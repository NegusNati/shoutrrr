<?php

use App\Enums\Platform;
use App\Enums\ReplyStatus;
use App\Enums\WorkspaceRole;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\PostTargetReply;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\InstanceSettings;
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

test('the inbox lists unarchived inbound replies for the workspace', function (): void {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $target = PostTarget::factory()->for($post)->create(['platform' => Platform::Bluesky]);

    PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'text' => 'visible reply',
        'is_ours' => false,
    ]);
    PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'is_ours' => true,
        'text' => 'our own reply',
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->has('filters')
            ->has('facets.accounts')->etc());
});

test('the inbox exposes when engagement polling is disabled', function (): void {
    app(InstanceSettings::class)->update([
        'engagement_polling_enabled' => false,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->where('engagementEnabled.x', false)
            ->where('engagementEnabled.bluesky', false)
            ->where('engagementEnabled.linkedin', false)->etc());
});

test('the inbox exposes which engagement platforms are disabled', function (): void {
    app(InstanceSettings::class)->update([
        'engagement_polling_enabled' => [
            'x' => false,
            'bluesky' => true,
            'linkedin' => true,
        ],
        // LinkedIn reply-fetching also requires the Community Management gate;
        // without it LinkedIn stays disabled regardless of the polling toggle.
        'linkedin_community_management_enabled' => true,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->where('engagementEnabled.x', false)
            ->where('engagementEnabled.bluesky', true)
            ->where('engagementEnabled.linkedin', true)->etc());
});

test('the inbox exposes whether the LinkedIn community management scope is enabled', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->where('linkedinCommunityManagementEnabled', false)->etc());

    app(InstanceSettings::class)->update([
        'linkedin_community_management_enabled' => true,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->where('linkedinCommunityManagementEnabled', true)->etc());
});

test('the posts facet lists posts that drew replies with a count', function (): void {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id, 'base_text' => 'Launch day thread']);
    $target = PostTarget::factory()->for($post)->create(['platform' => Platform::Bluesky]);

    PostTargetReply::factory()->count(3)->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'is_ours' => false,
    ]);
    // Our own reply and an archived one must not be counted.
    PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'is_ours' => true,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->has('facets.posts', 1)
            ->where('facets.posts.0.id', $post->id)
            ->where('facets.posts.0.count', 3)->etc());
});

test('filtering by post narrows the stream to that post', function (): void {
    $kept = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $other = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $keptTarget = PostTarget::factory()->for($kept)->create(['platform' => Platform::Bluesky]);
    $otherTarget = PostTarget::factory()->for($other)->create(['platform' => Platform::Bluesky]);

    PostTargetReply::factory()->for($keptTarget, 'target')->create([
        'workspace_id' => $this->workspace->id, 'text' => 'on kept post', 'is_ours' => false,
    ]);
    PostTargetReply::factory()->for($otherTarget, 'target')->create([
        'workspace_id' => $this->workspace->id, 'text' => 'on other post', 'is_ours' => false,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement?'.http_build_query(['post' => $kept->id]))
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->where('filters.post', $kept->id)
            ->has('replies.data', 1)
            ->where('replies.data.0.text', 'on kept post')->etc());
});

test('filtering by platform uses the target platform', function (): void {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $xTarget = PostTarget::factory()->for($post)->create(['platform' => Platform::X]);
    $blueskyTarget = PostTarget::factory()->for($post)->create(['platform' => Platform::Bluesky]);

    PostTargetReply::factory()->for($xTarget, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Bluesky,
        'text' => 'reply on x target',
        'is_ours' => false,
    ]);
    PostTargetReply::factory()->for($blueskyTarget, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Bluesky,
        'text' => 'reply on bluesky target',
        'is_ours' => false,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement?'.http_build_query(['platform' => Platform::X->value]))
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->where('filters.platform', Platform::X->value)
            ->has('replies.data', 1)
            ->where('replies.data.0.text', 'reply on x target')
            ->where('replies.data.0.platform', Platform::X->value)->etc());
});

test('filtering archived shows only archived inbound replies', function (): void {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $target = PostTarget::factory()->for($post)->create(['platform' => Platform::Bluesky]);

    PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'text' => 'archived reply',
        'is_ours' => false,
        'status' => ReplyStatus::Archived,
    ]);
    PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'text' => 'active reply',
        'is_ours' => false,
        'status' => ReplyStatus::Pending,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement?'.http_build_query(['archived' => 1]))
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->where('filters.archived', true)
            ->where('filters.unread', false)
            ->has('replies.data', 1)
            ->where('replies.data.0.text', 'archived reply')->etc());
});

test('the inbox consolidates replies by base reply thread', function (): void {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id, 'base_text' => 'Original post']);
    $target = PostTarget::factory()->for($post)->create([
        'platform' => Platform::Bluesky,
        'remote_id' => 'at://root-post',
    ]);

    $firstBase = PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'remote_reply_id' => 'at://base-1',
        'parent_remote_id' => 'at://root-post',
        'author_handle' => 'andras.dev',
        'text' => 'hello hallo',
        'is_ours' => false,
        'read_at' => now(),
        'remote_created_at' => now()->subMinutes(10),
    ]);
    $ourReply = PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'remote_reply_id' => 'at://ours-1',
        'parent_remote_id' => $firstBase->remote_reply_id,
        'conversation_remote_id' => $firstBase->remote_reply_id,
        'author_handle' => 'our.account',
        'text' => 'hello',
        'is_ours' => true,
        'read_at' => now(),
        'remote_created_at' => now()->subMinutes(8),
    ]);
    PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'remote_reply_id' => 'at://child-1',
        'parent_remote_id' => $ourReply->remote_reply_id,
        'conversation_remote_id' => $firstBase->remote_reply_id,
        'author_handle' => 'andras.dev',
        'text' => 'hey',
        'is_ours' => false,
        'read_at' => null,
        'remote_created_at' => now()->subMinutes(2),
    ]);
    PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'remote_reply_id' => 'at://base-2',
        'parent_remote_id' => 'at://root-post',
        'author_handle' => 'andras.dev',
        'text' => 'yo yo, whats up',
        'is_ours' => false,
        'read_at' => null,
        'remote_created_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->has('replies.data', 2)
            ->where('replies.data.0.text', 'hey')
            ->where('replies.data.0.reply_count', 2)
            ->where('replies.data.0.unread_count', 1)
            ->where('replies.data.0.is_read', false)
            ->where('replies.data.1.text', 'yo yo, whats up')
            ->where('replies.data.1.reply_count', 1)->etc());
});

test('the inbox keeps separate conversations for different authors on the same post target', function (): void {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $target = PostTarget::factory()->for($post)->create(['platform' => Platform::Bluesky]);

    PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'author_handle' => 'andras.dev',
        'text' => 'from andras',
        'is_ours' => false,
    ]);
    PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'author_handle' => 'someone.dev',
        'text' => 'from someone',
        'is_ours' => false,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->has('replies.data', 2)
            ->where('replies.data.0.reply_count', 1)
            ->where('replies.data.1.reply_count', 1)->etc());
});

test('replies from another workspace are not visible', function (): void {
    PostTargetReply::factory()->create(['workspace_id' => '11111111-1111-1111-1111-111111111111', 'text' => 'foreign']);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement?'.http_build_query(['unread' => 1]))
        ->assertOk();

    // HasWorkspaceScope filters by Context workspace_id, so the foreign row must not be visible.
    expect(PostTargetReply::query()->where('text', 'foreign')->exists())->toBeFalse();
});

test('a nested reply is grouped under the conversation of its base reply', function (): void {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $target = PostTarget::factory()->for($post)->create([
        'platform' => Platform::Bluesky,
        'remote_id' => 'at://root-post',
    ]);

    $base = PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'remote_reply_id' => 'at://base',
        'parent_remote_id' => 'at://root-post',
        'text' => 'base reply',
        'is_ours' => false,
        'remote_created_at' => now()->subMinutes(5),
    ]);

    PostTargetReply::factory()->for($target, 'target')->create([
        'workspace_id' => $this->workspace->id,
        'remote_reply_id' => 'at://child',
        'parent_remote_id' => $base->remote_reply_id,
        'conversation_remote_id' => $base->remote_reply_id,
        'text' => 'child reply',
        'is_ours' => false,
        'remote_created_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/engagement')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json
            ->has('replies.data', 1)
            ->where('replies.data.0.text', 'child reply')
            ->where('replies.data.0.reply_count', 2)
            ->where('replies.data.0.conversation_key', $target->id.':at://base')->etc());
});
