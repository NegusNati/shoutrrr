<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\PostShare;
use App\Models\User;
use App\Models\Workspace;

function shareFor(string $token, ?callable $state = null): PostShare
{
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();
    $post = Post::factory()->for($workspace)->create([
        'author_id' => $user->id, 'base_text' => 'shared body',
    ]);
    $factory = PostShare::factory()->for($post)->state(['token_hash' => hash('sha256', $token)]);
    if ($state !== null) {
        $factory = $state($factory);
    }

    return $factory->create();
}

it('redirects the legacy share URL into the SPA', function (): void {
    $this->get('/share/good-token')
        ->assertRedirect('/app/share/good-token')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('serves a read-only payload for a valid token', function (): void {
    shareFor('good-token');

    $this->getJson('/api/v1/shares/good-token')
        ->assertOk()
        ->assertJsonPath('post.base_text', 'shared body')
        ->assertJsonStructure([
            'post' => ['status', 'created_at', 'targets', 'media'],
        ]);
});

it('returns a null post for unknown / revoked / expired tokens', function (): void {
    $this->getJson('/api/v1/shares/nope')->assertOk()->assertJsonPath('post', null);

    shareFor('revoked-token', fn ($f) => $f->revoked());
    $this->getJson('/api/v1/shares/revoked-token')->assertJsonPath('post', null);

    shareFor('expired-token', fn ($f) => $f->expired());
    $this->getJson('/api/v1/shares/expired-token')->assertJsonPath('post', null);
});
