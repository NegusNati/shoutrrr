<?php

declare(strict_types=1);

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;

function apiFeedbackUser(): User
{
    $user = User::factory()->create(['name' => 'Ada', 'email' => 'ada@test.co']);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id, 'name' => 'Acme']);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => WorkspaceRole::Member,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    Context::add('workspace_id', $workspace->id);

    return $user;
}

it('returns 404 when the feature is disabled', function () {
    config(['feedback.enabled' => false, 'feedback.webhook_url' => null]);
    Http::fake();

    $this->actingAs(apiFeedbackUser())
        ->postJson('/api/v1/feedback', ['type' => 'bug', 'message' => 'hi'])
        ->assertNotFound();

    Http::assertNothingSent();
});

it('returns 404 when enabled but webhook url is missing', function () {
    config(['feedback.enabled' => true, 'feedback.webhook_url' => null]);

    $this->actingAs(apiFeedbackUser())
        ->postJson('/api/v1/feedback', ['type' => 'bug', 'message' => 'hi'])
        ->assertNotFound();
});

it('requires authentication', function () {
    config(['feedback.enabled' => true, 'feedback.webhook_url' => 'https://discord.com/api/webhooks/1/tok']);

    $this->postJson('/api/v1/feedback', ['type' => 'bug', 'message' => 'hi'])
        ->assertUnauthorized();
});

it('rejects API keys — the endpoint is session-only', function () {
    config(['feedback.enabled' => true, 'feedback.webhook_url' => 'https://discord.com/api/webhooks/1/tok']);
    [, , $token] = issuedKey();

    $this->withToken($token)
        ->postJson('/api/v1/feedback', ['type' => 'bug', 'message' => 'hi'])
        ->assertForbidden();
});

it('forbids unverified session users', function () {
    config([
        'feedback.enabled' => true,
        'feedback.webhook_url' => 'https://discord.com/api/webhooks/1/tok',
        'auth.email_verification.enabled' => true,
    ]);

    $user = User::factory()->unverified()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => WorkspaceRole::Owner,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();

    $this->actingAs($user)
        ->postJson('/api/v1/feedback', ['type' => 'bug', 'message' => 'hi'])
        ->assertForbidden();
});

it('sends a report to discord with server-derived context', function () {
    config(['feedback.enabled' => true, 'feedback.webhook_url' => 'https://discord.com/api/webhooks/1/tok']);
    Http::fake(['https://discord.com/*' => Http::response('', 204)]);

    $this->actingAs(apiFeedbackUser())
        ->postJson('/api/v1/feedback', [
            'type' => 'bug',
            'message' => 'It broke',
            'url' => 'https://app.test/app/dashboard',
            'browser' => 'Mozilla/5.0',
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    Http::assertSent(function ($request) {
        $embed = $request['embeds'][0];

        return $embed['description'] === 'It broke'
            && collect($embed['fields'])->contains(fn ($f) => $f['value'] === 'ada@test.co')
            && collect($embed['fields'])->contains(fn ($f) => str_contains($f['value'], 'Acme'));
    });
});

it('hides the host in the page url on self-hosted instances', function () {
    config([
        'feedback.enabled' => true,
        'feedback.webhook_url' => 'https://discord.com/api/webhooks/1/tok',
        'instance.self_hosted' => true,
    ]);
    Http::fake(['https://discord.com/*' => Http::response('', 204)]);

    $this->actingAs(apiFeedbackUser())
        ->postJson('/api/v1/feedback', [
            'type' => 'bug',
            'message' => 'It broke',
            'url' => 'https://acme.example.com/app/dashboard?tab=drafts',
            'browser' => 'Mozilla/5.0',
        ])
        ->assertOk();

    Http::assertSent(function ($request) {
        $page = collect($request['embeds'][0]['fields'])->firstWhere('name', 'Page');

        return $page['value'] === '/app/dashboard?tab=drafts'
            && ! str_contains($page['value'], 'acme.example.com');
    });
});

it('forwards and redacts an attached diagnostics file', function () {
    config([
        'feedback.enabled' => true,
        'feedback.webhook_url' => 'https://discord.com/api/webhooks/1/tok',
        'instance.self_hosted' => true,
    ]);
    Http::fake(['https://discord.com/*' => Http::response('', 204)]);

    $diagnostics = UploadedFile::fake()->createWithContent(
        'diagnostics.json',
        '{"network":[{"url":"https://acme.example.com/api/posts"},{"url":"https://api.twitter.com/2/tweets"}]}',
    );

    $this->actingAs(apiFeedbackUser())
        ->post('/api/v1/feedback', [
            'type' => 'bug',
            'message' => 'It broke',
            'url' => 'https://acme.example.com/app/dashboard',
            'browser' => 'Mozilla/5.0',
            'diagnostics' => $diagnostics,
        ])
        ->assertOk();

    Http::assertSent(function ($request) {
        $body = $request->body();

        // Operator host stripped everywhere (path kept), third-party untouched.
        return ! str_contains($body, 'acme.example.com')
            && str_contains($body, '/api/posts')
            && str_contains($body, 'api.twitter.com');
    });
});

it('attaches an uploaded screenshot as multipart', function () {
    config(['feedback.enabled' => true, 'feedback.webhook_url' => 'https://discord.com/api/webhooks/1/tok']);
    Http::fake(['https://discord.com/*' => Http::response('', 204)]);

    $this->actingAs(apiFeedbackUser())
        ->post('/api/v1/feedback', [
            'type' => 'feedback',
            'message' => 'Looks great',
            'url' => 'https://app.test/app/dashboard',
            'browser' => 'Mozilla/5.0',
            'screenshot' => UploadedFile::fake()->image('shot.png', 800, 600),
        ])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->body(), 'name="files[0]"'));
});

it('validates the request', function () {
    config(['feedback.enabled' => true, 'feedback.webhook_url' => 'https://discord.com/api/webhooks/1/tok']);

    $user = apiFeedbackUser();

    $this->actingAs($user)->postJson('/api/v1/feedback', ['type' => 'bug'])
        ->assertJsonValidationErrors('message');

    $this->actingAs($user)->postJson('/api/v1/feedback', ['type' => 'rant', 'message' => 'x'])
        ->assertJsonValidationErrors('type');

    $this->actingAs($user)->postJson('/api/v1/feedback', ['type' => 'bug', 'message' => str_repeat('a', 2001)])
        ->assertJsonValidationErrors('message');
});

it('throttles after five requests', function () {
    config(['feedback.enabled' => true, 'feedback.webhook_url' => 'https://discord.com/api/webhooks/1/tok']);
    Http::fake(['https://discord.com/*' => Http::response('', 204)]);

    $user = apiFeedbackUser();

    foreach (range(1, 5) as $i) {
        $this->actingAs($user)->postJson('/api/v1/feedback', [
            'type' => 'bug', 'message' => "n{$i}", 'url' => 'https://app.test', 'browser' => 'UA',
        ])->assertOk();
    }

    $this->actingAs($user)->postJson('/api/v1/feedback', [
        'type' => 'bug', 'message' => 'n6', 'url' => 'https://app.test', 'browser' => 'UA',
    ])->assertStatus(429);
});
