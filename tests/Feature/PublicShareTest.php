<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\PostShare;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\Fluent\AssertableJson as Assert;

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

it('renders a read-only view for a valid token', function (): void {
    shareFor('good-token');

    $this->get('/share/good-token')
        ->assertRedirect('/app/share/good-token');

    $this->getJson('/api/v1/shares/public/good-token')
        ->assertOk()
        ->assertJson(fn (Assert $json) => $json->where('post.base_text', 'shared body')->etc());
});

it('shows not-available for unknown / revoked / expired tokens', function (): void {
    $this->getJson('/api/v1/shares/public/nope')
        ->assertJson(fn (Assert $json) => $json->where('post', null)->etc());

    shareFor('revoked-token', fn ($f) => $f->revoked());
    $this->getJson('/api/v1/shares/public/revoked-token')
        ->assertJson(fn (Assert $json) => $json->where('post', null)->etc());

    shareFor('expired-token', fn ($f) => $f->expired());
    $this->getJson('/api/v1/shares/public/expired-token')
        ->assertJson(fn (Assert $json) => $json->where('post', null)->etc());
});
